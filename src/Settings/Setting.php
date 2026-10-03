<?php

/**
 * Setting enum.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Settings;

use DateTimeZone;
use Blush\Config\Config;
use Blush\Config\InvalidConfig;
use Blush\Content\Type\ContentConfig;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\TypeKind;
use Blush\Core\AppConfig;
use Blush\Extension\ExtensionName;
use Blush\Feed\FeedConfig;
use Blush\Feed\FeedFormat;
use Blush\Field\Control;
use Blush\Field\Field;
use Blush\Field\Fields\BoolField;
use Blush\Field\Fields\EnumField;
use Blush\Field\Fields\ListField;
use Blush\Field\Fields\NumberField;
use Blush\Field\Fields\ObjectField;
use Blush\Field\Fields\TextField;
use Blush\Icon\IconConfig;
use Blush\Llms\LlmsConfig;
use Blush\Media\MediaConfig;
use Blush\Media\MediaUploads;
use Blush\Plugin\PluginConfig;
use Blush\Routing\RouteConfig;
use Blush\Sitemap\AiCrawlerGroup;
use Blush\Sitemap\SitemapConfig;
use Blush\Theme\ThemeConfig;

/**
 * A setting the site owner may change in the admin (D-324, D-325). Its
 * value is `{section}.{key}`: the section of `user/data/settings.json` it's
 * saved in, named for the config file by convention (`feed` for
 * `config/feed.php`), and the key it sets in that config object's
 * `toArray()`. Everything else stays in `config/` and `.env`.
 *
 * Each is described as a field (`field()`, D-343), so the admin edits it
 * with the controls every form uses, on its screen (`screen()`), the
 * screen's own fields before any field set's; `normalize()` still checks
 * its value, more closely than the field type does. The active theme
 * (D-381) is on no Settings screen: the Themes screen saves it. Nor are
 * the plugins and icon packs turned off (D-385), which their screens
 * save.
 */
enum Setting: string
{
	case Name            = 'app.name';
	case Description     = 'app.description';
	case Locale          = 'app.locale';
	case Timezone        = 'app.timezone';
	case Home            = 'content.home';
	case TrailingSlash   = 'routes.trailingSlash';
	case FeedFormats     = 'feed.formats';
	case FeedContent     = 'feed.content';
	case FeedLimit       = 'feed.limit';
	case MediaUploads    = 'media.uploads';
	case Sitemap         = 'sitemap.enabled';
	case SitemapDisallow = 'sitemap.disallow';
	case Llms            = 'llms.enabled';
	case LlmsFull        = 'llms.full';
	case BlockAi         = 'sitemap.blockAi';
	case Theme           = 'theme.active';
	case Plugins         = 'plugins.enabled';
	case IconPacks       = 'icons.enabled';

	/**
	 * The most entries a feed may hold.
	 */
	public const int FEED_LIMIT_MAX = 100;

	/**
	 * The longest a site's description may be.
	 */
	public const int DESCRIPTION_MAX = 300;

	/**
	 * What the site's description is for.
	 */
	public const string DESCRIPTION_HELP = 'One line about the site, for llms.txt, and for the homepage and feeds when nothing more specific describes them. Search results show about 160 characters.';

	/**
	 * The most paths robots.txt may be asked to skip.
	 */
	public const int DISALLOW_MAX = 50;

	/**
	 * The setting saved under a section and key, if there is one.
	 */
	public static function find(string $section, string $key): ?self
	{
		return self::tryFrom("{$section}.{$key}");
	}

	/**
	 * The Settings screen it's on, or `null` for one another screen saves.
	 */
	public function screen(): ?SettingsScreen
	{
		return match ($this) {
			self::Theme, self::Plugins, self::IconPacks                   => null,
			self::Name, self::Description, self::Locale, self::Timezone   => SettingsScreen::General,
			self::Home, self::FeedFormats, self::FeedContent, self::FeedLimit => SettingsScreen::Reading,
			self::MediaUploads                                            => SettingsScreen::Media,
			self::TrailingSlash, self::Sitemap, self::SitemapDisallow     => SettingsScreen::Search,
			self::Llms, self::LlmsFull, self::BlockAi                     => SettingsScreen::Ai
		};
	}

