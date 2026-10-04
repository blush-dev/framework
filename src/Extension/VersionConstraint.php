<?php

/**
 * Version constraint.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

/**
 * Checks versions against Composer's constraints, for the `require` of a
 * manifest (D-385), by Composer's rules (`composer/semver`'s
 * `VersionParser`, D-429), since a version may come from a manifest, a
 * `composer.json`, or Composer's `installed.json`: `*`, an exact version
 * (`1.2`, `=1.2.3`), the comparisons (`>=2.0`, `<3`, `!=1.4.0`, `<>`),
 * `^1.2`, `~1.2`, wildcards (`1.2.*`, `2.x`), hyphen ranges (`1.0 - 2`),
 * and any of them joined by spaces or commas (all must hold) or `||`
 * (any may).
 *
 * Versions are normalized as Composer does (`v1.2` is `1.2.0.0`,
 * `2.0-b1` is `2.0.0.0-beta1`), and stability counts as it does in
 * Composer's constraints: `^2.0` and `>=2.0` take `2.0.0-dev` and
 * `2.0.0-beta1`, `<2.0` doesn't, and `>=2.0@beta` starts at the first
 * beta. A branch version (`dev-main`) satisfies only `*`, its own name,
 * and `!=`. Aliases (`… as 1.2.3`) and `#commit` references are read as
 * Composer reads them, and `@stability` flags count only as Composer
 * counts them in a constraint (there's no `minimum-stability`).
 */
