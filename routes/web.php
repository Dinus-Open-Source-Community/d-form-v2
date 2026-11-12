<?php

use Symfony\Component\Finder\Finder;

$path = __DIR__ . '/web';

$finder = new Finder();

$finder->files()->in("{$path}")->name('/^.*\.php$/')->sortByName();

foreach ($finder as $file) {
    // Use `require` (not `require_once`) so route files load again for each
    // new Application instance in PHPUnit, where the app is recreated per test.
    require $file->getRealPath();
}
