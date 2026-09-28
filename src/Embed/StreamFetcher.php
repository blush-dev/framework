<?php

/**
 * Stream fetcher.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed;

use Override;
use Blush\Core\Framework;

/**
 * The default `Fetcher`: PHP's HTTP stream wrapper, with a timeout, up to
 * three redirects, and a 1 MB limit. Only HTTPS URLs are fetched.
 */
final readonly class StreamFetcher implements Fetcher
{
	/**
	 * The most bytes read from a response.
	 */
	private const int LIMIT = 1_000_000;

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function get(string $url, int $timeout): ?string
	{
		if (! str_starts_with($url, 'https://')) {
			return null;
		}

		$context = stream_context_create(['http' => [
			'method'          => 'GET',
			'timeout'         => max(1, $timeout),
			'follow_location' => 1,
			'max_redirects'   => 3,
			'ignore_errors'   => true,
			'header'          => "Accept: application/json\r\nUser-Agent: " . Framework::NAME . '/' . Framework::VERSION . "\r\n"
		]]);

		$body   = @file_get_contents($url, false, $context, 0, self::LIMIT);
		$status = http_get_last_response_headers() ?? [];

		// After redirects, the last status line is the final response's.
		$lines = array_values(array_filter($status, static fn (string $line): bool => str_starts_with($line, 'HTTP/')));
		$last  = array_last($lines) ?? '';

		return is_string($body) && preg_match('#^HTTP/\S+\s+200\b#', $last) === 1 ? $body : null;
	}
}