final readonly class VersionConstraint
{
	/**
	 * A version's stability modifier: its stability, the stability's
	 * number, and `dev`.
	 */
	private const string MODIFIER = '[._-]?(?:(stable|beta|b|RC|alpha|a|patch|pl|p)((?:[.-]?\d+)*+)?)?([.-]?dev)?';

	/**
	 * The stabilities a `@` flag may name.
	 */
	private const string STABILITIES = 'stable|RC|beta|alpha|dev';

	/**
	 * A version in a constraint: its four numbers (1 to 4), modifier (5
	 * to 7), and an `x-dev` (8).
	 */
	private const string VERSION = 'v?(\d++)(?:\.(\d++))?(?:\.(\d++))?(?:\.(\d++))?(?:' . self::MODIFIER . '|\.([xX*][.-]?dev))(?:\+[^\s]+)?';

	/**
	 * Whether a constraint is one Composer understands.
	 */
	public static function isValid(string $constraint): bool
	{
		return self::parse($constraint) !== null;
	}

	/**
	 * Whether a version satisfies a constraint. An invalid constraint is
	 * never satisfied, and a version Composer can't read satisfies only
	 * `*`.
	 */
	public static function satisfies(string $version, string $constraint): bool
	{
		$alternatives = self::parse($constraint);

		if ($alternatives === null) {
			return false;
		}

		$version = self::normalize($version);

		foreach ($alternatives as $comparisons) {
			if ($comparisons === []) {
				return true;
			}

			if ($version !== null && array_all($comparisons, static fn (array $comparison): bool => self::compare($version, $comparison[1], $comparison[0]))) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalizes a version as Composer does (`v1.2-beta` is
	 * `1.2.0.0-beta`, `1.x-dev` is `1.9999999.9999999.9999999-dev`, and a
	 * branch is `dev-{name}`), or `null` when it isn't one.
	 */
	public static function normalize(string $version): ?string
	{
		$version = trim($version);

		if (preg_match('/^([^,\s]++) ++as ++([^,\s]++)$/', $version, $alias) === 1) {
			$version = $alias[1];
		}

		$version = (string) preg_replace('/@(?:' . self::STABILITIES . ')$/i', '', $version);

		if (in_array($version, ['master', 'trunk', 'default'], true)) {
			$version = "dev-{$version}";
		}

		if (stripos($version, 'dev-') === 0) {
			return 'dev-' . substr($version, 4);
		}

		// Build metadata.
		$version = (string) preg_replace('/^([^,\s+]++)\+[^\s]++$/', '$1', $version);

		if (preg_match('/^v?(\d{1,5}+)(\.\d++)?(\.\d++)?(\.\d++)?' . self::MODIFIER . '$/i', $version, $match, PREG_UNMATCHED_AS_NULL) === 1) {
			$normal = $match[1]
				. (self::filled($match[2]) ? $match[2] : '.0')
				. (self::filled($match[3]) ? $match[3] : '.0')
				. (self::filled($match[4]) ? $match[4] : '.0');
			[$stability, $number, $dev] = [$match[5], $match[6], $match[7]];
		} elseif (preg_match('/^v?(\d{4}(?:[.:-]?\d{2}){1,6}(?:[.:-]?\d{1,3}){0,2})' . self::MODIFIER . '$/i', $version, $match, PREG_UNMATCHED_AS_NULL) === 1) {
			// A date version.
			$normal = (string) preg_replace('/\D/', '.', (string) $match[1]);

			[$stability, $number, $dev] = [$match[2], $match[3], $match[4]];
		} else {
			// A numbered branch (`1.x-dev`); a named one needs `dev-`.
			return preg_match('/^(.*?)[.-]?dev$/i', $version, $match) === 1 ? self::branch($match[1]) : null;
		}

		if ($stability !== null) {
			if ($stability === 'stable') {
				return $normal;
			}

			$normal .= '-' . self::stability($stability) . ltrim((string) $number, '.-');
		}

		if (self::filled($dev)) {
			$normal .= '-dev';
		}

		return $normal;
	}

	/**
	 * Parses a constraint into alternatives, each a list of comparisons
	 * that must all hold (`[operator, normalized version]`; none for
	 * `*`), or `null` when it isn't valid.
	 *
	 * @return ?list<list<array{string, string}>>
	 */
	private static function parse(string $constraint): ?array
	{
		$constraint = trim($constraint);

		if ($constraint === '') {
			return null;
		}

		$alternatives = [];

		foreach (preg_split('/\s*\|\|?\s*/', $constraint) ?: [] as $alternative) {
			$comparisons = [];

			foreach (preg_split('/(?<!^|as|[=>< ,]) *(?<!-)[, ](?!-) *(?!,|as|$)/', $alternative) ?: [] as $part) {
				$more = self::part($part);

				if ($more === null) {
					return null;
				}

				array_push($comparisons, ...$more);
			}

			$alternatives[] = $comparisons;
		}

		return $alternatives;
	}

	/**
	 * Parses one part of a constraint, as Composer's `parseConstraint()`
	 * does: `*`, `~`, `^`, a wildcard, a hyphen range, or a comparison.
	 *
	 * @return ?list<array{string, string}>
	 */
	private static function part(string $constraint): ?array
	{
		if (preg_match('/^([^,\s]++) ++as ++([^,\s]++)$/', $constraint, $alias) === 1) {
			$constraint = $alias[1];
		}

		$flag = null;

		if (preg_match('/^([^,\s]*?)@(' . self::STABILITIES . ')$/i', $constraint, $match) === 1) {
			$constraint = $match[1] !== '' ? $match[1] : '*';
			$flag       = $match[2] !== 'stable' ? $match[2] : null;
		}

		// A `#commit` reference is only for Composer.
		$constraint = (string) preg_replace('/^(dev-[^,\s@]+?|[^,\s@]+?\.x-dev)#.+$/i', '$1', $constraint);

		if (preg_match('/^(v)?[xX*](\.[xX*])*$/i', $constraint, $match, PREG_UNMATCHED_AS_NULL) === 1) {
			return self::filled($match[1]) || self::filled($match[2]) ? [['>=', '0.0.0.0-dev']] : [];
		}

		if (preg_match('/^~>?' . self::VERSION . '$/i', $constraint, $match, PREG_UNMATCHED_AS_NULL) === 1) {
			return str_starts_with($constraint, '~>') ? null : self::tilde($constraint, $match);
		}

		if (preg_match('/^\^' . self::VERSION . '$/i', $constraint, $match, PREG_UNMATCHED_AS_NULL) === 1) {
			return self::caret($constraint, $match);
		}

		if (preg_match('/^v?(\d++)(?:\.(\d++))?(?:\.(\d++))?(?:\.[xX*])++$/', $constraint, $match, PREG_UNMATCHED_AS_NULL) === 1) {
			$position = self::filled($match[3]) ? 3 : (self::filled($match[2]) ? 2 : 1);
			$low      = self::bump($match, $position) . '-dev';
			$high     = self::bump($match, $position, 1) . '-dev';

			return $low === '0.0.0.0-dev' ? [['<', $high]] : [['>=', $low], ['<', $high]];
		}

		if (preg_match('/^(' . self::VERSION . ') +- +(' . self::VERSION . ')$/i', $constraint, $match, PREG_UNMATCHED_AS_NULL) === 1) {
			return self::hyphen($match);
		}

		return self::comparison($constraint, $flag);
	}

	/**
	 * A tilde range: from the version, below the next version of the part
	 * before the last one given (`~1.2` is below 2.0, `~1.2.3` below 1.3).
	 *
	 * @param  array<array-key, ?string> $match
	 * @return ?list<array{string, string}>
	 */
	private static function tilde(string $constraint, array $match): ?array
	{
		$position = match (true) {
			self::filled($match[4]) => 4,
			self::filled($match[3]) => 3,
			self::filled($match[2]) => 2,
			default                 => 1
		};

		// `2.x-dev` and `3.0.x-dev` count the `x` as a part.
		if (self::filled($match[8])) {
			$position++;
		}

		$low = self::normalize(substr($constraint . (self::suffixed($match, 5) ? '' : '-dev'), 1));

		return $low === null ? null : [['>=', $low], ['<', self::bump($match, max(1, $position - 1), 1) . '-dev']];
	}

	/**
	 * A caret range: from the version, below the next version that changes
	 * its first part that isn't zero (`^1.2` is below 2.0, `^0.3` below
	 * 0.4, `^0.0.3` below 0.0.4).
	 *
	 * @param  array<array-key, ?string> $match
	 * @return ?list<array{string, string}>
	 */
	private static function caret(string $constraint, array $match): ?array
	{
		$position = match (true) {
			$match[1] !== '0' || ! self::filled($match[2]) => 1,
			$match[2] !== '0' || ! self::filled($match[3]) => 2,
			default                                        => 3
		};

		$low = self::normalize(substr($constraint . (self::suffixed($match, 5) ? '' : '-dev'), 1));

		return $low === null ? null : [['>=', $low], ['<', self::bump($match, $position, 1) . '-dev']];
	}

	/**
	 * A hyphen range: from the first version, to the second inclusive. A
	 * partial second version takes everything that starts with it
	 * (`1.0 - 2.1` is below 2.2).
	 *
	 * @param  array<array-key, ?string> $match
	 * @return ?list<array{string, string}>
	 */
	private static function hyphen(array $match): ?array
	{
		$low  = self::normalize((string) $match[1]);
		$high = self::normalize((string) $match[10]);

		if ($low === null || $high === null) {
			return null;
		}

		$low .= self::suffixed($match, 6) ? '' : '-dev';

		if ((self::filled($match[12]) && self::filled($match[13])) || self::suffixed($match, 15)) {
			return [['>=', $low], ['<=', $high]];
		}

		$parts = [1 => $match[11], 2 => $match[12], 3 => $match[13], 4 => $match[14]];

		return [['>=', $low], ['<', self::bump($parts, self::filled($match[12]) ? 2 : 1, 1) . '-dev']];
	}

	/**
	 * A comparison, or an exact version. `<` and `>=` take the version's
	 * dev releases unless it names a stability (`<2.0` is below
	 * `2.0.0.0-dev`), and a `@` flag lowers a stable version to that
	 * stability.
	 *
	 * @return ?list<array{string, string}>
	 */
	private static function comparison(string $constraint, ?string $flag): ?array
	{
		preg_match('/^(<>|!=|>=?|<=?|==?)?\s*(.*)/', $constraint, $match, PREG_UNMATCHED_AS_NULL);

		$given   = (string) ($match[2] ?? '');
		$version = self::normalize($given);

		// `foo-dev` is read as the branch `dev-foo`.
		if ($version === null && str_ends_with($given, '-dev') && preg_match('/^[0-9a-zA-Z-.\/]+$/', $given) === 1) {
			$version = self::normalize('dev-' . substr($given, 0, -4));
		}

		if ($version === null) {
			return null;
		}

		$operator = match ($match[1] ?? '') {
			'', '=', '==' => '==',
			'<>', '!='    => '!=',
			default       => (string) $match[1]
		};

		if ($operator !== '==' && $flag !== null && self::isStable($version)) {
			$version .= "-{$flag}";
		} elseif (($operator === '<' || $operator === '>=') && preg_match('/-' . self::MODIFIER . '$/', strtolower($given)) !== 1 && ! str_starts_with($given, 'dev-')) {
			$version .= '-dev';
		}

		return [[$operator, $version]];
	}

	/**
	 * Compares two normalized versions as Composer does: a branch equals
	 * only itself, and is otherwise never above or below anything.
	 */
	private static function compare(string $a, string $b, string $operator): bool
	{
		$aIsBranch = str_starts_with($a, 'dev-');
		$bIsBranch = str_starts_with($b, 'dev-');

		if ($operator === '!=' && ($aIsBranch || $bIsBranch)) {
			return $a !== $b;
		}

		if ($aIsBranch || $bIsBranch) {
			return $aIsBranch && $bIsBranch && $operator === '==' && $a === $b;
		}

		return version_compare($a, $b, $operator);
	}

	/**
	 * Normalizes a numbered branch (`1.2.x` is
	 * `1.2.9999999.9999999-dev`), or `null` for a named one.
	 */
	private static function branch(string $name): ?string
	{
		if (preg_match('/^v?(\d++)(\.(?:\d++|[xX*]))?(\.(?:\d++|[xX*]))?(\.(?:\d++|[xX*]))?$/i', trim($name), $match, PREG_UNMATCHED_AS_NULL) !== 1) {
			return null;
		}

		$version = '';

		for ($i = 1; $i < 5; $i++) {
			$version .= isset($match[$i]) ? str_replace(['*', 'X'], 'x', $match[$i]) : '.x';
		}

		return str_replace('x', '9999999', $version) . '-dev';
	}

	/**
	 * A version from a match's numbers, the parts after a position zeroed
	 * and the one at it raised by an increment.
	 *
	 * @param array<array-key, ?string> $match
	 */
	private static function bump(array $match, int $position, int $increment = 0): string
	{
		$numbers = [];

		for ($i = 1; $i <= 4; $i++) {
			$number    = $i > $position ? 0 : (int) ($match[$i] ?? 0);
			$numbers[] = $i === $position ? $number + $increment : $number;
		}

		return implode('.', $numbers);
	}

	/**
	 * Whether a normalized version is stable (or a patch).
	 */
	private static function isStable(string $version): bool
	{
		return preg_match('/^[\d.]+(?:-patch[\d.-]*)?$/', $version) === 1;
	}

	/**
	 * Whether a matched version names a stability, `dev`, or an `x-dev`,
	 * from the match's stability group.
	 *
	 * @param array<array-key, ?string> $match
	 */
	private static function suffixed(array $match, int $stability): bool
	{
		return self::filled($match[$stability] ?? null)
			|| self::filled($match[$stability + 2] ?? null)
			|| self::filled($match[$stability + 3] ?? null);
	}

	/**
	 * Composer's name for a stability (`b` is `beta`, `p` is `patch`).
	 */
	private static function stability(string $stability): string
	{
		return match (strtolower($stability)) {
			'a'       => 'alpha',
			'b'       => 'beta',
			'p', 'pl' => 'patch',
			'rc'      => 'RC',
			default   => strtolower($stability)
		};
	}

	/**
	 * Whether a matched group holds something (`0` does).
	 */
	private static function filled(?string $group): bool
	{
		return $group !== null && $group !== '';
	}
}
