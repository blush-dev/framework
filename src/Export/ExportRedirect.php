<?php

/**
 * Export redirect.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export;

use Blush\Routing\InvalidRoute;
use Blush\Routing\RoutePattern;

/**
 * A redirect the export carries to its host (D-139): from a route
 * pattern (a redirect from the route table, which may have parameters)
 * or a literal path (one the crawl met), to a URL whose `{name}`
 * placeholders the pattern's parameters fill. The pattern's literal text
 * is decoded (`/café`), as hosts match it.
 */
final readonly class ExportRedirect
{
	public function __construct(
		public RoutePattern $pattern,
		public string $to,
		public int $status = 301
	) {}

	/**
	 * Builds a redirect from a route pattern, or returns `null` when it
	 * doesn't parse.
	 */
	public static function fromPattern(string $pattern, string $to, int $status = 301): ?self
	{
		try {
			$parsed = RoutePattern::parse($pattern);
		} catch (InvalidRoute) {
			return null;
		}

		$parts = array_map(static fn (string|array $part): string|array => is_string($part) ? rawurldecode($part) : $part, $parsed->parts);

		return new self(new RoutePattern($parsed->path, $parts, $parsed->params), $to, $status);
	}

	/**
	 * Builds a redirect from a raw (percent-encoded) path.
	 */
	public static function fromPath(string $path, string $to, int $status = 301): self
	{
		$decoded = rawurldecode($path);

		return new self(new RoutePattern($decoded, [$decoded], []), $to, $status);
	}

	/**
	 * Returns the literal path, or `null` for a pattern with parameters.
	 */
	public function path(): ?string
	{
		return $this->pattern->isStatic() ? implode('', array_filter($this->pattern->parts, is_string(...))) : null;
	}
}
