<?php
/**
 * Tiny mail sender with pluggable drivers.
 *
 * Drivers (chosen via config('mail.driver')):
 *   - 'log'  → writes the message to storage/logs/mail.log. Default in dev.
 *   - 'mail' → PHP's built-in mail() — works on most shared cPanel hosts
 *              that have a sendmail-compatible MTA. No config beyond
 *              from_addr / from_name.
 *   - 'smtp' → uses PHPMailer if vendored at includes/vendor/PHPMailer/.
 *              See INSTALL.md for the one-time vendor instructions.
 *              SMTP config comes from config('mail.smtp.*').
 *
 * The class never throws — failures are logged and `send()` returns false
 * so callers can show a graceful "email could not be sent" message.
 */

defined('APP_BOOTED') or exit;

class Mailer
{
    /**
     * Send a plain-text email. Returns true on success.
     */
    public static function send(string $to, string $subject, string $body, array $opts = []): bool
    {
        $driver  = (string) config('mail.driver', 'log');
        $from    = (string) config('mail.from_addr', 'no-reply@example.org');
        $fromN   = (string) config('mail.from_name', config('app_name', 'ePublicLibrary'));
        $replyTo = $opts['reply_to'] ?? null;

        try {
            return match ($driver) {
                'mail' => self::sendMail($to, $subject, $body, $from, $fromN, $replyTo),
                'smtp' => self::sendSmtp($to, $subject, $body, $from, $fromN, $replyTo),
                default => self::sendLog($to, $subject, $body, $from, $fromN),
            };
        } catch (Throwable $e) {
            log_error($e);
            return false;
        }
    }

    /* --------------- drivers --------------- */

    private static function sendLog(string $to, string $subject, string $body, string $from, string $fromName): bool
    {
        $line = "=== " . now_utc() . " ===\n"
              . "From: {$fromName} <{$from}>\n"
              . "To: {$to}\n"
              . "Subject: {$subject}\n\n"
              . $body . "\n\n";
        $dir = storage_path('logs_path');
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        @file_put_contents($dir . '/mail.log', $line, FILE_APPEND | LOCK_EX);
        return true;
    }

    private static function sendMail(string $to, string $subject, string $body, string $from, string $fromName, ?string $replyTo): bool
    {
        $headers = [
            'From: ' . self::encodeHeader($fromName) . ' <' . $from . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: ePublicLibrary',
        ];
        if ($replyTo) $headers[] = 'Reply-To: ' . $replyTo;

        $encodedSubject = self::encodeHeader($subject);
        $ok = @mail($to, $encodedSubject, $body, implode("\r\n", $headers), '-f' . $from);
        if (!$ok) {
            self::sendLog($to, $subject, $body, $from, $fromName);
        }
        return (bool) $ok;
    }

    private static function sendSmtp(string $to, string $subject, string $body, string $from, string $fromName, ?string $replyTo): bool
    {
        $vendorDir = project_path('includes/vendor/PHPMailer');
        $autoload  = $vendorDir . '/src/PHPMailer.php';
        if (!is_file($autoload)) {
            // Fall back to log + warn so dev sees something useful
            self::sendLog($to, $subject, $body, $from, $fromName);
            error_log('Mailer: smtp driver requested but PHPMailer not vendored at ' . $vendorDir);
            return false;
        }
        require_once $vendorDir . '/src/Exception.php';
        require_once $vendorDir . '/src/PHPMailer.php';
        require_once $vendorDir . '/src/SMTP.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = (string) config('mail.smtp.host', 'localhost');
        $mail->Port       = (int)    config('mail.smtp.port', 587);
        $mail->SMTPAuth   = true;
        $mail->Username   = (string) config('mail.smtp.username', '');
        $mail->Password   = (string) config('mail.smtp.password', '');
        $mail->SMTPSecure = (string) config('mail.smtp.encryption', 'tls');
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 10;

        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);
        if ($replyTo) $mail->addReplyTo($replyTo);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->isHTML(false);
        $mail->send();
        return true;
    }

    /* --------------- helpers --------------- */

    private static function encodeHeader(string $value): string
    {
        return preg_match('/[\x80-\xff]/', $value)
            ? '=?UTF-8?B?' . base64_encode($value) . '?='
            : $value;
    }
}
