<?php

/**
 * HTML exception renderer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Error;

use Override;
use Throwable;

/**
 * Renders an uncaught exception as a standalone HTML page. With `debug` on, the
 * page shows the exception chain with messages, locations, and traces;
 * otherwise it's a generic error page that reveals nothing. A themed error
 * page (`user/content/_errors/500.md`) replaces the generic one once views
 * exist (M5); this renderer remains the fallback when rendering that fails.
 */
final readonly class HtmlRenderer implements ExceptionRenderer
{
	public function __construct(private bool $debug = false)
	{
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function contentType(): string
	{
		return 'text/html; charset=UTF-8';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function render(Throwable $exception): string
	{
		$body = $this->debug
			? $this->details($exception)
			: '<h1>Something went wrong</h1><p>The server hit an error and could not finish the request.</p>';

		return <<<HTML
			<!DOCTYPE html>
			<html lang="en">
			<head>
			<meta charset="utf-8">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<meta name="robots" content="noindex">
			<title>Error</title>
			<style>
			body { font: 16px/1.5 system-ui, sans-serif; margin: 0 auto; max-width: 70rem; padding: 2rem; color: #1a1a1a; }
			h1 { font-size: 1.5rem; }
			h2 { font-size: 1.125rem; margin-top: 2rem; }
			pre { background: #f4f4f4; overflow-x: auto; padding: 1rem; font-size: 0.875rem; }
			@media (prefers-color-scheme: dark) { body { background: #111; color: #eee; } pre { background: #222; } }
			</style>
			</head>
			<body>
			<main>
			{$body}
			</main>
			</body>
			</html>
			HTML;
	}

	/**
	 * Renders the exception chain in detail.
	 */
	private function details(Throwable $exception): string
	{
		$html  = '';
		$first = true;

		do {
			$html .= sprintf(
				'<%1$s>%2$s</%1$s><p>%3$s</p><p><code>%4$s:%5$d</code></p><pre>%6$s</pre>',
				$first ? 'h1' : 'h2',
				$this->escape(($first ? '' : 'Caused by ') . $exception::class),
				$this->escape($exception->getMessage()),
				$this->escape($exception->getFile()),
				$exception->getLine(),
				$this->escape($exception instanceof FatalError
					? $exception->fatalTraceAsString()
					: $exception->getTraceAsString())
			);

			$first     = false;
			$exception = $exception->getPrevious();
		} while ($exception !== null);

		return $html;
	}

	/**
	 * Escapes text for HTML.
	 */
	private function escape(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
	}
}
