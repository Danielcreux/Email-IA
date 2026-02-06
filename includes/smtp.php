<?php 
function smtp_read_line($conn): string
{
    $data = '';
    while ($str = fgets($conn, 515)) {
        $data .= $str;
        if (strlen($str) >= 4 && $str[3] === ' ') break;
    }
    return $data;
}

function smtp_expect($conn, string $expectedCode, string $context): string
{
    $data = smtp_read_line($conn);
    if (substr($data, 0, 3) !== $expectedCode) {
        throw new RuntimeException("Error SMTP en $context. Esperado $expectedCode, recibido: $data");
    }
    return $data;
}

function smtp_send_email(string $to, string $subject, string $body): array
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Dirección de correo no válida'];
    }

    $conn = fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 30);
    if (!$conn) {
        return ['ok' => false, 'error' => "$errstr ($errno)"];
    }

    try {
        smtp_expect($conn, '220', 'CONNECT');

        $ehlo = 'mail.freire-sanchez-valencia.es';
        fwrite($conn, "EHLO $ehlo\r\n");
        smtp_expect($conn, '250', 'EHLO');

        fwrite($conn, "STARTTLS\r\n");
        smtp_expect($conn, '220', 'STARTTLS');

        stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

        fwrite($conn, "EHLO $ehlo\r\n");
        smtp_expect($conn, '250', 'EHLO TLS');

        fwrite($conn, "AUTH PLAIN\r\n");
        smtp_expect($conn, '334', 'AUTH');

        fwrite($conn, base64_encode("\0" . SMTP_USER . "\0" . SMTP_PASS) . "\r\n");
        smtp_expect($conn, '235', 'AUTH OK');

        fwrite($conn, "MAIL FROM:<" . SMTP_USER . ">\r\n");
        smtp_expect($conn, '250', 'MAIL FROM');

        fwrite($conn, "RCPT TO:<$to>\r\n");
        smtp_expect($conn, '250', 'RCPT TO');

        fwrite($conn, "DATA\r\n");
        smtp_expect($conn, '354', 'DATA');

        $headers = [
            "Date: " . date(DATE_RFC2822),
            "From: " . mb_encode_mimeheader(SMTP_FROM_NAME, 'UTF-8') . " <" . SMTP_USER . ">",
            "To: <$to>",
            "Subject: " . mb_encode_mimeheader($subject, 'UTF-8'),
            "Message-ID: <" . uniqid() . "@" . SMTP_HOST . ">",
            "MIME-Version: 1.0",
            "Content-Type: text/plain; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit",
        ];

        $bodySafe = preg_replace('/^\./m', '..', $body);

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $bodySafe . "\r\n.";

        fwrite($conn, $message . "\r\n");
        smtp_expect($conn, '250', 'SEND');

        fwrite($conn, "QUIT\r\n");
        fclose($conn);

        return ['ok' => true];
    } catch (Throwable $e) {
        fclose($conn);
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}