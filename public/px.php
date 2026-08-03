<?php

declare(strict_types=1);

/** @return never */
function tm_finish(float $startedAt, bool $imageResponse = false): never
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Credentials: false');
    header('Server-Timing: tm;dur='.number_format((microtime(true) - $startedAt) * 1000, 3, '.', ''));

    if ($imageResponse) {
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==', true);
        http_response_code(200);
        header('Content-Type: image/gif');
        header('Content-Length: '.strlen($gif === false ? '' : $gif));
        echo $gif === false ? '' : $gif;

        exit;
    }

    http_response_code(204);
    header('Content-Length: 0');

    exit;
}

function tm_env_int(string $name, int $default, int $minimum, int $maximum): int
{
    $value = getenv($name);

    if (! is_string($value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
        return $default;
    }

    return max($minimum, min($maximum, (int) $value));
}

function tm_is_image_request(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
        && str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'image/');
}

/** @return array<string, mixed>|null */
function tm_request_payload(int $maximumBytes): ?array
{
    if (PHP_SAPI !== 'cli' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        return $_GET;
    }

    $input = PHP_SAPI === 'cli' ? 'php://stdin' : 'php://input';
    $body = @file_get_contents($input, false, null, 0, $maximumBytes + 1);

    if (! is_string($body) || strlen($body) > $maximumBytes) {
        return null;
    }

    try {
        $payload = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return null;
    }

    return is_array($payload) ? $payload : null;
}

function tm_contains_ip_address(string $value): bool
{
    if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
        return true;
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

function tm_safe_text(mixed $value, int $maximumBytes): string
{
    if (! is_string($value)) {
        return '';
    }

    $value = substr($value, 0, $maximumBytes);
    $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

    if (tm_contains_ip_address($value) || ($userAgent !== '' && str_contains($value, $userAgent))) {
        return '';
    }

    return $value;
}

function tm_sanitize_url(mixed $value, int $maximumPathBytes): ?string
{
    $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

    if (
        ! is_string($value)
        || $value === ''
        || tm_contains_ip_address($value)
        || ($userAgent !== '' && str_contains($value, $userAgent))
    ) {
        return null;
    }

    $parts = @parse_url($value);

    if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
        return null;
    }

    $scheme = strtolower((string) $parts['scheme']);
    $host = strtolower(trim((string) $parts['host'], '[]'));

    if (($scheme !== 'http' && $scheme !== 'https') || $host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return null;
    }

    $path = substr((string) ($parts['path'] ?? '/'), 0, $maximumPathBytes);
    $path = $path === '' ? '/' : $path;
    $query = [];
    $allowed = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'ref'];

    if (isset($parts['query'])) {
        parse_str((string) $parts['query'], $rawQuery);

        foreach ($allowed as $key) {
            if (isset($rawQuery[$key]) && is_scalar($rawQuery[$key])) {
                $queryValue = tm_safe_text((string) $rawQuery[$key], 128);

                if ($queryValue !== '') {
                    $query[$key] = $queryValue;
                }
            }
        }
    }

    return $scheme.'://'.$host.$path.($query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));
}

function tm_url_host(?string $url): ?string
{
    if ($url === null) {
        return null;
    }

    $host = parse_url($url, PHP_URL_HOST);

    return is_string($host) && $host !== '' ? strtolower($host) : null;
}

function tm_request_header_host(string $header): ?string
{
    $value = $_SERVER[$header] ?? null;

    return is_string($value) ? tm_url_host($value) : null;
}

function tm_is_obvious_bot(): bool
{
    $userAgent = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

    foreach (['bot', 'crawl', 'spider', 'preview', 'headless', 'curl/', 'wget'] as $needle) {
        if (str_contains($userAgent, $needle)) {
            return true;
        }
    }

    return false;
}

/** @return array<string, string> */
function tm_event_properties(mixed $properties): array
{
    if (! is_array($properties)) {
        return [];
    }

    $result = [];

    foreach ($properties as $key => $value) {
        if (count($result) === 8) {
            break;
        }

        if (! is_string($key) || ! is_scalar($value)) {
            continue;
        }

        $key = tm_safe_text($key, 32);
        $value = tm_safe_text((string) $value, 128);

        if ($key === '' || $value === '') {
            continue;
        }

        $result[$key] = $value;
    }

    return $result;
}

function tm_buffer_would_exceed_limit(string $directory, int $maximumBytes, int $lineBytes): bool
{
    $size = 0;

    foreach (glob($directory.DIRECTORY_SEPARATOR.'*.ndjson') ?: [] as $path) {
        $fileSize = @filesize($path);
        $size += $fileSize === false ? 0 : $fileSize;

        if ($size + $lineBytes > $maximumBytes) {
            return true;
        }
    }

    return false;
}

