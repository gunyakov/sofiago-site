<?php

declare(strict_types=1);

namespace Sofiago\Core;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends mail through a real SMTP account (config.php -> 'smtp'), never PHP's mail(). Fill in
 * the real host/username/password once you have them; until then sends will fail loudly in
 * the error log instead of silently vanishing.
 */
final class Mailer
{
    public function __construct(private Config $config)
    {
    }

    public function send(string $toEmail, string $toName, string $subject, string $html, string $text = ''): bool
    {
        $c = $this->config->get('smtp', []);

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $c['host'] ?? '';
            $mail->SMTPAuth = true;
            $mail->Username = $c['username'] ?? '';
            $mail->Password = $c['password'] ?? '';
            $mail->SMTPSecure = $c['encryption'] ?? PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) ($c['port'] ?? 587);
            $mail->CharSet = 'UTF-8';

            // sofiago.eu is Cloudflare-proxied, so 'host' here is the mail server's real origin
            // IP (config.php), not the domain — Cloudflare doesn't proxy SMTP ports. That IP's
            // TLS cert is the box's shared hostname cert (some other domain on this VPS), which
            // will never match "sofiago.eu" or a bare IP, so hostname verification is switched
            // off for this one connection (not global PHP/cURL behavior) — set config.php's
            // smtp.verify_tls => true once SMTP moves to a hostname the cert actually covers.
            if (!($c['verify_tls'] ?? false)) {
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ];
            }

            $mail->setFrom($c['from_email'] ?? 'no-reply@sofiago.eu', $c['from_name'] ?? 'SofiaGO');
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text !== '' ? $text : strip_tags($html);

            return $mail->send();
        } catch (PHPMailerException|\Throwable $e) {
            error_log('Mailer error sending to ' . $toEmail . ': ' . $e->getMessage());

            return false;
        }
    }
}
