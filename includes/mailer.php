<?php
function send_invitation_email(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
{
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=utf-8';
    $headers[] = 'From: ' . (config('mail.from_name', 'Anketor') . ' <' . config('mail.from_email', 'no-reply@example.com') . '>');

    $message = $htmlBody;

    if (!empty($textBody)) {
        $boundary = uniqid('np');
        $headers[1] = 'Content-Type: multipart/alternative;boundary=' . $boundary;
        $message = "--$boundary\r\n" .
            "Content-Type: text/plain; charset=utf-8\r\n\r\n" .
            $textBody . "\r\n" .
            "--$boundary\r\n" .
            "Content-Type: text/html; charset=utf-8\r\n\r\n" .
            $htmlBody . "\r\n" .
            "--$boundary--";
    }

    return mail($to, $subject, $message, implode("\r\n", $headers));
}
