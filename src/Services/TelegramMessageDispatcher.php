<?php

namespace Chencongbao\LaravelVbenAdmin\Services;

use Chencongbao\LaravelVbenAdmin\Jobs\SendTelegramMessage;
use Illuminate\Contracts\Bus\Dispatcher;

final class TelegramMessageDispatcher
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
    ) {}

    public function text(string $text, ?string $title = null): void
    {
        if (! $this->configured()) {
            return;
        }

        $this->dispatcher->dispatch(new SendTelegramMessage($text, 'text', $title));
    }

    public function json(mixed $data, ?string $title = null): void
    {
        if (! $this->configured()) {
            return;
        }

        $this->dispatcher->dispatch(new SendTelegramMessage($data, 'json', $title));
    }

    public function html(string $safeHtml): void
    {
        if (! $this->configured()) {
            return;
        }

        $this->dispatcher->dispatch(new SendTelegramMessage($safeHtml, 'html'));
    }

    private function configured(): bool
    {
        return config('foundation_log.telegram.enabled', false) === true
            && trim((string) config('foundation_log.telegram.bot_token', '')) !== ''
            && (array) config('foundation_log.telegram.chat_ids', []) !== [];
    }
}
