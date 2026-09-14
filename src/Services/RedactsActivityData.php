<?php

namespace Chencongbao\LaravelVbenAdmin\Services;

use Illuminate\Http\UploadedFile;

trait RedactsActivityData
{
    private function redactActivityData(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && preg_match('/password|token|secret|credential|authorization|cookie|private[_-]?key|captcha|totp|two[_-]?factor/i', $key)) {
            return '[REDACTED]';
        }

        if ($value instanceof UploadedFile) {
            return [
                'file' => '[UPLOADED_FILE]',
                'name' => $value->getClientOriginalName(),
                'size' => $value->getSize(),
                'mime_type' => $value->getClientMimeType(),
            ];
        }

        if (is_array($value)) {
            foreach ($value as $childKey => $childValue) {
                $value[$childKey] = $this->redactActivityData($childValue, (string) $childKey);
            }
        }

        return $value;
    }
}
