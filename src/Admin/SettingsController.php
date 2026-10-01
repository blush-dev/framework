<?php

/**
 * Admin settings controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Cache\CacheConfig;
use Blush\Content\Type\ContentConfig;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
use Blush\Core\Environment;
use Blush\Feed\FeedConfig;
use Blush\Feed\FeedFormat;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Media\MediaConfig;
use Blush\Preview\PreviewConfig;
use Blush\Publish\PublishConfig;
use Blush\Routing\RouteConfig;
use Blush\Sitemap\SitemapConfig;

/**
 * Answers `GET {path}/api/settings` (D-309), for accounts with
 * `site.settings`: the site-wide settings that exist, to show, in
 * `groups` (`key`, `title`, `hint`, and the `file` it's set in, by
 * convention), each with `items`: a `key`, `label`, the `value` as text,
 * its `kind` (`text`, `mono`, `bool`, or `list`; a `bool`'s value is
 * `true` or `false`, a `list`'s a list), whether it's still the
 * `default` (`null` for one that follows from others, such as the
 * environment), and optional `help`. A `warning` marks a value that's risky
 * where it is (detailed errors on a live site).
 *
 * Settings live in `config/` and `.env` (D-039), so the screen only shows
 * them. Secrets are never sent: only whether one is set.
 */
