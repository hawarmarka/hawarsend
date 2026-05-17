<?php
require_once __DIR__ . '/Settings.php';

class Mailer {
    public static function send(string $to, string $subject, string $html, ?string $text = null): bool {
        $host = trim((string)Settings::get('smtp_host', getenv('SMTP_HOST') ?: ''));
        $port = (int)(Settings::get('smtp_port', getenv('SMTP_PORT') ?: 587) ?: 587);
        $user = trim((string)Settings::get('smtp_user', getenv('SMTP_USER') ?: ''));
        $pass = (string)Settings::get('smtp_pass', getenv('SMTP_PASS') ?: '');
        $from = trim((string)Settings::get('smtp_from', getenv('SMTP_FROM') ?: ''));
        $fromName = trim((string)Settings::get('smtp_from_name', getenv('SMTP_FROM_NAME') ?: APP_NAME));

        if (!$from) $from = $user ?: ('noreply@' . parse_url(APP_URL, PHP_URL_HOST));
        $text = $text ?: trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));

        if ($host) {
            try {
                return self::sendSmtp($host, $port, $user, $pass, $from, $fromName, $to, $subject, $html, $text);
            } catch (Throwable $e) {
                error_log('SMTP mail error: ' . $e->getMessage());
                return false;
            }
        }

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . self::encodeHeader($fromName) . " <$from>";
        return @mail($to, self::encodeHeader($subject), $html, implode("\r\n", $headers));
    }

    private static function sendSmtp(string $host, int $port, string $user, string $pass, string $from, string $fromName, string $to, string $subject, string $html, string $text): bool {
        $remote = ($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $fp = stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (!$fp) throw new RuntimeException("SMTP bağlantısı başarısız: $errstr");
        stream_set_timeout($fp, 20);

        self::expect($fp, [220]);
        self::cmd($fp, 'EHLO ' . (parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost'), [250]);

        if ($port !== 465) {
            self::cmd($fp, 'STARTTLS', [220], false);
            @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            self::cmd($fp, 'EHLO ' . (parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost'), [250]);
        }

        if ($user !== '') {
            self::cmd($fp, 'AUTH LOGIN', [334]);
            self::cmd($fp, base64_encode($user), [334]);
            self::cmd($fp, base64_encode($pass), [235]);
        }

        self::cmd($fp, 'MAIL FROM:<' . $from . '>', [250]);
        self::cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
        self::cmd($fp, 'DATA', [354]);

        $boundary = 'hs_' . bin2hex(random_bytes(12));
        $headers = [];
        $headers[] = 'From: ' . self::encodeHeader($fromName) . " <$from>";
        $headers[] = 'To: <' . $to . '>';
        $headers[] = 'Subject: ' . self::encodeHeader($subject);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $headers[] = 'Date: ' . date(DATE_RFC2822);
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (parse_url(APP_URL, PHP_URL_HOST) ?: 'hawarsend.local') . '>';

        $body = implode("\r\n", $headers) . "\r\n\r\n" .
            "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$text\r\n" .
            "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$html\r\n" .
            "--$boundary--\r\n.";
        fwrite($fp, str_replace("\n.", "\n..", $body) . "\r\n");
        self::expect($fp, [250]);
        self::cmd($fp, 'QUIT', [221], false);
        fclose($fp);
        return true;
    }

    private static function cmd($fp, string $cmd, array $expected, bool $throw = true): string {
        fwrite($fp, $cmd . "\r\n");
        return self::expect($fp, $expected, $throw);
    }

    private static function expect($fp, array $expected, bool $throw = true): string {
        $response = '';
        while (($line = fgets($fp, 515)) !== false) {
            $response .= $line;
            if (preg_match('/^([0-9]{3})\s/', $line, $m)) break;
        }
        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expected, true) && $throw) {
            throw new RuntimeException('SMTP beklenmeyen cevap: ' . trim($response));
        }
        return $response;
    }

    private static function encodeHeader(string $value): string {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
