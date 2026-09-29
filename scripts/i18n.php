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
 * Scans the plugin's source strings (__('...') in plugin/**\/*.php and
 * plugin/**\/*.phtml; <label>/<comment> elements and translate="..."
 * attributes in plugin/etc/**\/*.xml, Magento's own translatable-phrase
 * conventions) and updates plugin/i18n/es_ES.csv and plugin/i18n/es_CL.csv,
 * keeping existing translations for unchanged phrases and reporting new or
 * no-longer-used ones.
 *
 * Magento reads these CSV files directly (Vendor_Module,"phrase","phrase"):
 * unlike WordPress, there is no .pot template or compiled .mo catalog to
 * build, so this script both extracts and updates in one step. Usage:
 * php scripts/i18n.php
 */

const PLUGIN_DIR = __DIR__ . '/../plugin';
const LOCALES = ['es_ES', 'es_CL'];

/**
 * Every __('...') / __("...") call in a PHP or phtml file.
 *
 * @param string $file File path.
 * @return string[]
 */
function extract_php(string $file): array
{
    $code = (string) file_get_contents($file);
    $found = [];
    if (preg_match_all("/__\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $code, $matches)) {
        foreach ($matches[1] as $string) {
            $found[] = stripcslashes($string);
        }
    }
    if (preg_match_all('/__\\(\\s*"((?:[^"\\\\]|\\\\.)*)"/', $code, $matches)) {
        foreach ($matches[1] as $string) {
            $found[] = stripcslashes($string);
        }
    }
    return $found;
}

/**
 * Every <label>/<comment> element and translate="..." attribute of a
 * declarative config file (Magento's own translatable-phrase conventions).
 *
 * @param string $file File path.
 * @return string[]
 */
function extract_xml(string $file): array
{
    $found = [];
    $dom = new DOMDocument();
    $dom->load($file);
    foreach (['label', 'comment'] as $tag) {
        foreach ($dom->getElementsByTagName($tag) as $element) {
            $found[] = trim($element->textContent);
        }
    }
    foreach ($dom->getElementsByTagName('*') as $element) {
        $translate = $element->getAttribute('translate');
        if ($translate === '') {
            continue;
        }
        foreach (preg_split('/\s+/', $translate) as $attribute) {
            if ($element->hasAttribute($attribute)) {
                $found[] = $element->getAttribute($attribute);
            }
        }
    }
    return $found;
}

/**
 * Every file under a directory matching a glob pattern, recursively.
 *
 * @param string $dir     Directory to scan.
 * @param string $pattern Glob pattern (e.g. "*.php").
 * @return string[]
 */
function find_files(string $dir, string $pattern): array
{
    $found = glob($dir . '/' . $pattern) ?: [];
    foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $subdir) {
        $found = array_merge($found, find_files($subdir, $pattern));
    }
    return $found;
}

/**
 * A locale's existing catalog, by source phrase.
 *
 * @param string $file Catalog file path.
 * @return array<string, string>
 */
function load_catalog(string $file): array
{
    $rows = [];
    if (!is_file($file)) {
        return $rows;
    }
    $handle = fopen($file, 'r');
    while (($row = fgetcsv($handle)) !== false) {
        $rows[$row[0]] = $row[1];
    }
    fclose($handle);
    return $rows;
}

$extracted = [];
foreach (find_files(PLUGIN_DIR, '*.php') as $file) {
    $extracted = array_merge($extracted, extract_php($file));
}
foreach (find_files(PLUGIN_DIR, '*.phtml') as $file) {
    $extracted = array_merge($extracted, extract_php($file));
}
foreach (find_files(PLUGIN_DIR . '/etc', '*.xml') as $file) {
    $extracted = array_merge($extracted, extract_xml($file));
}
$extracted = array_values(array_unique($extracted));

foreach (LOCALES as $locale) {
    $catalog = PLUGIN_DIR . '/i18n/' . $locale . '.csv';
    $existing = load_catalog($catalog);
    $entries = [];
    foreach ($extracted as $phrase) {
        $entries[$phrase] = $existing[$phrase] ?? '';
        if (!isset($existing[$phrase])) {
            fwrite(STDERR, "{$locale}: new: {$phrase}\n");
        }
    }
    foreach ($existing as $phrase => $translation) {
        if (!isset($entries[$phrase])) {
            fwrite(STDERR, "{$locale}: unused (removed): {$phrase}\n");
        }
    }

    $handle = fopen($catalog, 'w');
    foreach ($entries as $phrase => $translation) {
        fputcsv($handle, [$phrase, $translation]);
    }
    fclose($handle);
    fwrite(STDERR, sprintf("%s: %d phrases in %s\n", $locale, count($entries), $catalog));
}