function tm_append_event(string $directory, string $bufferPath, string $line, int $maximumBufferBytes, int $maximumLines): bool
{
    $lock = @fopen($directory.DIRECTORY_SEPARATOR.'tm-buffer.lock', 'c');

    if ($lock === false) {
        return false;
    }

    try {
        if (! @flock($lock, LOCK_EX)) {
            return false;
        }

        $currentSize = @filesize($bufferPath);

        if (
            tm_buffer_would_exceed_limit($directory, $maximumBufferBytes, strlen($line))
            || ($currentSize !== false && $currentSize >= $maximumLines * 64)
        ) {
            tm_record_shed($directory);

            return false;
        }

        return @file_put_contents($bufferPath, $line, FILE_APPEND | LOCK_EX) !== false;
    } finally {
        @flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function tm_record_shed(string $directory): void
{
    @file_put_contents($directory.DIRECTORY_SEPARATOR.'tm-shed.count', "1\n", FILE_APPEND | LOCK_EX);
}

$startedAt = microtime(true);
$imageResponse = tm_is_image_request();
$maximumBodyBytes = tm_env_int('TM_MAX_BODY_BYTES', 2048, 1, 65_536);
$maximumPathBytes = tm_env_int('TM_MAX_PATH_BYTES', 512, 1, 4096);
$payload = tm_request_payload($maximumBodyBytes);

if ($payload === null) {
    tm_finish($startedAt, $imageResponse);
}

$root = dirname(__DIR__);
$mapPath = $root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'tm-sites.php';

if (! is_file($mapPath)) {
    tm_finish($startedAt, $imageResponse);
}

$map = require $mapPath;
$siteKey = $payload['k'] ?? null;

if (! is_array($map) || ! is_string($siteKey) || ! isset($map['sites'][$siteKey]) || ! is_array($map['sites'][$siteKey])) {
    tm_finish($startedAt, $imageResponse);
}

$site = $map['sites'][$siteKey];
$url = tm_sanitize_url($payload['u'] ?? null, $maximumPathBytes);

if ($url === null || ! isset($site['id']) || ! is_int($site['id'])) {
    tm_finish($startedAt, $imageResponse);
}

$hosts = is_array($site['hosts'] ?? null) ? array_map(static fn (mixed $host): string => strtolower((string) $host), $site['hosts']) : [];

if (($site['validate_host'] ?? true) === true) {
    $sourceHosts = array_filter([
        tm_request_header_host('HTTP_ORIGIN'),
        tm_request_header_host('HTTP_REFERER'),
    ]);

    if ($sourceHosts === []) {
        $sourceHosts[] = tm_url_host($url);
    }

    foreach ($sourceHosts as $sourceHost) {
        if (! in_array($sourceHost, $hosts, true)) {
            tm_finish($startedAt, $imageResponse);
        }
    }
}

$respectDnt = getenv('TM_RESPECT_DNT') !== '0';

if ($respectDnt && (($_SERVER['HTTP_DNT'] ?? '') === '1' || ($_SERVER['HTTP_SEC_GPC'] ?? '') === '1')) {
    tm_finish($startedAt, $imageResponse);
}

if (tm_is_obvious_bot()) {
    tm_finish($startedAt, $imageResponse);
}

try {
    $sample = max(1, min(100, (int) ($site['sample'] ?? 100)));

    if (random_int(1, 100) > $sample) {
        tm_finish($startedAt, $imageResponse);
    }

    $shards = tm_env_int('TM_BUFFER_SHARDS', 4, 1, 64);
    $shard = random_int(0, $shards - 1);
} catch (Throwable) {
    tm_finish($startedAt, $imageResponse);
}

$bufferDirectory = $root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'tm-buffer';

if (! is_dir($bufferDirectory) && ! @mkdir($bufferDirectory, 0775, true) && ! is_dir($bufferDirectory)) {
    tm_finish($startedAt, $imageResponse);
}

$maximumBufferBytes = tm_env_int('TM_BUFFER_MAX_MB', 64, 1, 4096) * 1024 * 1024;

$bufferPath = $bufferDirectory.DIRECTORY_SEPARATOR.gmdate('YmdHi').'-'.$shard.'.ndjson';
$maximumLines = tm_env_int('TM_MAX_LINES_PER_MINUTE', 20_000, 1, 1_000_000);

$event = [
    'site_id' => $site['id'],
    'timestamp' => gmdate('c'),
    'url' => $url,
    'referrer' => tm_sanitize_url($payload['r'] ?? null, $maximumPathBytes),
    'event' => tm_safe_text($payload['e'] ?? null, 64) ?: 'pageview',
    'name' => tm_safe_text($payload['n'] ?? null, 64),
    'properties' => tm_event_properties($payload['p'] ?? null),
];

try {
    $line = json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable) {
    tm_finish($startedAt, $imageResponse);
}

tm_append_event($bufferDirectory, $bufferPath, $line, $maximumBufferBytes, $maximumLines);

tm_finish($startedAt, $imageResponse);
