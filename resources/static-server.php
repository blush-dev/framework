<?php

/**
 * Static export server router.
 *
 * The router script for `blush serve --static` (`php -S … resources/static-server.php`),
 * which previews the static export (`build`) as a static host serves it
 * (D-138): a file is served as is; a folder by its first index file
 * (`index.html`, `index.xml`, `index.rss`, `index.atom`, `index.json`,
 * `index.txt`, as `Blush\Export\ExportLayout::INDEXES` lists them), with
 * the content type its extension gives; then the export's `_redirects`
 * rules (D-140: `:name` and `*` placeholders, 3xx redirects and 200
 * rewrites), which, as on Netlify, apply only where no file exists;
 * anything else is `404.html` with a 404. Dotfiles and the host files
 * (`_redirects`, `_headers`) are never served. It runs without the
 * framework, so the preview shows only what's in the export. Like the
 * front controller, this is an entry point, so it may read superglobals.
 * Never use it in production.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

$root  = realpath(is_string($_SERVER['DOCUMENT_ROOT'] ?? null) ? $_SERVER['DOCUMENT_ROOT'] : (string) getcwd());
$uri   = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
$path  = rawurldecode(strtok($uri, '?') ?: '/');
$types = [
	'html' => 'text/html; charset=utf-8',
	'htm'  => 'text/html; charset=utf-8',
	'xml'  => 'application/xml; charset=utf-8',
	'rss'  => 'application/rss+xml; charset=utf-8',
	'atom' => 'application/atom+xml; charset=utf-8',
	'json' => 'application/json; charset=utf-8',
	'txt'  => 'text/plain; charset=utf-8',
	'css'  => 'text/css; charset=utf-8',
	'js'   => 'text/javascript; charset=utf-8',
	'mjs'  => 'text/javascript; charset=utf-8',
	'svg'  => 'image/svg+xml'
];

if ($root === false) {
	http_response_code(500);
	echo 'The export folder does not exist.';

	return true;
}

$segments = explode('/', str_replace('\\', '/', $path));
$hidden   = array_any($segments, static fn (string $segment): bool => str_starts_with($segment, '.')) || in_array($path, ['/_redirects', '/_headers'], true);
$file     = null;

if (! $hidden && is_file($root . $path)) {
	$file = $root . $path;
} elseif (! $hidden && is_dir($root . $path)) {
	foreach (['index.html', 'index.xml', 'index.rss', 'index.atom', 'index.json', 'index.txt'] as $index) {
		if (is_file(rtrim($root . $path, '/') . "/{$index}")) {
			$file = rtrim($root . $path, '/') . "/{$index}";
			break;
		}
	}
}

// Redirect and rewrite rules from `_redirects`, first match wins.
$rules = $file === null && ! $hidden && is_file("{$root}/_redirects") ? (file("{$root}/_redirects", FILE_IGNORE_NEW_LINES) ?: []) : [];

foreach ($rules as $rule) {
	$fields = preg_split('/\s+/', trim($rule)) ?: [];

	if (count($fields) < 2 || str_starts_with($fields[0], '#')) {
		continue;
	}

	[$from, $to] = $fields;
	$status      = (int) ($fields[2] ?? 301);
	$names       = [];
	$regex       = preg_replace_callback('#:([A-Za-z_][A-Za-z0-9_]*)|\*|[^:*]+#', static function (array $match) use (&$names): string {
		if ($match[0] === '*') {
			$names[] = 'splat';

			return '(.*)';
		}

		if (isset($match[1]) && $match[1] !== '') {
			$names[] = $match[1];

			return '([^/]+)';
		}

		return preg_quote($match[0], '#');
	}, $from);
	$matches = [];

	if (preg_match("#^{$regex}$#", implode('/', array_map(rawurlencode(...), explode('/', $path))), $matches) !== 1) {
		continue;
	}

	foreach ($names as $index => $name) {
		$to = str_replace(":{$name}", $matches[$index + 1], $to);
	}

	if ($status >= 300 && $status < 400) {
		http_response_code($status);
		header('Location: ' . $to);
		header('Content-Type: text/plain; charset=utf-8');
		echo "Redirecting to {$to}";

		return true;
	}

	$target = rawurldecode((string) strtok($to, '?'));

	if ($status === 200 && ! str_contains($target, '..') && is_file($root . $target)) {
		$file = $root . $target;
		break;
	}
}

if ($file === null) {
	http_response_code(404);
	$file = is_file("{$root}/404.html") ? "{$root}/404.html" : null;
}

if ($file === null) {
	header('Content-Type: text/plain; charset=utf-8');
	echo 'Not found.';

	return true;
}

$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

header('Content-Type: ' . ($types[$extension] ?? (mime_content_type($file) ?: 'application/octet-stream')));
header('Content-Length: ' . filesize($file));
readfile($file);

return true;
