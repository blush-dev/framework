<?php

/**
 * Stray output.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Output printed while a request is handled instead of returned in the
 * response: `dump()` calls, `var_dump()`, a stray `echo`. `HttpRunner`
 * buffers it so it can't send headers before the response does, and
 * this puts it at the top of the response body: just inside `<body>` for
 * HTML, or before the body otherwise. Nothing printed, nothing changed.
 *
 * Output inside a view is already part of the rendered HTML and never
 * gets here. A future debug toolbar or nicer dumper can take over from
 * here without changing `dump()` calls.
 */
final readonly class StrayOutput
{
	/**
	 * Returns the response with the output added to its body.
	 */
	public static function insert(ResponseInterface $response, string $output): ResponseInterface
	{
		if ($output === '') {
			return $response;
		}

		$body = (string) $response->getBody();

		$isHtml = str_contains($response->getHeaderLine('Content-Type'), 'html');

		$body = $isHtml && preg_match('/<body\b[^>]*>/i', $body, $match, PREG_OFFSET_CAPTURE) === 1
			? substr_replace($body, $output, $match[0][1] + strlen($match[0][0]), 0)
			: $output . $body;

		return $response->withoutHeader('Content-Length')->withBody(Stream::fromString($body));
	}
}
