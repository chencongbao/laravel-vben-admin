<?php

namespace Chencongbao\LaravelVbenAdmin\Services;

use Chencongbao\LaravelVbenAdmin\Contracts\AdminNotificationPublisher;
use Chencongbao\LaravelVbenAdmin\Models\AdminNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class DatabaseAdminNotificationPublisher implements AdminNotificationPublisher
{
    private const SENSITIVE_KEY_FRAGMENTS = ['password', 'token', 'authorization', 'secret', 'credential', 'cookie', 'captcha', 'totp', 'two_factor', 'private_key'];

    public function publish(array $notification): AdminNotification
    {
        $data = Validator::make($notification, [
            'code' => ['required', 'string', 'max:160', 'regex:/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/'],
            'source' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_-]*$/'],
            'type' => ['nullable', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_-]*$/'],
            'severity' => ['nullable', Rule::in(['info', 'success', 'warning', 'error'])],
            'title' => ['nullable', 'string', 'max:255', 'required_without:title_key', $this->plainTextRule()],
            'message' => ['nullable', 'string', 'max:5000', $this->plainTextRule()],
            'title_key' => ['nullable', 'string', 'max:255', 'required_without:title'],
            'message_key' => ['nullable', 'string', 'max:255'],
            'parameters' => ['nullable', 'array'],
            'icon' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9-]+:[a-z0-9-]+$/'],
            'link' => ['nullable', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! str_starts_with($value, 'https://') && (! str_starts_with($value, '/') || str_starts_with($value, '//'))) {
                    $fail('The link must be an internal path or an HTTPS URL.');
                }
            }],
            'metadata' => ['nullable', 'array'],
            'created_by' => ['nullable', 'integer'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
        ])->validate();

        $this->rejectSensitiveData((array) ($data['parameters'] ?? []));
        $this->rejectSensitiveData((array) ($data['metadata'] ?? []));

        return AdminNotification::query()->create(array_merge([
            'source' => 'system',
            'type' => 'system',
            'severity' => 'info',
            'published_at' => now(),
        ], Arr::only($data, [
            'code', 'source', 'type', 'severity', 'title', 'message', 'title_key', 'message_key',
            'parameters', 'icon', 'link', 'metadata', 'created_by', 'published_at', 'expires_at',
        ])));
    }

    private function rejectSensitiveData(array $values, string $prefix = ''): void
    {
        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $normalizedKey = strtolower((string) $key);
            if (collect(self::SENSITIVE_KEY_FRAGMENTS)->contains(fn (string $fragment) => str_contains($normalizedKey, $fragment))) {
                throw ValidationException::withMessages([$path => 'Sensitive data cannot be stored in notifications.']);
            }
            if (is_array($value)) {
                $this->rejectSensitiveData($value, $path);
            }
        }
    }

    private function plainTextRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (is_string($value) && strip_tags($value) !== $value) {
                $fail('Notification content must be plain text.');
            }
        };
    }
}
