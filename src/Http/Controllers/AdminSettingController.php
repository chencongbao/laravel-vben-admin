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
    public function __construct(private readonly AuditRecorder $audit) {}

    public function index(): JsonResponse
    {
        $definitions = SystemSettings::definitions();
        $stored = AdminSetting::query()->whereIn('key', array_keys($definitions))->pluck('value', 'key');
        $settings = collect($definitions)->map(fn (array $definition, string $key) => [
            'key' => $key,
            'type' => $definition['type'],
            'value' => $stored->has($key) ? $stored->get($key) : $definition['default'],
        ])->values();

        return response()->json(['settings' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['settings' => ['required', 'array'], 'settings.*.key' => ['required', 'string'], 'settings.*.value' => ['present']]);
        $definitions = SystemSettings::definitions();
        $normalized = [];

        foreach ($data['settings'] as $item) {
            if (! isset($definitions[$item['key']])) {
                throw ValidationException::withMessages(['settings' => ["Setting [{$item['key']}] is not registered."]]);
            }
            $normalized[$item['key']] = $this->normalize($item['key'], $item['value'], $definitions[$item['key']]);
        }

        DB::transaction(function () use ($normalized, $definitions, $request): void {
            foreach ($normalized as $key => $value) {
                $setting = AdminSetting::query()->firstOrNew(['key' => $key]);
                $before = $setting->exists ? $setting->value : $definitions[$key]['default'];
                $setting->fill(['type' => $definitions[$key]['type'], 'value' => $value, 'is_system' => true])->save();
                $this->audit->record($request->user(), 'system.setting.updated', $setting, ['key' => $key, 'before' => $before, 'after' => $value]);
            }
        });

        return $this->index();
    }

    private function normalize(string $key, mixed $value, array $definition): mixed
    {
        return match ($definition['type']) {
            'string' => $this->stringValue($key, $value),
            'integer' => $this->integerValue($key, $value, $definition),
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? throw ValidationException::withMessages(['settings' => ["Setting [{$key}] must be boolean."]]),
            'timezone' => in_array($value, DateTimeZone::listIdentifiers(), true) ? $value : throw ValidationException::withMessages(['settings' => ["Setting [{$key}] must be an IANA timezone."]]),
            default => throw ValidationException::withMessages(['settings' => ["Setting [{$key}] has an unsupported type."]]),
        };
    }

    private function stringValue(string $key, mixed $value): string
    {
        if (! is_string($value) || mb_strlen($value) > 500) {
            throw ValidationException::withMessages(['settings' => ["Setting [{$key}] must be a string up to 500 characters."]]);
        }

        return $value;
    }

    private function integerValue(string $key, mixed $value, array $definition): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw ValidationException::withMessages(['settings' => ["Setting [{$key}] must be an integer."]]);
        }
        $value = (int) $value;
        if ($value < ($definition['min'] ?? PHP_INT_MIN) || $value > ($definition['max'] ?? PHP_INT_MAX)) {
            throw ValidationException::withMessages(['settings' => ["Setting [{$key}] is outside the allowed range."]]);
        }

        return $value;
    }
}
