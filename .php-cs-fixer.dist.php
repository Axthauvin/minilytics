<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = (new Finder())
    ->in(__DIR__)
    ->name('*.php')
    ->exclude(['vendor', 'node_modules', 'data', 'dist', 'umami-import'])
    ->ignoreDotFiles(true)
    ->ignoreVCSIgnored(true);

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(false)
    ->setRules([
        // Pinned on purpose: `@PER-CS` follows the newest revision and could
        // reformat the whole codebase after a `composer update`.
        '@PER-CS3.0' => true,
    ])
    ->setFinder($finder);
