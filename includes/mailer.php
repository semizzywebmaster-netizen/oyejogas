<?php
/**
 * Oyejo Gas - minimal mail/SMS sender (Phase 5).
 * Phase 22 replaces this with SMTP/SMS/WhatsApp providers + templates.
 *
 * Behaviour today: every message is appended to storage/logs/mail.log (or
 * sms.log) so flows are auditable and testable. Real delivery via PHP mail()
 * only happens when MAIL_REAL=true in .env — never by surprise.
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

function send_mail($to, $subject, $body) {
    if (!is_dir(LOG_PATH)) {
        @mkdir(LOG_PATH, 0755, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] TO: ' . $to . ' | SUBJ: ' . $subject
        . ' | ' . str_replace(["\r", "\n"], ' ', (string) $body) . "\n";
    @file_put_contents(LOG_PATH . '/mail.log', $line, FILE_APPEND | LOCK_EX);
    if (env_bool('MAIL_REAL', false)) {
        @mail(
            $to,
            $subject,
            (string) $body,
            'From: ' . env('MAIL_FROM_NAME', APP_NAME) . ' <' . env('MAIL_FROM', 'no-reply@localhost') . '>'
        );
    }
    return true;
}

/**
 * SMS provider hook. Set SMS_PROVIDER + credentials in .env and implement
 * the provider call here in Phase 22; until then messages are logged.
 */
function sms_send($to, $message) {
    if (!is_dir(LOG_PATH)) {
        @mkdir(LOG_PATH, 0755, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] TO: ' . $to
        . ' | ' . str_replace(["\r", "\n"], ' ', (string) $message) . "\n";
    @file_put_contents(LOG_PATH . '/sms.log', $line, FILE_APPEND | LOCK_EX);
    return true;
}
