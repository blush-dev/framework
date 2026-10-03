<?php

/**
 * Page cache middleware.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Cache;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Blush\Http\Response;

/**
 * Serves whole responses from the `pages` store, keyed by the content
 * version and the path, so a page is rendered once per version.
 *
 * Only plain page views are cached: `GET` and `HEAD` requests without a
 * query string or credentials, answered `200` with a body of at most
 * 2 MB, no cookie, no `Cache-Control` of `private`, `no-store`, or
 * `no-cache`, and no byte ranges (files are already on disk). A cached
 * response gets `Cache-Control` from `CacheConfig::$maxAge` unless it
 * set its own. `X-Page-Cache` says `hit` or `miss`.
 */
final readonly class PageCache implements MiddlewareInterface
{
	/**
	 * The response header that reports a hit or a miss.
	 */
	public const string HEADER = 'X-Page-Cache';

	/**
	 * The largest body stored, in bytes.
	 */
	public const int MAX_BYTES = 2_097_152;

	public function __construct(
		private Caches $caches,
		private CacheConfig $config,
		private ContentVersion $version
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		if (! $this->config->pages || ! $this->caches->enabled() || ! self::isCacheableRequest($request)) {
			return $handler->handle($request);
		}

		$store = $this->caches->store(CacheNamespace::Pages);
		$key   = hash('xxh128', $this->version->current() . ' ' . $request->getUri()->getPath());
		$hit   = self::fromArray($store->get($key));

		if ($hit !== null) {
			return $hit->withHeader(self::HEADER, 'hit');
		}

		$response = $handler->handle($request);

		if (! self::isCacheableResponse($response)) {
			return $response;
		}

		if (! $response->hasHeader('Cache-Control')) {
			$response = $response->withHeader('Cache-Control', $this->config->maxAge === 0 ? 'public, max-age=0, must-revalidate' : "public, max-age={$this->config->maxAge}");
		}

		$store->set($key, [
			'status'  => $response->getStatusCode(),
			'headers' => $response->getHeaders(),
			'body'    => (string) $response->getBody()
		]);

		return $response->withHeader(self::HEADER, 'miss');
	}

	/**
	 * Returns whether a request is a plain page view.
	 */
	private static function isCacheableRequest(ServerRequestInterface $request): bool
	{
		return in_array($request->getMethod(), ['GET', 'HEAD'], true)
			&& $request->getUri()->getQuery() === ''
			&& ! $request->hasHeader('Authorization');
	}

	/**
	 * Returns whether a response may be stored and shared.
	 */
	private static function isCacheableResponse(ResponseInterface $response): bool
	{
		if ($response->getStatusCode() !== 200 || $response->hasHeader('Set-Cookie') || $response->hasHeader('Accept-Ranges')) {
			return false;
		}

		if (preg_match('/\b(private|no-store|no-cache)\b/i', $response->getHeaderLine('Cache-Control')) === 1 || trim($response->getHeaderLine('Vary')) === '*') {
			return false;
		}

		$size = $response->getBody()->getSize();

		return $size !== null && $size <= self::MAX_BYTES;
	}

	/**
	 * Rebuilds a stored response, or returns `null` for a miss or a
	 * damaged entry.
	 */
	private static function fromArray(mixed $data): ?Response
	{
		if (! is_array($data) || ! is_int($data['status'] ?? null) || ! is_array($data['headers'] ?? null) || ! is_string($data['body'] ?? null)) {
			return null;
		}

		$headers = [];

		foreach ($data['headers'] as $name => $values) {
			if (is_string($name) && is_array($values)) {
				$headers[$name] = array_values(array_filter($values, is_string(...)));
			}
		}

		return new Response($data['status'], $headers, $data['body']);
	}
}
