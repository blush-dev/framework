<?php

/**
 * Controller handler.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

use BackedEnum;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Blush\Container\Container;
use Blush\Http\NotFound;

/**
 * Calls a matched route's handler: the innermost step of the route's
 * middleware pipeline. The handler is resolved through the container, and
 * its parameters are filled by name from the route's defaults and path
 * parameters (cast as the compiler recorded), with the request passed to
 * the parameters typed for it. The container autowires the rest.
 *
 * A path value that can't be cast (such as an out-of-range integer) means
 * the URL doesn't exist, so it's a 404. Integers may have leading zeros
 * (`/archives/2024/05`).
 */
final readonly class ControllerHandler implements RequestHandlerInterface
{
	public function __construct(
		private Container $container,
		private RouteMatch $match
	) {}

	/**
	 * @inheritDoc
	 * @throws InvalidRoute When the handler doesn't return a response.
	 */
	#[Override]
	public function handle(ServerRequestInterface $request): ResponseInterface
	{
		$route = $this->match->route;
		$args  = $route->defaults;

		foreach ($this->match->params as $name => $value) {
			$args[$name] = $this->cast($route->casts[$name] ?? null, $value);
		}

		foreach ($route->requestParams as $name) {
			$args[$name] = $request;
		}

		$response = $this->container->call([$route->controller, $route->action], $args);

		if (! $response instanceof ResponseInterface) {
			throw new InvalidRoute(sprintf(
				'%s must return a %s; %s returned.',
				$route->handlerName(),
				ResponseInterface::class,
				get_debug_type($response)
			));
		}

		return $response;
	}

	/**
	 * Casts a path value to its parameter's type.
	 *
	 * @param  null|'int'|'float'|'bool'|class-string<BackedEnum> $cast
	 * @throws NotFound
	 */
	private function cast(?string $cast, string $value): string|int|float|bool|BackedEnum
	{
		$result = match ($cast) {
			null    => $value,
			'int'   => filter_var(self::withoutLeadingZeros($value), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
			'float' => filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
			'bool'  => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
			default => array_find($cast::cases(), static fn (BackedEnum $case): bool => (string) $case->value === $value)
		};

		return $result ?? throw new NotFound(sprintf('"%s" is not a valid value here.', $value));
	}

	/**
	 * Strips leading zeros from an integer's digits, so `05` (a month in
	 * a date archive URL) casts to 5.
	 */
	private static function withoutLeadingZeros(string $value): string
	{
		return preg_replace('/^([+-]?)0+(?=\d)/', '$1', $value) ?? $value;
	}
}
