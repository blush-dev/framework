<?php

/**
 * API includes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Api;

/**
 * What `?include=` expands in place (D-738): `media`, `relations`, or
 * one relation (`relations.authors`). One level deep: what's included
 * has stubs of its own.
 */
final readonly class ApiIncludes
{
	/**
	 * @param bool              $media     Whether media stubs become records.
	 * @param bool|list<string> $relations Every relation's stubs (`true`), none, or those of the relations named.
	 */
	public function __construct(
		public bool $media = false,
		public bool|array $relations = false
	) {}

	/**
	 * Reads `include`'s values.
	 *
	 * @param  list<string> $values
	 * @throws ApiError When one isn't something that can be included.
	 */
	public static function from(array $values): self
	{
		$media     = false;
		$relations = [];
		$every     = false;

		foreach ($values as $value) {
			match (true) {
				$value === 'media'     => $media = true,
				$value === 'relations' => $every = true,
				preg_match('/^relations\.([a-z][a-z0-9_]*)$/', $value, $matches) === 1 => $relations[] = $matches[1],
				default                => throw ApiError::invalidQuery(sprintf('"%s" can\'t be included; use media, relations, or relations.{relation}.', $value))
			};
		}

		return new self($media, $every ?: ($relations === [] ? false : $relations));
	}

	/**
	 * Returns whether a relation's stubs become entries.
	 */
	public function includesRelation(string $name): bool
	{
		return $this->relations === true || (is_array($this->relations) && in_array($name, $this->relations, true));
	}
}
