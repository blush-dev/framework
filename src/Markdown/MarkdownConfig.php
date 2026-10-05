<?php

/**
 * Markdown config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;

/**
 * Markdown settings, from `config/markdown.php`:
 *
 *     return new MarkdownConfig(
 *         html: RawHtml::Filter,
 *         anchors: new HeadingAnchorOptions(symbol: '¶', before: true)
 *     );
 *
 * Blush's Markdown is one dialect on every site (D-492): CommonMark with
 * autolinks, struck text, tables, task lists, footnotes, definition
 * lists, highlighting, attributes, and directives, which the admin's
 * editor writes and reads. These settings change how it renders, not
 * what it means. The ones the Writing screen shows (D-494) can be saved
 * in `user/data/settings.json` over this file.
 */
final readonly class MarkdownConfig implements Config
{
	/**
	 * @param bool                 $smartPunctuation Whether straight quotes, `--`, and `...` become typographic ones.
	 * @param bool                 $headingAnchors   Whether each heading gets a link to itself.
	 * @param bool                 $mentions         Whether `@name` links to a profile (D-493).
	 * @param bool                 $lineBreaks       Whether a line break inside a paragraph is kept as `<br>`.
	 * @param RawHtml              $html             What raw HTML does on a page.
	 * @param bool                 $figures          Whether a lone image renders as a `<figure>`.
	 * @param bool                 $absoluteLinks    Whether root-relative links become absolute.
	 * @param bool                 $directives       Whether generic directives render as components (D-026).
	 * @param HeadingAnchorOptions $anchors          How heading anchors look.
	 * @param FootnoteOptions      $footnotes        How footnotes look.
	 */
	public function __construct(
		public bool $smartPunctuation = true,
		public bool $headingAnchors = true,
		public bool $mentions = true,
		public bool $lineBreaks = true,
		public RawHtml $html = RawHtml::Allow,
		public bool $figures = true,
		public bool $absoluteLinks = true,
		public bool $directives = true,
		public HeadingAnchorOptions $anchors = new HeadingAnchorOptions(),
		public FootnoteOptions $footnotes = new FootnoteOptions()
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['smartPunctuation', 'headingAnchors', 'mentions', 'lineBreaks', 'html', 'figures', 'absoluteLinks', 'directives', 'anchors', 'footnotes']);

		return new static(
			smartPunctuation: $values->bool('smartPunctuation', true),
			headingAnchors: $values->bool('headingAnchors', true),
			mentions: $values->bool('mentions', true),
			lineBreaks: $values->bool('lineBreaks', true),
			html: $values->enum('html', RawHtml::class, RawHtml::Allow),
			figures: $values->bool('figures', true),
			absoluteLinks: $values->bool('absoluteLinks', true),
			directives: $values->bool('directives', true),
			anchors: self::anchors($data['anchors'] ?? []),
			footnotes: self::footnotes($data['footnotes'] ?? [])
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'smartPunctuation' => $this->smartPunctuation,
			'headingAnchors'   => $this->headingAnchors,
			'mentions'         => $this->mentions,
			'lineBreaks'       => $this->lineBreaks,
			'html'             => $this->html->value,
			'figures'          => $this->figures,
			'absoluteLinks'    => $this->absoluteLinks,
			'directives'       => $this->directives,
			'anchors'          => $this->anchors->toArray(),
			'footnotes'        => $this->footnotes->toArray()
		];
	}

	/**
	 * Returns the heading anchor options, given them or their array.
	 *
	 * @throws InvalidConfig
	 */
	private static function anchors(mixed $value): HeadingAnchorOptions
	{
		return match (true) {
			$value instanceof HeadingAnchorOptions => $value,
			is_array($value)                       => HeadingAnchorOptions::fromArray($value),
			default                                => throw new InvalidConfig('MarkdownConfig "anchors" must be a HeadingAnchorOptions.')
		};
	}

	/**
	 * Returns the footnote options, given them or their array.
	 *
	 * @throws InvalidConfig
	 */
	private static function footnotes(mixed $value): FootnoteOptions
	{
		return match (true) {
			$value instanceof FootnoteOptions => $value,
			is_array($value)                  => FootnoteOptions::fromArray($value),
			default                           => throw new InvalidConfig('MarkdownConfig "footnotes" must be a FootnoteOptions.')
		};
	}
}
