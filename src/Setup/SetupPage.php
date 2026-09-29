<?php

/**
 * Setup page.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Setup;

use Blush\Core\Framework;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * The plain page a browser gets in place of the site while a setup problem
 * stops it from running (1.x's `Message`): what's wrong and what to do,
 * never a stack trace. It's self-contained (no theme, no application),
 * since the problem may be what the rest would need. Paths are shown
 * relative to the project root.
 */
final class SetupPage
{
	/**
	 * Builds the response for some failed checks: a 503, never cached.
	 *
	 * @param list<CheckResult> $failures
	 */
	public static function response(array $failures): Response
	{
		return Response::html(self::html($failures), Status::ServiceUnavailable, [
			'Cache-Control' => 'no-store',
			'Retry-After'   => '60'
		]);
	}

	/**
	 * Renders the page.
	 *
	 * @param list<CheckResult> $failures
	 */
	public static function html(array $failures): string
	{
		$items = '';

		foreach ($failures as $failure) {
			$items .= sprintf(
				"<li><code>%s</code> %s%s</li>\n",
				self::escape($failure->label),
				self::escape($failure->message),
				$failure->hint === '' ? '' : ' <span class="hint">' . self::escape($failure->hint) . '</span>'
			);
		}

		$name = self::escape(Framework::NAME);

		return <<<HTML
			<!doctype html>
			<html lang="en">
			<head>
			<meta charset="utf-8">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<meta name="robots" content="noindex">
			<title>This site needs setting up</title>
			<style>
			body { max-width: 40rem; margin: 3rem auto; padding: 0 1rem; font: 1rem/1.6 system-ui, sans-serif; color: #222; background: #fff; }
			code { font-size: 0.9em; background: #f2f2f2; padding: 0.1em 0.3em; border-radius: 3px; }
			.hint { display: block; color: #555; }
			li + li { margin-top: 0.75rem; }
			@media (prefers-color-scheme: dark) {
				body { color: #eee; background: #181818; }
				code { background: #2a2a2a; }
				.hint { color: #bbb; }
			}
			</style>
			</head>
			<body>
			<h1>This site needs setting up</h1>
			<p>{$name} can't run until these are fixed:</p>
			<ul>
			{$items}</ul>
			<p>Then reload this page. <code>doctor</code> on the command line checks everything at once.</p>
			</body>
			</html>

			HTML;
	}

	/**
	 * Escapes text for HTML.
	 */
	private static function escape(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
	}
}
