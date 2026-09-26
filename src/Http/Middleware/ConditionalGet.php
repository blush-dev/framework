<?php

/**
 * Conditional GET middleware.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Blush\Http\Response;

/**
 * Answers a repeat `GET` or `HEAD` with `304 Not Modified` when the
 * client's copy is current (RFC 9110). Successful responses get a strong
 * `ETag` (a hash of the body) unless they have one, or are files, which
 * have `Last-Modified` instead. `If-None-Match` wins over
 * `If-Modified-Since`, as the RFC says.
 *
 * It runs outside the page cache, so a cached page still costs a
 * browser nothing but the check.
 */
final readonly class ConditionalGet implements MiddlewareInterface
{
	/**
	 * Headers a 304 repeats from the full response.
	 */
	private const array KEPT = ['Cache-Control', 'Content-Location', 'Date', 'ETag', 'Expires', 'Last-Modified', 'Vary'];

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$response = $handler->handle($request);

		if (! in_array($request->getMethod(), ['GET', 'HEAD'], true) || $response->getStatusCode() !== 200) {
			return $response;
		}

		if (! $response->hasHeader('ETag') && ! $response->hasHeader('Accept-Ranges') && $response->getBody()->getSize() !== null) {
			$response = $response->withHeader('ETag', '"' . hash('xxh128', (string) $response->getBody()) . '"');
		}

		return self::isFresh($request, $response) ? self::notModified($response) : $response;
	}

	/**
	 * Returns whether the client's copy matches the response.
	 */
	private static function isFresh(ServerRequestInterface $request, ResponseInterface $response): bool
	{
		$match = $request->getHeaderLine('If-None-Match');

		if ($match !== '') {
			$etag = self::opaque($response->getHeaderLine('ETag'));

			if ($etag === '') {
				return false;
			}

			foreach (explode(',', $match) as $candidate) {
				$candidate = trim($candidate);

				if ($candidate === '*' || self::opaque($candidate) === $etag) {
					return true;
				}
			}

			return false;
		}

		$since    = strtotime($request->getHeaderLine('If-Modified-Since') ?: 'invalid');
		$modified = strtotime($response->getHeaderLine('Last-Modified') ?: 'invalid');

		return $since !== false && $modified !== false && $modified <= $since;
	}

	/**
	 * Returns an entity tag without its weak prefix, for the weak
	 * comparison `If-None-Match` uses.
	 */
	private static function opaque(string $etag): string
	{
		return str_starts_with($etag, 'W/') ? substr($etag, 2) : $etag;
	}

	/**
	 * Returns the 304 for a response, with the headers it must repeat.
	 */
	private static function notModified(ResponseInterface $response): ResponseInterface
	{
		$headers = [];

		foreach ($response->getHeaders() as $name => $values) {
			if (in_array(strtolower((string) $name), array_map(strtolower(...), self::KEPT), true) || str_starts_with(strtolower((string) $name), 'x-')) {
				$headers[(string) $name] = array_values($values);
			}
		}

		return Response::notModified($headers);
	}
}
