<?php

/**
 * Welcome handler.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Blush\Core\AppConfig;
use Blush\Core\Framework;

/**
 * The kernel's handler until the router arrives (M3): answers every request
 * with a small "hello" page naming the site. It proves the whole path from
 * the front controller through the kernel to the emitter.
 */
final readonly class WelcomeHandler implements RequestHandlerInterface
{
	public function __construct(private AppConfig $config)
	{
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function handle(ServerRequestInterface $request): ResponseInterface
	{
		$name      = htmlspecialchars($this->config->name, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
		$framework = htmlspecialchars(Framework::NAME . ' ' . Framework::VERSION, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

		return Response::html(<<<HTML
			<!DOCTYPE html>
			<html lang="en">
			<head>
			<meta charset="utf-8">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title>{$name}</title>
			<style>
			body { font: 18px/1.5 system-ui, sans-serif; margin: 0 auto; max-width: 40rem; padding: 4rem 2rem; color: #1a1a1a; }
			@media (prefers-color-scheme: dark) { body { background: #111; color: #eee; } }
			</style>
			</head>
			<body>
			<main>
			<h1>Hello from {$name}</h1>
			<p>{$framework} is running.</p>
			</main>
			</body>
			</html>
			HTML);
	}
}
