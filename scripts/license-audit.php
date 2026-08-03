<?php

declare(strict_types=1);

require dirname(__DIR__).'/app/Domain/Licensing/LicenseAudit.php';

$lock = json_decode((string) file_get_contents(dirname(__DIR__).'/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
$selections = require dirname(__DIR__).'/config/licence-selections.php';
$violations = (new App\Domain\Licensing\LicenseAudit())->violations($lock['packages'] ?? [], $selections);

if ($violations !== []) {
    fwrite(STDERR, implode(PHP_EOL, $violations).PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Runtime dependency licence audit passed.\n");
