<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LandingPageCaptcha
{
    private const SESSION_KEY = 'landing_page_captcha_challenges';
    private const LIFETIME_SECONDS = 300;
    private const MAX_STORED_CHALLENGES = 8;

    /**
     * Create a short-lived arithmetic CAPTCHA and store its answer in session.
     *
     * @return array{token:string,challenge:string,expires_in:int}
     */
    public static function create(Request $request): array
    {
        $left = random_int(2, 12);
        $right = random_int(1, 9);
        $operator = random_int(0, 1) === 1 ? '+' : '-';

        if ($operator === '-' && $right > $left) {
            [$left, $right] = [$right, $left];
        }

        $answer = $operator === '+' ? $left + $right : $left - $right;
        $token = Str::random(48);
        $now = now()->timestamp;

        $challenges = collect((array) $request->session()->get(self::SESSION_KEY, []))
            ->filter(fn ($challenge) => is_array($challenge) && (int) ($challenge['expires_at'] ?? 0) > $now)
            ->take(-1 * (self::MAX_STORED_CHALLENGES - 1))
            ->all();

        $challenges[$token] = [
            'answer' => (string) $answer,
            'expires_at' => $now + self::LIFETIME_SECONDS,
        ];

        $request->session()->put(self::SESSION_KEY, $challenges);

        return [
            'token' => $token,
            'challenge' => sprintf('%d %s %d = ?', $left, $operator, $right),
            'expires_in' => self::LIFETIME_SECONDS,
        ];
    }

    public static function valid(Request $request, ?string $token, mixed $answer): bool
    {
        $token = trim((string) $token);
        $answer = trim((string) $answer);

        if ($token === '' || $answer === '') {
            return false;
        }

        $challenge = data_get(
            (array) $request->session()->get(self::SESSION_KEY, []),
            $token
        );

        if (! is_array($challenge) || (int) ($challenge['expires_at'] ?? 0) <= now()->timestamp) {
            return false;
        }

        return hash_equals((string) ($challenge['answer'] ?? ''), $answer);
    }

    public static function consume(Request $request, ?string $token): void
    {
        $token = trim((string) $token);

        if ($token === '') {
            return;
        }

        $challenges = (array) $request->session()->get(self::SESSION_KEY, []);
        unset($challenges[$token]);
        $request->session()->put(self::SESSION_KEY, $challenges);
    }
}
