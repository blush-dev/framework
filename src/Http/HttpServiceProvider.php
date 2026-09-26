<?php

/**
 * HTTP service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use Override;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Blush\Container\Container;
use Blush\Core\AppConfig;
use Blush\Core\ServiceProvider;
use Blush\Error\ExceptionRenderer;
use Blush\Error\HtmlRenderer;
use Blush\Http\Middleware\ConditionalGet;
use Blush\Http\Middleware\HandleErrors;

/**
 * Wires the HTTP layer: the PSR-17 factories, the kernel, the emitter,
 * and conditional GETs.
 * Nothing is built until a request is handled.
 */
final class HttpServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		HttpFactory::class,
		Kernel::class,
		Emitter::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		Sapi::class => NativeSapi::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		ConditionalGet::class
	];

	/**
	 * `ConditionalGet` runs first among the kernel's middleware, outside
	 * the page cache.
	 */
	protected const array TAGS = [
		Kernel::MIDDLEWARE => [ConditionalGet::class]
	];

	/**
	 * The PSR-17 factories all resolve to `HttpFactory`.
	 */
	protected const array ALIASES = [
		RequestFactoryInterface::class      => HttpFactory::class,
		ResponseFactoryInterface::class     => HttpFactory::class,
		ServerRequestFactoryInterface::class => HttpFactory::class,
		StreamFactoryInterface::class       => HttpFactory::class,
		UploadedFileFactoryInterface::class => HttpFactory::class,
		UriFactoryInterface::class          => HttpFactory::class
	];

	/**
	 * Gives `HandleErrors` an HTML renderer whatever the SAPI, and
	 * registers the configured middleware so their plans are compiled
	 * (D-066). The kernel's handler (the router) is bound by
	 * `RoutingServiceProvider`.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->whenNeedsType(
			HandleErrors::class,
			ExceptionRenderer::class,
			static fn (Container $container): ExceptionRenderer => new HtmlRenderer(
				$container->make(AppConfig::class)->debug
			)
		);

		$this->container->transientIf(HandleErrors::class);

		foreach ($this->container->make(HttpConfig::class)->middleware as $middleware) {
			$this->container->transientIf($middleware);
		}
	}
}
