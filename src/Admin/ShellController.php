<?php

/**
 * Admin shell controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\AuthException;
use Blush\Auth\Authenticator;
use Blush\Auth\ColorScheme;
use Blush\Core\AppConfig;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Media\MediaConfig;
use Blush\Session\SessionReader;

/**
 * Serves the page the admin app runs in, at `{path}` and every screen
 * under it (the app routes in the browser). The page holds the app's
 * script and styles and a JSON block with what the app needs to start:
 * its base path, the API's, the site's name, and the signed-in account's
 * color scheme (D-235), which is also set on `<html>` so the first frame
 * is right. Everything else comes from the API. The session is only
 * read, never started or kept alive, so the page sets no cookies.
 *
 * The page is never cached or framed, and a strict Content Security
 * Policy allows only the site's own scripts and styles.
 */
final readonly class ShellController
{
	/**
	 * The id of the start-up JSON block.
	 */
	public const string CONFIG_ID = 'blush-admin-config';

	public function __construct(
		private AdminApp $app,
		private AdminConfig $config,
		private AppConfig $site,
		private SessionReader $sessions,
		private Authenticator $authenticator,
		private MediaConfig $media
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$headers = [
			'Cache-Control'           => 'no-store',
			'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'",
			'X-Frame-Options'         => 'DENY',
			'X-Content-Type-Options'  => 'nosniff',
			'Referrer-Policy'         => 'same-origin',
			'X-Robots-Tag'            => 'noindex, nofollow'
		];

		$entry = $this->app->entry();

		if ($entry === null) {
			return Response::text('The admin app isn\'t built. Build it, or set AdminConfig "app" to a built one.', Status::ServiceUnavailable, $headers);
		}

		$styles = '';

		foreach ($entry['styles'] as $style) {
			$styles .= sprintf("<link rel=\"stylesheet\" href=\"%s\">\n", self::escape($this->app->url($style)));
		}

		$scheme = $this->colorScheme($request);
		$config = json_encode([
			'base'        => $this->config->path,
			'api'         => "{$this->config->path}/api",
			'site'        => ['name' => $this->site->name, 'url' => $this->site->url],
			'media'       => ['url' => $this->media->url],
			'colorScheme' => $scheme?->value
		], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

		$title  = self::escape("Admin · {$this->site->name}");
		$script = self::escape($this->app->url($entry['script']));
		$id     = self::CONFIG_ID;
		$html   = $scheme === null || $scheme === ColorScheme::System ? '' : " data-color-scheme=\"{$scheme->value}\"";

		return Response::html(<<<HTML
			<!doctype html>
			<html lang="en"{$html}>
			<head>
			<meta charset="utf-8">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<meta name="robots" content="noindex, nofollow">
			<title>{$title}</title>
			{$styles}<script type="module" src="{$script}"></script>
			</head>
			<body>
			<div id="app"></div>
			<script type="application/json" id="{$id}">{$config}</script>
			</body>
			</html>

			HTML, Status::Ok, $headers);
	}

	/**
	 * Escapes text for HTML.
	 */
	/**
	 * Returns the signed-in account's color scheme, or `null` when no one
	 * is signed in.
	 */
	private function colorScheme(ServerRequestInterface $request): ?ColorScheme
	{
		$session = $this->sessions->read($request);

		try {
			return $session === null ? null : $this->authenticator->account($session)?->preferences->colorScheme;
		} catch (AuthException) {
			return null;
		}
	}

	private static function escape(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
	}
}
