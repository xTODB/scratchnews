<?php
// Raw Web Push sender - no Composer/vendor dir since the site has no build step and
// deploys straight from GitHub via FTP. Implements RFC 8291 (message encryption) +
// RFC 8292 (VAPID) using only PHP's built-in openssl/hash extensions.
//
// The encryption pipeline (ECDH -> HKDF -> AES-128-GCM) was verified against the
// official worked example in RFC 8291 Appendix A before this went live - every
// intermediate value (ecdh_secret, PRK_key, IKM, PRK, CEK, NONCE) and the final
// ciphertext matched the RFC's test vector exactly. VAPID JWT signing (ES256 with
// raw r||s signatures, not DER) was verified with a round-trip self-check. What
// COULDN'T be tested here: an actual send to a real push service and a real browser
// receiving/decrypting it - that needs a live test after this deploys. If
// notifications don't show up, check TTL, dead subscriptions (should auto-prune on
// 404/410), and that VAPID keys are actually set in api_settings first.

function b64urlDecode(string $s): string {
    $s = strtr($s, '-_', '+/');
    $pad = strlen($s) % 4;
    if ($pad) $s .= str_repeat('=', 4 - $pad);
    return base64_decode($s);
}

function b64urlEncode(string $bin): string {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

// Fixed DER byte layout for a P-256 (prime256v1) key in PKCS8/SPKI form - determined
// empirically from a real openssl-generated key so it's not hand-derived ASN.1.
const EC_PRIV_DER_PREFIX_HEX = '308187020100301306072a8648ce3d020106082a8648ce3d030107046d306b0201010420';
const EC_PRIV_DER_SUFFIX_HEX = 'a14403420004';
const EC_PUB_DER_PREFIX_HEX = '3059301306072a8648ce3d020106082a8648ce3d0301070342' . '00';

function pemFromDer(string $der, string $label): string {
    return "-----BEGIN $label-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END $label-----\n";
}

// $pubRaw: 65 bytes, uncompressed point form, starts with 0x04
function ecPublicKeyFromRaw(string $pubRaw) {
    if (strlen($pubRaw) !== 65 || ord($pubRaw[0]) !== 0x04) {
        throw new RuntimeException('Invalid raw EC public key (need 65 bytes starting with 0x04)');
    }
    $der = hex2bin(EC_PUB_DER_PREFIX_HEX) . $pubRaw;
    $key = openssl_pkey_get_public(pemFromDer($der, 'PUBLIC KEY'));
    if (!$key) throw new RuntimeException('Failed to load EC public key: ' . openssl_error_string());
    return $key;
}

// $privRaw: 32 bytes. $pubRaw: 65 bytes starting with 0x04 (the matching public key -
// PKCS8 EC keys embed it, openssl_pkey_derive needs a structurally valid key).
function ecPrivateKeyFromRaw(string $privRaw, string $pubRaw) {
    if (strlen($privRaw) !== 32) throw new RuntimeException('Invalid raw EC private key (need 32 bytes)');
    if (strlen($pubRaw) !== 65 || ord($pubRaw[0]) !== 0x04) {
        throw new RuntimeException('Invalid raw EC public key (need 65 bytes starting with 0x04)');
    }
    $xy = substr($pubRaw, 1);
    $der = hex2bin(EC_PRIV_DER_PREFIX_HEX) . $privRaw . hex2bin(EC_PRIV_DER_SUFFIX_HEX) . $xy;
    $key = openssl_pkey_get_private(pemFromDer($der, 'PRIVATE KEY'));
    if (!$key) throw new RuntimeException('Failed to load EC private key: ' . openssl_error_string());
    return $key;
}

// Generates a fresh P-256 keypair and returns raw base64url-encoded components
// (public: 65 bytes/0x04-prefixed, private: 32 bytes). Used both for the persistent
// VAPID identity keypair (generated once, see admin/setup-push.php) and for the
// per-message ephemeral ECDH keypair RFC 8291 requires (generated fresh each send,
// never stored).
function generateRawEcKeypair(): array {
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$key) throw new RuntimeException('Failed to generate EC keypair: ' . openssl_error_string());
    $details = openssl_pkey_get_details($key);
    $x = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
    $y = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    $d = str_pad($details['ec']['d'], 32, "\x00", STR_PAD_LEFT);
    $pubRaw = "\x04" . $x . $y;
    return ['public' => b64urlEncode($pubRaw), 'private' => b64urlEncode($d)];
}

function derEcdsaSigToRawJose(string $der): string {
    $pos = 2; // skip outer SEQUENCE tag+len (assumes short-form length, true for P-256 sigs)
    $readInt = function (string $der, int &$pos): string {
        if (ord($der[$pos]) !== 0x02) throw new RuntimeException('Expected DER INTEGER');
        $pos++;
        $len = ord($der[$pos]);
        $pos++;
        $bytes = substr($der, $pos, $len);
        $pos += $len;
        $bytes = ltrim($bytes, "\x00");
        return str_pad($bytes, 32, "\x00", STR_PAD_LEFT);
    };
    $r = $readInt($der, $pos);
    $s = $readInt($der, $pos);
    return $r . $s;
}

