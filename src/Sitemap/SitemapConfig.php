<?php

/**
 * Sitemap config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Sitemap;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;

/**
 * The site's sitemap and `robots.txt` settings, from `config/sitemap.php`:
 *
 *     return new SitemapConfig(disallow: ['/private']);
 *
 * - `enabled` serves `/sitemap` (1.x's `app.sitemap`); each type's own
 *   `sitemap` option decides whether it's included.
 * - `disallow` adds `Disallow:` paths to the generated `robots.txt`.
 * - `robots`, when set, is served as `robots.txt` as written.
 * - `blockAi` names the kinds of AI crawler (`AiCrawlerGroup`) the
 *   generated `robots.txt` asks to stay away (D-398); none by default.
 * - `perSitemap` is the most URLs one sitemap holds (D-911): a type with
 *   more is split into pages (`/sitemap/{type}/2`, …). The protocol's
 *   limit, 50,000, is the default and the most it may be.
 *
 * Outside production the generated `robots.txt` disallows everything, so
 * staging sites stay out of search engines.
 */
final readonly class SitemapConfig implements Config
{
	/**
	 * The most URLs the sitemap protocol allows in one sitemap.
	 */
	public const int MAX_URLS = 50000;

	/**
	 * @param list<string>         $disallow
	 * @param list<AiCrawlerGroup> $blockAi
	 *
	 * @throws InvalidConfig When `perSitemap` is out of range.
	 */
	public function __construct(
		public bool $enabled = true,
		public array $disallow = [],
		public ?string $robots = null,
		public array $blockAi = [],
		public int $perSitemap = self::MAX_URLS
	) {
		if ($perSitemap < 1 || $perSitemap > self::MAX_URLS) {
			throw new InvalidConfig(sprintf('SitemapConfig "perSitemap" must be from 1 to %d; %d given.', self::MAX_URLS, $perSitemap));
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['enabled', 'disallow', 'robots', 'blockAi', 'perSitemap']);

		$blockAi = [];

		foreach ($values->stringList('blockAi') as $group) {
			$blockAi[] = AiCrawlerGroup::tryFrom($group) ?? throw new InvalidConfig(sprintf('SitemapConfig "blockAi" can\'t include "%s"; use training, search, or fetchers.', $group));
		}

		return new static(
			enabled: $values->bool('enabled', true),
			disallow: $values->stringList('disallow'),
			robots: $values->nullableString('robots'),
			blockAi: $blockAi,
			perSitemap: $values->int('perSitemap', self::MAX_URLS)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'enabled'    => $this->enabled,
			'disallow'   => $this->disallow,
			'robots'     => $this->robots,
			'blockAi'    => array_map(static fn (AiCrawlerGroup $group): string => $group->value, $this->blockAi),
			'perSitemap' => $this->perSitemap
		];
	}
}
