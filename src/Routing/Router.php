<?php

/**
 * Router.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Blush\Container\Container;
use Blush\Core\Paths;
use Blush\Event\Dispatcher;
use Blush\Http\MethodNotAllowed;
use Blush\Http\Middleware\Pipeline;
use Blush\Http\NotFound;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Routing\Events\RouteMatched;

/**
 * The kernel's handler: matches the request to a route and runs it.
 *
 * 1. A non-canonical path (trailing slash, per `RouteConfig`) that would
 *    match redirects to the canonical one, unless the route it reaches is
 *    `exact()` (the admin, webhooks), which answers either form. When
 *    only a fallback route matches, it's run first, so paths that lead
 *    nowhere stay 404s.
 * 2. The route table is searched. `HEAD` falls back to `GET`. A path that
 *    matches only other methods is a 405 with an `Allow` header, except
 *    `OPTIONS`, which gets a 204 listing them.
 * 3. The match and its parameters become request attributes,
 *    `RouteMatched` is dispatched, and the route's middleware and handler
 *    run.
 *    A fallback route that finds nothing, on a path other routes answer
 *    with other methods, is a 405.
 * 4. On a 404 (no route, or a handler throwing `NotFound`), redirects are
 *    checked: the redirect map, then `/public/...` URLs from a
 *    whole-project install (D-071), which redirect to the path without it.
 */
