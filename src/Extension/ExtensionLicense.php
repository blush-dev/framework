<?php

/**
 * Extension license.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

/**
 * Links an extension's `license` to the text of each license it names,
 * for the common open source ones (D-426). A license is as Composer has
 * it (https://getcomposer.org/doc/04-schema.md#license): an SPDX
 * identifier (`MIT`), several any of which applies (a list, which
 * `ComposerJson::license()` joins as `MIT or GPL-2.0-or-later`, or
 * `(LGPL-2.1-only or GPL-3.0-or-later)`), several that all apply
 * (`(LGPL-2.1-only and GPL-3.0-or-later)`), or `proprietary`. Each known
 * identifier links to its page on spdx.org; the operators (`or`, `and`,
 * SPDX's `with`) and anything else (`proprietary`, a custom name) are
 * kept as text.
 */
final readonly class ExtensionLicense
{
	/**
	 * The words that join licenses in an expression.
	 *
	 * @var list<string>
	 */
	public const array OPERATORS = ['or', 'and', 'with'];

	/**
	 * The identifiers linked, in SPDX's case: Composer's recommended ones,
	 * other common open source ones, and the deprecated GPL family ones
	 * (`GPL-2.0`, `GPL-2.0+`) many `composer.json` files still use.
	 * Matched without regard to case.
	 *
	 * @var list<string>
	 */
	public const array KNOWN = [
		'0BSD',
		'AGPL-3.0-only',
		'AGPL-3.0-or-later',
		'Apache-2.0',
		'Artistic-2.0',
		'BSD-2-Clause',
		'BSD-3-Clause',
		'BSD-4-Clause',
		'BSL-1.0',
		'CC-BY-4.0',
		'CC-BY-SA-4.0',
		'CC0-1.0',
		'EPL-2.0',
		'GPL-2.0',
		'GPL-2.0+',
		'GPL-2.0-only',
		'GPL-2.0-or-later',
		'GPL-3.0',
		'GPL-3.0+',
		'GPL-3.0-only',
		'GPL-3.0-or-later',
		'ISC',
		'LGPL-2.1',
		'LGPL-2.1+',
		'LGPL-2.1-only',
		'LGPL-2.1-or-later',
		'LGPL-3.0',
		'LGPL-3.0+',
		'LGPL-3.0-only',
		'LGPL-3.0-or-later',
		'MIT',
		'MIT-0',
		'MPL-2.0',
		'OFL-1.1',
		'Unlicense',
		'Zlib'
	];

	/**
	 * Reads a manifest's `license` as Composer has it (D-428): a string,
	 * or a list of strings any of which applies, joined as `MIT or
	 * GPL-2.0-or-later` (as `ComposerJson::license()` reads a
	 * `composer.json`'s leniently).
	 *
	 * @throws ExtensionException When it's neither.
	 */
	public static function fromManifest(mixed $license): string
	{
		if (is_string($license)) {
			return trim($license);
		}

		if (! is_array($license) || ! array_is_list($license) || ! array_all($license, static fn (mixed $item): bool => is_string($item) && trim($item) !== '')) {
			throw new ExtensionException('"license" must be a string, or a list of strings any of which applies.');
		}

		/** @var list<string> $license Checked above. */
		return implode(' or ', array_map(trim(...), $license));
	}

	/**
	 * Splits a license into its parts, in order: each license it names,
	 * with the URL of its text (`null` when it isn't a known one), and
	 * the operators between them (`or`, `and`, `with`, lowercased, with a
	 * `null` URL). Parentheses are dropped.
	 *
	 * @return list<array{text: string, url: ?string, operator: bool}>
	 */
	public static function parts(string $license): array
	{
		$parts = [];

		foreach (preg_split('/[\s()]+/', $license, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
			$operator = in_array(strtolower($word), self::OPERATORS, true);
			$parts[]  = [
				'text'     => $operator ? strtolower($word) : $word,
				'url'      => $operator ? null : self::url($word),
				'operator' => $operator
			];
		}

		return $parts;
	}

	/**
	 * Returns the URL of a known license's text, or `null`.
	 */
	public static function url(string $name): ?string
	{
		foreach (self::KNOWN as $known) {
			if (strcasecmp($known, $name) === 0) {
				return "https://spdx.org/licenses/{$known}.html";
			}
		}

		return null;
	}
}
