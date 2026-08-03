<?php

declare(strict_types=1);

namespace App\Application\Ingest;

use DateTimeImmutable;

final class BufferReader
{
    /** @return list<string> */
    public function closedFiles(DateTimeImmutable $now): array
    {
        $directory = storage_path('tm-buffer');
        $currentMinute = $now->format('YmdHi');
        $files = [];

        foreach (glob($directory.DIRECTORY_SEPARATOR.'*.ndjson') ?: [] as $path) {
            $filename = basename($path);

            if (preg_match('/^(\\d{12})-\\d+\\.ndjson$/', $filename, $matches) === 1 && $matches[1] < $currentMinute) {
                $files[] = $path;
            }
        }

        sort($files, SORT_STRING);

        return $files;
    }
}