	/**
	 * The setting as a field (D-343), named by its key, with its label,
	 * help, and control; a choice has the options it can be (the
	 * homepage's, from the site's collections with addresses; a site with
	 * none has nothing to choose, and the admin only shows it).
	 */
	public function field(ContentTypes $types): Field
	{
		$field = match ($this) {
			self::Name            => new TextField('name')->labeled('Site name')->required(),
			self::Description     => new TextField('description')->labeled('Description')->described(self::DESCRIPTION_HELP),
			self::Locale          => new TextField('locale')->labeled('Language and region')->described('A language code, with a region if you like, such as en_US or fr.')->control(Control::Mono),
			self::Timezone        => new EnumField('timezone', DateTimeZone::listIdentifiers())->labeled('Time zone'),
			self::Home            => self::homeChoices($types) === []
				? new TextField('home')->labeled('Homepage')
				: new EnumField('home', array_keys(self::homeChoices($types)))->labeled('Homepage'),
			self::FeedFormats     => new ListField('formats', new EnumField('', array_column(FeedFormat::cases(), 'value')))->labeled('Formats')->described('None turns every feed off.')->control(Control::Checks),
			self::FeedContent     => new BoolField('content')->labeled('Full content')->described('Off, a feed carries each entry\'s summary only.'),
			self::MediaUploads    => new ObjectField('uploads')->labeled('Uploads')->described('What may be uploaded, how large, and the folder under user/media it goes in.')->control(Control::Readonly),
			self::FeedLimit       => new NumberField('limit', integer: true, min: 1, max: self::FEED_LIMIT_MAX)->labeled('Entries per feed')->described(sprintf('From 1 to %d.', self::FEED_LIMIT_MAX)),
			self::TrailingSlash   => new BoolField('trailingSlash')->labeled('Trailing slash')->described('The other form redirects, so links to either still work.'),
			self::Sitemap         => new BoolField('enabled')->labeled('Sitemap and robots.txt')->described('Off, the site has neither, and search engines find pages by their links.'),
			self::SitemapDisallow => new ListField('disallow')->labeled('Paths robots.txt asks to skip')->described('One path a line, each starting with /, such as /drafts/.'),
			self::Llms            => new BoolField('enabled')->labeled('Markdown copies')->described('A page\'s copy is at its address with .md, such as /about.md, and llms.txt lists them, for AI tools to read.'),
			self::LlmsFull        => new BoolField('full')->labeled('llms-full.txt')->described('Every page llms.txt lists, in full, in one file, so a tool can read the whole site at once. It can be several megabytes on a large site.'),
			self::BlockAi         => new ListField('blockAi', new EnumField('', array_column(AiCrawlerGroup::cases(), 'value')))->labeled('Ask to stay away')->described('robots.txt is a request: well-behaved crawlers follow it, others may not.')->control(Control::Checks),
			self::Theme           => new TextField('active')->labeled('Theme')->control(Control::Mono),
			self::Plugins         => new ListField('enabled')->labeled('Plugins turned on'),
			self::IconPacks       => new ListField('enabled')->labeled('Icon packs turned on')
		};

		return $field->named($this->key());
	}

	/**
	 * Words for the field's values, where its options aren't words: a
	 * time zone without underscores, the homepage's collections, and the
	 * feed formats' names.
	 *
	 * @return array<string, string>
	 */
	public function choices(ContentTypes $types): array
	{
		return match ($this) {
			self::Timezone    => array_combine(DateTimeZone::listIdentifiers(), array_map(static fn (string $zone): string => str_replace('_', ' ', $zone), DateTimeZone::listIdentifiers())),
			self::Home        => self::homeChoices($types),
			self::FeedFormats => array_combine(array_column(FeedFormat::cases(), 'value'), array_map(static fn (FeedFormat $format): string => $format->label(), FeedFormat::cases())),
			self::BlockAi     => array_combine(array_column(AiCrawlerGroup::cases(), 'value'), array_map(static fn (AiCrawlerGroup $group): string => $group->label(), AiCrawlerGroup::cases())),
			default           => []
		};
	}

