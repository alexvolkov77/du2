<?php

namespace App\Services;

use InvalidArgumentException;

final class TelegramInitDataVerifier
{
    /** @return array<string, mixed> */
    public function verify(string $raw, string $token): array
    {
        if ($token === '' || $raw === '' || strlen($raw) > 8192) {
            throw new InvalidArgumentException('Telegram authentication is not configured.');
        }

        $data = [];
        foreach (explode('&', $raw) as $part) {
            $pair = explode('=', $part, 2);
            $key = urldecode($pair[0]);
            $value = urldecode($pair[1] ?? '');
            if ($key === '' || isset($data[$key]) || !preg_match('/^[a-z_]+$/', $key)) {
                throw new InvalidArgumentException('Invalid Telegram data.');
            }
            $data[$key] = $value;
        }

        $received = $data['hash'] ?? '';
        if (!preg_match('/^[0-9a-f]{64}$/i', $received)) {
            throw new InvalidArgumentException('Missing Telegram hash.');
        }

        unset($data['hash']);
        ksort($data, SORT_STRING);
        $lines = [];
        foreach ($data as $key => $value) {
            $lines[] = $key.'='.$value;
        }

        $secret = hash_hmac('sha256', $token, 'WebAppData', true);
        $expected = hash_hmac('sha256', implode("\n", $lines), $secret);
        if (!hash_equals($expected, strtolower($received))) {
            throw new InvalidArgumentException('Telegram signature mismatch.');
        }

        $date = $data['auth_date'] ?? '';
        if (!ctype_digit($date) || (int) $date > time() + 60 || (int) $date < time() - 86400) {
            throw new InvalidArgumentException('Telegram session has expired.');
        }

        $user = json_decode($data['user'] ?? '', true);
        if (!is_array($user) || !isset($user['id']) || !ctype_digit((string) $user['id']) || (int) $user['id'] < 1) {
            throw new InvalidArgumentException('Missing Telegram user.');
        }

        return $user;
    }
}
