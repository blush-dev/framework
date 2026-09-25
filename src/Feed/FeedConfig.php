<?php

/**
 * Feed config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Feed;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;

/**
 * The site's feed settings, from `config/feed.php`:
 *
 *     return new FeedConfig(formats: [FeedFormat::Rss, FeedFormat::Atom], content: false);
 *
 * - `formats` are the feeds every type with a `feed` gets.
 * - `content` puts each entry's full body in its feed item; off, items
 *   carry only the excerpt.
 * - `limit` is how many entries a feed holds, unless the type's `feed`
 *   `collection` arguments set a `number` (1.x's default is 10).
 */
final readonly class FeedConfig implements Config
{
	/**
	 * @param  list<FeedFormat> $formats
	 * @throws InvalidConfig
	 */
	public function __construct(
		public array $formats = [FeedFormat::Rss, FeedFormat::Atom, FeedFormat::Json],
		public bool $content = true,
		public int $limit = 10
	) {
		if ($limit < 1) {
			throw new InvalidConfig(sprintf('FeedConfig "limit" must be at least 1; %d given.', $limit));
		}
	}

	/**
	 * Returns whether a format is on.
	 */
	public function has(FeedFormat $format): bool
	{
		return in_array($format, $this->formats, true);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['formats', 'content', 'limit']);

		$formats = [];

		foreach ($values->stringList('formats', ['rss', 'atom', 'json']) as $format) {
			$formats[] = FeedFormat::tryFrom($format) ?? throw new InvalidConfig(sprintf('FeedConfig "formats" can\'t include "%s"; use rss, atom, or json.', $format));
		}

		return new static(
			formats: $formats,
			content: $values->bool('content', true),
			limit: $values->int('limit', 10)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'formats' => array_map(static fn (FeedFormat $format): string => $format->value, $this->formats),
			'content' => $this->content,
			'limit'   => $this->limit
		];
	}
}
