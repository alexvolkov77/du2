<?php

namespace Tests\Unit;

use App\Services\TelegramInitDataVerifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TelegramInitDataVerifierTest extends TestCase
{
    public function test_valid_and_modified_init_data(): void
    {
        $token = 'test:bot-token';
        $fields = [
            'auth_date' => (string) time(),
            'user' => '{"id":12345,"first_name":"Test"}',
            'query_id' => 'test-query',
        ];
        ksort($fields);
        $dataCheck = implode("\n", array_map(fn ($k, $v) => $k.'='.$v, array_keys($fields), array_values($fields)));
        $secret = hash_hmac('sha256', $token, 'WebAppData', true);
        $hash = hash_hmac('sha256', $dataCheck, $secret);
        $data = http_build_query([...$fields, 'hash' => $hash], '', '&', PHP_QUERY_RFC3986);

        $this->assertSame(12345, (new TelegramInitDataVerifier)->verify($data, $token)['id']);

        $this->expectException(InvalidArgumentException::class);
        (new TelegramInitDataVerifier)->verify(str_replace('12345', '12346', $data), $token);
    }

    public function test_expired_data_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new TelegramInitDataVerifier)->verify('hash='.str_repeat('a', 64), 'test:bot-token');
    }
}
