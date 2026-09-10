<?php
/**
 * Oyejo Gas - Web Push (Phase 27, PW-09).
 * Subscription storage plus a dependency-free sender: VAPID (ES256 JWT)
 * auth with aes128gcm payload encryption (RFC 8291/8292). Keys live in
 * server env (VAPID_PUBLIC_KEY/VAPID_PRIVATE_KEY); the public key alone
 * is exposed to browsers for subscribing.
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

function push_enabled() {
    if (!oyejo_feature('push_notifications')) {
        return false;
    }
    [$pub, $priv] = push_vapid_keys();
    return $pub !== null && $priv !== null;
}

function push_b64url_decode($s) {
    $s = (string) $s;
    if (!preg_match('/^[A-Za-z0-9\-_]+$/', $s)) {
        return false;
    }
    $raw = base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    return $raw === false ? false : $raw;
}

function push_b64url_encode($raw) {
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

/** [public_raw|null, private_raw|null] 65-byte point + 32-byte scalar. */
function push_vapid_keys() {
    $pub = push_b64url_decode(trim((string) env('VAPID_PUBLIC_KEY', '')));
    $priv = push_b64url_decode(trim((string) env('VAPID_PRIVATE_KEY', '')));
    if (!is_string($pub) || strlen($pub) !== 65 || $pub[0] !== "\x04") {
        $pub = null;
    }
    if (!is_string($priv) || strlen($priv) !== 32) {
        $priv = null;
    }
    return [$pub, $priv];
}

function push_subscribe($user_id, $endpoint, $p256dh, $auth) {
    $endpoint = trim((string) $endpoint);
    if (!preg_match('#^https://#i', $endpoint) || strlen($endpoint) > 500) {
        return [false, 'That push endpoint looks invalid.'];
    }
    $p = push_b64url_decode($p256dh);
    $a = push_b64url_decode($auth);
    if (!is_string($p) || strlen($p) !== 65 || $p[0] !== "\x04") {
        return [false, 'That push key looks invalid.'];
    }
    if (!is_string($a) || strlen($a) < 16) {
        return [false, 'That push auth secret looks invalid.'];
    }
    db()->prepare('INSERT INTO `push_subscriptions` (`user_id`, `endpoint`, `p256dh`, `auth`)
                    VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE `user_id` = VALUES(`user_id`),
                    `p256dh` = VALUES(`p256dh`), `auth` = VALUES(`auth`)')
        ->execute([(int) $user_id, $endpoint, (string) $p256dh, (string) $auth]);
    return [true, 'Push notifications enabled on this device.'];
}

function push_unsubscribe($user_id, $endpoint) {
    db()->prepare('DELETE FROM `push_subscriptions` WHERE `user_id` = ? AND `endpoint` = ?')
        ->execute([(int) $user_id, trim((string) $endpoint)]);
    return [true, 'Push notifications disabled on this device.'];
}

function push_subscriptions_for($user_id) {
    $stmt = db()->prepare('SELECT * FROM `push_subscriptions` WHERE `user_id` = ? ORDER BY `id`');
    $stmt->execute([(int) $user_id]);
    return $stmt->fetchAll();
}

/* ---------------- sender (VAPID + aes128gcm) ---------------- */

function push_pem($der, $label) {
    return "-----BEGIN $label-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END $label-----\n";
}

/** DER-encoded ECDSA signature -> raw R||S (64 bytes). */
function push_der_to_raw($der) {
    $p = 2;
    if (strlen($der) < 8 || $der[0] !== "\x30") {
        return false;
    }
    if ($der[1] === "\x81") {
        $p = 3;
    }
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        if ($der[$p] !== "\x02") {
            return false;
        }
        $len = ord($der[$p + 1]);
        $num = substr($der, $p + 2, $len);
        $num = ltrim($num, "\x00");
        $out .= str_pad($num, 32, "\x00", STR_PAD_LEFT);
        $p += 2 + $len;
    }
    return strlen($out) === 64 ? $out : false;
}

/** VAPID JWT for $audience (push service origin). Returns '' on failure. */
function push_jwt($audience) {
    [$pub, $priv] = push_vapid_keys();
    if ($pub === null || $priv === null) {
        return '';
    }
    // PKCS#8 PrivateKeyInfo around the raw scalar (built, not magic bytes).
    $oid_curve = hex2bin('06082a8648ce3d030107'); // prime256v1
    $ec_priv = "\x30\x31\x02\x01\x01\x04\x20" . $priv . "\xa0\x0a" . $oid_curve;
    $der = "\x30\x4d\x02\x01\x00\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"
        . $oid_curve . "\x04\x33" . $ec_priv;
    $key = @openssl_pkey_get_private(push_pem($der, 'PRIVATE KEY'));
    if ($key === false) {
        return '';
    }
    $sub = trim((string) env('VAPID_SUBJECT', ''));
    if ($sub === '') {
        $sub = 'mailto:' . setting('contact_email', 'no-reply@localhost');
    }
    $head = push_b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $body = push_b64url_encode(json_encode(['aud' => $audience, 'exp' => time() + 43200, 'sub' => $sub]));
    if (!@openssl_sign($head . '.' . $body, $sig, $key, OPENSSL_ALGO_SHA256)) {
        return '';
    }
    $raw = push_der_to_raw($sig);
    return $raw === false ? '' : $head . '.' . $body . '.' . push_b64url_encode($raw);
}

