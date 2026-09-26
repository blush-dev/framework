<?php

/**
 * Apache host files.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export\Host;

use Override;
use Blush\Export\ExportLayout;
use Blush\Export\ExportRedirect;

/**
 * Writes an `.htaccess` for an export served by Apache from the root of
 * its host, such as shared hosting (D-140):
 *
 * - `DirectoryIndex` with `ExportLayout::INDEXES`, the content types of
 *   the index extensions (`.rss`, `.atom`, `.json`, and `.xml`, since a
 *   host's own MIME list may lack them), UTF-8 for text, no listings or MultiViews, the
 *   404 page, and the host files themselves kept private.
 * - Each redirect as an anchored `RewriteRule`. Paths the site confirmed
 *   redirect always do; patterns (regexes with their constraints, and
 *   `{name}` in the target as `$n`) only where no file or folder
 *   exists, as the live site checks redirects only before a 404.
 * - Without trailing slashes (the default), `DirectorySlash Off`, a 301
 *   from `/about/` to `/about`, and `/about` served from its folder's
 *   index file, so URLs keep their exact form. With them, Apache's own
 *   slash redirect does the job.
 *
 * Needs `mod_rewrite` for redirects and extensionless URLs, and
 * `AllowOverride` that permits these directives (shared hosts do).
 */
final class ApacheFiles extends HostFiles
{
	/**
	 * The file written.
	 */
	public const string FILE = '.htaccess';

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function files(HostContext $context): HostOutput
	{
		$lines = [
			'# ' . self::banner(),
			'',
			'Options -Indexes -MultiViews',
			'DirectoryIndex ' . implode(' ', ExportLayout::INDEXES),
			'AddType application/rss+xml .rss',
			'AddType application/atom+xml .atom',
			'AddType application/json .json',
			'AddType application/xml .xml',
			'AddDefaultCharset utf-8',
			'AddCharset utf-8 .xml .rss .atom .json .txt .css .js .mjs .svg'
		];

		if ($context->notFound !== null) {
			$lines[] = "ErrorDocument 404 /{$context->notFound}";
		}

		array_push(
			$lines,
			'',
			'<FilesMatch "^(\.htaccess|_redirects|_headers)$">',
			"\tRequire all denied",
			'</FilesMatch>'
		);

		if (! $context->trailingSlash) {
			array_push($lines, '', 'DirectorySlash Off');
		}

		array_push($lines, '', '<IfModule mod_rewrite.c>', "\tRewriteEngine On");

		if ($context->redirects !== []) {
			$lines[] = '';
			$lines[] = "\t# Redirects";

			foreach ($context->redirects as $redirect) {
				if (! $redirect->pattern->isStatic()) {
					array_push($lines, "\tRewriteCond %{REQUEST_FILENAME} !-f", "\tRewriteCond %{REQUEST_FILENAME} !-d");
				}

				$lines[] = "\t" . self::rule($redirect);
			}
		}

		if (! $context->trailingSlash) {
			array_push(
				$lines,
				'',
				"\t# Canonical URLs have no trailing slash; serve folders by their index file.",
				"\tRewriteCond %{REQUEST_FILENAME} -d",
				"\tRewriteRule ^(.+)/$ /$1 [R=301,L,NE]"
			);

			foreach (ExportLayout::INDEXES as $index) {
				array_push(
					$lines,
					"\tRewriteCond %{REQUEST_FILENAME} -d",
					"\tRewriteCond %{REQUEST_FILENAME}/{$index} -f",
					"\tRewriteRule ^(.+)$ /$1/{$index} [L]"
				);
			}
		}

		$lines[] = '</IfModule>';

		return new HostOutput([self::FILE => implode("\n", $lines) . "\n"]);
	}

	/**
	 * Returns a redirect's `RewriteRule`. Rules match the decoded path
	 * without its leading slash.
	 */
	private static function rule(ExportRedirect $redirect): string
	{
		$regex = '';
		$to    = self::escapeTarget($redirect->to);

		foreach ($redirect->pattern->parts as $index => $part) {
			$regex .= is_string($part)
				? preg_quote($index === 0 ? ltrim($part, '/') : $part)
				: "({$part[1]})";
		}

		foreach ($redirect->pattern->params as $position => $name) {
			$to = str_replace('{' . $name . '}', '$' . ($position + 1), $to);
		}

		return sprintf('RewriteRule ^%s$ %s [R=%d,L,NE]', str_replace(' ', '\x20', $regex), $to, $redirect->status);
	}

	/**
	 * Escapes a redirect target for a `RewriteRule` substitution: spaces
	 * percent-encoded, and `$` and `%` taken literally.
	 */
	private static function escapeTarget(string $to): string
	{
		return str_replace(['%', '$', ' '], ['\%', '\$', '\%20'], $to);
	}
}
