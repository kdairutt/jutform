<?php

namespace JutForm\Core;

class QueueService
{
    public const QUEUE_DEFAULT = 'jutform:jobs';

    public static function dispatch(string $job, array $data, ?string $queue = null): void
    {
        $queue = $queue ?? self::QUEUE_DEFAULT;
        $payload = json_encode(['job' => $job, 'data' => $data, 'ts' => time()], JSON_UNESCAPED_UNICODE);
        RedisClient::getInstance()->lPush($queue, $payload);
    }

    public static function pop(?string $queue = null): ?array
    {
        $queue = $queue ?? self::QUEUE_DEFAULT;
        $redis = RedisClient::getInstance();
        $payload = $redis->rPop($queue);
        if ($payload === false || $payload === null) {
            return null;
        }
        $decoded = json_decode((string) $payload, true);
        return is_array($decoded) ? $decoded : null;
    }
}
