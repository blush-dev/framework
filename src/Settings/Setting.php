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
use Blush\Content\Type\ContentConfig;
use Blush\Core\AppConfig;
use Blush\Feed\FeedConfig;
use Blush\Feed\FeedFormat;
use Blush\Routing\RouteConfig;
use Blush\Sitemap\SitemapConfig;

/**
 * A setting the site owner may change in the admin (D-324, D-325). Its
 * value is `{section}.{key}`: the section of `user/data/settings.json` it's
 * saved in, named for the config file by convention (`feed` for
 * `config/feed.php`), and the key it sets in that config object's
 * `toArray()`. Everything else stays in `config/` and `.env`.
 */
enum Setting: string
{
	case Name            = 'app.name';
	case Locale          = 'app.locale';
	case Timezone        = 'app.timezone';
	case Home            = 'content.home';
	case TrailingSlash   = 'routes.trailingSlash';
	case FeedFormats     = 'feed.formats';
	case FeedContent     = 'feed.content';
	case FeedLimit       = 'feed.limit';
	case Sitemap         = 'sitemap.enabled';
	case SitemapDisallow = 'sitemap.disallow';

	/**
	 * The most entries a feed may hold.
	 */
	public const int FEED_LIMIT_MAX = 100;

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
	 * again, and the content reindexed: the home page, the time zone dates
	 * are read in, and what has addresses.
	 */
	public function needsRefresh(): bool
	{
		return match ($this) {
			self::Home, self::Timezone, self::TrailingSlash, self::FeedFormats, self::Sitemap => true,
			default => false
		};
	}

	/**
	 * Checks a value and returns it as it's saved: trimmed, lists without
	 * repeats, and feed formats in their usual order. Whether the home
	 * page's type exists is checked where the types are known.
	 *
	 * @throws InvalidSetting
	 */
	public function normalize(mixed $value): mixed
	{
		return match ($this) {
			self::Name            => self::name($value),
			self::Locale          => self::locale($value),
			self::Timezone        => self::timezone($value),
			self::Home            => self::home($value),
			self::FeedFormats     => self::formats($value),
			self::FeedLimit       => self::limit($value),
			self::SitemapDisallow => self::disallow($value),
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
			: throw new InvalidSetting('The home page must name a content type, or be null for the page at user/content/index.md.');
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
