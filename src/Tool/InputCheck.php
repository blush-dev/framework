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

namespace Blush\Tool;

use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Checks a tool's input against its JSON Schema (D-757) before the tool
 * runs, for the parts of JSON Schema tools use: `type` (one or a list),
 * `properties`, `required`, `additionalProperties: false`, `enum`,
 * `items`, `minLength`, `maxLength`, `minimum`, `maximum`, `minItems`,
 * and `maxItems`. What it doesn't know it lets by.
 *
 * Each problem is a whole sentence (D-873, D-877), translated: one for
 * each type, and for each type a value may also be left empty (`null`);
 * other lists of types are named by their JSON names.
 */
final readonly class InputCheck
{
	public function __construct(
		private Messages $messages
	) {}

	/**
	 * Returns what's wrong with a value under a schema, in words, or
	 * `null` when nothing is.
	 *
	 * @param array<string, mixed> $schema
	 */
	public function problem(mixed $value, array $schema, string $at = 'input'): ?Text
	{
		$types = $schema['type'] ?? null;
		$types = is_string($types) ? [$types] : (is_array($types) ? array_values(array_filter($types, is_string(...))) : []);

		if ($types !== [] && ! array_any($types, static fn (string $type): bool => self::is($value, $type))) {
			return $this->wrongType($types, $at);
		}

		if (is_array($schema['enum'] ?? null) && ! in_array($value, $schema['enum'], true)) {
			return $this->messages->t('tool.input_check.enum', '{field} must be one of: {options}.', field: $at, options: implode(', ', array_map(static fn (mixed $option): string => json_encode($option) ?: '', $schema['enum'])));
		}

		return match (true) {
			is_string($value)                         => $this->text($value, $schema, $at),
			is_int($value) || is_float($value)        => $this->number($value, $schema, $at),
			is_array($value) && array_is_list($value) && ! self::isObjectSchema($schema) => $this->list($value, $schema, $at),
			is_array($value)                          => $this->object($value, $schema, $at),
			default                                   => null
		};
	}

	/**
	 * Says what type a value must be: a sentence for each type, and for
	 * each with `null` beside it; else the types' JSON names.
	 *
	 * @param non-empty-list<string> $types
	 */
	private function wrongType(array $types, string $at): Text
	{
		$alone   = count($types) === 1 ? $types[0] : null;
		$orEmpty = count($types) === 2 && in_array('null', $types, true) ? array_first(array_diff($types, ['null'])) : null;

		$problem = match ($alone) {
			'string'  => $this->messages->t('tool.input_check.string', '{field} must be text.', field: $at),
			'integer' => $this->messages->t('tool.input_check.integer', '{field} must be a whole number.', field: $at),
			'number'  => $this->messages->t('tool.input_check.number', '{field} must be a number.', field: $at),
			'boolean' => $this->messages->t('tool.input_check.boolean', '{field} must be true or false.', field: $at),
			'array'   => $this->messages->t('tool.input_check.array', '{field} must be a list.', field: $at),
			'object'  => $this->messages->t('tool.input_check.object', '{field} must be an object.', field: $at),
			'null'    => $this->messages->t('tool.input_check.null', '{field} must be left empty.', field: $at),
			default   => match ($orEmpty) {
				'string'  => $this->messages->t('tool.input_check.string_or_empty', '{field} must be text, or left empty.', field: $at),
				'integer' => $this->messages->t('tool.input_check.integer_or_empty', '{field} must be a whole number, or left empty.', field: $at),
				'number'  => $this->messages->t('tool.input_check.number_or_empty', '{field} must be a number, or left empty.', field: $at),
				'boolean' => $this->messages->t('tool.input_check.boolean_or_empty', '{field} must be true or false, or left empty.', field: $at),
				'array'   => $this->messages->t('tool.input_check.array_or_empty', '{field} must be a list, or left empty.', field: $at),
				'object'  => $this->messages->t('tool.input_check.object_or_empty', '{field} must be an object, or left empty.', field: $at),
				default   => null
			}
		};

		return $problem ?? $this->messages->t('tool.input_check.types', '{field} must be one of these JSON types: {types}.', field: $at, types: implode(', ', $types));
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
	private function text(string $value, array $schema, string $at): ?Text
	{
		$length = mb_strlen($value);

		return match (true) {
			is_int($schema['minLength'] ?? null) && $length < $schema['minLength'] => $this->messages->t('tool.input_check.min_length', '{field} must be at least {min, plural, one {# character} other {# characters}}.', field: $at, min: $schema['minLength']),
			is_int($schema['maxLength'] ?? null) && $length > $schema['maxLength'] => $this->messages->t('tool.input_check.max_length', '{field} must be at most {max, plural, one {# character} other {# characters}}.', field: $at, max: $schema['maxLength']),
			default                                                                => null
		};
	}

	/**
	 * @param array<string, mixed> $schema
	 */
	private function number(int|float $value, array $schema, string $at): ?Text
	{
		return match (true) {
			is_numeric($schema['minimum'] ?? null) && $value < $schema['minimum'] => $this->messages->t('tool.input_check.minimum', '{field} must be at least {min}.', field: $at, min: $schema['minimum']),
			is_numeric($schema['maximum'] ?? null) && $value > $schema['maximum'] => $this->messages->t('tool.input_check.maximum', '{field} must be at most {max}.', field: $at, max: $schema['maximum']),
			default                                                              => null
		};
	}

	/**
	 * @param list<mixed>          $value
	 * @param array<string, mixed> $schema
	 */
	private function list(array $value, array $schema, string $at): ?Text
	{
		if (is_int($schema['minItems'] ?? null) && count($value) < $schema['minItems']) {
			return $this->messages->t('tool.input_check.min_items', '{field} must have at least {min, plural, one {# item} other {# items}}.', field: $at, min: $schema['minItems']);
		}

		if (is_int($schema['maxItems'] ?? null) && count($value) > $schema['maxItems']) {
			return $this->messages->t('tool.input_check.max_items', '{field} must have at most {max, plural, one {# item} other {# items}}.', field: $at, max: $schema['maxItems']);
		}

		$items = $schema['items'] ?? null;

		if (! is_array($items)) {
			return null;
		}

		foreach ($value as $index => $item) {
			$problem = $this->problem($item, self::schema($items), "{$at}[{$index}]");

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
	private function object(array $value, array $schema, string $at): ?Text
	{
		$properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

		foreach (is_array($schema['required'] ?? null) ? $schema['required'] : [] as $name) {
			if (is_string($name) && ! array_key_exists($name, $value)) {
				return $this->messages->t('tool.input_check.required', '{field} needs "{name}".', field: $at, name: $name);
			}
		}

		foreach ($value as $name => $item) {
			$property = $properties[$name] ?? null;
			$path     = $at === 'input' ? (string) $name : "{$at}.{$name}";

			if (! is_array($property)) {
				if (($schema['additionalProperties'] ?? true) === false) {
					return $properties === []
						? $this->messages->t('tool.input_check.takes_nothing', '{field} has no "{name}"; it takes nothing.', field: $at, name: (string) $name)
						: $this->messages->t('tool.input_check.unknown', '{field} has no "{name}"; it takes {names}.', field: $at, name: (string) $name, names: implode(', ', array_map(static fn (int|string $key): string => '"' . $key . '"', array_keys($properties))));
				}

				continue;
			}

			$problem = $this->problem($item, self::schema($property), $path);

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
}
