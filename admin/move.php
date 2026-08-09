<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/auth.php';

$db = getDB();
$error = '';
$success = '';

function moveUserId(mysqli $db, int $oldId, int $newId): void {
    $db->begin_transaction();
    try {
        $db->query("SET FOREIGN_KEY_CHECKS=0");

        $refs = [
            ['comments', 'user_id'],
            ['likes', 'user_id'],
            ['dislikes', 'user_id'],
            ['comment_reports', 'reporter_id'],
            ['submissions', 'user_id'],
            ['impersonation_log', 'admin_id'],
            ['impersonation_log', 'target_user_id'],
        ];

        foreach ($refs as [$table, $col]) {
            $stmt = $db->prepare("UPDATE $table SET $col = ? WHERE $col = ?");
            $stmt->bind_param("ii", $newId, $oldId);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $db->prepare("UPDATE users SET id = ? WHERE id = ?");
        $stmt->bind_param("ii", $newId, $oldId);
        $stmt->execute();
        $stmt->close();

        $db->query("SET FOREIGN_KEY_CHECKS=1");
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        $db->query("SET FOREIGN_KEY_CHECKS=1");
        throw $e;
    }

    // Self-heal: reset AUTO_INCREMENT to just above the highest NON-anonymized
    // id. Anonymized accounts deliberately sit in a huge 9-digit range, so a
    // blanket MAX(id) would drag the counter up into that range and hand the
    // next real signup a huge id — which is exactly the bug this caused before.
    $result = $db->query("SELECT MAX(id) AS max_id FROM users WHERE id < 1000000");
    $maxId = (int)($result->fetch_assoc()['max_id'] ?? 0);
    $db->query("ALTER TABLE users AUTO_INCREMENT = " . ($maxId + 1));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $type = $_POST['type'] ?? 'article';

    if ($type === 'article') {
        $oldId = (int)($_POST['old_id'] ?? 0);
        $newId = (int)($_POST['new_id'] ?? 0);

        if ($oldId <= 0 || $newId <= 0) {
            $error = 'Both IDs must be positive numbers.';
        } elseif ($oldId === $newId) {
            $error = 'New ID must be different from the current ID.';
        } else {
            $stmt = $db->prepare("SELECT id FROM articles WHERE id = ?");
            $stmt->bind_param("i", $oldId);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $stmt = $db->prepare("SELECT id FROM articles WHERE id = ?");
            $stmt->bind_param("i", $newId);
            $stmt->execute();
            $targetTaken = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$exists) {
                $error = "Article #$oldId doesn't exist.";
            } elseif ($targetTaken) {
                $error = "ID #$newId is already in use by another article. Move or delete that one first.";
            } else {
                $db->query("SET FOREIGN_KEY_CHECKS=0");

                $stmt = $db->prepare("UPDATE articles SET id = ? WHERE id = ?");
                $stmt->bind_param("ii", $newId, $oldId);
                $stmt->execute();
                $stmt->close();

                $stmt = $db->prepare("UPDATE comments SET article_id = ? WHERE article_id = ?");
                $stmt->bind_param("ii", $newId, $oldId);
                $stmt->execute();
                $stmt->close();

                $stmt = $db->prepare("UPDATE likes SET article_id = ? WHERE article_id = ?");
                $stmt->bind_param("ii", $newId, $oldId);
                $stmt->execute();
                $stmt->close();

                $db->query("SET FOREIGN_KEY_CHECKS=1");
                $success = "Moved article from #$oldId to #$newId.";
            }
        }
    } elseif ($type === 'user') {
        $oldId = (int)($_POST['old_id'] ?? 0);
        $newId = (int)($_POST['new_id'] ?? 0);

        if ($oldId <= 0 || $newId <= 0) {
            $error = 'Both IDs must be positive numbers.';
        } elseif ($oldId === $newId) {
            $error = 'New ID must be different from the current ID.';
        } else {
            $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->bind_param("i", $oldId);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->bind_param("i", $newId);
            $stmt->execute();
            $targetTaken = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$exists) {
                $error = "User #$oldId doesn't exist.";
            } elseif ($targetTaken) {
                $error = "ID #$newId is already in use by another user.";
            } else {
                try {
                    moveUserId($db, $oldId, $newId);
                    $success = "Moved user from #$oldId to #$newId.";
                } catch (Throwable $e) {
                    $error = "Move failed: " . $e->getMessage();
                }
            }
        }
    } elseif ($type === 'cleanup_anonymized') {
        $count = bulkDeleteAnonymizedUsers();
        $success = "Hard-deleted $count anonymized account" . ($count === 1 ? '' : 's') . " and reset the user ID counter.";
    } elseif ($type === 'assign_article_user') {
        $articleId = (int)($_POST['article_id'] ?? 0);
        $assignUserId = (int)($_POST['assign_user_id'] ?? 0);

        if ($articleId <= 0 || $assignUserId <= 0) {
            $error = 'Both IDs must be positive numbers.';
        } else {
            $stmt = $db->prepare("SELECT id FROM articles WHERE id = ?");
            $stmt->bind_param("i", $articleId);
            $stmt->execute();
            $articleExists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->bind_param("i", $assignUserId);
            $stmt->execute();
            $userExists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$articleExists) {
                $error = "Article #$articleId doesn't exist.";
            } elseif (!$userExists) {
                $error = "User #$assignUserId doesn't exist.";
            } else {
                $stmt = $db->prepare("UPDATE articles SET user_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $assignUserId, $articleId);
                $stmt->execute();
                $stmt->close();
                $success = "Assigned article #$articleId to user #$assignUserId.";
            }
        }
    } elseif ($type === 'assign_article_categories') {
        $articleId = (int)($_POST['cat_article_id'] ?? 0);
        $categoryIds = $_POST['categories'] ?? [];

        if ($articleId <= 0) {
            $error = 'Article ID must be a positive number.';
        } else {
            $stmt = $db->prepare("SELECT id FROM articles WHERE id = ?");
            $stmt->bind_param("i", $articleId);
            $stmt->execute();
            $articleExists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$articleExists) {
                $error = "Article #$articleId doesn't exist.";
            } else {
                setArticleCategories($articleId, $categoryIds);
                $success = "Updated categories for article #$articleId.";
            }
        }
    } elseif ($type === 'create_api_key') {
        $label = trim($_POST['key_label'] ?? '');
        $rateLimitRaw = trim($_POST['key_rate_limit'] ?? '');
        $rateLimit = $rateLimitRaw === '' ? null : max(1, (int)$rateLimitRaw);
        $newKey = generateApiKey($label !== '' ? $label : 'Unlabeled key', $rateLimit);
        $success = "New API key created — copy it now, it won't be shown again: $newKey";
    } elseif ($type === 'revoke_api_key') {
        $keyId = (int)($_POST['key_id'] ?? 0);
        if ($keyId > 0) {
            revokeApiKey($keyId);
            $success = "API key revoked.";
        }
    } elseif ($type === 'update_api_key_limit') {
        $keyId = (int)($_POST['key_id'] ?? 0);
        $rateLimitRaw = trim($_POST['new_rate_limit'] ?? '');
        $rateLimit = $rateLimitRaw === '' ? null : max(1, (int)$rateLimitRaw);
        if ($keyId > 0) {
            setApiKeyRateLimit($keyId, $rateLimit);
            $success = $rateLimit === null ? "Key #$keyId set to unlimited." : "Key #$keyId limit set to $rateLimit/min.";
        }
    } elseif ($type === 'update_api_settings') {
        $anonLimitRaw = trim($_POST['anonymous_rate_limit'] ?? '');
        $anonLimit = $anonLimitRaw === '' ? 0 : max(0, (int)$anonLimitRaw);
        setApiSetting('anonymous_rate_limit', (string)$anonLimit);
        setApiSetting('rate_limiting_enabled', isset($_POST['rate_limiting_enabled']) ? '1' : '0');
        $success = "API settings updated.";
    } elseif ($type === 'update_scratch_verify_settings') {
        $targetUser = trim($_POST['scratch_verify_target_user'] ?? '');
        $projectId = trim($_POST['scratch_verify_project_id'] ?? '');
        setApiSetting('scratch_verify_target_user', $targetUser);
        setApiSetting('scratch_verify_project_id', $projectId);
        $success = "Scratch verification settings updated.";
    } elseif ($type === 'add_suspicious_ip') {
        $ipToFlag = trim($_POST['ip_address'] ?? '');
        $note = trim($_POST['note'] ?? '');
        if ($ipToFlag === '') {
            $error = 'IP address is required.';
        } elseif (!addSuspiciousIp($ipToFlag, $note)) {
            $error = 'That IP is already flagged.';
        } else {
            $success = "IP $ipToFlag flagged for phone verification.";
        }
    } elseif ($type === 'remove_suspicious_ip') {
        removeSuspiciousIp((int)($_POST['suspicious_ip_id'] ?? 0));
        $success = "IP unflagged.";
    } elseif ($type === 'approve_phone_verification') {
        $approveUserId = (int)($_POST['phone_user_id'] ?? 0);
        approvePhoneVerification($approveUserId);
        $success = "Phone verification approved for user #$approveUserId.";
    } elseif ($type === 'unlink_scratch_username') {
        $unlinkUserId = (int)($_POST['scratch_user_id'] ?? 0);
        if ($unlinkUserId > 0) {
            $unlinkedName = unlinkScratchUsername($unlinkUserId);
            $success = $unlinkedName !== null
                ? "Unlinked Scratch account @$unlinkedName from user #$unlinkUserId. They can now re-verify with any Scratch username."
                : "User #$unlinkUserId has no linked Scratch username.";
        }
    } elseif ($type === 'manually_verify_user') {
        $verifyUsername = trim($_POST['verify_username'] ?? '');
        if ($verifyUsername !== '') {
            $success = verifyUserManually($verifyUsername)
                ? "Manually verified @$verifyUsername."
                : "No user found with username @$verifyUsername.";
        }
    }
}

