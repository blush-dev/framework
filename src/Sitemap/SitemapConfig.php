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

/**
 * The site's sitemap and `robots.txt` settings, from `config/sitemap.php`:
 *
 *     return new SitemapConfig(disallow: ['/private']);
 *
 * - `enabled` serves `/sitemap` (1.x's `app.sitemap`); each type's own
 *   `sitemap` option decides whether it's included.
 * - `disallow` adds `Disallow:` paths to the generated `robots.txt`.
 * - `robots`, when set, is served as `robots.txt` as written.
 *
 * Outside production the generated `robots.txt` disallows everything, so
 * staging sites stay out of search engines.
 */
final readonly class SitemapConfig implements Config
{
	/**
	 * @param list<string> $disallow
	 */
	public function __construct(
		public bool $enabled = true,
		public array $disallow = [],
		public ?string $robots = null
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['enabled', 'disallow', 'robots']);

		return new static(
			enabled: $values->bool('enabled', true),
			disallow: $values->stringList('disallow'),
			robots: $values->nullableString('robots')
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return ['enabled' => $this->enabled, 'disallow' => $this->disallow, 'robots' => $this->robots];
	}
}
