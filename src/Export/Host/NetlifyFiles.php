<?php

/**
 * Netlify host files.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export\Host;

use Override;
use Blush\Export\ExportRedirect;
use Blush\Routing\RoutePattern;
use Blush\Support\UrlPath;

/**
 * Writes `_redirects` and `_headers`, the files Netlify and Cloudflare
 * Pages read (D-140):
 *
 * - Each redirect as `from to status`. A parameter that fills a whole
 *   segment becomes `:name` (its constraint is dropped, so it matches
 *   any segment), and one that ends the pattern and may hold slashes
 *   (`{path:.+}`) becomes `*` with `:splat` in the target. Other
 *   patterns can't be written, and get a notice. These hosts apply a
 *   redirect only where no file exists, as the live site checks
 *   redirects only before a 404.
 * - Each folder whose index isn't `index.html` rewritten to its index
 *   (`/feed /feed/index.rss 200`), since these hosts look only for
 *   `index.html`, and its content type in `_headers`.
 *
 * Both hosts serve `404.html` for missing pages by themselves.
 */
final class NetlifyFiles extends HostFiles
{
	/**
	 * The redirects file.
	 */
	public const string REDIRECTS = '_redirects';

	/**
	 * The headers file.
	 */
	public const string HEADERS = '_headers';

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function files(HostContext $context): HostOutput
	{
		$redirects = ['# ' . self::banner()];
		$headers   = ['# ' . self::banner()];
		$notices   = [];

		foreach ($context->redirects as $redirect) {
			$line = self::rule($redirect);

			if ($line === null) {
				$notices[] = sprintf('The redirect from "%s" can\'t be written to %s.', $redirect->pattern->path, self::REDIRECTS);
			} else {
				$redirects[] = $line;
			}
		}

		foreach ($context->indexes as $path => [$file, $type]) {
			$redirects[] = sprintf('%s /%s 200', UrlPath::encode($path), UrlPath::encode($file));
			array_push($headers, UrlPath::encode($path), "  Content-Type: {$type}", '/' . UrlPath::encode($file), "  Content-Type: {$type}");
		}

		return new HostOutput(
			[self::REDIRECTS => implode("\n", $redirects) . "\n", self::HEADERS => implode("\n", $headers) . "\n"],
			$notices
		);
	}

	/**
	 * Returns a redirect's line, or `null` when its pattern can't be
	 * written.
	 */
	private static function rule(ExportRedirect $redirect): ?string
	{
		$from  = '';
		$to    = $redirect->to;
		$parts = $redirect->pattern->parts;
		$last  = array_key_last($parts);

		foreach ($parts as $index => $part) {
			if (is_string($part)) {
				$from .= UrlPath::encode($part);
				continue;
			}

			[$name, $regex] = $part;
			$next           = $parts[$index + 1] ?? '';
			$whole          = str_ends_with($from, '/') && ($index === $last || (is_string($next) && str_starts_with($next, '/')));

			if (! $whole) {
				return null;
			}

			if ($regex === RoutePattern::SEGMENT || ! self::spansSegments($regex)) {
				$from .= ":{$name}";
				$to    = str_replace('{' . $name . '}', ":{$name}", $to);
			} elseif ($index === $last) {
				$from .= '*';
				$to    = str_replace('{' . $name . '}', ':splat', $to);
			} else {
				return null;
			}
		}

		return preg_match('/\s/', $from . $to) === 1 ? null : sprintf('%s %s %d', $from === '' ? '/' : $from, $to, $redirect->status);
	}

	/**
	 * Returns whether a constraint can match a slash.
	 */
	private static function spansSegments(string $regex): bool
	{
		return preg_match("~^(?:{$regex})$~", 'a/b') === 1;
	}
}
