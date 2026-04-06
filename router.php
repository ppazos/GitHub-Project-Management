<?php
/**
 * Development router for the PHP built-in server.
 * Usage: php -S localhost:8080 router.php
 *
 * NOT for production — Apache + .htaccess handles routing there.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Strip trailing slash (except root)
if ($uri !== '/' && str_ends_with($uri, '/')) {
    $uri = rtrim($uri, '/');
}

$routes = [
    '/'                 => 'public/index.php',
    '/app'              => 'public/index.php',
    '/auth/login'       => 'auth/login.php',
    '/auth/callback'    => 'auth/callback.php',
    '/auth/logout'      => 'auth/logout.php',
    '/api/me'           => 'api/me.php',
    '/api/repos'        => 'api/repos.php',
    '/api/milestones'   => 'api/milestones.php',
    '/api/issues'       => 'api/issues.php',
    '/api/issue_update' => 'api/issue_update.php',
    '/api/issue_order'      => 'api/issue_order.php',
    '/api/milestone_create' => 'api/milestone_create.php',
    '/api/milestone_stats'  => 'api/milestone_stats.php',
];

// Deny direct access to lib/ and .env (mirrors .htaccess rules)
if (str_starts_with($uri, '/lib/') || $uri === '/.env') {
    http_response_code(403);
    exit('403 Forbidden');
}

if (isset($routes[$uri])) {
    require __DIR__ . '/' . $routes[$uri];
    exit;
}

// Serve real static files (CSS, JS, images) if they exist under public/
$static = __DIR__ . '/public' . $uri;
if (is_file($static)) {
    return false; // let the built-in server serve it directly
}

http_response_code(404);
echo '404 Not Found';
