<?php
declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * SMTP via PHPMailer. With MAIL_ENABLED=false (development), messages are
 * appended to storage/logs/mail.log instead of being sent.
 */
final class Mailer
{
    public static function send(string $to, string $name, string $subject, string $html, string $text): void
    {
        $cfg = config('mail');
        if (!$cfg['enabled']) {
            $entry = sprintf("=== %s\nTo: %s <%s>\nSubject: %s\n\n%s\n\n", date('c'), $name, $to, $subject, $text);
            file_put_contents(BASE_PATH . '/storage/logs/mail.log', $entry, FILE_APPEND | LOCK_EX);
            return;
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $cfg['host'];
        $mail->Port = $cfg['port'];
        $mail->SMTPAuth = $cfg['username'] !== '';
        $mail->Username = $cfg['username'];
        $mail->Password = $cfg['password'];
        $mail->SMTPSecure = match ($cfg['encryption']) {
            'ssl'   => PHPMailer::ENCRYPTION_SMTPS,
            'none'  => '',
            default => PHPMailer::ENCRYPTION_STARTTLS,
        };
        $mail->SMTPAutoTLS = $cfg['encryption'] !== 'none';
        $mail->Timeout = 15;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom($cfg['from'], $cfg['from_name']);
        $mail->addAddress($to, $name);
        $mail->Subject = one_line($subject); // never allow header line breaks
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $text;
        $mail->send();
    }

    /**
     * Generic branded message: a heading, paragraphs and one link.
     * @return array{0: string, 1: string} [html, text]
     */
    public static function renderMessage(string $name, string $title, array $paragraphs, string $link, string $linkLabel): array
    {
        $first = explode(' ', $name)[0];
        $paras = implode('', array_map(
            static fn ($p) => '<p style="margin:0 0 12px;white-space:pre-line;word-break:break-all">' . e($p) . '</p>',
            $paragraphs
        ));
        $html = '<!doctype html><html><body style="margin:0;background:#f5f5f5;font-family:Segoe UI,Arial,sans-serif;color:#545454">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;border:1px solid #e2e2e2">'
            . '<tr><td style="background:#0d8257;border-radius:12px 12px 0 0;padding:16px 24px;color:#ffffff;font-weight:bold">'
            . e(setting('org.name', 'RUMA Hospital')) . ' · IT Support</td></tr>'
            . '<tr><td style="padding:24px"><p style="margin:0 0 12px">Hello ' . e($first) . ',</p>'
            . '<h1 style="margin:0 0 12px;font-size:18px;color:#2b2b2b">' . e($title) . '</h1>' . $paras
            . '<a href="' . e($link) . '" style="display:inline-block;background:#0d8257;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:8px">'
            . e($linkLabel) . '</a></td></tr></table></td></tr></table></body></html>';
        $text = "Hello {$first},\n\n{$title}\n\n" . implode("\n\n", $paragraphs) . "\n\n{$linkLabel}: {$link}";
        return [$html, $text];
    }

    /**
     * Branded, minimal notification email. Inline styles are fine here (email
     * clients ignore stylesheets); every dynamic value is escaped.
     * @return array{0: string, 1: string} [html, text]
     */
    public static function render(string $name, string $title, string $body, array $ticket, string $link): array
    {
        $org = setting('org.name', 'RUMA Hospital');
        $ref = (string) ($ticket['ref'] ?? '');
        $tTitle = (string) ($ticket['title'] ?? '');
        $first = explode(' ', $name)[0];

        $html = '<!doctype html><html><body style="margin:0;background:#f5f5f5;font-family:Segoe UI,Arial,sans-serif;color:#545454">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;border:1px solid #e2e2e2">'
            . '<tr><td style="background:#0d8257;background-image:linear-gradient(to right,#1b5e08,#0d8257);border-radius:12px 12px 0 0;padding:16px 24px;color:#ffffff;font-weight:bold;font-size:16px">'
            . e($org) . ' · IT Support</td></tr>'
            . '<tr><td style="padding:24px">'
            . '<p style="margin:0 0 12px">Hello ' . e($first) . ',</p>'
            . '<h1 style="margin:0 0 12px;font-size:18px;color:#2b2b2b">' . e($title) . '</h1>'
            . ($body !== '' ? '<p style="margin:0 0 16px;white-space:pre-line;border-left:3px solid #e2e2e2;padding-left:12px">' . e($body) . '</p>' : '')
            . '<p style="margin:0 0 20px;font-size:14px;color:#666666">' . e($ref) . ' · ' . e($tTitle) . '</p>'
            . '<a href="' . e($link) . '" style="display:inline-block;background:#0d8257;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:8px">Open ticket</a>'
            . '</td></tr>'
            . '<tr><td style="padding:16px 24px;border-top:1px solid #e2e2e2;font-size:12px;color:#666666">'
            . 'You receive this because of your notification settings. Change them under My account. Please do not reply to this email.'
            . '</td></tr></table></td></tr></table></body></html>';

        $text = "Hello {$first},\n\n{$title}\n" . ($body !== '' ? "\n{$body}\n" : '')
            . "\n{$ref} · {$tTitle}\nOpen ticket: {$link}\n\n"
            . "You receive this because of your notification settings. Change them under My account.";

        return [$html, $text];
    }
}
