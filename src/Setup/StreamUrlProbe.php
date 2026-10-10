<?php

/**
 * Stream URL probe.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Setup;

use Override;
use Blush\Core\Framework;

/**
 * The default `UrlProbe`: PHP's HTTP stream wrapper, with a short
 * timeout, no redirects, and certificates verified.
 */
final readonly class StreamUrlProbe implements UrlProbe
{
	/**
	 * Seconds to wait for an answer.
	 */
	public const int TIMEOUT = 3;

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function get(string $url, int $limit): ?ProbeResponse
	{
		if (! str_starts_with($url, 'https://') && ! str_starts_with($url, 'http://')) {
			return null;
		}

		$context = stream_context_create(['http' => [
			'method'          => 'GET',
			'timeout'         => self::TIMEOUT,
			'follow_location' => 0,
			'ignore_errors'   => true,
			'header'          => 'User-Agent: ' . Framework::NAME . '/' . Framework::VERSION . "\r\n"
		]]);

		$body    = @file_get_contents($url, false, $context, 0, max(1, $limit));
		$headers = http_get_last_response_headers() ?? [];
		$status  = preg_match('#^HTTP/\S+\s+(\d{3})\b#', $headers[0] ?? '', $match) === 1 ? (int) $match[1] : null;

		return $status === null ? null : new ProbeResponse($status, is_string($body) ? $body : '');
	}
}
