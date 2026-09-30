<?php

/**
 * Directive variant check.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Lint;

use Blush\Component\ComponentName;
use Blush\Component\ComponentRegistry;
use Blush\Component\ComponentType;
use Blush\Component\ComponentVariants;
use Blush\Component\Variant;
use Blush\Content\Parser\FrontMatter;
use Blush\Content\Schema\Severity;
use Blush\Content\Schema\Violation;
use Blush\Markdown\CommonMark\Directive\DirectiveAttributes;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeResolver;

/**
 * Finds directives in a body that ask for a variant their component
 * doesn't have under the active theme (D-266): they render as Default,
 * which is seldom what was meant. Only the core and registered
 * components are checked, and nothing in fenced code. Like the editor's
 * reading (`markdown.ts`), this isn't a Markdown parser, only close
 * enough to point at a directive.
 */
final readonly class VariantCheck
{
	/**
	 * The field its violations use.
	 */
	public const string FIELD = 'body';

	public function __construct(
		private ThemeResolver $themes,
		private ComponentRegistry $components,
		private ComponentVariants $variants
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

		foreach (self::directives(FrontMatter::split($contents)[1]) as [$line, $name, $attributes]) {
			$variant = trim(DirectiveAttributes::parse($attributes)['variant'] ?? '');
			$parsed  = ComponentName::parse($name);

			if ($variant === '' || $variant === Variant::DEFAULT || $parsed === null || ! $this->isKnown($parsed)) {
				continue;
			}

			$names = array_map(static fn (Variant $known): string => $known->name, $this->variants->for($parsed, $chain));

			if (! in_array($variant, $names, true)) {
				$violations[] = new Violation(self::FIELD, sprintf(
					'line %d: %s has no "%s" variant under the active theme, so it renders as Default (%s).',
					$line,
					$parsed,
					$variant,
					$names === [] ? 'it has no variants' : 'it has ' . implode(', ', $names)
				), Severity::Warning);
			}
		}

		return $violations;
	}

	/**
	 * Returns whether a component is core or registered.
	 */
	private function isKnown(ComponentName $name): bool
	{
		return ($name->isCore() && ComponentType::tryFrom($name->name) !== null) || $this->components->isRegistered((string) $name);
	}

	/**
	 * Returns the directives in a body that have attributes, outside
	 * fenced code, as line number, name, and attribute text.
	 *
	 * @return list<array{int, string, string}>
	 */
	private static function directives(string $body): array
	{
		$found = [];
		$fence = null;
		$block = '/^ {0,3}:{2,}\s*' . DirectiveAttributes::SYNTAX . '\s*$/';
		$span  = '/(?<![\p{L}\p{N}_:]):' . DirectiveAttributes::NAME . '\[[^\]\n]*\]\{([^}\n]*)\}/u';

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
