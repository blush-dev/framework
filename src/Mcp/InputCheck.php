<?php

/**
 * MCP input check.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp;

/**
 * Checks a tool's input against its JSON Schema (D-757) before the tool
 * runs, for the parts of JSON Schema tools use: `type` (one or a list),
 * `properties`, `required`, `additionalProperties: false`, `enum`,
 * `items`, `minLength`, `maxLength`, `minimum`, `maximum`, `minItems`,
 * and `maxItems`. What it doesn't know it lets by.
 */
final class InputCheck
{
	/**
	 * Returns what's wrong with a value under a schema, in words, or
	 * `null` when nothing is.
	 *
	 * @param array<string, mixed> $schema
	 */
	public static function problem(mixed $value, array $schema, string $at = 'input'): ?string
	{
		$types = $schema['type'] ?? null;
		$types = is_string($types) ? [$types] : (is_array($types) ? $types : []);

		if ($types !== [] && ! array_any($types, static fn (mixed $type): bool => is_string($type) && self::is($value, $type))) {
			return sprintf('%s must be %s.', $at, implode(' or ', array_map(self::named(...), array_filter($types, is_string(...)))));
		}

		if (is_array($schema['enum'] ?? null) && ! in_array($value, $schema['enum'], true)) {
			return sprintf('%s must be one of: %s.', $at, implode(', ', array_map(static fn (mixed $option): string => json_encode($option) ?: '', $schema['enum'])));
		}

		return match (true) {
			is_string($value)                         => self::text($value, $schema, $at),
			is_int($value) || is_float($value)        => self::number($value, $schema, $at),
			is_array($value) && array_is_list($value) && ! self::isObjectSchema($schema) => self::list($value, $schema, $at),
			is_array($value)                          => self::object($value, $schema, $at),
			default                                   => null
		};
	}

	/**
	 * Whether a value is of a JSON type.
	 */
	private static function is(mixed $value, string $type): bool
	{
		return match ($type) {
			'string'  => is_string($value),
			'integer' => is_int($value),
			'number'  => is_int($value) || is_float($value),
			'boolean' => is_bool($value),
			'null'    => $value === null,
			'array'   => is_array($value) && array_is_list($value),
			'object'  => is_array($value) && ($value === [] || ! array_is_list($value)),
			default   => true
		};
	}

	/**
	 * Whether a schema is for an object.
	 *
	 * @param array<string, mixed> $schema
	 */
	private static function isObjectSchema(array $schema): bool
	{
		$types = $schema['type'] ?? null;

		return $types === 'object' || (is_array($types) && in_array('object', $types, true) && ! in_array('array', $types, true));
	}

	/**
	 * @param array<string, mixed> $schema
	 */
	private static function text(string $value, array $schema, string $at): ?string
	{
		$length = mb_strlen($value);

		return match (true) {
			is_int($schema['minLength'] ?? null) && $length < $schema['minLength'] => sprintf('%s must be at least %d characters.', $at, $schema['minLength']),
			is_int($schema['maxLength'] ?? null) && $length > $schema['maxLength'] => sprintf('%s must be at most %d characters.', $at, $schema['maxLength']),
			default                                                                => null
		};
	}

	/**
	 * @param array<string, mixed> $schema
	 */
	private static function number(int|float $value, array $schema, string $at): ?string
	{
		return match (true) {
			is_numeric($schema['minimum'] ?? null) && $value < $schema['minimum'] => sprintf('%s must be at least %s.', $at, $schema['minimum']),
			is_numeric($schema['maximum'] ?? null) && $value > $schema['maximum'] => sprintf('%s must be at most %s.', $at, $schema['maximum']),
			default                                                              => null
		};
	}

	/**
	 * @param list<mixed>          $value
	 * @param array<string, mixed> $schema
	 */
	private static function list(array $value, array $schema, string $at): ?string
	{
		if (is_int($schema['minItems'] ?? null) && count($value) < $schema['minItems']) {
			return sprintf('%s must have at least %d items.', $at, $schema['minItems']);
		}

		if (is_int($schema['maxItems'] ?? null) && count($value) > $schema['maxItems']) {
			return sprintf('%s must have at most %d items.', $at, $schema['maxItems']);
		}

		$items = $schema['items'] ?? null;

		if (! is_array($items)) {
			return null;
		}

		foreach ($value as $index => $item) {
			$problem = self::problem($item, self::schema($items), "{$at}[{$index}]");

			if ($problem !== null) {
				return $problem;
			}
		}

		return null;
	}

	/**
	 * @param array<mixed>         $value
	 * @param array<string, mixed> $schema
	 */
	private static function object(array $value, array $schema, string $at): ?string
	{
		$properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

		foreach (is_array($schema['required'] ?? null) ? $schema['required'] : [] as $name) {
			if (is_string($name) && ! array_key_exists($name, $value)) {
				return sprintf('%s needs "%s".', $at, $name);
			}
		}

		foreach ($value as $name => $item) {
			$property = $properties[$name] ?? null;
			$path     = $at === 'input' ? (string) $name : "{$at}.{$name}";

			if (! is_array($property)) {
				if (($schema['additionalProperties'] ?? true) === false) {
					return sprintf('%s has no "%s"; it takes %s.', $at, $name, $properties === [] ? 'nothing' : implode(', ', array_map(static fn (int|string $key): string => '"' . $key . '"', array_keys($properties))));
				}

				continue;
			}

			$problem = self::problem($item, self::schema($property), $path);

			if ($problem !== null) {
				return $problem;
			}
		}

		return null;
	}

	/**
	 * Returns a schema's keywords.
	 *
	 * @param  array<mixed>         $schema
	 * @return array<string, mixed>
	 */
	private static function schema(array $schema): array
	{
		$keywords = [];

		foreach ($schema as $key => $value) {
			if (is_string($key)) {
				$keywords[$key] = $value;
			}
		}

		return $keywords;
	}

	/**
	 * Names a JSON type as a sentence does.
	 */
	private static function named(string $type): string
	{
		return match ($type) {
			'string'  => 'text',
			'integer' => 'a whole number',
			'number'  => 'a number',
			'boolean' => 'true or false',
			'null'    => 'null',
			'array'   => 'a list',
			'object'  => 'an object',
			default   => $type
		};
	}
}
