<?php
// Moderator applications (v0.30). Loaded from functions.php.
// Tunables - edit here, no other file needs to change.

const MOD_APPS_OPEN = true;                  // false = form shows "closed", queue still works
const MOD_APP_MIN_ACCOUNT_DAYS = 7;          // minimum account age to apply
const MOD_APP_COOLDOWN_DAYS = 30;            // wait after a declined application
const MOD_APP_DECISION_ADMIN_ONLY = true;    // true = only the dev accepts/declines; false = Head Mods can too
const MOD_APP_AVAILABILITY = ['Daily', 'A few times a week', 'Weekends mostly', 'Not sure yet'];

function getModApplicationById(int $id): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT a.*, u.username FROM mod_applications a JOIN users u ON u.id = a.user_id WHERE a.id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function getLatestModApplicationForUser(int $userId): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM mod_applications WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// Returns ['ok' => bool, 'reason' => string]. Used by the form page AND again
// at submit time, so a double-click or a stale tab can't create a duplicate.
function getModApplicationEligibility(int $userId): array {
    if (!MOD_APPS_OPEN) {
        return ['ok' => false, 'reason' => 'Moderator applications are closed right now. Check back later.'];
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT is_banned, is_admin, is_moderator, DATEDIFF(NOW(), created_at) AS age_days FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$u) return ['ok' => false, 'reason' => 'Account not found.'];
    if (!empty($u['is_banned'])) return ['ok' => false, 'reason' => 'Banned accounts cannot apply.'];
    if (!empty($u['is_admin']) || !empty($u['is_moderator'])) {
        return ['ok' => false, 'reason' => 'You are already part of the moderation team.'];
    }
    $age = (int)($u['age_days'] ?? 0);
    if ($age < MOD_APP_MIN_ACCOUNT_DAYS) {
        $left = MOD_APP_MIN_ACCOUNT_DAYS - $age;
        return ['ok' => false, 'reason' => 'Your account needs to be at least ' . MOD_APP_MIN_ACCOUNT_DAYS . ' days old to apply. Try again in ' . $left . ' day' . ($left === 1 ? '' : 's') . '.'];
    }
    $latest = getLatestModApplicationForUser($userId);
    if ($latest && $latest['status'] === 'pending') {
        return ['ok' => false, 'reason' => 'You already have an application waiting for review.'];
    }
    if ($latest && $latest['status'] === 'declined' && !empty($latest['reviewed_at'])) {
        $stmt = $db->prepare("SELECT DATEDIFF(NOW(), reviewed_at) AS d FROM mod_applications WHERE id = ?");
        $lid = (int)$latest['id'];
        $stmt->bind_param('i', $lid);
        $stmt->execute();
        $d = (int)($stmt->get_result()->fetch_assoc()['d'] ?? 0);
        $stmt->close();
        if ($d < MOD_APP_COOLDOWN_DAYS) {
            $left = MOD_APP_COOLDOWN_DAYS - $d;
            return ['ok' => false, 'reason' => 'You can apply again in ' . $left . ' day' . ($left === 1 ? '' : 's') . '.'];
        }
    }
    return ['ok' => true, 'reason' => ''];
}

function submitModApplication(int $userId, string $why, string $experience, string $scenario, string $availability): int {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO mod_applications (user_id, why_text, experience_text, scenario_text, availability) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('issss', $userId, $why, $experience, $scenario, $availability);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();
    // Head Mods + dev only, same audience as Contact Us.
    notifyContactRecipients('admin_new_mod_application', $userId, '/admin/mod-applications', null);
    return $id;
}

function getPendingModApplications(): array {
    $db = getDB();
    $result = $db->query("SELECT a.*, u.username, u.created_at AS user_created_at FROM mod_applications a JOIN users u ON u.id = a.user_id WHERE a.status = 'pending' ORDER BY a.created_at ASC");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getRecentDecidedModApplications(int $limit = 20): array {
    $db = getDB();
    $limit = max(1, $limit);
    $result = $db->query("SELECT a.*, u.username, r.username AS reviewer_username FROM mod_applications a JOIN users u ON u.id = a.user_id LEFT JOIN users r ON r.id = a.reviewed_by WHERE a.status <> 'pending' ORDER BY a.reviewed_at DESC LIMIT " . $limit);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getPendingModApplicationsCount(): int {
    $db = getDB();
    $result = $db->query("SELECT COUNT(*) AS cnt FROM mod_applications WHERE status = 'pending'");
    return $result ? (int)($result->fetch_assoc()['cnt'] ?? 0) : 0;
}

// $decision: 'accepted' or 'declined'. Returns ['ok' => bool, 'reason' => string].
function decideModApplication(int $id, int $reviewerId, string $decision, string $note): array {
    if (!in_array($decision, ['accepted', 'declined'], true)) {
        return ['ok' => false, 'reason' => 'Invalid decision.'];
    }
    $app = getModApplicationById($id);
    if (!$app || $app['status'] !== 'pending') {
        return ['ok' => false, 'reason' => 'Application not found or already reviewed.'];
    }
    if ((int)$app['user_id'] === $reviewerId) {
        return ['ok' => false, 'reason' => "You can't review your own application."];
    }
    $db = getDB();
    $noteVal = trim($note) === '' ? null : trim($note);
    // The status = 'pending' guard makes this a no-op if two reviewers click at once.
    $stmt = $db->prepare("UPDATE mod_applications SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ? AND status = 'pending'");
    $stmt->bind_param('sisi', $decision, $reviewerId, $noteVal, $id);
    $stmt->execute();
    $changed = $stmt->affected_rows;
    $stmt->close();
    if ($changed < 1) {
        return ['ok' => false, 'reason' => 'Application was already reviewed.'];
    }
    $applicantId = (int)$app['user_id'];
    if ($decision === 'accepted') {
        setUserModerator($applicantId, true);
        createNotification($applicantId, 'mod_application_accepted', null, '/moderator', null);
    } else {
        createNotification($applicantId, 'mod_application_declined', null, '/apply-moderator', null);
    }
    return ['ok' => true, 'reason' => ''];
}