/**
 * Encrypt $payload for a subscriber. Returns [headers, body] or [null, error].
 * Single-record aes128gcm: salt || rs=4096 || server_pub || ciphertext.
 */
function push_encrypt($ua_pub_raw, $auth_raw, $payload) {
    $server = @openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    if ($server === false) {
        return [null, 'Could not create an encryption key.'];
    }
    $det = openssl_pkey_get_details($server);
    $as_pub = "\x04" . $det['ec']['x'] . $det['ec']['y'];
    // Peer key: SubjectPublicKeyInfo wrap around the raw point.
    $peer_der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $ua_pub_raw;
    $peer = @openssl_pkey_get_public(push_pem($peer_der, 'PUBLIC KEY'));
    if ($peer === false) {
        return [null, 'Subscriber key rejected.'];
    }
    $secret = @openssl_pkey_derive($peer, $server, 32);
    if (!is_string($secret) || strlen($secret) !== 32) {
        return [null, 'Key agreement failed.'];
    }
    $context = "WebPush: info\x00" . $ua_pub_raw . $as_pub;
    $prk = hash_hkdf('sha256', $secret, 32, $context, $auth_raw);
    $salt = random_bytes(16);
    $cek = hash_hkdf('sha256', $prk, 16, "Content-Encoding: aes128gcm\x00", $salt);
    $nonce = hash_hkdf('sha256', $prk, 12, "Content-Encoding: nonce\x00", $salt);
    $tag = '';
    $ct = @openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    if (!is_string($ct)) {
        return [null, 'Encryption failed.'];
    }
    $body = $salt . pack('N', 4096) . chr(65) . $as_pub . $ct . $tag;
    return [['Crypto-Key' => 'dh=' . push_b64url_encode($as_pub)], $body];
}

/**
 * POST one encrypted push. Returns 'sent', 'gone' (410/404: drop it) or
 * 'failed'. Never throws.
 */
function push_send_to(array $sub, $title, $body) {
    try {
        [$pub] = push_vapid_keys();
        if ($pub === null) {
            return 'failed';
        }
        $ua_pub = push_b64url_decode($sub['p256dh']);
        $auth = push_b64url_decode($sub['auth']);
        if (!is_string($ua_pub) || !is_string($auth)) {
            return 'failed';
        }
        [$extra, $cipher] = push_encrypt($ua_pub, $auth, json_encode([
            'title' => (string) $title, 'body' => (string) $body, 'url' => url('customer/notifications.php'),
        ]));
        if ($extra === null) {
            push_log('encrypt failed for sub ' . (int) $sub['id'] . ': ' . $cipher);
            return 'failed';
        }
        $parts = parse_url($sub['endpoint']);
        $aud = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
        $jwt = push_jwt($aud);
        if ($jwt === '') {
            return 'failed';
        }
        $headers = "Content-Type: application/octet-stream\r\n"
            . "Content-Encoding: aes128gcm\r\n"
            . 'Crypto-Key: ' . $extra['Crypto-Key'] . ';p256ecdsa=' . push_b64url_encode($pub) . "\r\n"
            . 'Authorization: vapid t=' . $jwt . ', k=' . push_b64url_encode($pub) . "\r\n"
            . "TTL: 86400\r\n";
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'header' => $headers, 'content' => $cipher,
            'timeout' => 10, 'ignore_errors' => true,
        ], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        @file_get_contents($sub['endpoint'], false, $ctx);
        $code = 0;
        foreach ((array) ($http_response_header ?? []) as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $mm)) {
                $code = (int) $mm[1];
            }
        }
        if ($code === 404 || $code === 410) {
            return 'gone';
        }
        if ($code >= 200 && $code < 300) {
            return 'sent';
        }
        push_log('push to sub ' . (int) $sub['id'] . ' got HTTP ' . $code);
        return 'failed';
    } catch (Throwable $t) {
        push_log('push error: ' . $t->getMessage());
        return 'failed';
    }
}

/** Best-effort fan-out to a user's devices. Returns [sent, gone, failed]. */
function push_web_send($user_id, $title, $body) {
    $out = ['sent' => 0, 'gone' => 0, 'failed' => 0];
    if (!push_enabled()) {
        return $out;
    }
    $subs = push_subscriptions_for($user_id);
    if ($subs === []) {
        return $out;
    }
    foreach ($subs as $s) {
        $r = push_send_to($s, $title, $body);
        $out[$r]++;
        if ($r === 'gone') {
            db()->prepare('DELETE FROM `push_subscriptions` WHERE `id` = ?')->execute([(int) $s['id']]);
        }
    }
    return $out;
}

function push_log($line) {
    if (function_exists('mailer_log')) {
        mailer_log('push.log', $line);
    }
}
