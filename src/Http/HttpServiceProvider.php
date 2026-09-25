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
use Psr\Http\Server\RequestHandlerInterface;
use Blush\Container\Container;
use Blush\Core\AppConfig;
use Blush\Core\ServiceProvider;
use Blush\Error\ExceptionRenderer;
use Blush\Error\HtmlRenderer;
use Blush\Http\Middleware\HandleErrors;

/**
 * Wires the HTTP layer: the PSR-17 factories, the kernel, and the emitter.
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
		Emitter::class,
		WelcomeHandler::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		Sapi::class => NativeSapi::class
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
	 * Hands the kernel its handler (the router replaces `WelcomeHandler`
	 * in M3), gives `HandleErrors` an HTML renderer whatever the SAPI, and
	 * registers the configured middleware so their plans are compiled
	 * (D-066).
	 */
	#[Override]
	public function register(): void
	{
		$this->container->whenNeedsType(Kernel::class, RequestHandlerInterface::class, WelcomeHandler::class);

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
