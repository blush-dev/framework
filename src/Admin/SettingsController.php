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
use Blush\Field\FieldSets;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Media\MediaConfig;
use Blush\Preview\PreviewConfig;
use Blush\Publish\PublishConfig;
use Blush\Routing\RouteConfig;
use Blush\Settings\Setting;
use Blush\Settings\Settings;
use Blush\Settings\SettingsFile;
use Blush\Settings\SettingsScreen;
use Blush\Settings\SettingsTarget;
use Blush\Sitemap\SitemapConfig;

/**
 * Answers `GET {path}/api/settings/{screen}` (D-309, D-324, D-325), for
 * accounts with `site.settings`: one Settings screen (`general`,
 * `reading`, `search`, or `system`) as `groups` of settings (`key`,
 * `title`, `hint`, and a `note`, where backticks mark code), each with
 * `items`: a `key`, `label`, the `value` to show, its `kind` (`text`,
 * `mono`, `bool`, or `list`; a `bool`'s value is `true` or `false`, a
 * `list`'s a list), whether it's still the `default` (`null` for one that
 * follows from others, such as the environment), the `file` it's set in
 * by convention (`null` when it follows from others), and optional
 * `help`. A `warning` marks a value that's risky where it is (detailed
 * errors on a live site).
 *
 * A setting the admin can change (`Setting`) adds the `setting` it saves
 * as (`feed.limit`), its `field` (D-343: as forms take it, with the
 * `control` to draw, `choices` naming its options, and a `caption` for a
 * checkbox or an empty choice), the `input` the form starts from, and
 * whether it's `saved` in `user/data/settings.json`; its `file` is where
 * its value comes from when it isn't. After a screen's own groups, each
 * field set on it (`settings:{screen}`) adds a group of its settings,
 * saved as `site.{name}`, with no `file` behind them. The rest live in `config/` and `.env`
 * (D-039) and are only shown, beside the ones they relate to. Secrets are
 * never sent: only whether one is set.
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
		private SettingsFile $file,
		private ClockInterface $clock,
		private Permissions $permissions,
		private FieldSets $fieldSets
	) {}

	public function __invoke(ServerRequestInterface $request, string $screen): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, Capability::SiteSettings)) {
			return Response::json(['error' => 'You aren\'t allowed to see the site\'s settings.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		$saved = $this->file->read();

		$groups = match ($screen) {
			'general' => $this->general($saved),
			'reading' => $this->reading($saved),
			'search'  => $this->search($saved),
			'system'  => $this->system(),
			default   => null
		};

		$target = SettingsScreen::tryFrom($screen);

		if ($groups !== null && $target !== null) {
			$groups = [...$groups, ...$this->sets($target, $saved)];
		}

		return $groups === null
			? Response::json(['error' => 'There\'s no such settings screen.'], Status::NotFound, ['Cache-Control' => 'no-store'])
			: Response::json(['groups' => $groups], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * The groups the field sets on a screen add (D-343): one per set,
	 * headed by its label, each of its fields a setting saved in
	 * `user/data/settings.json`'s `site` section (`site.{name}`), with no
	 * config value behind it.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function sets(SettingsScreen $screen, Settings $saved): array
	{
		$values = $saved->site();
		$groups = [];

		foreach ($this->fieldSets->for(SettingsTarget::keyFor($screen)) as $set) {
			$items = [];

			foreach ($set->schema->fields as $name => $field) {
				$has     = array_key_exists($name, $values);
				$items[] = [
					...self::item("site-{$name}", $field->label === '' ? ucfirst(str_replace('_', ' ', $name)) : $field->label, '', ! $has, help: $field->description === '' ? null : $field->description),
					'setting' => Settings::SITE . ".{$name}",
					'field'   => $field->toForm(),
					'input'   => $has ? $values[$name] : $field->default,
					'saved'   => $has
				];
			}

			$groups[] = self::group("set-{$set->name}", $set->label, $set->description === '' ? sprintf('From the %s field set', $set->label) : $set->description, $items);
		}

		return $groups;
	}

	/**
	 * General: the site's name, language, and time, and where it runs.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function general(Settings $saved): array
	{
		$app         = new AppConfig();
		$environment = $this->app->environment;
		$now         = DateTimeImmutable::createFromInterface($this->clock->now())->setTimezone($this->app->timezone());

		return [
			self::group('site', 'Site', 'Its name and language', [
				$this->edit(self::item('name', 'Site name', $this->app->name, $this->app->name === $app->name), $saved, Setting::Name, $this->app->name),
				$this->edit(self::item('locale', 'Language and region', $this->app->locale, $this->app->locale === $app->locale, 'mono', 'A language code, with a region if you like, such as en_US or fr.'), $saved, Setting::Locale, $this->app->locale),
				self::item('url', 'Site address', $this->app->url, $this->app->url === $app->url, 'mono', 'From APP_URL in .env by default.', 'config/app.php')
			]),
			self::group('dates', 'Dates and Time', 'How times are read and shown', [
				$this->edit(self::item('timezone', 'Time zone', $this->app->timezone, $this->app->timezone === $app->timezone, 'mono', sprintf('It\'s %s there now.', $now->format('D, j M Y, H:i'))), $saved, Setting::Timezone, $this->app->timezone)
			]),
			self::group('environment', 'Environment', 'Set where the site runs', [
				self::item('environment', 'Environment', ucfirst($environment->value), $environment === $app->environment, help: match ($environment) {
					Environment::Development => 'Content changes show up on the next request, caching is off, and search engines are asked not to index the site.',
					Environment::Staging     => 'Search engines are asked not to index the site.',
					Environment::Production  => null
				}, file: 'config/app.php'),
				[
					...self::item('debug', 'Detailed error pages', $this->app->debug, $this->app->debug === $app->debug, 'bool', file: 'config/app.php'),
					'warning' => $this->app->debug && $environment === Environment::Production ? 'Never on a live site: error pages show the site\'s code and settings.' : null
				]
			], 'From `.env` (`APP_ENV`, `APP_DEBUG`) when `config/app.php` reads them, as it does by default.')
		];
	}

	/**
	 * Reading: what the home page shows, and the feeds.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function reading(Settings $saved): array
	{
		$feeds = new FeedConfig();

		return [
			self::group('home', 'Home Page', 'What the site opens with', [
				Setting::homeChoices($this->types) === []
					? self::item('home', 'Home page', $this->homeLabel(), $this->content->home === null, help: 'The site has no collection with addresses to show instead.', file: Setting::Home->file())
					: $this->edit(self::item('home', 'Home page', $this->homeLabel(), $this->content->home === null), $saved, Setting::Home, $this->content->home)
			]),
			self::group('feeds', 'Feeds', 'For types with a feed', [
				$this->edit(self::item('formats', 'Formats', array_map(static fn (FeedFormat $format): string => $format->label(), $this->feeds->formats), $this->feeds->formats === $feeds->formats, 'list', 'None turns every feed off.'), $saved, Setting::FeedFormats, array_map(static fn (FeedFormat $format): string => $format->value, $this->feeds->formats)),
				$this->edit(self::item('content', 'Full content', $this->feeds->content, $this->feeds->content, 'bool', 'Off, a feed carries each entry\'s summary only.'), $saved, Setting::FeedContent, $this->feeds->content),
				$this->edit(self::item('limit', 'Entries per feed', (string) $this->feeds->limit, $this->feeds->limit === $feeds->limit, help: sprintf('From 1 to %d.', Setting::FEED_LIMIT_MAX)), $saved, Setting::FeedLimit, $this->feeds->limit)
			])
		];
	}

	/**
	 * Addresses and Search: how URLs are written, and what search engines
	 * are told.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function search(Settings $saved): array
	{
		$environment = $this->app->environment;

		return [
			self::group('addresses', 'Addresses', 'How URLs are written', [
				$this->edit(self::item('trailingSlash', 'Trailing slash', $this->routes->trailingSlash, ! $this->routes->trailingSlash, 'bool', 'The other form redirects, so links to either still work.'), $saved, Setting::TrailingSlash, $this->routes->trailingSlash),
				self::item('media', 'Media address', $this->media->url, $this->media->url === new MediaConfig()->url, 'mono', 'Where user/media is served.', 'config/media.php')
			]),
			self::group('search', 'Search Engines', 'The sitemap and robots.txt', [
				$this->edit(self::item('sitemap', 'Sitemap and robots.txt', $this->sitemap->enabled, $this->sitemap->enabled, 'bool', 'Off, the site has neither, and search engines find pages by their links.'), $saved, Setting::Sitemap, $this->sitemap->enabled),
				$this->edit(self::item('disallow', 'Paths robots.txt asks to skip', $this->sitemap->disallow, $this->sitemap->disallow === [], 'list', 'One path a line, each starting with /, such as /drafts/.'), $saved, Setting::SitemapDisallow, $this->sitemap->disallow),
				self::item('indexing', 'Asks not to be indexed', $environment !== Environment::Production, null, 'bool', 'Outside production, robots.txt asks search engines to skip the whole site. It follows the environment.')
			])
		];
	}

	/**
	 * System: how the site is put together and run, all set in code.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function system(): array
	{
		$environment = $this->app->environment;

		return [
			self::group('types', 'Content Types', 'Where types come from', [
				self::item('dataTypes', 'Content types from user/data/types', $this->content->dataTypes, $this->content->dataTypes, 'bool', file: 'config/content.php'),
				self::item('disabled', 'Built-in types turned off', $this->content->disabled, $this->content->disabled === [], 'list', file: 'config/content.php')
			]),
			self::group('caching', 'Caching', 'How pages are kept', [
				self::item('enabled', 'Caching', $this->cache->isEnabled($environment), $this->cache->enabled === null, 'bool', $this->cache->enabled === null ? 'Off in development, on elsewhere.' : null, 'config/cache.php'),
				self::item('driver', 'Where it\'s kept', $this->cache->driver, $this->cache->driver === new CacheConfig()->driver, 'mono', file: 'config/cache.php'),
				self::item('pages', 'Whole pages', $this->cache->pages, $this->cache->pages, 'bool', file: 'config/cache.php'),
				self::item('maxAge', 'Browsers keep a page', $this->cache->maxAge === 0 ? 'Not at all; they ask each time' : sprintf('%d seconds', $this->cache->maxAge), $this->cache->maxAge === 0, file: 'config/cache.php')
			]),
			self::group('publishing', 'Publishing and Previews', 'Secrets are never shown', [
				self::item('webhook', 'Publish webhook', $this->publish->secret !== null, $this->publish->secret === null, 'bool', $this->publish->secret === null ? 'On with a PUBLISH_SECRET in .env.' : null, 'config/publish.php'),
				self::item('git', 'Pull with git when publishing', $this->publish->git, ! $this->publish->git, 'bool', file: 'config/publish.php'),
				self::item('preview', 'Preview links', $this->preview->secret !== null, $this->preview->secret === null, 'bool', $this->preview->secret === null ? 'On with an APP_SECRET in .env.' : null, 'config/preview.php')
			], 'From `.env` (`PUBLISH_*`, `APP_SECRET`) when these files don\'t exist.')
		];
	}

	/**
	 * What the home page shows, in words.
	 */
	private function homeLabel(): string
	{
		$home = $this->content->home === null ? null : $this->types->find($this->content->home);

		return $home === null ? 'The page at user/content/index.md' : sprintf('The latest %s', mb_strtolower($home->labels->plural));
	}

	/**
	 * A group of settings.
	 *
	 * @param  list<array<string, mixed>> $items
	 * @return array<string, mixed>
	 */
	private static function group(string $key, string $title, string $hint, array $items, ?string $note = null): array
	{
		return ['key' => $key, 'title' => $title, 'hint' => $hint, 'note' => $note, 'items' => $items];
	}

	/**
	 * Makes a setting editable: what it saves as, its field (D-343, with
	 * words for its options and its `caption`), the value the form starts
	 * from, and whether it's saved.
	 *
	 * @param  array<string, mixed> $item
	 * @return array<string, mixed>
	 */
	private function edit(array $item, Settings $saved, Setting $setting, mixed $input): array
	{
		$field   = $setting->field($this->types)->toForm();
		$choices = $setting->choices($this->types);

		if ($choices !== []) {
			if (is_array($field['item'] ?? null)) {
				$field['item']['choices'] = $choices;
			} else {
				$field['choices'] = $choices;
			}
		}

		if ($setting->caption() !== null) {
			$field['caption'] = $setting->caption();
		}

		return [...$item, 'setting' => $setting->value, 'field' => $field, 'input' => $input, 'saved' => $saved->has($setting), 'file' => $setting->file()];
	}

	/**
	 * One setting.
	 *
	 * @param  string|bool|list<string> $value
	 * @param  ?bool                    $default `null` for a value that follows from others.
	 * @return array<string, mixed>
	 */
	private static function item(string $key, string $label, string|bool|array $value, ?bool $default, string $kind = 'text', ?string $help = null, ?string $file = null): array
	{
		return ['key' => $key, 'label' => $label, 'value' => $value, 'kind' => $kind, 'default' => $default, 'help' => $help, 'warning' => null, 'file' => $file];
	}
}
