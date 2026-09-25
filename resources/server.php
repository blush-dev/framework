<?php

/**
 * Development server router.
 *
 * The router script for `blush serve` (`php -S … resources/server.php`).
 * Requests for files that exist under the document root are left to the
 * built-in server; everything else goes to the front controller. Like the
 * front controller, this is an entry point, so it may read superglobals.
 * Never use it in production.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

$root = realpath(is_string($_SERVER['DOCUMENT_ROOT'] ?? null) ? $_SERVER['DOCUMENT_ROOT'] : (string) getcwd());
$uri  = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
$path = rawurldecode(strtok($uri, '?') ?: '/');

if ($root === false) {
	http_response_code(500);
	echo 'The document root does not exist.';

	return true;
}

// Leave existing files to the built-in server, unless the path climbs out
// of the document root. Symlinked files (such as published media) are
// served where they point.
$escapes = in_array('..', explode('/', str_replace('\\', '/', $path)), true);

if ($path !== '/' && ! $escapes && is_file($root . $path)) {
	return false;
}

require $root . '/index.php';
