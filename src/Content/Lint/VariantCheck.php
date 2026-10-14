<?php

/**
 * Block variant check.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Lint;

use Blush\Block\BlockName;
use Blush\Block\BlockRegistry;
use Blush\Block\BlockVariants;
use Blush\Block\Variant;
use Blush\Content\Parser\FrontMatter;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Field\ViolationKind;
use Blush\Markdown\CommonMark\Block\BlockAttributes;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeResolver;
use Blush\Translation\Messages;

/**
 * Finds blocks in a body that ask for a variant the block doesn't
 * have under the active theme (D-266): they render as Default, which is
 * seldom what was meant. Only registered blocks are checked, and nothing in fenced code. Like the editor's
 * reading (`markdown.ts`), this isn't a Markdown parser, only close
 * enough to point at a block.
 */
final readonly class VariantCheck
{
	/**
	 * The field its violations use.
	 */
	public const string FIELD = 'body';

	public function __construct(
		private ThemeResolver $themes,
		private BlockRegistry $blocks,
		private BlockVariants $variants,
		private Messages $messages
	) {}

	/**
	 * Checks a file's contents.
	 *
	 * @return list<Violation>
	 */
	public function check(string $contents): array
	{
		try {
			$chain = $this->themes->active();
		} catch (ThemeException) {
			return [];
		}

		$violations = [];

		foreach (self::blocks(FrontMatter::split($contents)[1]) as [$line, $name, $attributes]) {
			$variant = trim(BlockAttributes::parse($attributes)['variant'] ?? '');
			$parsed  = BlockName::parse($name);

			if ($variant === '' || $variant === Variant::DEFAULT || $parsed === null || ! $this->isKnown($parsed)) {
				continue;
			}

			$names = array_map(static fn (Variant $known): string => $known->name, $this->variants->for($parsed, $chain));

			if (! in_array($variant, $names, true)) {
				$message = $names === []
					? $this->messages->t('content.variant_check.no_variants', 'line {line}: {block} has no "{variant}" variant under the active theme, so it renders as Default (it has no variants).', line: (string) $line, block: (string) $parsed, variant: $variant)
					: $this->messages->t('content.variant_check.unknown', 'line {line}: {block} has no "{variant}" variant under the active theme, so it renders as Default (it has {variants}).', line: (string) $line, block: (string) $parsed, variant: $variant, variants: implode(', ', $names));

				$violations[] = new Violation(self::FIELD, $message, Severity::Warning, ViolationKind::Variant);
			}
		}

		return $violations;
	}

	/**
	 * Returns whether a block is registered.
	 */
	private function isKnown(BlockName $name): bool
	{
		return $this->blocks->isRegistered((string) $name);
	}

	/**
	 * Returns the blocks in a body that have attributes, outside
	 * fenced code, as line number, name, and attribute text.
	 *
	 * @return list<array{int, string, string}>
	 */
	private static function blocks(string $body): array
	{
		$found = [];
		$fence = null;
		$block = '/^ {0,3}:{2,}\s*' . BlockAttributes::SYNTAX . '\s*$/';
		$span  = '/(?<![\p{L}\p{N}_:]):' . BlockAttributes::NAME . '\[[^\]\n]*\]\{([^}\n]*)\}/u';

		foreach (preg_split('/\R/', $body) ?: [] as $index => $text) {
			if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $text, $match) === 1) {
				$fence = $fence === null ? $match[1] : (str_starts_with(trim($text), $fence) ? null : $fence);

				continue;
			}

			if ($fence !== null) {
				continue;
			}

			if (preg_match($block, $text, $match) === 1) {
				if (($match[3] ?? '') !== '') {
					$found[] = [$index + 1, $match[1], $match[3]];
				}

				continue;
			}

			preg_match_all($span, $text, $matches, PREG_SET_ORDER);

			foreach ($matches as $inline) {
				$found[] = [$index + 1, $inline[1], $inline[2]];
			}
		}

		return $found;
	}
}