final readonly class SettingsController
{
	public function __construct(
		private AppConfig $app,
		private ContentConfig $content,
		private ContentTypes $types,
		private RouteConfig $routes,
		private MediaConfig $media,
		private FeedConfig $feeds,
		private SitemapConfig $sitemap,
		private CacheConfig $cache,
		private PublishConfig $publish,
		private PreviewConfig $preview,
		private ClockInterface $clock,
		private Permissions $permissions
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, Capability::SiteSettings)) {
			return Response::json(['error' => 'You aren\'t allowed to see the site\'s settings.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		return Response::json(['groups' => $this->groups()], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * The settings, in groups.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function groups(): array
	{
		$app         = new AppConfig();
		$environment = $this->app->environment;
		$home        = $this->content->home === null ? null : $this->types->find($this->content->home);
		$now         = DateTimeImmutable::createFromInterface($this->clock->now())->setTimezone($this->app->timezone());

		return [
			self::group('general', 'General', 'Names and addresses', 'config/app.php', [
				self::item('name', 'Site name', $this->app->name, $this->app->name === $app->name),
				self::item('url', 'Site address', $this->app->url, $this->app->url === $app->url, 'mono'),
				self::item('locale', 'Language and region', $this->app->locale, $this->app->locale === $app->locale, 'mono'),
				self::item('environment', 'Environment', ucfirst($environment->value), $environment === $app->environment, help: match ($environment) {
					Environment::Development => 'Content changes show up on the next request, caching is off, and search engines are asked not to index the site.',
					Environment::Staging     => 'Search engines are asked not to index the site.',
					Environment::Production  => null
				}),
				[
					...self::item('debug', 'Detailed error pages', $this->app->debug, $this->app->debug === $app->debug, 'bool'),
					'warning' => $this->app->debug && $environment === Environment::Production ? 'Never on a live site: error pages show the site\'s code and settings.' : null
				]
			], 'From `.env` (`APP_*`) when `config/app.php` reads them, as it does by default.'),
			self::group('dates', 'Dates and Time', 'How times are read and shown', 'config/app.php', [
				self::item('timezone', 'Time zone', $this->app->timezone, $this->app->timezone === $app->timezone, 'mono', sprintf('It\'s %s there now.', $now->format('D, j M Y, H:i'))),
			], 'From `APP_TIMEZONE` in `.env` by default.'),
			self::group('content', 'Content', 'What the site opens with', 'config/content.php', [
				self::item('home', 'Home page', $home === null ? 'The page at user/content/index.md' : sprintf('The latest %s', mb_strtolower($home->labels->plural)), $this->content->home === null),
				self::item('dataTypes', 'Content types from user/data/types', $this->content->dataTypes, $this->content->dataTypes, 'bool'),
				self::item('disabled', 'Built-in types turned off', $this->content->disabled, $this->content->disabled === [], 'list')
			]),
			self::group('addresses', 'Addresses', 'How URLs are written', 'config/routes.php', [
				self::item('trailingSlash', 'Trailing slash', $this->routes->trailingSlash, ! $this->routes->trailingSlash, 'bool', $this->routes->trailingSlash ? 'Addresses end in a slash (/about/); the other form redirects.' : 'Addresses don\'t end in a slash (/about); the other form redirects.'),
				self::item('media', 'Media address', $this->media->url, $this->media->url === new MediaConfig()->url, 'mono', 'Where user/media is served, set in config/media.php.')
			]),
			self::group('feeds', 'Feeds', 'For types with a feed', 'config/feed.php', [
				self::item('formats', 'Formats', array_map(static fn (FeedFormat $format): string => $format->label(), $this->feeds->formats), $this->feeds->formats === new FeedConfig()->formats, 'list'),
				self::item('content', 'Full content', $this->feeds->content, $this->feeds->content, 'bool', $this->feeds->content ? null : 'Each entry\'s summary only.'),
				self::item('limit', 'Entries per feed', (string) $this->feeds->limit, $this->feeds->limit === new FeedConfig()->limit)
			]),
			self::group('search', 'Search Engines', 'The sitemap and robots.txt', 'config/sitemap.php', [
				self::item('sitemap', 'Sitemap and robots.txt', $this->sitemap->enabled, $this->sitemap->enabled, 'bool'),
				self::item('indexing', 'Asks not to be indexed', $environment !== Environment::Production, null, 'bool', 'Outside production, robots.txt asks search engines to skip the whole site.'),
				self::item('disallow', 'Paths robots.txt asks to skip', $this->sitemap->disallow, $this->sitemap->disallow === [], 'list')
			]),
			self::group('caching', 'Caching', 'How pages are kept', 'config/cache.php', [
				self::item('enabled', 'Caching', $this->cache->isEnabled($environment), $this->cache->enabled === null, 'bool', $this->cache->enabled === null ? 'Off in development, on elsewhere.' : null),
				self::item('driver', 'Where it\'s kept', $this->cache->driver, $this->cache->driver === new CacheConfig()->driver, 'mono'),
				self::item('pages', 'Whole pages', $this->cache->pages, $this->cache->pages, 'bool'),
				self::item('maxAge', 'Browsers keep a page', $this->cache->maxAge === 0 ? 'Not at all; they ask each time' : sprintf('%d seconds', $this->cache->maxAge), $this->cache->maxAge === 0)
			]),
			self::group('publishing', 'Publishing and Previews', 'Secrets are never shown', 'config/publish.php', [
				self::item('webhook', 'Publish webhook', $this->publish->secret !== null, $this->publish->secret === null, 'bool', $this->publish->secret === null ? 'On with a PUBLISH_SECRET in .env.' : null),
				self::item('git', 'Pull with git when publishing', $this->publish->git, ! $this->publish->git, 'bool'),
				self::item('preview', 'Preview links', $this->preview->secret !== null, $this->preview->secret === null, 'bool', $this->preview->secret === null ? 'On with an APP_SECRET in .env (config/preview.php).' : null)
			], 'From `.env` (`PUBLISH_*`, `APP_SECRET`) when these files don\'t exist.')
		];
	}

	/**
	 * A group of settings.
	 *
	 * @param  list<array<string, mixed>> $items
	 * @return array<string, mixed>
	 */
	private static function group(string $key, string $title, string $hint, string $file, array $items, ?string $note = null): array
	{
		return ['key' => $key, 'title' => $title, 'hint' => $hint, 'file' => $file, 'note' => $note, 'items' => $items];
	}

	/**
	 * One setting.
	 *
	 * @param  string|bool|list<string> $value
	 * @param  ?bool                    $default `null` for a value that follows from others.
	 * @return array<string, mixed>
	 */
	private static function item(string $key, string $label, string|bool|array $value, ?bool $default, string $kind = 'text', ?string $help = null): array
	{
		return ['key' => $key, 'label' => $label, 'value' => $value, 'kind' => $kind, 'default' => $default, 'help' => $help, 'warning' => null];
	}
}
