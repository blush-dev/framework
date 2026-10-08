<?php

/**
 * Folder pattern.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use DateTimeInterface;
use Stringable;

/**
 * The folders a collection or profiles type keeps its files in below its
 * own (D-629), written after the type's folder in `folder`:
 * `_posts/{year}` keeps a post published in 2026 in `_posts/2026`. Each
 * folder is one token: `{year}`, `{month}` (after `{year}`), or
 * `{initial}`, the slug's first letter or digit (`tags/{initial}` keeps
 * `hello` in `tags/h`).
 *
 * The folders are only where a file is kept, never part of an entry's
 * key or address, so a pattern can change, and a file move, without
 * either changing.
 */
final readonly class FolderPattern implements Stringable
{
	/**
	 * Each token and the `DateTimeInterface::format()` format it takes,
	 * or `null` for one made from the slug.
	 */
	private const array TOKENS = [
		'year'    => 'Y',
		'month'   => 'm',
		'initial' => null
	];

	/**
	 * The folder `{initial}` gives a slug that doesn't start with a
	 * letter or digit.
	 */
	private const string OTHER = '0';

	/**
	 * The tokens, in order.
	 *
	 * @var list<string>
	 */
	public array $tokens;

	/**
	 * @throws InvalidContentType When the pattern isn't one.
	 */
	public function __construct(public string $pattern)
	{
		$segments = explode('/', $pattern);
		$tokens   = array_map(static fn (string $segment): string => preg_match('/^\{([a-z]+)\}$/', $segment, $match) === 1 ? $match[1] : '', $segments);
		$problem  = match (true) {
			in_array('', $tokens, true)                                          => 'must be tokens, one to a folder.',
			array_diff($tokens, array_keys(self::TOKENS)) !== []                 => sprintf('uses {%s}; the tokens are {%s}.', implode('}, {', array_diff($tokens, array_keys(self::TOKENS))), implode('}, {', array_keys(self::TOKENS))),
			count($tokens) !== count(array_unique($tokens))                      => 'uses a token twice.',
			in_array('month', $tokens, true) && ! self::before('year', 'month', $tokens) => 'needs {year} before {month}.',
			default                                                              => null
		};

		if ($problem !== null) {
			throw new InvalidContentType(sprintf('The folder pattern "%s" %s', $pattern, $problem));
		}

		$this->tokens = $tokens;
	}

	/**
	 * Splits a type's `folder` into the type's own folder and the pattern
	 * after it: `_posts/{year}` is `_posts` and `{year}`, and `_posts` is
	 * `_posts` and `null`. The pattern starts at the first folder with a
	 * `{` in it.
	 *
	 * @return array{string, ?string}
	 */
	public static function split(string $folder): array
	{
		$segments = $folder === '' ? [] : explode('/', $folder);
		$first    = array_find_key($segments, static fn (string $segment): bool => str_contains($segment, '{'));

		if ($first === null) {
			return [$folder, null];
		}

		return [implode('/', array_slice($segments, 0, $first)), implode('/', array_slice($segments, $first))];
	}

	/**
	 * Returns the folders for an entry's slug and date, as a path:
	 * `2026/10`, say.
	 */
	public function path(string $slug, DateTimeInterface $date): string
	{
		return implode('/', $this->segments($slug, $date));
	}

	/**
	 * Returns the folders for an entry's slug and date.
	 *
	 * @return list<string>
	 */
	public function segments(string $slug, DateTimeInterface $date): array
	{
		return array_map(static fn (string $token): string => self::TOKENS[$token] === null ? self::initial($slug) : $date->format(self::TOKENS[$token]), $this->tokens);
	}

	/**
	 * Returns whether folders have the shape the pattern gives them: one
	 * for each token, with digits where a date's are, and one letter or
	 * digit for `{initial}`.
	 *
	 * @param list<string> $segments
	 */
	public function explains(array $segments): bool
	{
		if (count($segments) !== count($this->tokens)) {
			return false;
		}

		foreach ($this->tokens as $index => $token) {
			$shape = match ($token) {
				'year'  => '/^\d{4}$/',
				'month' => '/^(0[1-9]|1[0-2])$/',
				default => '/^[\p{Ll}\p{Lo}\d]$/u'
			};

			if (preg_match($shape, $segments[$index]) !== 1) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns whether the folders need a date.
	 */
	public function isDated(): bool
	{
		return array_any($this->tokens, static fn (string $token): bool => self::TOKENS[$token] !== null);
	}

	/**
	 * Returns the pattern.
	 */
	public function __toString(): string
	{
		return $this->pattern;
	}

	/**
	 * Returns a slug's first letter or digit, lowercased, for `{initial}`.
	 */
	private static function initial(string $slug): string
	{
		$first = mb_strtolower(mb_substr($slug, 0, 1));

		return preg_match('/^[\p{Ll}\p{Lo}\d]$/u', $first) === 1 ? $first : self::OTHER;
	}

	/**
	 * Returns whether one token comes before another.
	 *
	 * @param list<string> $tokens
	 */
	private static function before(string $first, string $second, array $tokens): bool
	{
		$a = array_search($first, $tokens, true);
		$b = array_search($second, $tokens, true);

		return $a !== false && $b !== false && $a < $b;
	}
}
