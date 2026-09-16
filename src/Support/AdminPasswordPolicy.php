<?php

namespace Chencongbao\LaravelVbenAdmin\Support;

use Illuminate\Validation\Rules\Password;

final class AdminPasswordPolicy
{
    public static function type(): string
    {
        $type = SystemSettings::value('system.password_strength');

        return in_array($type, ['weak', 'strong'], true) ? $type : 'strong';
    }

    public static function rule(): Password
    {
        if (self::type() === 'weak') {
            return Password::min(6);
        }

        return Password::min(12)->letters()->mixedCase()->numbers();
    }

    public static function payload(): array
    {
        $strong = self::type() === 'strong';

        return [
            'type' => $strong ? 'strong' : 'weak',
            'min_length' => $strong ? 12 : 6,
            'requires_mixed_case' => $strong,
            'requires_numbers' => $strong,
        ];
    }
}
