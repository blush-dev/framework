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
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;

/**
 * Markdown settings, from `config/markdown.php`:
 *
 *     return new MarkdownConfig(
 *         options: ['renderer' => ['soft_break' => '<br />']],
 *         extensions: [...MarkdownConfig::DEFAULT_EXTENSIONS, AttributesExtension::class],
 *         inlineParsers: [App\Markdown\Cite::class]
 *     );
 *
 * These settings are specific to the CommonMark adapter (D-080): `options`
 * is its configuration array (https://commonmark.thephpleague.com), and the
 * class lists name its extension and inline parser classes, each built
 * without constructor arguments. Raw HTML is allowed by default, since
 * authors are trusted; set `options: ['html_input' => 'escape']` to change
 * that. Extensions that need services hook in through the
 * `MarkdownEnvironmentBuilding` event instead.
 *
 * `fromArray()` also accepts the 1.x keys `config` and `inline_parsers`
 * (D-078).
 */
final readonly class MarkdownConfig implements Config
{
	/**
	 * The extensions used when none are configured: CommonMark plus the
	 * GitHub-flavored extras and footnotes.
	 *
	 * @var list<class-string<ExtensionInterface>>
	 */
	public const array DEFAULT_EXTENSIONS = [
		CommonMarkCoreExtension::class,
		AutolinkExtension::class,
		StrikethroughExtension::class,
		TableExtension::class,
		TaskListExtension::class,
		FootnoteExtension::class
	];

	/**
	 * The classes aren't checked here, which would load them on every
	 * request; `CommonMarkParser` checks them when it first converts.
	 *
	 * @param array<string, mixed>                       $options       CommonMark configuration.
	 * @param list<class-string<ExtensionInterface>>    $extensions    Extensions to add, in order.
	 * @param list<class-string<InlineParserInterface>> $inlineParsers Inline parsers to add.
	 * @param bool                                      $figures       Whether a lone image renders as a `<figure>`.
	 * @param bool                                      $absoluteLinks Whether root-relative links become absolute.
	 * @param bool                                      $directives    Whether generic directives render as components (D-026).
	 */
	public function __construct(
		public array $options = [],
		public array $extensions = self::DEFAULT_EXTENSIONS,
		public array $inlineParsers = [],
		public bool $figures = true,
		public bool $absoluteLinks = true,
		public bool $directives = true
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$data = self::renamed($data, ['config' => 'options', 'inline_parsers' => 'inlineParsers']);

		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['options', 'extensions', 'inlineParsers', 'figures', 'absoluteLinks', 'directives']);

		$options = $data['options'] ?? [];

		if (! is_array($options) || ($options !== [] && array_is_list($options))) {
			throw new InvalidConfig('MarkdownConfig "options" must be a map.');
		}

		/** @var array<string, mixed> $options */
		/** @var list<class-string<ExtensionInterface>> $extensions Checked by `CommonMarkParser`. */
		$extensions = $values->stringList('extensions', self::DEFAULT_EXTENSIONS);

		/** @var list<class-string<InlineParserInterface>> $inlineParsers Checked by `CommonMarkParser`. */
		$inlineParsers = $values->stringList('inlineParsers');

		return new static(
			options: $options,
			extensions: $extensions,
			inlineParsers: $inlineParsers,
			figures: $values->bool('figures', true),
			absoluteLinks: $values->bool('absoluteLinks', true),
			directives: $values->bool('directives', true)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'options'       => $this->options,
			'extensions'    => $this->extensions,
			'inlineParsers' => $this->inlineParsers,
			'figures'       => $this->figures,
			'absoluteLinks' => $this->absoluteLinks,
			'directives'    => $this->directives
		];
	}

	/**
	 * Moves 1.x keys to their 2.x names. A 2.x key that's also present
	 * wins.
	 *
	 * @param  array<array-key, mixed> $data
	 * @param  array<string, string>   $renames
	 * @return array<array-key, mixed>
	 */
	private static function renamed(array $data, array $renames): array
	{
		foreach ($renames as $old => $new) {
			if (array_key_exists($old, $data)) {
				$data[$new] ??= $data[$old];
				unset($data[$old]);
			}
		}

		return $data;
	}
}
