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

namespace Blush\Admin\Http;

use JsonException;
use stdClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Admin\AdminApp;
use Blush\Admin\AdminConfig;
use Blush\Admin\AdminLanguages;
use Blush\Admin\Theme\AdminThemes;
use Blush\Auth\Authenticator;
use Blush\Auth\AuthException;
use Blush\Auth\ColorScheme;
use Blush\Auth\Preferences;
use Blush\Core\AppConfig;
use Blush\Core\Language;
use Blush\Extension\ExtensionText;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Mail\MailTransport;
use Blush\Markdown\Html\HtmlRules;
use Blush\Markdown\MarkdownConfig;
use Blush\Media\MediaConfig;
use Blush\Session\SessionReader;
use Blush\Translation\CurrentLocale;
use Blush\Translation\Messages;
use Blush\Translation\Translator;
use Blush\Workflow\Status as EntryStatus;
use Blush\Workflow\Statuses;

/**
 * Serves the page the admin app runs in, at `{path}` and every screen
 * under it (the app routes in the browser). The page holds the app's
 * script and styles and a JSON block with what the app needs to start:
 * its base path, the API's, the site's name, and the signed-in account's
 * color scheme (D-235) and admin theme (D-827), which are also set on
 * `<html>`, with the theme's stylesheet linked, so the first frame is
 * right (the site's default theme when no one is signed in), the admin
 * themes an account may choose, whether mentions are on (D-493), the HTML rules (D-495),
 * so the editor can mark what its account couldn't add, and the site's
 * date and time formats and language, which the admin shows dates read
 * as dates in (D-693). Everything else comes from the API. The session is only
 * read, never started or kept alive, so the page sets no cookies.
 *
 * The page is in the account's language (D-831), or the site's: it's
 * `<html lang>`, and core's `admin` catalog in it is printed for
 * `blush/messages` to read, with the languages an account may choose.
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
		private MediaConfig $media,
		private MarkdownConfig $markdown,
		private MailTransport $mail,
		private AdminThemes $themes,
		private ExtensionText $text,
		private AdminLanguages $languages,
		private Translator $translator,
		private CurrentLocale $locale,
		private Messages $messages,
		private Statuses $statuses
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

		$preferences = $this->preferences($request);
		$scheme      = $preferences?->colorScheme;
		$choice      = $preferences?->adminTheme;
		$theme       = $this->themes->inUse($choice);
		$skinned     = $theme !== null && $theme->name !== AdminThemes::BASE;
		$language    = $this->languages->for($preferences);

		if ($skinned) {
			$styles .= sprintf("<link rel=\"stylesheet\" id=\"blush-admin-theme\" href=\"%s\">\n", self::escape($this->app->themeUrl($theme)));
		}

		$config = self::json([
			'base'        => $this->config->path,
			'api'         => "{$this->config->path}/api",
			'site'        => ['name' => $this->site->name, 'url' => $this->site->url],
			'media'       => ['url' => $this->media->url],
			'colorScheme' => $scheme?->value,
			'adminTheme'  => $choice,
			'adminThemes' => $this->adminThemes(),
			// The account's language, `null` for the site's, and the ones
			// it may choose (D-831).
			'language'    => $preferences?->language,
			'adminLanguages' => $this->languages->offered(),
			'mentions'    => $this->markdown->mentions,
			// Whether the site can send email (D-846): sign-in offers
			// "Forgot your password?", and password links can be emailed.
			'mail'        => $this->mail->isSetUp(),
			'dates'       => [
				'locale' => str_replace('_', '-', $this->site->locale),
				'date'   => $this->site->dateFormat,
				'time'   => $this->site->timeFormat
			],
			// The languages content is written in (D-455), the default
			// first, for the editor's Language (D-728).
			'languages'   => array_values(array_map(static fn (Language $language): array => ['code' => $language->code, 'label' => $language->name()], $this->site->languages->all())),
			// Entries' statuses, core's own included, each with its label
			// and tone in the account's language (D-850).
			'statuses'    => $this->locale->in($language, fn (): array => array_values(array_map(static fn (EntryStatus $status): array => $status->toArray(), $this->statuses->all()))),
			'html'        => [
				'allowed'           => HtmlRules::ALLOWED,
				'global'            => HtmlRules::GLOBAL,
				'refused'           => HtmlRules::REFUSED,
				'refusedAttributes' => HtmlRules::REFUSED_ATTRIBUTES,
				'urlAttributes'     => HtmlRules::URL_ATTRIBUTES
			]
		]);

		$messages = self::json($this->translator->catalog(Translator::CORE, AdminLanguages::CATALOG, $language, Translator::LAST) ?: new stdClass());
		$lang     = self::escape(str_replace('_', '-', $language));
		$title    = self::escape($this->locale->in($language, fn (): string => (string) $this->messages->t('admin.shell_controller.title', 'Admin · {site}', site: $this->site->name)));
		$script = self::escape($this->app->url($entry['script']));
		$id     = self::CONFIG_ID;
		$html   = ($scheme === null || $scheme === ColorScheme::System ? '' : " data-color-scheme=\"{$scheme->value}\"")
			. ($skinned ? ' data-admin-theme="' . self::escape($theme->name) . '"' : '');

		return Response::html(<<<HTML
			<!doctype html>
			<html lang="{$lang}"{$html}>
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
			<script type="application/json" data-blush-messages="core" data-blush-catalog="admin">{$messages}</script>
			</body>
			</html>

			HTML, Status::Ok, $headers);
	}

	/**
	 * Returns the admin themes an account may choose (Neutral first), each
	 * with its stylesheet's URL (none for Neutral, the admin's own) and
	 * its colors for a swatch, and the site's default (D-827).
	 *
	 * @return array{default: ?string, offered: list<array{name: string, label: string, description: string, stylesheet: ?string, preview: array{background: string, surface: string, accent: string, text: string}}>}
	 */
	private function adminThemes(): array
	{
		$offered = [];

		foreach ($this->themes->offered() as $theme) {
			$offered[] = [
				'name'        => $theme->name,
				'label'       => (string) $this->text->label($theme),
				'description' => (string) $this->text->description($theme),
				'stylesheet'  => $theme->name === AdminThemes::BASE ? null : $this->app->themeUrl($theme),
				'preview'     => $this->themes->preview($theme)
			];
		}

		return ['default' => $this->themes->default()?->name, 'offered' => $offered];
	}

	/**
	 * Returns the signed-in account's preferences (its color scheme and
	 * admin theme), or `null` when no one is signed in.
	 */
	private function preferences(ServerRequestInterface $request): ?Preferences
	{
		$session = $this->sessions->read($request);

		try {
			return $session === null ? null : $this->authenticator->account($session)?->preferences;
		} catch (AuthException) {
			return null;
		}
	}

	/**
	 * Returns a value as JSON safe in a `<script>` block.
	 *
	 * @throws JsonException
	 */
	private static function json(mixed $value): string
	{
		return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
	}

	/**
	 * Escapes text for HTML.
	 */
	private static function escape(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
	}
}
