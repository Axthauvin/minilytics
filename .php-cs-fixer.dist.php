<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = (new Finder())
    ->in([__DIR__ . '/app', __DIR__ . '/scripts'])
    ->name('*.php')
    ->exclude(['vendor', 'data', 'umami-import'])
    ->ignoreDotFiles(true)
    ->ignoreVCSIgnored(true);

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PER-CS3.0' => true,
    ])
    ->setFinder($finder);
