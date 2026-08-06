<?php

namespace App\Extraction\Support;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Per-user caps on how many scans can be submitted, to guard against
 * runaway Gemini usage/cost. Checks a short burst window and a daily cap.
 */
class ScanRateLimiter
{
    public function __construct(
        private readonly int $perMinute,
        private readonly int $perDay,
    ) {}

    /**
     * Returns the number of seconds until the user may scan again, or false
     * if they are within their limits.
     */
    public function tooManyAttempts(User $user): int|false
    {
        if (RateLimiter::tooManyAttempts($this->minuteKey($user), $this->perMinute)) {
            return RateLimiter::availableIn($this->minuteKey($user));
        }

        if (RateLimiter::tooManyAttempts($this->dayKey($user), $this->perDay)) {
            return RateLimiter::availableIn($this->dayKey($user));
        }

        return false;
    }

    public function hit(User $user): void
    {
        RateLimiter::hit($this->minuteKey($user), 60);
        RateLimiter::hit($this->dayKey($user), 86400);
    }

    public function retryMessage(int $availableInSeconds): string
    {
        $unit = $availableInSeconds >= 3600 ? 'hour' : ($availableInSeconds >= 60 ? 'minute' : 'second');
        $amount = match ($unit) {
            'hour' => (int) ceil($availableInSeconds / 3600),
            'minute' => (int) ceil($availableInSeconds / 60),
            'second' => $availableInSeconds,
        };

        return "You've reached the scan limit. Try again in {$amount} ".Str::plural($unit, $amount).'.';
    }

    private function minuteKey(User $user): string
    {
        return "scans:minute:{$user->id}";
    }

    private function dayKey(User $user): string
    {
        return "scans:day:{$user->id}";
    }
}