	/**
	 * More about each of the field's options, where a name alone doesn't
	 * say enough (D-404): a sentence (`text`) and the machine names it
	 * covers (`code`), as the AI crawler groups' user agents.
	 *
	 * @return array<string, array{text: string, code: string}>
	 */
	public function details(): array
	{
		return match ($this) {
			self::BlockAi => array_combine(array_column(AiCrawlerGroup::cases(), 'value'), array_map(static fn (AiCrawlerGroup $group): array => ['text' => $group->description(), 'code' => implode(' · ', $group->agents())], AiCrawlerGroup::cases())),
			default       => []
		};
	}

	/**
	 * What a checkbox beside the setting says, or what a choice left
	 * empty means.
	 */
	public function caption(): ?string
	{
		return match ($this) {
			self::TrailingSlash => 'Addresses end in a slash',
			self::FeedContent   => 'Feeds carry each entry\'s full content',
			self::Sitemap       => 'The site has a sitemap and robots.txt',
			self::Llms          => 'Every page has a Markdown copy, and the site has llms.txt',
			self::LlmsFull      => 'The site has llms-full.txt',
			self::Home          => 'The page at user/content/index.md',
			default             => null
		};
	}

	/**
	 * The collection types that can be the homepage, by name, with what
	 * each shows.
	 *
	 * @return array<string, string>
	 */
	public static function homeChoices(ContentTypes $types): array
	{
		$choices = [];

		foreach ($types->all() as $type) {
			if ($type->kind() === TypeKind::Collection && $type->hasUrls()) {
				$choices[$type->name] = sprintf('The latest %s', mb_strtolower($type->labels->plural));
			}
		}

		return $choices;
	}

	/**
	 * The file section it's saved in.
	 */
	public function section(): string
	{
		return strstr($this->value, '.', true) ?: $this->value;
	}

	/**
	 * The key it sets in its config object's `toArray()`.
	 */
	public function key(): string
	{
		return substr($this->value, strlen($this->section()) + 1);
	}

	/**
	 * The key it replaces in its config object: its own key, except the
	 * admin's lists of what's on, which are kept apart from the config
	 * file's (`saved`, D-391) because they name Composer extensions too.
	 */
	public function configKey(): string
	{
		return match ($this) {
			self::Plugins, self::IconPacks => 'saved',
			default                        => $this->key()
		};
	}

	/**
	 * The config object the setting is laid over.
	 *
	 * @return class-string<Config>
	 */
	public function config(): string
	{
		return match ($this->section()) {
			'app'     => AppConfig::class,
			'content' => ContentConfig::class,
			'routes'  => RouteConfig::class,
			'feed'    => FeedConfig::class,
			'theme'   => ThemeConfig::class,
			'plugins' => PluginConfig::class,
			'icons'   => IconConfig::class,
			'llms'    => LlmsConfig::class,
			'media'   => MediaConfig::class,
			default   => SitemapConfig::class
		};
	}

	/**
	 * The file its value comes from when it isn't saved, by convention.
	 */
	public function file(): string
	{
		return "config/{$this->section()}.php";
	}

	/**
	 * Whether a change needs the compiled routes and content types written
	 * again, and the content reindexed: the homepage, the time zone dates
	 * are read in, what has addresses, and the theme and plugins (their
	 * providers run at boot, so what's compiled is built again with them,
	 * to be safe).
	 */
	public function needsRefresh(): bool
	{
		return match ($this) {
			self::Home, self::Timezone, self::TrailingSlash, self::FeedFormats, self::Sitemap, self::Llms, self::LlmsFull, self::Theme, self::Plugins => true,
			default => false
		};
	}

