/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

/**
 * Configuration file for ESLint: the plugin's JavaScript
 * (plugin/view/adminhtml/web/js), a RequireJS module loaded as is by the
 * admin theme (no build step), with ESLint's recommended rules, a JSDoc
 * block on every function (with its @param and @return tags) and a style
 * close to the PHP code's (PSR-12, PHP CS Fixer): 4 spaces, single quotes,
 * semicolons, trailing commas in multi-line literals, no spaces inside
 * parentheses.
 */

import js from '@eslint/js';
import stylistic from '@stylistic/eslint-plugin';
import jsdoc from 'eslint-plugin-jsdoc';
import globals from 'globals';

export default [
    {
        // Only the plugin's scripts (not dependencies, builds or tests).
        ignores: ['**/*', '!plugin/', '!plugin/view/', '!plugin/view/adminhtml/', '!plugin/view/adminhtml/web/', '!plugin/view/adminhtml/web/js/', '!plugin/view/adminhtml/web/js/**/*.js'],
    },
    {
        files: ['plugin/view/adminhtml/web/js/**/*.js'],
        ...js.configs.recommended,
    },
    {
        files: ['plugin/view/adminhtml/web/js/**/*.js'],
        ...stylistic.configs.customize({
            indent: 4,
            quotes: 'single',
            semi: true,
            commaDangle: 'always-multiline',
            braceStyle: '1tbs',
            arrowParens: true,
        }),
    },
    {
        files: ['plugin/view/adminhtml/web/js/**/*.js'],
        languageOptions: {
            // A RequireJS module (no ES modules) for the browser.
            ecmaVersion: 2017,
            sourceType: 'script',
            globals: {
                ...globals.browser,
                define: 'readonly',
            },
        },
        plugins: {
            jsdoc,
        },
        // @return, as in the PHP docblocks.
        settings: {
            jsdoc: {
                tagNamePreference: {
                    returns: 'return',
                },
            },
        },
        rules: {
            'jsdoc/require-jsdoc': ['error', {
                require: {
                    FunctionDeclaration: true,
                    FunctionExpression: false,
                    ArrowFunctionExpression: false,
                },
            }],
            'jsdoc/require-param': 'error',
            'jsdoc/require-param-type': 'error',
            'jsdoc/require-returns': ['error', { forceReturnsWithAsync: true }],
            'jsdoc/require-returns-type': 'error',
            'jsdoc/check-param-names': 'error',
            'jsdoc/check-tag-names': 'error',
            'jsdoc/check-types': 'error',
            'jsdoc/valid-types': 'error',
            'strict': ['error', 'function'],
            'no-var': 'off',
        },
    },
];
