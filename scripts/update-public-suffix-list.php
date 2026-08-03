<?php

declare(strict_types=1);

$url = 'https://publicsuffix.org/list/public_suffix_list.dat';
$target = dirname(__DIR__).'/resources/data/public_suffix_list.dat';
$curl = curl_init($url);

if ($curl === false) {
    throw new RuntimeException('Unable to initialize the Public Suffix List request.');
}

curl_setopt_array($curl, [
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HTTPHEADER => ['Accept: text/plain'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_USERAGENT => 'TallyMark Public Suffix List updater',
]);
$contents = curl_exec($curl);
$status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
curl_close($curl);

if (! is_string($contents) || $status !== 200 || ! str_starts_with($contents, '// This Source Code Form is subject to the terms of the Mozilla Public')) {
    throw new RuntimeException('Public Suffix List download was not a valid MPL-2.0 list.');
}

if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0775, true) && ! is_dir(dirname($target))) {
    throw new RuntimeException('Unable to create the Public Suffix List directory.');
}

$temporary = $target.'.tmp';
if (file_put_contents($temporary, $contents, LOCK_EX) === false || ! rename($temporary, $target)) {
    @unlink($temporary);
    throw new RuntimeException('Unable to atomically write the Public Suffix List.');
}

fwrite(STDOUT, "Public Suffix List updated.\n");
