<?php

namespace App\Events\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Broadcast an event once the surrounding transaction commits (nothing is sent
 * if it rolls back), reporting but swallowing broadcasting errors so a Reverb
 * outage never breaks the action itself — pages fall back to polling.
 *
 * After a failure, broadcasting is skipped for a short while: an action often
 * sends several events, and each would otherwise wait for the connection to fail.
 */
trait SendsAfterCommit
{
    public const BROADCAST_DOWN_KEY = 'broadcasting.down';

    public const BROADCAST_RETRY_SECONDS = 30;

    public static function send(mixed ...$arguments): void
    {
        DB::afterCommit(function () use ($arguments): void {
            if (Cache::has(self::BROADCAST_DOWN_KEY)) {
                return;
            }

            try {
                event(new static(...$arguments));
            } catch (Throwable $exception) {
                Cache::put(self::BROADCAST_DOWN_KEY, true, self::BROADCAST_RETRY_SECONDS);
                report($exception);
            }
        });
    }
}
