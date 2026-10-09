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
use Blush\Auth\AuthConfig;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Auth\Role;
use Blush\Auth\Roles;
use Blush\Cache\CacheConfig;
use Blush\Cache\PageCache;
use Blush\Clock\DateFormat;
use Blush\Clock\TimeZones;
use Blush\Content\ContentConfig;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
use Blush\Core\Environment;
use Blush\Embed\EmbedConfig;
use Blush\Embed\EmbedProvider;
use Blush\Embed\EmbedProviders;
use Blush\Feed\FeedConfig;
use Blush\Feed\FeedFormat;
use Blush\Field\Field;
use Blush\Field\FieldSets;
use Blush\Field\Fields\EnumField;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Llms\LlmsConfig;
use Blush\Llms\LlmsRoutes;
use Blush\Llms\LlmsTxt;
use Blush\Markdown\MarkdownConfig;
use Blush\Media\Index\MediaLibrary;
use Blush\Media\Index\MediaQuery;
use Blush\Media\MediaConfig;
use Blush\Media\MediaException;
use Blush\Media\MediaKind;
use Blush\Media\MediaResolver;
use Blush\Media\MediaUploads;
use Blush\Preview\PreviewConfig;
use Blush\Publish\PublishConfig;
use Blush\Routing\RouteConfig;
use Blush\Settings\Setting;
use Blush\Settings\Settings;
use Blush\Settings\SettingsStore;
use Blush\Settings\SettingsScreen;
use Blush\Settings\SettingsTarget;
use Blush\Sitemap\AiCrawlerGroup;
use Blush\Sitemap\SitemapConfig;
use Blush\Translation\Locales;

