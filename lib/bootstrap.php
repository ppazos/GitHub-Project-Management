<?php
/**
 * Bootstrap: load .env variables into $_ENV.
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
        // Strip surrounding quotes
        if (preg_match('/^(["\'])(.*)\1$/', $val, $m)) {
            $val = $m[2];
        }
        $_ENV[$key] = $val;
        putenv("{$key}={$val}");
    }
}
