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
 * Checks versions against Composer-style constraints, for the `requires`
 * of a manifest (D-385): `*`, an exact version (`1.2`, `=1.2.3`), the
 * comparisons (`>=2.0`, `<3`, `!=1.4.0`), `^1.2`, `~1.2`, wildcards
 * (`1.2.*`, `2.x`), hyphen ranges (`1.0 - 2.0`), and any of them joined
 * by spaces or commas (all must hold) or `||` (any may).
 *
 * Stability is ignored, on both sides: a version's pre-release or `-dev`
 * suffix and a constraint's `@dev` flag are dropped before comparing, so
 * `2.0.0-dev` satisfies `^2.0`. A branch version (`dev-main`) satisfies
 * only `*`.
 */
final readonly class VersionConstraint
{
	/**
	 * Matches a version, once its `v` prefix and suffixes are dropped.
	 */
	private const string VERSION = '/^\d+(?:\.\d+){0,3}$/';

	/**
	 * Whether a constraint is one this class understands.
	 */
	public static function isValid(string $constraint): bool
	{
		return self::parse($constraint) !== null;
	}

	/**
	 * Whether a version satisfies a constraint. An invalid constraint is
	 * never satisfied.
	 */
	public static function satisfies(string $version, string $constraint): bool
	{
		$alternatives = self::parse($constraint);

		if ($alternatives === null) {
			return false;
		}

		$version = self::version($version);

		foreach ($alternatives as $comparisons) {
			if ($comparisons === []) {
				return true;
			}

			if ($version !== null && array_all($comparisons, static fn (array $comparison): bool => version_compare($version, $comparison[1], $comparison[0]))) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parses a constraint into alternatives, each a list of comparisons
	 * that must all hold (`[operator, version]`; none for `*`), or `null`
	 * when it isn't valid.
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
			$comparisons = self::alternative($alternative);

			if ($comparisons === null) {
				return null;
			}

			$alternatives[] = $comparisons;
		}

		return $alternatives;
	}

	/**
	 * Parses one alternative: a hyphen range, or parts that must all hold.
	 *
	 * @return ?list<array{string, string}>
	 */
	private static function alternative(string $alternative): ?array
	{
		if ($alternative === '') {
			return null;
		}

		if (preg_match('/^(\S+)\s+-\s+(\S+)$/', $alternative, $range) === 1) {
			$low  = self::version($range[1]);
			$high = self::version($range[2]);

			return $low === null || $high === null ? null : [['>=', $low], ['<=', $high]];
		}

		// An operator may be spaced from its version: ">= 2.0".
		$alternative = (string) preg_replace('/(<=|>=|!=|==|<|>|=|\^|~)\s+/', '$1', $alternative);
		$comparisons = [];

		foreach (preg_split('/\s*,\s*|\s+/', $alternative) ?: [] as $part) {
			$part = (string) preg_replace('/@[a-z]+$/i', '', $part);
			$more = self::part($part);

			if ($more === null) {
				return null;
			}

			array_push($comparisons, ...$more);
		}

		return $comparisons;
	}

	/**
	 * Parses one part: `*`, a wildcard, `^`, `~`, a comparison, or an exact
	 * version.
	 *
	 * @return ?list<array{string, string}>
	 */
	private static function part(string $part): ?array
	{
		if ($part === '*' || $part === 'x') {
			return [];
		}

		if (preg_match('/^v?(\d+(?:\.\d+){0,2})\.[*x]$/i', $part, $match) === 1) {
			$numbers = array_map(intval(...), explode('.', $match[1]));
			$upper   = $numbers;
			$upper[count($upper) - 1]++;

			return [['>=', self::join($numbers)], ['<', self::join($upper)]];
		}

		if (preg_match('/^(\^|~|<=|>=|!=|==|<|>|=)?(.+)$/', $part, $match) !== 1) {
			return null;
		}

		$version = self::version($match[2]);

		if ($version === null) {
			return null;
		}

		// The numbers as written, since `^1.2` and `^1.2.0` differ for `~`.
		$numbers = array_map(intval(...), explode('.', self::numbers($match[2])));

		return match ($match[1]) {
			'^'           => [['>=', $version], ['<', self::caret($numbers)]],
			'~'           => [['>=', $version], ['<', self::tilde($numbers)]],
			'', '=', '==' => [['==', $version]],
			default       => [[$match[1], $version]]
		};
	}

	/**
	 * The upper bound of `^`: the next version that changes the first
	 * part that isn't zero (`^1.2` is below 2.0, `^0.3` below 0.4).
	 *
	 * @param list<int> $numbers
	 */
	private static function caret(array $numbers): string
	{
		foreach ($numbers as $index => $number) {
			if ($number !== 0 || $index === count($numbers) - 1) {
				$upper = array_slice($numbers, 0, $index + 1);
				$upper[$index]++;

				return self::join(array_values($upper));
			}
		}

		return self::join([1]);
	}

	/**
	 * The upper bound of `~`: the next version of the part before the
	 * last one given (`~1.2` is below 2.0, `~1.2.3` below 1.3).
	 *
	 * @param list<int> $numbers
	 */
	private static function tilde(array $numbers): string
	{
		$upper = count($numbers) > 1 ? array_slice($numbers, 0, -1) : $numbers;
		$upper[count($upper) - 1]++;

		return self::join(array_values($upper));
	}

	/**
	 * A version's numbers, without a `v` prefix or a stability suffix,
	 * padded to three parts (`2` is `2.0.0`), or `null` when it isn't a
	 * version.
	 */
	private static function version(string $version): ?string
	{
		$numbers = self::numbers($version);

		return preg_match(self::VERSION, $numbers) === 1
			? self::join(array_map(intval(...), explode('.', $numbers)))
			: null;
	}

	/**
	 * A version without its `v` prefix, build metadata, or stability
	 * suffix (`v2.0.0-beta.1+abc` is `2.0.0`).
	 */
	private static function numbers(string $version): string
	{
		$version = ltrim(trim($version), 'vV');
		$version = (string) preg_replace('/\+.*$/', '', $version);

		return (string) preg_replace('/[-_.]?(?:dev|alpha|beta|rc|a|b|p|pl|patch)(?:[.-]?\d+)*$/i', '', $version);
	}

	/**
	 * Joins numbers as a version of at least three parts.
	 *
	 * @param list<int> $numbers
	 */
	private static function join(array $numbers): string
	{
		return implode('.', array_pad($numbers, 3, 0));
	}
}