/**
 * Answers `GET {path}/api/settings/{screen}` (D-309, D-324, D-325), for
 * accounts with `site.settings`: one Settings screen (`general`,
 * `reading`, `writing` (D-494), `media` (D-406), `search`, `ai` (D-398), or `system`) as `groups` of settings (`key`,
 * `title`, `hint`, and a `note`, where backticks mark code), each with
 * `items`: a `key`, `label`, the `value` to show, its `kind` (`text`,
 * `mono`, `bool`, or `list`; a `bool`'s value is `true` or `false`, a
 * `list`'s a list), whether it's still the `default` (`null` for one that
 * follows from others, such as the environment), the `file` it's set in
 * by convention (`null` when it follows from others), and optional
 * `help`. A `warning` marks a value that's risky where it is (detailed
 * errors on a live site). A shown setting may have a `link` (`label`
 * and `href`, a page on the site) or `links` (`label` and `to`, admin
 * paths), such as `llms.txt` and the types it lists. An editable one
 * may name the setting it `requires` (`setting`, and a `note` saying
 * so): while that's off in the form, it's locked.
 *
 * A setting the admin can change (`Setting`) adds the `setting` it saves
 * as (`feed.limit`), its `field` (D-343: as forms take it, with the
 * `control` to draw, `choices` naming its options, and a `caption` for a
 * checkbox or an empty choice), the `input` the form starts from, and
 * whether it's `saved` in the saved settings (`user/data/settings/`); its `file` is where
 * its value comes from when it isn't. After a screen's own groups, each
 * field set on it (`settings:{screen}`) adds a group of its settings,
 * saved as `site.{name}`, with no `file` behind them. Media's upload
 * rules are one setting drawn as a grid (`kind` `uploads`, D-406; see
 * `media()`). The language (`app.locale`) adds `locales`, the menu it's
 * picked from (`Locales::options()`, D-441), and may be any code typed
 * instead. The time zone adds `menu`, the options its searchable menu
 * is drawn from (`TimeZones::options()`, D-444). The date and time
 * formats add `formats`, their menus (`DateFormat::options()`, D-445),
 * and may be any pattern typed instead; each links to ICU's pattern
 * letters (D-446). The role for new accounts (`auth.signupRole`,
 * D-518) is chosen from the site's roles, but for those signing up can
 * never give. The rest live in `config/` and `.env`
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
		private MarkdownConfig $markdown,
		private FeedConfig $feeds,
		private SitemapConfig $sitemap,
		private LlmsConfig $llms,
		private LlmsTxt $llmsTxt,
		private CacheConfig $cache,
		private PublishConfig $publish,
		private PreviewConfig $preview,
		private SettingsStore $store,
		private ClockInterface $clock,
		private Permissions $permissions,
		private FieldSets $fieldSets,
		private MediaLibrary $library,
		private AuthConfig $auth,
		private Roles $roles,
		private EmbedConfig $embed,
		private EmbedProviders $embeds
	) {}

	public function __invoke(ServerRequestInterface $request, string $screen): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, Capability::SiteSettings)) {
			return Response::json(['error' => 'You aren\'t allowed to see the site\'s settings.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		$saved = $this->store->read();

		$groups = match ($screen) {
			'general' => $this->general($saved),
			'reading' => $this->reading($saved),
			'writing' => $this->writing($saved),
			'media'   => $this->media($saved),
			'search'  => $this->search($saved),
			'ai'      => $this->ai($saved),
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
	 * Answers `GET settings/date-format` (D-445): how a date or time
	 * format shows now, for the admin's preview of one typed in. Takes
	 * `format`, `kind` (`date` or `time`), and the `locale` the form has
	 * (the site's when it isn't a language code). Answers `{"text"}`, or
	 * a `422` saying what's wrong with the format.
	 */
	public function format(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, Capability::SiteSettings)) {
			return Response::json(['error' => 'You aren\'t allowed to see the site\'s settings.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		$query   = $request->getQueryParams();
		$format  = is_string($query['format'] ?? null) ? trim($query['format']) : '';
		$locale  = is_string($query['locale'] ?? null) && preg_match('/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/', $query['locale']) === 1 ? $query['locale'] : $this->app->locale;
		$problem = DateFormat::problem($format);

		if ($problem !== null) {
			return Response::json(['error' => $problem], Status::UnprocessableContent, ['Cache-Control' => 'no-store']);
		}

		$now  = $this->clock->now();
		$text = ($query['kind'] ?? 'date') === 'time'
			? DateFormat::format($now, $locale, $this->app->timezone, null, $format)
			: DateFormat::format($now, $locale, $this->app->timezone, $format);

		return Response::json(['text' => $text], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * The groups the field sets on a screen add (D-343): one per set,
	 * headed by its label, each of its fields a setting saved in
	 * the `site` group of saved settings (`user/data/settings/site.json`) (`site.{name}`), with no
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
	 * General: the site's name, language, and time, who can make an
	 * account, and where it runs.
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
				$this->edit(self::item('description', 'Description', $this->app->description, $this->app->description === '', help: Setting::DESCRIPTION_HELP), $saved, Setting::Description, $this->app->description),
				[...$this->edit(self::item('locale', 'Language and region', $this->app->locale, $this->app->locale === $app->locale, 'mono', 'Choose Other to type a code the menu doesn\'t have.'), $saved, Setting::Locale, $this->app->locale), 'locales' => Locales::options()],
				...$this->untranslated($saved, $app),
				self::item('url', 'Site address', $this->app->url, $this->app->url === $app->url, 'mono', 'From APP_URL in .env by default.', 'config/app.php')
			]),
			self::group('dates', 'Dates and Time', 'How times are read and shown', [
				[...$this->edit(self::item('timezone', 'Time zone', $this->app->timezone, $this->app->timezone === $app->timezone, 'mono', sprintf('It\'s %s there now.', $now->format('D, j M Y, H:i'))), $saved, Setting::Timezone, $this->app->timezone), 'menu' => TimeZones::options($now)],
				[...$this->edit(self::item('dateFormat', 'Date format', $this->app->dateFormat, $this->app->dateFormat === $app->dateFormat, 'mono', Setting::FORMAT_HELP), $saved, Setting::DateFormat, $this->app->dateFormat), 'formats' => DateFormat::options('date', $now, $this->app->locale, $this->app->timezone), 'link' => ['label' => 'Pattern letters', 'href' => DateFormat::REFERENCE]],
				[...$this->edit(self::item('timeFormat', 'Time format', $this->app->timeFormat, $this->app->timeFormat === $app->timeFormat, 'mono', Setting::FORMAT_HELP), $saved, Setting::TimeFormat, $this->app->timeFormat), 'formats' => DateFormat::options('time', $now, $this->app->locale, $this->app->timezone), 'link' => ['label' => 'Pattern letters', 'href' => DateFormat::REFERENCE]]
			]),
			self::group('accounts', 'Accounts', 'Who can make one', $this->accounts($saved)),
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
	 * Whether anyone can sign up, and the role they're given (D-518);
	 * nothing reads them yet. The role is locked while sign-ups are off.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function accounts(Settings $saved): array
	{
		$defaults = new AuthConfig();
		$roles    = array_filter($this->roles->all(), static fn (Role $role): bool => AuthConfig::signupRoleProblem($role->name) === null);
		$role     = $this->roles->get($this->auth->signupRole);
		$field    = new EnumField('signupRole', array_keys($roles))
			->labeled(Setting::SignupRole->field($this->types)->label)
			->described(Setting::SignupRole->field($this->types)->description)
			->required();
		$item     = $this->edit(
			self::item('signupRole', $field->label, $role->label ?? $this->auth->signupRole, $this->auth->signupRole === $defaults->signupRole, help: $field->description),
			$saved,
			Setting::SignupRole,
			$this->auth->signupRole,
			$field
		);

		return [
			$this->edit(self::item('signups', 'Sign-ups', $this->auth->signups, $this->auth->signups === $defaults->signups, 'bool', Setting::Signups->field($this->types)->description), $saved, Setting::Signups, $this->auth->signups),
			[
				...$item,
				'field'    => [...is_array($item['field'] ?? null) ? $item['field'] : [], 'choices' => array_map(static fn (Role $role): string => $role->label, $roles)],
				'requires' => ['setting' => Setting::Signups->value, 'note' => 'It needs sign-ups on.']
			]
		];
	}

	/**
	 * What another language's address does for an untranslated entry
	 * (D-467, D-468), on a site with other languages; a site without
	 * them has nothing for it to do, so it isn't shown.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function untranslated(Settings $saved, AppConfig $app): array
	{
		$setting = $this->app->untranslated;
		$item    = self::item('untranslated', 'Untranslated pages', $setting->label(), $setting === $app->untranslated, help: Setting::Untranslated->field($this->types)->description);

		return $this->app->languages->isMultilingual() ? [$this->edit($item, $saved, Setting::Untranslated, $setting->value)] : [];
	}

	/**
	 * Reading: what the homepage shows, and the feeds.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function reading(Settings $saved): array
	{
		$feeds = new FeedConfig();

		return [
			self::group('home', 'Homepage', 'What the site opens with', [
				Setting::homeChoices($this->types) === []
					? self::item('home', 'Homepage', $this->homeLabel(), $this->content->home === null, help: 'The site has no collection with addresses to show instead.', file: Setting::Home->file())
					: $this->edit(self::item('home', 'Homepage', $this->homeLabel(), $this->content->home === null), $saved, Setting::Home, $this->content->home)
			]),
			self::group('feeds', 'Feeds', 'For types with a feed', [
				$this->edit(self::item('formats', 'Formats', array_map(static fn (FeedFormat $format): string => $format->label(), $this->feeds->formats), $this->feeds->formats === $feeds->formats, 'list', 'None turns every feed off.'), $saved, Setting::FeedFormats, array_map(static fn (FeedFormat $format): string => $format->value, $this->feeds->formats)),
				$this->edit(self::item('content', 'Full content', $this->feeds->content, $this->feeds->content, 'bool', 'Off, a feed carries each entry\'s summary only.'), $saved, Setting::FeedContent, $this->feeds->content),
				$this->edit(self::item('limit', 'Entries per feed', (string) $this->feeds->limit, $this->feeds->limit === $feeds->limit, help: sprintf('From 1 to %d.', Setting::FEED_LIMIT_MAX)), $saved, Setting::FeedLimit, $this->feeds->limit)
			])
		];
	}

	/**
	 * Media (D-406): the upload rules, one setting (`media.uploads`) the
	 * admin draws as a grid. Its item's `input` is the rules as
	 * `MediaUploads::toArray()` writes them, and `uploads` says what the
	 * grid needs: each kind (`key`, `label`, the `folder` its `{kind}`
	 * becomes, an `example` file name, and the `extensions` the site
	 * allows for it, none for a kind nothing can be uploaded as), the path
	 * `tokens`, the site's date `now` (`year`, `month`, `day`) for the
	 * examples, the most PHP accepts (`serverLimit`, in bytes, or `null`),
	 * and how many `files` the library has.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function media(Settings $saved): array
	{
		$uploads = $this->media->uploads;
		$now     = DateTimeImmutable::createFromInterface($this->clock->now())->setTimezone($this->app->timezone());
		$kinds   = [];

		foreach (MediaKind::cases() as $kind) {
			$kinds[] = [
				'key'        => $kind->value,
				'label'      => $kind->label(),
				'folder'     => $kind->folder(),
				'example'    => match ($kind) {
					MediaKind::Image    => 'photo.jpg',
					MediaKind::Video    => 'clip.mp4',
					MediaKind::Audio    => 'track.mp3',
					MediaKind::Document => 'notes.pdf',
					MediaKind::File     => 'captions.vtt'
				},
				'extensions' => array_keys(array_filter(MediaResolver::EXTENSIONS, fn (string $mime): bool => MediaKind::fromMime($mime) === $kind && $this->media->allows($mime) && ! MediaUploads::refuses($mime)))
			];
		}

		try {
			$files = $this->library->query(new MediaQuery(per: 1))->total;
		} catch (MediaException) {
			$files = null;
		}

		return [
			self::group('uploads', 'Uploads', 'What may be uploaded, and where it goes', [[
				'key'     => 'uploads',
				'label'   => 'Uploads',
				'value'   => [],
				'kind'    => 'uploads',
				'default' => $uploads->toArray() === new MediaUploads()->toArray(),
				'help'    => null,
				'warning' => null,
				'file'    => Setting::MediaUploads->file(),
				'setting' => Setting::MediaUploads->value,
				'input'   => $uploads->toArray(),
				'saved'   => $saved->has(Setting::MediaUploads),
				'uploads' => [
					'kinds'       => $kinds,
					'tokens'      => MediaUploads::TOKENS,
					'now'         => ['year' => $now->format('Y'), 'month' => $now->format('m'), 'day' => $now->format('d')],
					'serverLimit' => MediaUploadController::limit(),
					'files'       => $files
				]
			]]),
			self::group('artwork', 'Artwork', 'The pictures sounds and videos carry', [
				$this->edit(self::item('addArtwork', Setting::AddArtwork->field($this->types)->label, $this->media->addArtwork, ! $this->media->addArtwork, 'bool', Setting::AddArtwork->field($this->types)->description), $saved, Setting::AddArtwork, $this->media->addArtwork)
			])
		];
	}

	/**
	 * Writing (D-494): how what's written in Markdown renders, what raw
	 * HTML in it does, and the sites links embed from (D-633): a switch
	 * for each provider, built in or not, saved together as the list of
	 * those turned off (`embed.off`, kind `embeds`), with each provider's
	 * `name`, `label`, and the `hosts` its links are on.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function writing(Settings $saved): array
	{
		$markdown = $this->markdown;
		$defaults = new MarkdownConfig();
		$switch   = fn (Setting $setting, string $key, bool $value, bool $default): array => $this->edit(
			self::item($key, $setting->field($this->types)->label, $value, $value === $default, 'bool', $setting->field($this->types)->description),
			$saved,
			$setting,
			$value
		);

		return [
			self::group('markdown', 'Markdown', 'How what\'s written renders', [
				$switch(Setting::Mentions, 'mentions', $markdown->mentions, $defaults->mentions),
				$switch(Setting::SmartPunctuation, 'smartPunctuation', $markdown->smartPunctuation, $defaults->smartPunctuation),
				$switch(Setting::HeadingAnchors, 'headingAnchors', $markdown->headingAnchors, $defaults->headingAnchors),
				$switch(Setting::Figures, 'figures', $markdown->figures, $defaults->figures)
			]),
			self::group('html', 'HTML', 'Raw HTML in content', [
				$this->edit(
					self::item('html', 'Raw HTML', $markdown->html->label(), $markdown->html === $defaults->html, help: Setting::Html->field($this->types)->description),
					$saved,
					Setting::Html,
					$markdown->html->value
				)
			]),
			self::group('embeds', 'Embeds', 'Sites whose links play on the page', [
				[
					...$this->edit(
						self::item('off', Setting::EmbedsOff->field($this->types)->label, $this->embed->off, $this->embed->off === [], 'embeds', Setting::EmbedsOff->field($this->types)->description),
						$saved,
						Setting::EmbedsOff,
						$this->embed->off
					),
					'providers' => array_map(static fn (EmbedProvider $provider): array => [
						'name'  => $provider->name,
						'label' => $provider->label,
						'hosts' => self::hosts($provider)
					], $this->embeds->all())
				]
			])
		];
	}

	/**
	 * Returns the sites a provider's links are on, from its schemes, as
	 * people write them (`youtube.com`, `youtu.be`).
	 *
	 * @return list<string>
	 */
	private static function hosts(EmbedProvider $provider): array
	{
		$hosts = [];

		foreach ($provider->schemes as $scheme) {
			$host = parse_url(str_replace('*.', 'x.', $scheme), PHP_URL_HOST);

			if (is_string($host)) {
				$host         = preg_replace('/^(?:www|x)\./', '', strtolower($host)) ?? $host;
				$hosts[$host] = true;
			}
		}

		return array_keys($hosts);
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
				[
					...self::item('media', 'Media address', $this->media->url, $this->media->url === new MediaConfig()->url, 'mono', 'Where user/media is served. The folders under it are named on Media.', 'config/media.php'),
					'links' => [['label' => 'Media settings', 'to' => '/settings/media']]
				]
			]),
			self::group('search', 'Search Engines', 'The sitemap and robots.txt', [
				$this->edit(self::item('sitemap', 'Sitemap and robots.txt', $this->sitemap->enabled, $this->sitemap->enabled, 'bool', 'Off, the site has neither, and search engines find pages by their links.'), $saved, Setting::Sitemap, $this->sitemap->enabled),
				$this->edit(self::item('disallow', 'Paths robots.txt asks to skip', $this->sitemap->disallow, $this->sitemap->disallow === [], 'list', 'One path a line, each starting with /, such as /drafts/.'), $saved, Setting::SitemapDisallow, $this->sitemap->disallow),
				self::item('indexing', 'Asks not to be indexed', $environment !== Environment::Production, null, 'bool', 'Outside production, robots.txt asks search engines to skip the whole site. It follows the environment.')
			])
		];
	}

	/**
	 * AI (D-398): the Markdown copies and `llms.txt` AI tools read, and
	 * what `robots.txt` asks of AI crawlers.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function ai(Settings $saved): array
	{
		$types   = $this->llmsTxt->types();
		$listed  = $this->llms->enabled ? $this->llmsTxt->count() : 0;
		$blocked = array_map(static fn (AiCrawlerGroup $group): string => $group->label(), $this->sitemap->blockAi);

		return [
			self::group('markdown', 'Markdown Copies', 'What AI tools read', [
				$this->edit(self::item('llms', 'Markdown copies', $this->llms->enabled, $this->llms->enabled, 'bool', Setting::Llms->field($this->types)->description), $saved, Setting::Llms, $this->llms->enabled),
				[
					...self::item('llmsTxt', 'llms.txt', $this->llms->enabled ? sprintf($listed === 1 ? 'Lists %s page' : 'Lists %s pages', number_format($listed)) : 'Off, with the Markdown copies', null, help: 'A Markdown map of the site: its name and description, then each listed type\'s pages.'),
					'link' => $this->llms->enabled ? ['label' => 'View llms.txt', 'href' => $this->app->absoluteUrl('/llms.txt')] : null
				],
				$this->full($saved),
				[
					...self::item('types', 'Types in llms.txt', array_map(static fn (ContentType $type): string => $type->labels->plural, $types), null, 'list', 'Each content type chooses with its "Listed in llms.txt" option: on for collections and trees unless they say otherwise, off for taxonomies and profiles unless they say so.'),
					'links' => [['label' => 'Content Types', 'to' => '/types']]
				],
				[
					...self::item('description', 'Description', $this->app->description === '' ? 'None' : $this->app->description, null, help: 'The line under the site\'s name in llms.txt.'),
					'links' => [['label' => 'Change it on General', 'to' => '/settings/general']]
				]
			]),
			self::group('crawlers', 'AI Crawlers', 'What robots.txt asks of them', [
				[
					...$this->edit(self::item('blockAi', 'Ask to stay away', $blocked, $blocked === [], 'list', Setting::BlockAi->field($this->types)->description), $saved, Setting::BlockAi, array_map(static fn (AiCrawlerGroup $group): string => $group->value, $this->sitemap->blockAi)),
					'warning' => match (true) {
						$this->sitemap->robots !== null                          => 'config/sitemap.php\'s own robots.txt replaces the generated one, so these choices aren\'t used.',
						$this->app->environment !== Environment::Production => 'Outside production, robots.txt asks every crawler to stay away, so these choices apply once the site is live.',
						default                                              => null
					}
				]
			])
		];
	}

	/**
	 * The `llms-full.txt` switch (D-402), locked while the Markdown copies
	 * are off (`requires`). When it's served, its link gives its size,
	 * and a warning says when it's too big for the page cache, so a site
	 * served by PHP builds it on every request.
	 *
	 * @return array<string, mixed>
	 */
	private function full(Settings $saved): array
	{
		$served = $this->llms->full && $this->llms->enabled;
		$size   = $served ? strlen($this->llmsTxt->renderFull()) : 0;
		$cached = $this->cache->isEnabled($this->app->environment) && $this->cache->pages;

		return [
			...$this->edit(self::item('full', 'llms-full.txt', $this->llms->full, ! $this->llms->full, 'bool', Setting::LlmsFull->field($this->types)->description), $saved, Setting::LlmsFull, $this->llms->full),
			'requires' => ['setting' => Setting::Llms->value, 'note' => 'It needs the Markdown copies on.'],
			'link'     => $served ? ['label' => sprintf('View llms-full.txt (%s)', self::bytes($size)), 'href' => $this->app->absoluteUrl(LlmsRoutes::FULL)] : null,
			'warning'  => $served && $cached && $size > PageCache::MAX_BYTES
				? sprintf('At %s, it\'s over the page cache\'s %s, so it\'s built again on every request for it.', self::bytes($size), self::bytes(PageCache::MAX_BYTES))
				: null
		];
	}

	/**
	 * A size in words: `3.6 MB`, `140 KB`.
	 */
	private static function bytes(int $bytes): string
	{
		return match (true) {
			$bytes >= 1_048_576 => sprintf('%s MB', rtrim(rtrim(number_format($bytes / 1_048_576, 1), '0'), '.')),
			$bytes >= 1_024     => sprintf('%d KB', (int) round($bytes / 1_024)),
			default             => sprintf('%d bytes', $bytes)
		};
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
	 * What the homepage shows, in words.
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
	 * from, and whether it's saved. A setting whose options are known only
	 * here passes its `$field`.
	 *
	 * @param  array<string, mixed> $item
	 * @return array<string, mixed>
	 */
	private function edit(array $item, Settings $saved, Setting $setting, mixed $input, ?Field $field = null): array
	{
		$field   = ($field ?? $setting->field($this->types))->toForm();
		$choices = $setting->choices($this->types);
		$details = $setting->details();

		if ($choices !== []) {
			if (is_array($field['item'] ?? null)) {
				$field['item']['choices'] = $choices;
			} else {
				$field['choices'] = $choices;
			}
		}

		if ($details !== []) {
			$field['details'] = $details;
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
