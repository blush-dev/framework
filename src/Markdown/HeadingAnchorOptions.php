<?php

/**
 * Heading anchor options.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown;

use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;

/**
 * How heading anchors look (`MarkdownConfig::$anchors`), when they're on
 * (`MarkdownConfig::$headingAnchors`): each heading gets a link to
 * itself, `<a id="{prefix}{slug}" href="#{prefix}{slug}" class="{class}"
 * title="{title}" aria-hidden="true">{symbol}</a>`, before or after its
 * text.
 */
final readonly class HeadingAnchorOptions
{
	/**
	 * @param string $class  The link's class.
	 * @param string $symbol What the link shows.
	 * @param string $title  The link's title, or `''` for none.
	 * @param string $prefix Goes before every heading's slug, in its id.
	 * @param bool   $before Whether the link goes before the text.
	 */
	public function __construct(
		public string $class = 'heading-anchor',
		public string $symbol = '#',
		public string $title = 'Link to this section',
		public string $prefix = '',
		public bool $before = false
	) {}

	/**
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidConfig
	 */
	public static function fromArray(array $data): self
	{
		$values = new ConfigValues($data, 'HeadingAnchorOptions');
		$values->assertKnownKeys(['class', 'symbol', 'title', 'prefix', 'before']);

		return new self(
			class: $values->string('class', 'heading-anchor'),
			symbol: $values->string('symbol', '#'),
			title: $values->string('title', 'Link to this section'),
			prefix: $values->string('prefix', ''),
			before: $values->bool('before', false)
		);
	}

	/**
	 * @return array{class: string, symbol: string, title: string, prefix: string, before: bool}
	 */
	public function toArray(): array
	{
		return [
			'class'  => $this->class,
			'symbol' => $this->symbol,
			'title'  => $this->title,
			'prefix' => $this->prefix,
			'before' => $this->before
		];
	}
}