	/**
	 * Checks a value and returns it as it's saved: trimmed, lists without
	 * repeats, and feed formats in their usual order. Whether the
	 * homepage's type exists is checked where the types are known.
	 *
	 * @throws InvalidSetting
	 */
	public function normalize(mixed $value): mixed
	{
		return match ($this) {
			self::Name            => self::name($value),
			self::Description     => self::description($value),
			self::Locale          => self::locale($value),
			self::Timezone        => self::timezone($value),
			self::Home            => self::home($value),
			self::FeedFormats     => self::formats($value),
			self::FeedLimit       => self::limit($value),
			self::SitemapDisallow => self::disallow($value),
			self::BlockAi         => self::blockAi($value),
			self::MediaUploads    => self::uploads($value),
			self::Theme           => self::theme($value),
			self::Plugins         => self::names($value, 'plugins'),
			self::IconPacks       => self::names($value, 'icon packs'),
			default               => is_bool($value) ? $value : throw new InvalidSetting(sprintf('"%s" must be true or false.', $this->value))
		};
	}

	/**
	 * @throws InvalidSetting
	 */
	private static function name(mixed $value): string
	{
		$name = is_string($value) ? trim($value) : throw new InvalidSetting('The site name must be text.');

		return match (true) {
			$name === ''                                    => throw new InvalidSetting('The site needs a name.'),
			mb_strlen($name) > 100                          => throw new InvalidSetting('The site name can be at most 100 characters.'),
			preg_match('/[\p{Cc}\p{Zl}\p{Zp}]/u', $name) !== 0 => throw new InvalidSetting('The site name can\'t hold line breaks or control characters.'),
			default                                         => $name
		};
	}

	/**
	 * @throws InvalidSetting
	 */
	private static function description(mixed $value): string
	{
		$description = is_string($value) ? trim($value) : throw new InvalidSetting('The site\'s description must be text.');

		return match (true) {
			mb_strlen($description) > self::DESCRIPTION_MAX                => throw new InvalidSetting(sprintf('The site\'s description can be at most %d characters.', self::DESCRIPTION_MAX)),
			preg_match('/[\p{Cc}\p{Zl}\p{Zp}]/u', $description) !== 0 => throw new InvalidSetting('The site\'s description is one line, without control characters.'),
			default                                                       => $description
		};
	}

	/**
	 * @throws InvalidSetting
	 */
	private static function locale(mixed $value): string
	{
		$locale = is_string($value) ? trim($value) : '';

		return preg_match('/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/', $locale) === 1
			? $locale
			: throw new InvalidSetting('Use a language code, with a region if you like, such as en_US or fr.');
	}

	/**
	 * @throws InvalidSetting
	 */
	private static function timezone(mixed $value): string
	{
		return is_string($value) && in_array($value, DateTimeZone::listIdentifiers(), true)
			? $value
			: throw new InvalidSetting(sprintf('"%s" isn\'t a time zone; use one such as America/Chicago or UTC.', is_string($value) ? $value : get_debug_type($value)));
	}

	/**
	 * @throws InvalidSetting
	 */
	private static function home(mixed $value): ?string
	{
		return $value === null || (is_string($value) && preg_match('/^[a-z][a-z0-9_-]*$/', $value) === 1)
			? $value
			: throw new InvalidSetting('The homepage must name a content type, or be null for the page at user/content/index.md.');
	}

	/**
	 * @return list<string>
	 * @throws InvalidSetting
	 */
	private static function formats(mixed $value): array
	{
		if (! is_array($value) || ! array_is_list($value)) {
			throw new InvalidSetting('Feed formats must be a list: rss, atom, or json.');
		}

		foreach ($value as $format) {
			if (! is_string($format) || FeedFormat::tryFrom($format) === null) {
				throw new InvalidSetting(sprintf('"%s" isn\'t a feed format; use rss, atom, or json.', is_string($format) ? $format : get_debug_type($format)));
			}
		}

		return array_values(array_map(
			static fn (FeedFormat $format): string => $format->value,
			array_filter(FeedFormat::cases(), static fn (FeedFormat $format): bool => in_array($format->value, $value, true))
		));
	}

