<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/IoException.php';

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(dirname(__DIR__) . '/src', FilesystemIterator::SKIP_DOTS),
);

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    if ($file->getFilename() === 'IoException.php') {
        continue;
    }

    require $file->getPathname();
}