// Builds an "Authorization: vapid t=..., k=..." header value per RFC 8292.
function buildVapidAuthHeader(string $audience, string $subject, string $vapidPublicB64, string $vapidPrivateB64): string {
    $header = b64urlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $payload = b64urlEncode(json_encode([
        'aud' => $audience,
        'exp' => time() + 12 * 3600,
        'sub' => $subject,
    ]));
    $signInput = "$header.$payload";

    $pubRaw = b64urlDecode($vapidPublicB64);
    $privRaw = b64urlDecode($vapidPrivateB64);
    $privKey = ecPrivateKeyFromRaw($privRaw, $pubRaw);

    $derSig = '';
    if (!openssl_sign($signInput, $derSig, $privKey, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('VAPID signing failed: ' . openssl_error_string());
    }
    $rawSig = derEcdsaSigToRawJose($derSig);
    $jwt = "$signInput." . b64urlEncode($rawSig);

    return 'vapid t=' . $jwt . ', k=' . $vapidPublicB64;
}

// Encrypts $payload per RFC 8291/8188 for delivery to one push subscription.
// Returns the raw request body to POST to the subscription's endpoint.
function encryptWebPushPayload(string $payload, string $p256dhB64, string $authB64): string {
    $uaPublicRaw = b64urlDecode($p256dhB64);
    $authSecret = b64urlDecode($authB64);
    if (strlen($uaPublicRaw) !== 65) throw new RuntimeException('Invalid subscription p256dh key');
    if (strlen($authSecret) !== 16) throw new RuntimeException('Invalid subscription auth secret');

    // Fresh ephemeral keypair per message, per RFC 8291 - never reused/stored.
    $asKeypair = generateRawEcKeypair();
    $asPublicRaw = b64urlDecode($asKeypair['public']);
    $asPrivateRaw = b64urlDecode($asKeypair['private']);

    $uaPubKey = ecPublicKeyFromRaw($uaPublicRaw);
    $asPrivKey = ecPrivateKeyFromRaw($asPrivateRaw, $asPublicRaw);
    $ecdhSecret = openssl_pkey_derive($uaPubKey, $asPrivKey, 32);
    if ($ecdhSecret === false) throw new RuntimeException('ECDH derivation failed: ' . openssl_error_string());

    $prkKey = hash_hmac('sha256', $ecdhSecret, $authSecret, true);
    $keyInfo = "WebPush: info\x00" . $uaPublicRaw . $asPublicRaw;
    $ikm = hash_hmac('sha256', $keyInfo . "\x01", $prkKey, true);

    $salt = random_bytes(16);
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $prk, true), 0, 16);
    $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $prk, true), 0, 12);

    $recordSize = 4096;
    $header = $salt . pack('N', $recordSize) . chr(strlen($asPublicRaw)) . $asPublicRaw;

    $padded = $payload . "\x02"; // single record = last record -> 0x02 delimiter, no extra padding
    $tag = '';
    $ciphertext = openssl_encrypt($padded, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($ciphertext === false) throw new RuntimeException('AES-GCM encryption failed: ' . openssl_error_string());

    return $header . $ciphertext . $tag;
}

// Sends one push message. Returns the HTTP status code (or 0 on a curl-level failure)
// so the caller can prune dead subscriptions on 404/410.
function sendWebPush(array $subscription, string $title, string $body, string $url): int {
    $vapidPublic = getApiSetting('vapid_public_key', '');
    $vapidPrivate = getApiSetting('vapid_private_key', '');
    $vapidSubject = getApiSetting('vapid_subject', 'mailto:admin@' . parse_url(getApiSetting('site_url', 'https://scratchnews.freedev.app'), PHP_URL_HOST));
    if ($vapidPublic === '' || $vapidPrivate === '') {
        return 0; // VAPID keys not generated yet - see admin/setup-push.php
    }

    $endpointParts = parse_url($subscription['endpoint']);
    $audience = ($endpointParts['scheme'] ?? 'https') . '://' . ($endpointParts['host'] ?? '');

    $payloadJson = json_encode(['title' => $title, 'body' => $body, 'url' => $url]);
    $body_ = encryptWebPushPayload($payloadJson, $subscription['p256dh'], $subscription['auth']);
    $authHeader = buildVapidAuthHeader($audience, $vapidSubject, $vapidPublic, $vapidPrivate);

    $ch = curl_init($subscription['endpoint']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body_);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'TTL: 86400',
        'Authorization: ' . $authHeader,
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return (int)$status;
}

// Stores/updates a subscription and its category opt-ins (empty $categoryIds = all
// categories). $userId links the subscription to a logged-in account (needed to target
// one specific person, e.g. a Contest Scratcher mention) - null leaves it anonymous.
// COALESCE on conflict means a subscription already linked to a user never gets
// silently unlinked by a later anonymous call from the same browser.
function savePushSubscription(string $endpoint, string $p256dh, string $auth, array $categoryIds = [], ?int $userId = null): int {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO push_subscriptions (endpoint, p256dh, auth, user_id) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth), user_id = COALESCE(VALUES(user_id), user_id)");
    $stmt->bind_param('sssi', $endpoint, $p256dh, $auth, $userId);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare("SELECT id FROM push_subscriptions WHERE endpoint = ?");
    $stmt->bind_param('s', $endpoint);
    $stmt->execute();
    $id = (int)($stmt->get_result()->fetch_assoc()['id'] ?? 0);
    $stmt->close();

    setPushSubscriptionCategories($id, $categoryIds);
    return $id;
}

