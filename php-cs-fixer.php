<?php

declare(strict_types=1);

/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

/**
 * Configuration file for PHP CS Fixer.
 */

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$dir = __DIR__;

$finder = Finder::create()
    ->in($dir)
    ->exclude('var')
    ->exclude('dist')
    ->exclude('vendor')
    ->exclude('node_modules')
    ->exclude('plugin/i18n')
;

return (new Config())
    // Allow risky rules that may change code logic.
    ->setRiskyAllowed(true)
    // Based on PSR-12, the latest style recommendation.
    ->setRules([
        '@PSR12' => true,
        // Add "declare(strict_types=1);" to files.
        'declare_strict_types' => true,
        // Indent using spaces.
        'indentation_type' => true,
        // Sort "use" statements alphabetically.
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
        ],
        // Remove unused imports.
        'no_unused_imports' => true,
        // One import per statement.
        'single_import_per_statement' => true,
        // Convert arrays to short syntax "[]".
        'array_syntax' => [
            'syntax' => 'short',
        ],
        // Add trailing commas in multi-line arrays.
        'trailing_comma_in_multiline' => true,
        // Separate constants and properties.
        'class_attributes_separation' => [
            'elements' => [
                'const' => 'one',
                'property' => 'one',
                'method' => 'one',
            ],
        ],
        // The plugin's floor is PHP 8.4: use str_contains() and friends.
        'modernize_strpos' => true,
        // Use arrow functions where possible.
        'use_arrow_functions' => true,
        // Use PHPUnit constructors instead of factory methods.
        'php_unit_construct' => true,
        // Use stricter assertions in PHPUnit.
        // Example: use assertSame() instead of assertEquals().
        'php_unit_strict' => true,
    ])
    ->setLineEnding("\n")
    ->setCacheFile($dir . '/var/cache/php-cs-fixer.cache')
    ->setFinder($finder)
;
