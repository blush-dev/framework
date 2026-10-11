<?php

/**
 * API query parameters.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Api;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A request's query parameters, read for one endpoint (D-740): only the
 * names it takes are allowed, so a mistyped one is an error rather than
 * a filter silently ignored. `rel:{name}` parameters are allowed where
 * the endpoint takes relations. Lists are comma-separated.
 */
final readonly class ApiQuery
{
	/**
	 * The most items a page may hold (D-737).
	 */
	public const int MAX_PER_PAGE = 100;

	/**
	 * How many items a page holds when the request doesn't say.
	 */
	public const int PER_PAGE = 20;

	/**
	 * The prefix of a relation filter's name (D-740).
	 */
	public const string RELATION = 'rel:';

	/**
	 * @param array<string, string> $params
	 */
	private function __construct(private array $params)
	{}

	/**
	 * Reads a request's parameters, allowing only those named, and
	 * `rel:{name}` ones when `$relations` is set.
	 *
	 * @param  list<string> $allowed
	 * @throws ApiError When a parameter isn't allowed or isn't text.
	 */
	public static function from(ServerRequestInterface $request, array $allowed, bool $relations = false): self
	{
		$params = [];

		foreach ($request->getQueryParams() as $name => $value) {
			$name = (string) $name;

			if (! in_array($name, $allowed, true) && ! ($relations && str_starts_with($name, self::RELATION) && strlen($name) > strlen(self::RELATION))) {
				throw ApiError::invalidQuery(sprintf('"%s" isn\'t a parameter here; use %s.', $name, $allowed === [] ? 'none' : implode(', ', $allowed) . ($relations ? ', or rel:{relation}' : '')));
			}

			if (! is_string($value)) {
				throw ApiError::invalidQuery(sprintf('"%s" takes one value; use commas for several.', $name));
			}

			$params[$name] = $value;
		}

		ksort($params, SORT_STRING);

		return new self($params);
	}

	/**
	 * Returns the parameters, sorted by name, as a cache key reads them.
	 *
	 * @return array<string, string>
	 */
	public function all(): array
	{
		return $this->params;
	}

	/**
	 * Returns whether a parameter was given.
	 */
	public function has(string $name): bool
	{
		return isset($this->params[$name]);
	}

	/**
	 * Returns a parameter's value trimmed, or `null` when it wasn't
	 * given or is empty.
	 */
	public function string(string $name): ?string
	{
		$value = trim($this->params[$name] ?? '');

		return $value === '' ? null : $value;
	}

	/**
	 * Returns a comma-separated parameter's values, trimmed, once each.
	 *
	 * @return list<string>
	 */
	public function list(string $name): array
	{
		$values = array_map(trim(...), explode(',', $this->params[$name] ?? ''));

		return array_values(array_unique(array_filter($values, static fn (string $value): bool => $value !== '')));
	}

	/**
	 * Returns a whole number of at least `$min` and at most `$max`, or
	 * the default when it wasn't given.
	 *
	 * @throws ApiError
	 */
	public function int(string $name, int $default, int $min = 1, ?int $max = null): int
	{
		$value = $this->string($name);

		if ($value === null) {
			return $default;
		}

		if (preg_match('/^\d{1,9}$/', $value) !== 1 || (int) $value < $min || ($max !== null && (int) $value > $max)) {
			throw ApiError::invalidQuery(sprintf('"%s" must be a whole number from %d%s.', $name, $min, $max === null ? ' up' : " to {$max}"));
		}

		return (int) $value;
	}

	/**
	 * Returns the page asked for, from 1.
	 *
	 * @throws ApiError
	 */
	public function page(): int
	{
		return $this->int('page', 1);
	}

	/**
	 * Returns how many items a page holds.
	 *
	 * @throws ApiError
	 */
	public function perPage(): int
	{
		return $this->int('per_page', self::PER_PAGE, 1, self::MAX_PER_PAGE);
	}

	/**
	 * Returns one of a closed set of values, or the default.
	 *
	 * @param  list<string> $values
	 * @throws ApiError
	 */
	public function choice(string $name, array $values, string $default): string
	{
		$value = $this->string($name) ?? $default;

		return in_array($value, $values, true)
			? $value
			: throw ApiError::invalidQuery(sprintf('"%s" is one of: %s.', $name, implode(', ', $values)));
	}

	/**
	 * Returns a date and time, read in a time zone when it doesn't give
	 * its own, or `null` when it wasn't given.
	 *
	 * @throws ApiError
	 */
	public function date(string $name, DateTimeZone $zone): ?DateTimeImmutable
	{
		$value = $this->string($name);

		if ($value === null) {
			return null;
		}

		try {
			return preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ][\d:.]+)?(?:Z|[+-]\d{2}:?\d{2})?$/', $value) === 1
				? new DateTimeImmutable($value, $zone)
				: throw ApiError::invalidQuery(sprintf('"%s" must be a date, such as 2026-10-01 or 2026-10-01T09:00:00Z.', $name));
		} catch (Exception) {
			throw ApiError::invalidQuery(sprintf('"%s" must be a date, such as 2026-10-01 or 2026-10-01T09:00:00Z.', $name));
		}
	}

	/**
	 * Returns the relation filters (`rel:{name}`), each relation's
	 * values by its name.
	 *
	 * @return array<string, list<string>>
	 */
	public function relations(): array
	{
		$relations = [];

		foreach (array_keys($this->params) as $name) {
			if (str_starts_with($name, self::RELATION)) {
				$values = $this->list($name);

				if ($values !== []) {
					$relations[substr($name, strlen(self::RELATION))] = $values;
				}
			}
		}

		return $relations;
	}
}