	/**
	 * @throws InvalidSetting
	 */
	private static function limit(mixed $value): int
	{
		return is_int($value) && $value >= 1 && $value <= self::FEED_LIMIT_MAX
			? $value
			: throw new InvalidSetting(sprintf('A feed holds from 1 to %d entries.', self::FEED_LIMIT_MAX));
	}

	/**
	 * Checks a theme's name; whether it's installed is checked where the
	 * themes are known.
	 *
	 * @throws InvalidSetting
	 */
	private static function theme(mixed $value): string
	{
		return is_string($value) && ExtensionName::isValid($value)
			? $value
			: throw new InvalidSetting('The theme must be a theme\'s name (vendor/name), such as "acme/nova".');
	}

	/**
	 * Checks a list of extensions' names, and returns it without repeats,
	 * in order; whether they're installed doesn't matter, so a list
	 * outlives a plugin that's removed and put back.
	 *
	 * @return list<string>
	 * @throws InvalidSetting
	 */
	private static function names(mixed $value, string $kind): array
	{
		if (! is_array($value) || ! array_is_list($value)) {
			throw new InvalidSetting(sprintf('The %s turned off must be a list of names.', $kind));
		}

		foreach ($value as $name) {
			if (! is_string($name) || ! ExtensionName::isValid($name)) {
				throw new InvalidSetting(sprintf('"%s" isn\'t a name; the %s turned off are named vendor/name, such as "acme/gallery".', is_string($name) ? $name : get_debug_type($name), $kind));
			}
		}

		$names = array_values(array_unique($value));
		sort($names);

		return $names;
	}

	/**
	 * Checks the upload rules as `MediaUploads` does, and returns them as
	 * it writes them (D-406).
	 *
	 * @return array<string, mixed>
	 * @throws InvalidSetting
	 */
	private static function uploads(mixed $value): array
	{
		if (! is_array($value) || ($value !== [] && array_is_list($value))) {
			throw new InvalidSetting('The upload rules must be an object: enabled, maxSize, path, and kinds.');
		}

		try {
			return MediaUploads::fromArray($value)->toArray();
		} catch (InvalidConfig $error) {
			throw new InvalidSetting($error->getMessage(), previous: $error);
		}
	}

	/**
	 * Checks the AI crawler groups to block, and returns them in their
	 * usual order.
	 *
	 * @return list<string>
	 * @throws InvalidSetting
	 */
	private static function blockAi(mixed $value): array
	{
		if (! is_array($value) || ! array_is_list($value)) {
			throw new InvalidSetting('The AI crawlers to block must be a list: training, search, or fetchers.');
		}

		foreach ($value as $group) {
			if (! is_string($group) || AiCrawlerGroup::tryFrom($group) === null) {
				throw new InvalidSetting(sprintf('"%s" isn\'t a kind of AI crawler; use training, search, or fetchers.', is_string($group) ? $group : get_debug_type($group)));
			}
		}

		return array_values(array_map(
			static fn (AiCrawlerGroup $group): string => $group->value,
			array_filter(AiCrawlerGroup::cases(), static fn (AiCrawlerGroup $group): bool => in_array($group->value, $value, true))
		));
	}

	/**
	 * @return list<string>
	 * @throws InvalidSetting
	 */
	private static function disallow(mixed $value): array
	{
		if (! is_array($value) || ! array_is_list($value)) {
			throw new InvalidSetting('The paths robots.txt asks to skip must be a list.');
		}

		$paths = [];

		foreach ($value as $path) {
			$path = is_string($path) ? trim($path) : '';

			if ($path === '') {
				continue;
			}

			if (! str_starts_with($path, '/') || preg_match('/[\s\p{Cc}]/u', $path) !== 0) {
				throw new InvalidSetting(sprintf('"%s" isn\'t a path; a path starts with / and has no spaces, such as /drafts/.', $path));
			}

			$paths[$path] = true;
		}

		if (count($paths) > self::DISALLOW_MAX) {
			throw new InvalidSetting(sprintf('robots.txt can be asked to skip at most %d paths.', self::DISALLOW_MAX));
		}

		return array_keys($paths);
	}
}