$articles = getAllArticles();
$allUsers = $db->query("SELECT id, username FROM users ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
$apiKeys = $db->query("SELECT * FROM api_keys ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$apiRateLimitingEnabled = getApiSetting('rate_limiting_enabled', '1') === '1';
$apiAnonLimit = (int)getApiSetting('anonymous_rate_limit', '30');
$scratchVerifyTargetUser = getApiSetting('scratch_verify_target_user', '');
$scratchVerifyProjectId = getApiSetting('scratch_verify_project_id', '');
$suspiciousIps = getSuspiciousIps();
$pendingPhoneVerifications = getPendingPhoneVerifications();
$scratchLinkedUsers = getScratchLinkedUsers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Move Content - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=9">
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/nav.php'; ?>
<main>
    <h2>Move Content</h2>

    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>

    <h3>Move Article</h3>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="type" value="article">
        <label for="old_id">Current ID</label>
        <input type="number" id="old_id" name="old_id" required>
        <label for="new_id">New ID</label>
        <input type="number" id="new_id" name="new_id" required>
        <button class="btn" type="submit">Move</button>
    </form>

    <h3 style="margin-top:2rem;">Move User</h3>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="type" value="user">
        <label for="user_old_id">Current ID</label>
        <input type="number" id="user_old_id" name="old_id" required>
        <label for="user_new_id">New ID</label>
        <input type="number" id="user_new_id" name="new_id" required>
        <button class="btn" type="submit">Move</button>
    </form>

    <h3 style="margin-top:2rem;">Hard-Delete Anonymized Accounts</h3>
    <form method="post" onsubmit="return confirm('Permanently delete every deleted_user_* account from the database? This cannot be undone.');">
        <?= csrfField() ?>
        <input type="hidden" name="type" value="cleanup_anonymized">
        <button class="btn secondary" type="submit">Hard-delete all anonymized users</button>
    </form>

    <h3 style="margin-top:2rem;">Scratch Verification Settings</h3>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="type" value="update_scratch_verify_settings">
        <label for="scratch_verify_target_user">Scratch account new users must follow</label>
        <input type="text" id="scratch_verify_target_user" name="scratch_verify_target_user" value="<?= e($scratchVerifyTargetUser) ?>" placeholder="e.g. ScratchNews">
        <label for="scratch_verify_project_id">Scratch project ID they must comment on</label>
        <input type="text" id="scratch_verify_project_id" name="scratch_verify_project_id" value="<?= e($scratchVerifyProjectId) ?>" placeholder="e.g. 123456789">
        <button class="btn" type="submit">Save</button>
    </form>

    <h3 style="margin-top:2rem;">Suspicious IPs (require phone verification)</h3>
    <p style="color:#888; font-size:0.9rem; margin-top:-0.5rem;">Visitors registering from these IPs get a silent extra step requiring SMS phone verification. Normal visitors never see this step.</p>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="type" value="add_suspicious_ip">
        <label for="ip_address">IP address</label>
        <input type="text" id="ip_address" name="ip_address" placeholder="e.g. 203.0.113.5" required>
        <label for="note">Note (optional)</label>
        <input type="text" id="note" name="note" placeholder="Why this IP was flagged">
        <button class="btn" type="submit">Flag IP</button>
    </form>
    <?php if ($suspiciousIps): ?>
    <table style="width:auto; margin-top:1rem;">
        <tr><th>IP Address</th><th>Note</th><th>Flagged</th><th>Actions</th></tr>
        <?php foreach ($suspiciousIps as $sip): ?>
            <tr>
                <td><?= e($sip['ip_address']) ?></td>
                <td><?= e($sip['note'] ?: '—') ?></td>
                <td><?= utcTimeTag($sip['created_at']) ?></td>
                <td>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Unflag this IP?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="type" value="remove_suspicious_ip">
                        <input type="hidden" name="suspicious_ip_id" value="<?= (int)$sip['id'] ?>">
                        <button class="btn secondary" type="submit">Unflag</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <h3 style="margin-top:2rem;">Pending Phone Verifications</h3>
    <p style="color:#888; font-size:0.9rem; margin-top:-0.5rem;">These accounts registered with a phone number instead of Scratch verification. They can't comment or submit articles until you contact them yourself and approve below.</p>
    <?php if ($pendingPhoneVerifications): ?>
    <table style="width:auto; margin-top:1rem;">
        <tr><th>Username</th><th>Phone Number</th><th>Registered</th><th>Actions</th></tr>
        <?php foreach ($pendingPhoneVerifications as $ppv): ?>
            <tr>
                <td><a href="/@<?= e($ppv['username']) ?>">@<?= e($ppv['username']) ?></a></td>
                <td><?= e($ppv['phone_number']) ?></td>
                <td><?= utcTimeTag($ppv['created_at']) ?></td>
                <td>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Approve this phone number? This unlocks commenting and submissions for @<?= e($ppv['username']) ?>.');">
                        <?= csrfField() ?>
                        <input type="hidden" name="type" value="approve_phone_verification">
                        <input type="hidden" name="phone_user_id" value="<?= (int)$ppv['id'] ?>">
                        <button class="btn" type="submit">Approve</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p style="color:#888; font-size:0.9rem;">No pending phone verifications.</p>
    <?php endif; ?>

    <h3 style="margin-top:2rem;">Verified Scratch Usernames</h3>
    <p style="color:#888; font-size:0.9rem; margin-top:-0.5rem;">Unlinking clears the account's Scratch username so verify-scratch.php will stop rejecting it as "already linked" — lets the user (or someone else) verify that Scratch username again. Does not delete or ban the ScratchNews account itself.</p>
    <?php if ($scratchLinkedUsers): ?>
    <table style="width:auto; margin-top:1rem;">
        <tr><th>Username</th><th>Linked Scratch Account</th><th>Verified</th><th>Actions</th></tr>
        <?php foreach ($scratchLinkedUsers as $slu): ?>
            <tr>
                <td><a href="/@<?= e($slu['username']) ?>">@<?= e($slu['username']) ?></a></td>
                <td><a href="https://scratch.mit.edu/users/<?= rawurlencode($slu['scratch_username']) ?>/" target="_blank" rel="noopener">@<?= e($slu['scratch_username']) ?></a></td>
                <td><?= $slu['scratch_verified_at'] ? utcTimeTag($slu['scratch_verified_at']) : '—' ?></td>
                <td>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Unlink @<?= e($slu['scratch_username']) ?> from @<?= e($slu['username']) ?>? They will need to re-verify to link a Scratch account again.');">
                        <?= csrfField() ?>
                        <input type="hidden" name="type" value="unlink_scratch_username">
                        <input type="hidden" name="scratch_user_id" value="<?= (int)$slu['id'] ?>">
                        <button class="btn secondary" type="submit">Unlink</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p style="color:#888; font-size:0.9rem;">No verified Scratch usernames yet.</p>
    <?php endif; ?>

    <h3 style="margin-top:2rem;">Manually Verify a User</h3>
    <p style="color:#888; font-size:0.9rem; margin-top:-0.5rem;">Grants verified status directly, bypassing Scratch or phone verification — for cases where the normal flow isn't working for someone.</p>
    <form method="post" style="margin-top:1rem; display:flex; gap:0.5rem; align-items:center;">
        <?= csrfField() ?>
        <input type="hidden" name="type" value="manually_verify_user">
        <input type="text" name="verify_username" placeholder="ScratchNews username" required style="padding:0.4rem 0.6rem;">
        <button class="btn" type="submit">Verify</button>
    </form>

    <h3 style="margin-top:2rem;">Assign Article to User</h3>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="type" value="assign_article_user">
        <label for="assign_article_id">Article ID</label>
        <input type="number" id="assign_article_id" name="article_id" required>
        <label for="assign_user_id">User ID</label>
        <input type="number" id="assign_user_id" name="assign_user_id" required>
        <button class="btn" type="submit">Assign</button>
    </form>

    <h3 style="margin-top:2rem;">Assign Categories to Article</h3>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="type" value="assign_article_categories">
        <label for="cat_article_id">Article ID</label>
        <input type="number" id="cat_article_id" name="cat_article_id" required>
        <div class="category-checkboxes">
            <?php foreach (getAllCategories() as $cat): ?>
                <label class="category-checkbox">
                    <input type="checkbox" name="categories[]" value="<?= (int)$cat['id'] ?>" class="category-cb">
                    <?= e($cat['name']) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <button class="btn" type="submit">Assign (up to 3)</button>
    </form>

    <h3 style="margin-top:2rem;">API Settings</h3>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="type" value="update_api_settings">
        <label>
            <input type="checkbox" name="rate_limiting_enabled" <?= $apiRateLimitingEnabled ? 'checked' : '' ?>>
            Rate limiting enabled (uncheck to fully open the API, no limits at all)
        </label>
        <label for="anonymous_rate_limit">Default limit for requests with no key (per min, 0 = unlimited)</label>
        <input type="number" id="anonymous_rate_limit" name="anonymous_rate_limit" min="0" value="<?= (int)$apiAnonLimit ?>">
        <button class="btn" type="submit">Save</button>
    </form>

    <h3 style="margin-top:2rem;">API Keys</h3>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="type" value="create_api_key">
        <label for="key_label">Label</label>
        <input type="text" id="key_label" name="key_label" placeholder="e.g. ScratchStats (MaterArc)" required>
        <label for="key_rate_limit">Rate limit (per min, blank = unlimited)</label>
        <input type="number" id="key_rate_limit" name="key_rate_limit" min="1" placeholder="unlimited">
        <button class="btn" type="submit">Create Key</button>
    </form>
    <table>
        <tr><th>ID</th><th>Label</th><th>Rate Limit</th><th>Created</th><th></th></tr>
        <?php foreach ($apiKeys as $k): ?>
            <tr>
                <td>#<?= (int)$k['id'] ?></td>
                <td><?= e($k['label'] ?? '') ?></td>
                <td>
                    <form method="post" style="display:flex;gap:0.5rem;align-items:center;">
                        <?= csrfField() ?>
                        <input type="hidden" name="type" value="update_api_key_limit">
                        <input type="hidden" name="key_id" value="<?= (int)$k['id'] ?>">
                        <input type="number" name="new_rate_limit" min="1" placeholder="unlimited" value="<?= $k['rate_limit_per_minute'] !== null ? (int)$k['rate_limit_per_minute'] : '' ?>" style="width:6rem;">
                        <button class="btn inline" type="submit">Update</button>
                    </form>
                </td>
                <td><?= e($k['created_at']) ?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="type" value="revoke_api_key">
                        <input type="hidden" name="key_id" value="<?= (int)$k['id'] ?>">
                        <button class="btn inline" type="submit">Revoke</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h3 style="margin-top:2rem;">Current Articles</h3>
    <table>
        <tr><th>ID</th><th>Title</th></tr>
        <?php foreach ($articles as $a): ?>
            <tr><td>#<?= (int)$a['id'] ?></td><td><?= e($a['title']) ?></td></tr>
        <?php endforeach; ?>
    </table>

    <h3 style="margin-top:2rem;">Current Users</h3>
    <table>
        <tr><th>ID</th><th>Username</th></tr>
        <?php foreach ($allUsers as $u): ?>
            <tr><td>#<?= (int)$u['id'] ?></td><td><?= e($u['username']) ?></td></tr>
        <?php endforeach; ?>
    </table>
</main>
</body>
</html>