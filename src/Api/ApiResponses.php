<?php

/**
 * API responses.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Api;

use Closure;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Token;
use Blush\Cache\CacheNamespace;
use Blush\Cache\ContentCache;
use Blush\Core\AppConfig;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Sends the API's answers (D-737, D-741), so every endpoint shares one
 * shape, one set of headers, and one cache:
 *
 * - **Answers** are built once per content version and request (its
 *   path and sorted query), under the page cache's namespace, so
 *   publishing and `cache:clear` empty them as they empty pages. The
 *   `ETag` and `304` come from `ConditionalGet`, a hash of the body.
 * - **Headers:** `Cache-Control: public, max-age` from
 *   `ApiConfig::$maxAge`, and CORS open to any origin
 *   (`Access-Control-Allow-Origin: *`): an anonymous answer is public,
 *   and a token is a header a browser never sends on its own, so a page
 *   on another site gets nothing a token didn't ask for.
 * - **With a token** (D-745), an answer is built for the request alone,
 *   never kept, and sent `Cache-Control: private, no-store`.
 * - **Errors** (`ApiError`) are `{ "error": { "code", "message" } }`
 *   with their status, never cached; a `401` says `WWW-Authenticate:
 *   Bearer`.
 * - **Lists** are `{ items, total, page, pages, per_page, next, prev }`,
 *   `next` and `prev` full URLs or `null`.
 * - **Writes** (D-762) answer what they wrote, never kept, `201` with a
 *   `Location` for something new.
 */
final readonly class ApiResponses
{
	public function __construct(
		private ApiConfig $config,
		private ContentCache $cache,
		private AppConfig $app
	) {}

	/**
	 * Answers a request with what `$build` returns, kept for the content
	 * version, or with the error it throws.
	 *
	 * @param Closure(): (array<array-key, mixed>|object) $build
	 */
	public function answer(ServerRequestInterface $request, Closure $build): ResponseInterface
	{
		$params  = $request->getQueryParams();
		$private = $request->getAttribute(Token::class) instanceof Token;
		ksort($params, SORT_STRING);

		try {
			$json = $private ? self::encode($build()) : $this->cache->remember(
				CacheNamespace::Pages,
				'api ' . $request->getUri()->getPath() . '?' . http_build_query($params),
				static fn (): string => self::encode($build())
			);
		} catch (ApiError $error) {
			return $this->error($error);
		}

		return new Response(Status::Ok, [
			'Content-Type'  => 'application/json; charset=UTF-8',
			'Cache-Control' => $private ? 'private, no-store' : "public, max-age={$this->config->maxAge}",
			...self::cors()
		], $json);
	}

	/**
	 * Answers a write with what it wrote: `201` and where it is now, for
	 * something new.
	 *
	 * @param array<array-key, mixed>|object $data
	 */
	public function written(array|object $data, Status $status = Status::Ok, ?string $location = null): ResponseInterface
	{
		return new Response($status, [
			'Content-Type'  => 'application/json; charset=UTF-8',
			'Cache-Control' => 'no-store',
			...($location === null ? [] : ['Location' => $location]),
			...self::cors()
		], self::encode($data));
	}

	/**
	 * Answers with an error.
	 */
	public function error(ApiError $error): ResponseInterface
	{
		return new Response($error->status, [
			'Content-Type'  => 'application/json; charset=UTF-8',
			'Cache-Control' => 'no-store',
			...($error->status === Status::Unauthorized ? ['WWW-Authenticate' => $error->error === 'invalid_token' ? 'Bearer error="invalid_token"' : 'Bearer'] : []),
			...($error->retryAfter === null ? [] : ['Retry-After' => (string) $error->retryAfter]),
			...self::cors()
		], self::encode($error->toArray()));
	}

	/**
	 * Answers a browser's CORS preflight: reads and writes (D-762), with
	 * the headers a conditional request sends, a token's, and a write's.
	 */
	public function preflight(): ResponseInterface
	{
		return new Response(Status::NoContent, [
			...self::cors(),
			'Access-Control-Allow-Methods' => 'GET, HEAD, POST, PATCH, DELETE, OPTIONS',
			'Access-Control-Allow-Headers' => 'Accept, Authorization, Content-Type, If-Match, If-None-Match, If-Modified-Since',
			'Access-Control-Max-Age'       => '86400'
		]);
	}

	/**
	 * Returns a page of a list, with links to the pages beside it that
	 * keep the request's other parameters.
	 *
	 * @param  list<mixed> $items
	 * @return array{items: list<mixed>, total: int, page: int, pages: int, per_page: int, next: ?string, prev: ?string}
	 */
	public function page(ServerRequestInterface $request, array $items, int $total, int $page, int $perPage): array
	{
		$pages = max(1, (int) ceil($total / max(1, $perPage)));

		return [
			'items'    => $items,
			'total'    => $total,
			'page'     => $page,
			'pages'    => $pages,
			'per_page' => $perPage,
			'next'     => $page < $pages ? $this->pageUrl($request, $page + 1) : null,
			'prev'     => $page > 1 ? $this->pageUrl($request, min($page - 1, $pages)) : null
		];
	}

	/**
	 * Returns a path under the API as a full URL: `/entries` is
	 * `https://example.com/api/v1/entries`.
	 */
	public function url(string $path = ''): string
	{
		return $this->app->absoluteUrl($this->config->base() . $path);
	}

	/**
	 * Returns the request's URL on another page.
	 */
	private function pageUrl(ServerRequestInterface $request, int $page): string
	{
		$params = [...$request->getQueryParams(), 'page' => (string) $page];

		if ($page === 1) {
			unset($params['page']);
		}

		$query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

		return $this->app->absoluteUrl($request->getUri()->getPath()) . ($query === '' ? '' : "?{$query}");
	}

	/**
	 * Returns the CORS headers every answer has.
	 *
	 * @return array<string, string>
	 */
	private static function cors(): array
	{
		return ['Access-Control-Allow-Origin' => '*', 'Access-Control-Expose-Headers' => 'ETag, Location, Retry-After, WWW-Authenticate'];
	}

	/**
	 * Encodes an answer as JSON.
	 *
	 * @param array<array-key, mixed>|object $data
	 */
	private static function encode(array|object $data): string
	{
		try {
			return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
		} catch (JsonException $error) {
			throw new ApiError('server_error', 'The answer couldn\'t be encoded: ' . $error->getMessage(), Status::InternalServerError);
		}
	}
}
