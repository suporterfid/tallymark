<?php

namespace App\Application\Sites;

use App\Application\Identity\CurrentSaltProvider;
use App\Infrastructure\Persistence\Eloquent\Site;
use RuntimeException;

final class SiteMapGenerator implements SiteMapWriter
{
    public function __construct(private readonly CurrentSaltProvider $currentSaltProvider) {}

    public function regenerate(): void
    {
        $path = storage_path('tm-sites.php');
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the site map directory.');
        }

        $lock = fopen($directory.DIRECTORY_SEPARATOR.'tm-sites.lock', 'c');

        if ($lock === false) {
            throw new RuntimeException('Unable to open the site map lock.');
        }

        $temporaryPath = null;

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Unable to lock site map generation.');
            }

            $sites = Site::withoutGlobalScopes()
                ->with(['hosts' => fn ($query) => $query->withoutGlobalScopes()->orderBy('hostname')])
                ->orderBy('id')
                ->get();

            $map = [
                'salt' => $this->currentSaltProvider->current()->value,
                'sites' => [],
            ];

            foreach ($sites as $site) {
                $map['sites'][$site->site_key] = [
                    'id' => $site->id,
                    'hosts' => $site->hosts->pluck('hostname')->all(),
                    'sample' => $site->sample,
                    'validate_host' => $site->validate_host,
                ];
            }

            $temporaryPath = tempnam($directory, 'tm-sites-');

            if ($temporaryPath === false) {
                throw new RuntimeException('Unable to create a temporary site map.');
            }

            $contents = "<?php\n\nreturn ".var_export($map, true).";\n";

            if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
                throw new RuntimeException('Unable to write the temporary site map.');
            }

            if (! rename($temporaryPath, $path)) {
                throw new RuntimeException('Unable to atomically replace the site map.');
            }
        } finally {
            if (is_string($temporaryPath) && is_file($temporaryPath)) {
                unlink($temporaryPath);
            }

            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
