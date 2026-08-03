<?php

declare(strict_types=1);

namespace App\Domain\Collection;

use JsonException;

final class EventLine
{
    /** @param array<string, mixed> $payload */
    private function __construct(private readonly array $payload) {}

    public static function fromJson(string $line): ?self
    {
        try {
            $payload = json_decode($line, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        $required = ['site_id', 'visitor_id', 'timestamp', 'url', 'referrer', 'event', 'name', 'properties'];
        $classificationFields = ['is_bot', 'device', 'browser', 'os'];
        if (array_diff($required, array_keys($payload)) !== [] || array_diff(array_keys($payload), array_merge($required, $classificationFields)) !== []) {
            return null;
        }

        if (
            ! is_int($payload['site_id'])
            || ! is_string($payload['visitor_id'])
            || preg_match('/^[a-f0-9]{16}$/', $payload['visitor_id']) !== 1
            || ! is_string($payload['timestamp'])
            || ! is_string($payload['url'])
            || ($payload['referrer'] !== null && ! is_string($payload['referrer']))
            || ! is_string($payload['event'])
            || ! is_string($payload['name'])
            || ! is_array($payload['properties'])
            || (isset($payload['is_bot']) && ! is_bool($payload['is_bot']))
            || (isset($payload['device']) && (! is_string($payload['device']) || ! in_array($payload['device'], ['desktop', 'mobile', 'tablet', 'tv', 'bot', 'unknown'], true)))
            || (isset($payload['browser']) && (! is_string($payload['browser']) || ! in_array($payload['browser'], ['bot', 'chrome', 'edge', 'firefox', 'safari', 'unknown'], true)))
            || (isset($payload['os']) && (! is_string($payload['os']) || ! in_array($payload['os'], ['android', 'ios', 'linux', 'macos', 'windows', 'unknown'], true)))
            || self::containsIpAddress($payload)
        ) {
            return null;
        }

        return new self($payload);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->payload;
    }

    public function toJson(): string
    {
        return json_encode($this->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private static function containsIpAddress(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::containsIpAddress($item)) {
                    return true;
                }
            }

            return false;
        }

        if (! is_string($value)) {
            return false;
        }

        if (preg_match('/\\b(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])(?:\\.(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])){3}\\b/', $value) === 1) {
            return true;
        }

        foreach (preg_split('/[^0-9a-fA-F:.\\[\\]]+/', $value) ?: [] as $candidate) {
            if (str_contains($candidate, ':') && filter_var(trim($candidate, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                return true;
            }
        }

        return false;
    }
}
