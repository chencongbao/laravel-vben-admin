<?php

namespace Chencongbao\LaravelVbenAdmin\Services;

final class AuditLogRegistry
{
    /**
     * @return array{code: string, label_key: string|null, module: string|null, type: string|null}
     */
    public function action(string $code): array
    {
        $definitions = config('vben-admin-log.actions', []);
        $definition = is_array($definitions) ? ($definitions[$code] ?? []) : [];

        return [
            'code' => $code,
            'label_key' => $this->stringValue($definition, 'label_key'),
            'module' => $this->stringValue($definition, 'module'),
            'type' => $this->actionType($definition),
        ];
    }

    /**
     * @param  iterable<int, string>  $codes
     * @return list<array{code: string, label_key: string|null, module: string|null, type: string|null}>
     */
    public function actions(iterable $codes): array
    {
        $actions = [];
        foreach ($codes as $code) {
            $actions[] = $this->action($code);
        }

        return $actions;
    }

    public function subjectLabelKey(?string $subjectType): ?string
    {
        if ($subjectType === null || $subjectType === '') {
            return null;
        }

        $key = config('vben-admin-log.subjects.'.class_basename($subjectType));

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * @return array<string, string>
     */
    public function fieldLabelKeys(?string $subjectType): array
    {
        if ($subjectType === null || $subjectType === '') {
            return [];
        }

        $fields = config('vben-admin-log.fields.'.class_basename($subjectType), []);

        return is_array($fields)
            ? array_filter($fields, fn ($value, $key) => is_string($key) && is_string($value) && $value !== '', ARRAY_FILTER_USE_BOTH)
            : [];
    }

    private function actionType(mixed $definition): ?string
    {
        $type = $this->stringValue($definition, 'type');

        return in_array($type, ['created', 'deleted', 'other', 'updated'], true) ? $type : null;
    }

    private function stringValue(mixed $definition, string $key): ?string
    {
        if (! is_array($definition)) {
            return null;
        }

        $value = $definition[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
