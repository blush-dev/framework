<?php

/**
 * HTTP config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use Override;
use Psr\Http\Server\MiddlewareInterface;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;

/**
 * HTTP settings, from `config/http.php`.
 */
final readonly class HttpConfig implements Config
{
	/**
	 * @param list<class-string<MiddlewareInterface>> $middleware Global middleware, outermost first.
	 *        The kernel's error handling always runs outside these.
	 * @throws InvalidConfig
	 */
	public function __construct(public array $middleware = [])
	{
		foreach ($middleware as $class) {
			if (! is_subclass_of($class, MiddlewareInterface::class)) {
				throw new InvalidConfig(sprintf(
					'HttpConfig "middleware" must list %s classes; "%s" is not one.',
					MiddlewareInterface::class,
					$class
				));
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['middleware']);

		/** @var list<class-string<MiddlewareInterface>> $middleware Validated by the constructor. */
		$middleware = $values->stringList('middleware');

		return new static(middleware: $middleware);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return ['middleware' => $this->middleware];
	}
}
