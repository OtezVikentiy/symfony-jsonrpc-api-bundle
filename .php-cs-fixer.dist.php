<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__ . '/src')
    ->name('*.php');

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        // This codebase always imports global-namespace classes instead of
        // referencing them inline; @Symfony's global_namespace_import would
        // otherwise drop `use` statements for classes like ReflectionClass
        // or Throwable in favor of a leading backslash.
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
        // @Symfony collapses multi-line throw/sprintf calls onto one very
        // long line; this codebase consistently breaks long exception
        // messages across lines for readability, so keep that intact.
        'single_line_throw' => false,
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'trailing_comma_in_multiline' => true,
        'yoda_style' => false,
        'phpdoc_align' => false,
        'concat_space' => ['spacing' => 'one'],
    ])
    ->setFinder($finder);
