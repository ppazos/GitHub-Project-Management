<?php
/**
 * Bootstrap: load .env into $_ENV and define APP_BASE constant.
 * Must be the first require in every entry point.
 */

$env_file = dirname(__DIR__) . '/.env';
if (file_exists($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        if (preg_match('/^(["\'])(.*)\1$/', $val, $m)) {
            $val = $m[2];
        }
        $_ENV[$key] = $val;
        putenv("{$key}={$val}");
    }
}

// APP_BASE is the URL prefix when the app lives in a subdirectory.
// Auto-detected from DOCUMENT_ROOT vs the project root directory,
// so it works at any path without configuration.
// Can be overridden by setting APP_BASE in .env.
if (!defined('APP_BASE')) {
    if (isset($_ENV['APP_BASE'])) {
        $base = rtrim($_ENV['APP_BASE'], '/');
    } else {
        // Project root = parent of this lib/ directory.
        $project_root = dirname(__DIR__);
        $doc_root     = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
        // Strip the document root prefix to get the URL base path.
        $base = $doc_root !== '' ? str_replace($doc_root, '', $project_root) : '';
        $base = rtrim($base, '/');
    }
    define('APP_BASE', $base);
}