function setPushSubscriptionCategories(int $subscriptionId, array $categoryIds): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM push_subscription_categories WHERE subscription_id = ?");
    $stmt->bind_param('i', $subscriptionId);
    $stmt->execute();
    $stmt->close();
    $categoryIds = array_unique(array_map('intval', $categoryIds));
    if (empty($categoryIds)) return; // empty = all categories
    $stmt = $db->prepare("INSERT INTO push_subscription_categories (subscription_id, category_id) VALUES (?, ?)");
    foreach ($categoryIds as $catId) {
        $stmt->bind_param('ii', $subscriptionId, $catId);
        $stmt->execute();
    }
    $stmt->close();
}

// A specific logged-in user's subscription(s) - e.g. a Contest Scratcher checking
// for a device to push their "someone wrote about you" notification to. Most users
// will have 0 or 1, but nothing stops multiple devices linking to the same account.
function getPushSubscriptionsForUser(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM push_subscriptions WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function deletePushSubscriptionByEndpoint(string $endpoint): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?");
    $stmt->bind_param('s', $endpoint);
    $stmt->execute();
    $stmt->close();
}

function deletePushSubscriptionById(int $id): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM push_subscriptions WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

function getPushSubscriptionCategoryIds(string $endpoint): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT psc.category_id FROM push_subscription_categories psc JOIN push_subscriptions ps ON ps.id = psc.subscription_id WHERE ps.endpoint = ?");
    $stmt->bind_param('s', $endpoint);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return array_map('intval', array_column($rows, 'category_id'));
}

// Subscriptions with no category rows (= all categories) OR a matching row for one of
// $articleCategoryIds.
function getPushSubscriptionsForCategories(array $articleCategoryIds): array {
    $db = getDB();
    if (empty($articleCategoryIds)) {
        // Uncategorized article - only reaches "all categories" subscribers.
        $stmt = $db->prepare("SELECT ps.* FROM push_subscriptions ps WHERE NOT EXISTS (SELECT 1 FROM push_subscription_categories psc WHERE psc.subscription_id = ps.id)");
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }
    $placeholders = implode(',', array_fill(0, count($articleCategoryIds), '?'));
    $sql = "SELECT DISTINCT ps.* FROM push_subscriptions ps
            LEFT JOIN push_subscription_categories psc ON psc.subscription_id = ps.id
            WHERE psc.subscription_id IS NULL
               OR psc.category_id IN ($placeholders)";
    $stmt = $db->prepare($sql);
    $types = str_repeat('i', count($articleCategoryIds));
    $stmt->bind_param($types, ...$articleCategoryIds);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Call this right after a new article is published (both the direct-admin-create path
// and the submission-approval path). Sends to every matching subscriber and prunes any
// subscription the push service reports as gone (404/410 = unsubscribed/expired).
function notifyPushSubscribersOfNewArticle(int $articleId, string $title, array $categoryIds): void {
    $subs = getPushSubscriptionsForCategories($categoryIds);
    if (empty($subs)) return;
    $url = '/article/' . $articleId;
    foreach ($subs as $sub) {
        try {
            $status = sendWebPush($sub, 'New on ScratchNews', $title, $url);
            if ($status === 404 || $status === 410) {
                deletePushSubscriptionById((int)$sub['id']);
            }
        } catch (Throwable $e) {
            // One bad subscription shouldn't stop the rest of the batch from sending.
            error_log('Push send failed for subscription ' . $sub['id'] . ': ' . $e->getMessage());
        }
    }
}

// Call this once a Writers' Contest entry gets approved and published. Sends both an
// in-app notification and, if they have a linked device, a push to the specific
// Scratcher the entry is about - separate from notifyPushSubscribersOfNewArticle()'s
// broadcast-by-category, since this needs to reach exactly one person regardless of
// their category subscriptions.
function notifyContestScratcherOfMention(int $scratcherUserId, int $writerUserId, string $writerUsername, int $articleId, string $articleTitle): void {
    $link = '/article/' . $articleId;
    createNotification($scratcherUserId, 'contest_mention', $writerUserId, $link, $articleTitle);

    $subs = getPushSubscriptionsForUser($scratcherUserId);
    foreach ($subs as $sub) {
        try {
            $status = sendWebPush($sub, 'Someone wrote about you!', $writerUsername . ' published an article about you: ' . $articleTitle, $link);
            if ($status === 404 || $status === 410) {
                deletePushSubscriptionById((int)$sub['id']);
            }
        } catch (Throwable $e) {
            error_log('Contest mention push failed for subscription ' . $sub['id'] . ': ' . $e->getMessage());
        }
    }
}