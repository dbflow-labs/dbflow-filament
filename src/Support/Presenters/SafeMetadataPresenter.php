<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Support\Presenters;

use DbflowLabs\Core\Actions\Webhook\Redactor;
use DbflowLabs\Core\Models\WorkflowActionAttempt;
use DbflowLabs\Core\Models\WorkflowActionExecution;

/**
 * Formats already-persisted Action/Webhook metadata for display.
 *
 * Core webhook persistence shape (Stage 1.1-D):
 * - execution.result_metadata / attempt.response_metadata: {status_code, body}
 * - execution.payload_snapshot: definition payload (url/method templates, never secrets)
 * - attempt.request_metadata: currently unused by Core (kept for forward compatibility)
 */
final class SafeMetadataPresenter
{
    /**
     * @var list<string>
     */
    private const FORBIDDEN_PATTERNS = [
        'SENTINEL_SECRET_VALUE_9f3a2c1b',
        'Bearer ',
        'x-dbflow-signature',
    ];

    public function __construct(
        private readonly ?Redactor $redactor = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function executionSummary(WorkflowActionExecution $execution): array
    {
        $resultMetadata = $this->sanitizeArray($execution->result_metadata);
        $payloadSnapshot = $this->sanitizeArray($execution->payload_snapshot);

        $responseStatus = $execution->response_status !== null
            ? (string) $execution->response_status
            : $this->scalarString($resultMetadata['status_code'] ?? null);

        return [
            'handler' => (string) ($execution->action_key ?? '—'),
            'mode' => app(RuntimeBadgePresenter::class)->executionModeLabel($execution->execution_mode),
            'status' => app(RuntimeBadgePresenter::class)->actionExecutionStatusLabel($execution->status),
            'logical_key' => $this->shortenIdentifier((string) ($execution->logical_execution_key ?? '—')),
            'response_status' => $responseStatus !== '' ? $responseStatus : '—',
            'last_error' => $this->safeErrorSummary($execution->last_error),
            'destination' => $this->destinationFromPayloadOrMetadata($payloadSnapshot, $resultMetadata),
            'idempotency_key' => $this->shortenIdentifier((string) ($execution->logical_execution_key ?? '')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attemptSummary(WorkflowActionAttempt $attempt): array
    {
        $request = $this->sanitizeArray($attempt->request_metadata);
        $response = $this->sanitizeArray($attempt->response_metadata);

        $responseStatus = $this->scalarString($response['status_code'] ?? $response['status'] ?? null);

        return [
            'attempt_number' => (string) $attempt->attempt_number,
            'status' => (string) ($attempt->status ?? '—'),
            'method' => is_string($request['method'] ?? null) ? (string) $request['method'] : '—',
            'destination' => $this->destinationSummary($request),
            'response_status' => $responseStatus !== '' ? $responseStatus : '—',
            'duration_ms' => $this->scalarString($response['duration_ms'] ?? null) ?: '—',
            'retryable' => $this->formatBoolean($response['retryable'] ?? null),
            'security_category' => is_string($response['security_rejection_category'] ?? null)
                ? (string) $response['security_rejection_category']
                : '—',
            'last_error' => $this->safeErrorSummary($attempt->last_error),
            'idempotency_key' => $this->idempotencySummary($request, ''),
        ];
    }

    public function containsForbiddenContent(string $content): bool
    {
        foreach (self::FORBIDDEN_PATTERNS as $pattern) {
            if (str_contains($content, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>|null  $metadata
     */
    public function destinationFromPayloadOrMetadata(?array $payload, ?array $metadata): string
    {
        $fromPayload = $this->destinationFromUrlTemplate($payload['url'] ?? null);

        if ($fromPayload !== '—') {
            return $fromPayload;
        }

        return $this->destinationSummary($metadata);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function destinationSummary(?array $metadata): string
    {
        if ($metadata === null || $metadata === []) {
            return '—';
        }

        if (isset($metadata['url']) && is_string($metadata['url'])) {
            return $this->destinationFromUrlTemplate($metadata['url']);
        }

        $host = is_string($metadata['host'] ?? null) ? $metadata['host'] : null;
        $path = is_string($metadata['path'] ?? null) ? $metadata['path'] : null;
        $url = is_string($metadata['url_summary'] ?? null) ? $metadata['url_summary'] : null;

        if ($url !== null && $url !== '') {
            return $this->destinationFromUrlTemplate($url);
        }

        if ($host !== null && $host !== '') {
            $summary = $host.($path !== null && $path !== '' ? $path : '');

            return $this->truncate($this->scrubSecrets($summary));
        }

        return '—';
    }

    public function safeErrorSummary(?string $error): string
    {
        if ($error === null || $error === '') {
            return '—';
        }

        return $this->truncate($this->scrubSecrets($error));
    }

    /**
     * Host + path only — never query strings, fragments, or credentials.
     */
    private function destinationFromUrlTemplate(mixed $url): string
    {
        if (! is_string($url) || $url === '') {
            return '—';
        }

        $scrubbed = $this->scrubSecrets($url);
        $parts = parse_url($scrubbed);

        if (! is_array($parts)) {
            return '—';
        }

        $host = isset($parts['host']) && is_string($parts['host']) ? $parts['host'] : null;
        $path = isset($parts['path']) && is_string($parts['path']) ? $parts['path'] : '';

        if ($host === null || $host === '') {
            // Relative or template-only URL — show path segment only when safe.
            if ($path !== '' && ! str_contains($path, '{{')) {
                return $this->truncate($path);
            }

            return '—';
        }

        return $this->truncate($host.$path);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function idempotencySummary(?array $metadata, string $fallback): string
    {
        $key = is_string($metadata['idempotency_key'] ?? null)
            ? (string) $metadata['idempotency_key']
            : $fallback;

        if ($key === '') {
            return '—';
        }

        return $this->shortenIdentifier($key);
    }

    /**
     * @param  array<string, mixed>|null  $value
     * @return array<string, mixed>|null
     */
    private function sanitizeArray(?array $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $redactor = $this->redactor ?? (class_exists(Redactor::class) ? app(Redactor::class) : null);

        if ($redactor instanceof Redactor) {
            return $redactor->redactArray($value);
        }

        return $value;
    }

    private function scrubSecrets(string $value): string
    {
        $scrubbed = preg_replace('/Bearer\s+\S+/i', 'Bearer [REDACTED]', $value) ?? $value;

        return str_replace('SENTINEL_SECRET_VALUE_9f3a2c1b', '[REDACTED]', $scrubbed);
    }

    private function shortenIdentifier(string $value): string
    {
        if ($value === '') {
            return '—';
        }

        if (mb_strlen($value) <= 48) {
            return $value;
        }

        return mb_substr($value, 0, 20).'…'.mb_substr($value, -12);
    }

    private function truncate(string $value, int $max = 160): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max).'…';
    }

    private function scalarString(mixed $value): string
    {
        if ($value === null || is_bool($value) || is_array($value)) {
            return '';
        }

        return (string) $value;
    }

    private function formatBoolean(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        return $value ? (string) __('dbflow-filament::dbflow-filament.labels.yes') : (string) __('dbflow-filament::dbflow-filament.labels.no');
    }
}