final readonly class Router implements RequestHandlerInterface
{
	public function __construct(
		private RouteTable $routes,
		private RouteConfig $config,
		private Container $container,
		private Dispatcher $events,
		private Paths $paths
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function handle(ServerRequestInterface $request): ResponseInterface
	{
		$path      = $this->pathOf($request);
		$canonical = $this->config->canonicalPath($path);

		if ($canonical !== $path && ! $this->isExact($request, $path) && $this->answers($request, $canonical)) {
			return $this->redirect($request, $canonical);
		}

		try {
			return $this->dispatch($request, $this->lookupPath($path));
		} catch (NotFound $notFound) {
			return $this->redirectFor($request, $path) ?? throw $notFound;
		}
	}

	/**
	 * Returns whether the route a request reaches answers its path as
	 * written (`Route::exact()`). `HEAD` reaches a `GET` route.
	 */
	private function isExact(ServerRequestInterface $request, string $path): bool
	{
		$lookup = $this->lookupPath($path);
		$method = $request->getMethod();
		$match  = $this->routes->find($method, $lookup) ?? ($method === 'HEAD' ? $this->routes->find('GET', $lookup) : null);

		return $match?->route->exact === true;
	}

	/**
	 * Returns whether something answers a path. When only a fallback route
	 * does, it's asked, so a non-canonical URL that leads nowhere is a 404
	 * rather than a redirect to one.
	 */
	private function answers(ServerRequestInterface $request, string $path): bool
	{
		$lookup = $this->lookupPath($path);

		if ($this->routes->methodsFor($lookup, fallbacks: false) !== []) {
			return true;
		}

		if ($this->routes->methodsFor($lookup) === []) {
			return false;
		}

		try {
			$this->dispatch($request->withUri($request->getUri()->withPath($path)), $lookup);
		} catch (NotFound) {
			return false;
		} catch (MethodNotAllowed) {
			return true;
		}

		return true;
	}

	/**
	 * Matches and runs a route.
	 *
	 * A fallback route (such as the page catch-all) answers `GET` for any
	 * path, so when one finds nothing but other routes answer the path
	 * with other methods, the request is a 405 rather than a 404.
	 *
	 * @throws NotFound
	 * @throws MethodNotAllowed
	 */
	private function dispatch(ServerRequestInterface $request, string $path): ResponseInterface
	{
		$method = $request->getMethod();
		$match  = $this->routes->find($method, $path)
			?? ($method === 'HEAD' ? $this->routes->find('GET', $path) : null);

		if ($match !== null && $match->route->priority === RoutePriority::Fallback) {
			try {
				return $this->run($request, $match);
			} catch (NotFound $notFound) {
				$allowed = $this->routes->methodsFor($path, fallbacks: false);

				throw $allowed === [] ? $notFound : new MethodNotAllowed(self::withHead($allowed));
			}
		}

		if ($match !== null) {
			return $this->run($request, $match);
		}

		$allowed = $this->routes->methodsFor($path, fallbacks: false) ?: $this->routes->methodsFor($path);

		if ($allowed === []) {
			throw new NotFound(sprintf('No route matches "%s".', $path));
		}

		$allowed = self::withHead($allowed);

		if ($method === 'OPTIONS') {
			return new Response(Status::NoContent, ['Allow' => implode(', ', [...$allowed, 'OPTIONS'])]);
		}

		throw new MethodNotAllowed($allowed);
	}

	/**
	 * Adds `HEAD` to allowed methods that include `GET`.
	 *
	 * @param  list<string> $allowed
	 * @return list<string>
	 */
	private static function withHead(array $allowed): array
	{
		if (in_array('GET', $allowed, true) && ! in_array('HEAD', $allowed, true)) {
			$allowed[] = 'HEAD';
		}

		return $allowed;
	}

	/**
	 * Runs a matched route through its middleware to its handler.
	 */
	private function run(ServerRequestInterface $request, RouteMatch $match): ResponseInterface
	{
		foreach ($match->params as $name => $value) {
			$request = $request->withAttribute($name, $value);
		}

		$request = $request->withAttribute(RouteMatch::class, $match);

		$this->events->dispatch(new RouteMatched($request, $match));

		$middleware = array_map(
			fn (string $class): MiddlewareInterface => $this->middleware($class),
			$match->route->middleware
		);

		return new Pipeline($middleware, new ControllerHandler($this->container, $match))->handle($request);
	}

	/**
	 * Returns a redirect for a path nothing answered, if there is one.
	 */
	private function redirectFor(ServerRequestInterface $request, string $path): ?ResponseInterface
	{
		$redirect = $this->routes->redirectFor($this->lookupPath($path));

		if ($redirect !== null) {
			return $this->redirect($request, $redirect->to, $redirect->status);
		}

		$public = '/' . basename($this->paths->public) . '/';

		if (dirname($this->paths->public) === $this->paths->root && str_starts_with($path, $public)) {
			return $this->redirect($request, $this->config->canonicalPath(substr($path, strlen($public) - 1)));
		}

		return null;
	}

	/**
	 * Builds a redirect, carrying the query string over unless the target
	 * has its own. Without an explicit status, a safe method gets a 301 and
	 * anything else a 308, which keeps the method and body.
	 */
	private function redirect(ServerRequestInterface $request, string $to, ?Status $status = null): ResponseInterface
	{
		$query = $request->getUri()->getQuery();

		if ($query !== '' && ! str_contains($to, '?')) {
			$to .= "?{$query}";
		}

		$status ??= in_array($request->getMethod(), ['GET', 'HEAD'], true)
			? Status::MovedPermanently
			: Status::PermanentRedirect;

		return Response::redirect($to, $status);
	}

	/**
	 * Returns the request's raw path.
	 */
	private function pathOf(ServerRequestInterface $request): string
	{
		$path = $request->getUri()->getPath();

		return $path === '' ? '/' : $path;
	}

	/**
	 * Returns the form of a path the table is keyed by: without a trailing
	 * slash (routes are written without one).
	 */
	private function lookupPath(string $path): string
	{
		$trimmed = rtrim($path, '/');

		return $trimmed === '' ? '/' : $trimmed;
	}

	/**
	 * Resolves a route middleware.
	 *
	 * @param  class-string $class
	 * @throws InvalidRoute
	 */
	private function middleware(string $class): MiddlewareInterface
	{
		$middleware = $this->container->make($class);

		return $middleware instanceof MiddlewareInterface
			? $middleware
			: throw new InvalidRoute(sprintf('"%s" is not a %s.', $class, MiddlewareInterface::class));
	}
}
