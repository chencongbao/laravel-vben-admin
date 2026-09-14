<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\Models\AdminSetting;
use Chencongbao\LaravelVbenAdmin\Support\SystemSettings;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdminSettingController extends Controller
{
    private const SYSTEM_KEYS = [
        'system.name',
        'system.page_size',
        'system.login_remember_me',
        'system.login_description',
    ];

    private const THEME_KEYS = [
        'system.login_theme',
        'system.login_layout',
        'system.admin_theme',
        'system.admin_theme_mode',
        'system.admin_layout',
        'system.tabbar_enable',
        'system.tabbar_persist',
        'system.tabbar_visit_history',
        'system.tabbar_max_count',
        'system.tabbar_draggable',
        'system.tabbar_wheelable',
        'system.tabbar_middle_click_to_close',
        'system.tabbar_show_icon',
        'system.tabbar_show_more',
        'system.tabbar_show_maximize',
        'system.tabbar_style_type',
        'system.advanced_preferences',
    ];

    public function __construct(private readonly AuditRecorder $audit) {}

    public function index(): JsonResponse
    {
        return $this->settings(self::SYSTEM_KEYS);
    }

    public function theme(): JsonResponse
    {
        return $this->settings(self::THEME_KEYS);
    }

    public function updateTheme(Request $request): JsonResponse
    {
        return $this->updateSettings($request, self::THEME_KEYS, fn () => $this->theme());
    }

    private function settings(array $keys): JsonResponse
    {
        $definitions = array_intersect_key(SystemSettings::definitions(), array_flip($keys));
        $stored = AdminSetting::query()->whereIn('key', array_keys($definitions))->pluck('value', 'key');
        $settings = collect($definitions)->map(fn (array $definition, string $key) => [
            'key' => $key,
            'type' => $definition['type'],
            'value' => $stored->has($key) && $definition['type'] !== 'json'
                ? $stored->get($key)
                : SystemSettings::value($key),
        ])->values();

        return response()->json(['settings' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        return $this->updateSettings($request, self::SYSTEM_KEYS, fn () => $this->index());
    }

    private function updateSettings(Request $request, array $keys, callable $response): JsonResponse
    {
        $data = $request->validate(['settings' => ['required', 'array', 'max:100'], 'settings.*.key' => ['required', 'string', 'max:160', 'distinct'], 'settings.*.value' => ['present']]);
        $definitions = array_intersect_key(SystemSettings::definitions(), array_flip($keys));
        $normalized = [];

        foreach ($data['settings'] as $item) {
            if (! isset($definitions[$item['key']])) {
                throw ValidationException::withMessages(['settings' => ["Setting [{$item['key']}] is not registered."]]);
            }
            $normalized[$item['key']] = $this->normalize($item['key'], $item['value'], $definitions[$item['key']]);
        }

        DB::transaction(function () use ($normalized, $definitions, $request): void {
            $changes = [];

            foreach ($normalized as $key => $value) {
                $setting = AdminSetting::query()->firstOrNew(['key' => $key]);
                $before = $setting->exists ? $setting->value : $definitions[$key]['default'];

                if ($before === $value) {
                    continue;
                }

                $setting->fill(['type' => $definitions[$key]['type'], 'value' => $value, 'is_system' => true])->save();
                $changes[$key] = ['before' => $before, 'after' => $value];
            }

            if ($changes !== []) {
                $this->audit->record(
                    $request->user(),
                    'system.settings.updated',
                    null,
                    ['settings' => $changes],
                    ['keys' => array_keys($changes)],
                );
            }
        });

        return $response();
    }

    private function normalize(string $key, mixed $value, array $definition): mixed
    {
        return match ($definition['type']) {
            'string' => $this->stringValue($key, $value),
            'integer' => $this->integerValue($key, $value, $definition),
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? throw ValidationException::withMessages(["settings.{$key}" => ["Setting [{$key}] must be boolean."]]),
            'timezone' => in_array($value, DateTimeZone::listIdentifiers(), true) ? $value : throw ValidationException::withMessages(["settings.{$key}" => ["Setting [{$key}] must be an IANA timezone."]]),
            'enum' => is_string($value) && in_array($value, $definition['values'] ?? [], true) ? $value : throw ValidationException::withMessages(["settings.{$key}" => ["Setting [{$key}] has an invalid option."]]),
            'json' => $this->jsonValue($key, $value),
            default => throw ValidationException::withMessages(["settings.{$key}" => ["Setting [{$key}] has an unsupported type."]]),
        };
    }

    private function jsonValue(string $key, mixed $value): array
    {
        if (! is_array($value) || strlen((string) json_encode($value)) > 20000) {
            throw ValidationException::withMessages(["settings.{$key}" => ["Setting [{$key}] must be a JSON object up to 20 KB."]]);
        }

        if ($key === 'system.advanced_preferences') {
            unset(
                $value['custom']['boardTitle'],
                $value['custom']['defaultVisibleCount'],
                $value['custom']['showQuickActions'],
                $value['custom']['highlightStyle'],
            );
        }

        return $value;
    }

    private function stringValue(string $key, mixed $value): string
    {
        if (! is_string($value) || mb_strlen($value) > 500) {
            throw ValidationException::withMessages(["settings.{$key}" => ["Setting [{$key}] must be a string up to 500 characters."]]);
        }

        return $value;
    }

    private function integerValue(string $key, mixed $value, array $definition): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw ValidationException::withMessages(["settings.{$key}" => ["Setting [{$key}] must be an integer."]]);
        }
        $value = (int) $value;
        if ($value < ($definition['min'] ?? PHP_INT_MIN) || $value > ($definition['max'] ?? PHP_INT_MAX)) {
            throw ValidationException::withMessages(["settings.{$key}" => ["Setting [{$key}] is outside the allowed range."]]);
        }

        return $value;
    }
}
