<?php

namespace Chencongbao\LaravelVbenAdmin\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class LoginCaptcha
{
    public const HEIGHT = 130;

    public const POINT_TOLERANCE = 30;

    public const WIDTH = 428;

    public function isRequired(Request $request, string $username): bool
    {
        return Cache::has($this->requiredKey($request, $username));
    }

    public function require(Request $request, string $username): void
    {
        Cache::put($this->requiredKey($request, $username), true, now()->addMinutes(10));
    }

    public function clear(Request $request, string $username): void
    {
        Cache::forget($this->requiredKey($request, $username));
    }

    public function issue(Request $request, string $username): array
    {
        $key = Str::random(40);
        $characters = collect([
            '安', '全', '管', '理', '系', '统', '用', '户',
            '权', '限', '数', '据', '服', '务', '平', '台',
            '登', '录', '设', '置', '信', '息', '审', '核',
        ])->shuffle()->take(6)->values();
        $targets = $characters->take(3)->values();
        $positions = collect([
            ['x' => random_int(45, 105), 'y' => random_int(35, 65)],
            ['x' => random_int(165, 235), 'y' => random_int(70, 105)],
            ['x' => random_int(320, 398), 'y' => random_int(35, 65)],
        ]);

        Cache::put($this->challengeKey($key), [
            'points' => $positions->all(),
            'scope' => $this->scope($request, $username),
        ], now()->addMinutes(10));

        return [
            'captcha_key' => $key,
            'captcha_image' => 'data:image/svg+xml;base64,'.base64_encode($this->svg($characters->all(), $positions->all())),
            'captcha_hint' => $targets->implode(' → '),
            'expires_in' => 600,
        ];
    }

    public function verify(Request $request, string $username, ?string $key, mixed $answer): bool
    {
        if (! is_string($key) || $key === '' || ! is_array($answer) || count($answer) !== 3) {
            return false;
        }

        $challenge = Cache::pull($this->challengeKey($key));

        if (! is_array($challenge)
            || ! hash_equals((string) ($challenge['scope'] ?? ''), $this->scope($request, $username))
            || ! is_array($challenge['points'] ?? null)) {
            return false;
        }

        foreach ($challenge['points'] as $index => $target) {
            $point = $answer[$index] ?? null;
            if (! is_array($point)
                || abs((int) ($point['x'] ?? -100) - (int) $target['x']) > self::POINT_TOLERANCE
                || abs((int) ($point['y'] ?? -100) - (int) $target['y']) > self::POINT_TOLERANCE) {
                return false;
            }
        }

        return true;
    }

    private function requiredKey(Request $request, string $username): string
    {
        return 'laravel-vben-admin:login-captcha-required:'.$this->scope($request, $username);
    }

    private function challengeKey(string $key): string
    {
        return 'laravel-vben-admin:login-captcha:'.$key;
    }

    private function scope(Request $request, string $username): string
    {
        return hash('sha256', ($request->ip() ?? 'unknown').'|'.Str::lower(trim($username)));
    }

    private function svg(array $characters, array $positions): string
    {
        $decoyPositions = [['x' => 95, 'y' => 102], ['x' => 285, 'y' => 45], ['x' => 370, 'y' => 104]];
        $allPositions = [...$positions, ...$decoyPositions];
        $text = implode('', array_map(
            static fn (string $character, int $index): string => sprintf(
                '<text x="%d" y="%d" text-anchor="middle" dominant-baseline="middle" font-size="30" font-family="PingFang SC,Microsoft YaHei,sans-serif" font-weight="700" fill="#%s" transform="rotate(%d %d %d)">%s</text>',
                $allPositions[$index]['x'],
                $allPositions[$index]['y'],
                ['2563eb', '7c3aed', '0891b2', 'db2777', '16a34a', 'ea580c'][$index],
                [-12, 8, -6, 13, -9, 7][$index],
                $allPositions[$index]['x'],
                $allPositions[$index]['y'],
                $character,
            ),
            $characters,
            array_keys($characters),
        ));

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.self::WIDTH.'" height="'.self::HEIGHT.'" viewBox="0 0 '.self::WIDTH.' '.self::HEIGHT.'">'
            .'<defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#eff6ff"/><stop offset="1" stop-color="#f5f3ff"/></linearGradient></defs>'
            .'<rect width="'.self::WIDTH.'" height="'.self::HEIGHT.'" rx="8" fill="url(#bg)"/>'
            .'<path d="M5 22 C112 2 300 48 423 20 M8 116 C135 88 305 132 420 98 M18 64 L410 76" stroke="#94a3b8" stroke-width="1" opacity=".38" fill="none"/>'
            .$text
            .'</svg>';
    }
}
