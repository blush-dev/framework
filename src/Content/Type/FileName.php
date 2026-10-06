<?php

/**
 * File name pattern.
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
 * How a collection names the files it creates (D-511), such as
 * `{date}.{slug}` for `2026-10-05.hello.md`. A pattern ends in `{slug}`,
 * and anything before it ends in a `.`, so the slug is still the name
 * after its last `.` and reading files doesn't change (D-078): a pattern
 * only says what new files are called, so it can change over time
 * without moving any entry.
 *
 * The prefix may use the date tokens (`{date}` is `Y-m-d`, `{time}` is
 * `His`, and `{year}`, `{month}`, `{day}`, `{hour}`, `{minute}`, and
 * `{second}`), letters, digits, `-`, `_`, and `.`, and doesn't start with
 * `_` (which hides a file) or `.`.
 */
final readonly class FileName implements Stringable
{
	/**
	 * The slug's token.
	 */
	public const string SLUG = '{slug}';

	/**
	 * A slug alone (`hello.md`), every type's default.
	 */
	public const string PLAIN = self::SLUG;

	/**
	 * The date, then the slug (`2026-10-05.hello.md`).
	 */
	public const string DATED = '{date}.' . self::SLUG;

	/**
	 * The date tokens and their `DateTimeInterface::format()` formats.
	 */
	private const array TOKENS = [
		'date'   => 'Y-m-d',
		'time'   => 'His',
		'year'   => 'Y',
		'month'  => 'm',
		'day'    => 'd',
		'hour'   => 'H',
		'minute' => 'i',
		'second' => 's'
	];

	/**
	 * @throws InvalidContentType When the pattern isn't one.
	 */
	public function __construct(public string $pattern)
	{
		$problem = self::problem($pattern);

		if ($problem !== null) {
			throw new InvalidContentType(sprintf('The file name pattern "%s" %s', $pattern, $problem));
		}
	}

	/**
	 * Returns the pattern a type has when it doesn't name one: the slug
	 * alone, whatever its date archives (D-515).
	 */
	public static function byDefault(): self
	{
		return new self(self::PLAIN);
	}

	/**
	 * Returns the file name for a slug and date, without the extension.
	 */
	public function name(string $slug, DateTimeInterface $date): string
	{
		return $this->prefix($date) . $slug;
	}

	/**
	 * Returns what comes before the slug for a date: `''` for a slug
	 * alone, else ending in `.`.
	 */
	public function prefix(DateTimeInterface $date): string
	{
		$prefix = substr($this->pattern, 0, -strlen(self::SLUG));

		return (string) preg_replace_callback('/\{([a-z]+)\}/', static fn (array $match): string => $date->format(self::TOKENS[$match[1]]), $prefix);
	}

	/**
	 * Returns whether a file name (without its extension or language
	 * suffix) has the shape the pattern gives names: the pattern's prefix,
	 * with digits where its tokens are, then a slug.
	 */
	public function explains(string $name): bool
	{
		$prefix = substr($this->pattern, 0, -strlen(self::SLUG));
		$parts  = preg_split('/(\{[a-z]+\})/', $prefix, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
		$regex  = implode('', array_map(static fn (string $part): string => match ($part) {
			'{date}'          => '\d{4}-\d{2}-\d{2}',
			'{time}'          => '\d{6}',
			'{year}'          => '\d{4}',
			'{month}', '{day}', '{hour}', '{minute}', '{second}' => '\d{2}',
			default           => preg_quote($part, '/')
		}, $parts));

		return preg_match("/^{$regex}[^.]+\$/", $name) === 1;
	}

	/**
	 * Returns whether the pattern has a date in it, so naming a file
	 * needs one.
	 */
	public function isDated(): bool
	{
		return preg_match('/\{(?!slug\})[a-z]+\}/', $this->pattern) === 1;
	}

	/**
	 * Returns the pattern.
	 */
	public function __toString(): string
	{
		return $this->pattern;
	}

	/**
	 * Returns what's wrong with a pattern, finishing a sentence, or
	 * `null` when nothing is.
	 */
	private static function problem(string $pattern): ?string
	{
		if (! str_ends_with($pattern, self::SLUG)) {
			return 'must end in {slug}.';
		}

		$prefix = substr($pattern, 0, -strlen(self::SLUG));

		if ($prefix === '') {
			return null;
		}

		if (! str_ends_with($prefix, '.')) {
			return 'needs a "." before {slug}, which starts the slug.';
		}

		preg_match_all('/\{([^}]*)\}/', $prefix, $tokens);

		$unknown = array_diff($tokens[1], array_keys(self::TOKENS));

		if ($unknown !== []) {
			return sprintf('uses {%s}; the tokens are {slug} and {%s}.', implode('}, {', $unknown), implode('}, {', array_keys(self::TOKENS)));
		}

		$literal = (string) preg_replace('/\{[a-z]+\}/', '0', $prefix);

		return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $literal) === 1
			? null
			: 'may use only the tokens, letters, digits, "-", "_", and ".", and can\'t start with "_" or ".".';
	}
}
