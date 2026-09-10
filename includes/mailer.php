<?php
/**
 * Oyejo Gas - mail / SMS / WhatsApp senders (Phase 22).
 *
 * Every message is appended to storage/logs/{mail,sms,whatsapp}.log so flows
 * stay auditable and testable. Real delivery only happens when the matching
 * provider settings exist in .env — credentials never live in code, the
 * database or any web-accessible file (WA-08).
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

function mailer_log($file, $line) {
    if (!is_dir(LOG_PATH)) {
        @mkdir(LOG_PATH, 0755, true);
    }
    @file_put_contents(LOG_PATH . '/' . $file, $line, FILE_APPEND | LOCK_EX);
}

function send_mail($to, $subject, $body) {
    $line = '[' . date('Y-m-d H:i:s') . '] TO: ' . $to . ' | SUBJ: ' . $subject
        . ' | ' . str_replace(["\r", "\n"], ' ', (string) $body) . "\n";
    mailer_log('mail.log', $line);
    if (env_bool('MAIL_REAL', false)) {
        @mail(
            $to,
            $subject,
            (string) $body,
            'From: ' . env('MAIL_FROM_NAME', APP_NAME) . ' <' . env('MAIL_FROM', 'no-reply@localhost') . '>' . "\r\n"
            . 'Content-Type: text/plain; charset=UTF-8'
        );
    }
    return true;
}

/**
 * POST JSON to a generic message provider.
 * Returns [true, $provider_msg_id] or [false, $error].
 */
function mailer_provider_post($url, $api_key, array $payload, $timeout = 10) {
    if (!function_exists('curl_init')) {
        return [false, 'curl unavailable'];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
        ],
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        return [false, 'provider unreachable: ' . $err];
    }
    if ($code < 200 || $code >= 300) {
        return [false, 'provider HTTP ' . $code . ': ' . substr((string) $resp, 0, 200)];
    }
    $data = json_decode((string) $resp, true);
    $mid = is_array($data) ? (string) ($data['message_id'] ?? $data['id'] ?? $data['sid'] ?? '') : '';
    return [true, $mid !== '' ? substr($mid, 0, 150) : 'ok'];
}

/**
 * SMS sender. Returns [true, $provider_msg_id|'log'] or [false, $error].
 */
function sms_send($to, $message) {
    $to = trim((string) $to);
    $message = (string) $message;
    mailer_log('sms.log', '[' . date('Y-m-d H:i:s') . '] TO: ' . $to
        . ' | ' . str_replace(["\r", "\n"], ' ', $message) . "\n");
    $url = trim((string) env('SMS_API_URL', ''));
    $key = (string) env('SMS_API_KEY', '');
    if ($url === '' || $key === '') {
        return [true, 'log']; // no provider configured: logged only
    }
    return mailer_provider_post($url, $key, ['to' => $to, 'message' => $message]);
}

/**
 * WhatsApp sender. Returns [true, $provider_msg_id|'log'] or [false, $error].
 */
function whatsapp_send($to, $message) {
    $to = trim((string) $to);
    $message = (string) $message;
    mailer_log('whatsapp.log', '[' . date('Y-m-d H:i:s') . '] TO: ' . $to
        . ' | ' . str_replace(["\r", "\n"], ' ', $message) . "\n");
    $url = trim((string) env('WHATSAPP_API_URL', ''));
    $key = (string) env('WHATSAPP_API_KEY', '');
    if ($url === '' || $key === '') {
        return [true, 'log']; // no provider configured: logged only
    }
    return mailer_provider_post($url, $key, [
        'from' => (string) env('WHATSAPP_SENDER', ''),
        'to' => $to,
        'type' => 'text',
        'text' => $message,
    ]);
}
