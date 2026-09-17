<?php

namespace Chencongbao\LaravelVbenAdmin\Jobs;

use Chencongbao\Foundation\Exceptions\TelegramTransportException;
use Chencongbao\Foundation\Services\Notification\TelegramMessenger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

final class SendTelegramMessage implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $timeout;

    private int $retryBackoff;

    public function __construct(
        private readonly array|string|int|float|bool|null $content,
        private readonly string $format = 'text',
        private readonly ?string $title = null,
    ) {
        $queue = (array) config('foundation_log.telegram.queue', []);
        $this->tries = max(1, (int) ($queue['tries'] ?? 3));
        $this->timeout = max(1, (int) ($queue['timeout_seconds'] ?? 30));
        $this->retryBackoff = max(0, (int) ($queue['backoff_seconds'] ?? 5));

        $connection = trim((string) ($queue['connection'] ?? ''));
        if ($connection !== '') {
            $this->onConnection($connection);
        }
        $this->onQueue(trim((string) ($queue['name'] ?? 'notice')) ?: 'notice');
    }

    public function handle(TelegramMessenger $telegram): void
    {
        if (! $this->configured()) {
            return;
        }

        $format = strtolower(trim($this->format));
        $options = trim((string) $this->title) === '' ? [] : ['title' => trim((string) $this->title)];
        $message = match ($format) {
            'html' => $this->htmlContent(),
            'json', 'text' => $telegram->format($this->content, $format, $options),
            default => throw new InvalidArgumentException("Unsupported Telegram message format [{$format}]."),
        };

        if (! $telegram->sendHtml($message)) {
            throw new TelegramTransportException('Laravel Vben Admin Telegram message delivery failed.');
        }
    }

    public function backoff(): int
    {
        return $this->retryBackoff;
    }

    private function htmlContent(): string
    {
        if (! is_string($this->content)) {
            throw new InvalidArgumentException('Telegram HTML content must be a string.');
        }

        return $this->content;
    }

    private function configured(): bool
    {
        return config('foundation_log.telegram.enabled', false) === true
            && trim((string) config('foundation_log.telegram.bot_token', '')) !== ''
            && (array) config('foundation_log.telegram.chat_ids', []) !== [];
    }
}
