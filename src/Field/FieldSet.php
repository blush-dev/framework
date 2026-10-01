<?php

/**
 * Field set.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

/**
 * A named, labeled, ordered group of fields attached to the places it
 * names (D-337): its `targets`, such as `type:post`. Sets are attached
 * from their own side, so an extension or a site can add fields to a
 * type it doesn't own without editing the type:
 *
 *     new FieldSet('seo', [new TextField('meta_title')], ['type:post', 'type:page'], 'SEO')
 *
 * Where a target takes the set's fields, they come after its own, and a
 * name used twice is an error, never an override. A target that doesn't
 * exist is left alone (`content:lint` notes it), so a set can name a type
 * that's sometimes turned off.
 */
final readonly class FieldSet
{
	/**
	 * What a set's name may be: lowercase letters, digits, `_`, and `-`.
	 */
	public const string NAME_PATTERN = '/^[a-z0-9][a-z0-9_-]*$/';

	/**
	 * What a target may be: a kind, a colon, and a name (`type:post`).
	 */
	public const string TARGET_PATTERN = '/^[a-z]+:[a-z0-9][a-z0-9_-]*$/';

	/**
	 * The definition keys a set takes.
	 *
	 * @var list<string>
	 */
	private const array KEYS = ['name', 'label', 'description', 'targets', 'fields'];

	/**
	 * The set's fields.
	 */
	public Schema $schema;

	/**
	 * The set's name for people: its own, or its name made readable.
	 */
	public string $label;

	/**
	 * @param  iterable<Field> $fields
	 * @param  list<string>    $targets The places the set is attached to.
	 * @throws InvalidSchema When the name, a target, or the fields aren't valid.
	 */
	public function __construct(
		public string $name,
		iterable $fields = [],
		public array $targets = [],
		string $label = '',
		public string $description = ''
	) {
		if (preg_match(self::NAME_PATTERN, $name) !== 1) {
			throw new InvalidSchema(sprintf('"%s" can\'t be a field set\'s name; use lowercase letters, digits, "_", and "-".', $name));
		}

		foreach ($targets as $target) {
			if (preg_match(self::TARGET_PATTERN, $target) !== 1) {
				throw new InvalidSchema(sprintf('Field set "%s" target "%s" must be a kind and a name, such as "type:post".', $name, $target));
			}
		}

		try {
			$this->schema = new Schema($fields);
		} catch (InvalidSchema $e) {
			throw new InvalidSchema(sprintf('Field set "%s": %s', $name, $e->getMessage()), previous: $e);
		}

		$this->label = $label === '' ? ucfirst(str_replace(['_', '-'], ' ', $name)) : $label;
	}

	/**
	 * Returns whether the set is attached to a target, by its key.
	 */
	public function attachesTo(string $target): bool
	{
		return in_array($target, $this->targets, true);
	}

	/**
	 * Builds a set from a definition, as written in `user/data/fields` or
	 * `config/fields.php`. `targets` may be one target or a list, and
	 * `fields` a list of definitions with names or a map of names to them.
	 * A JSON file's `$schema` (`field-set.schema.json`) is ignored.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidSchema
	 */
	public static function fromArray(array $data, FieldFactory $factory): self
	{
		unset($data['$schema']);

		$name       = $data['name'] ?? null;
		$name       = is_string($name) ? $name : '';
		$definition = new Definition($data, sprintf('Field set "%s"', $name));
		$unknown    = array_diff(array_map(strval(...), array_keys($data)), self::KEYS);

		if ($unknown !== []) {
			throw new InvalidSchema(sprintf('Field set "%s" has unknown keys: %s.', $name, implode(', ', $unknown)));
		}

		try {
			$fields = array_map($factory->fromArray(...), FieldFactory::definitions($definition->listOrMap('fields')));
		} catch (InvalidSchema $e) {
			throw new InvalidSchema(sprintf('Field set "%s": %s', $name, $e->getMessage()), previous: $e);
		}

		return new self(
			$name,
			$fields,
			$definition->strings('targets'),
			$definition->string('label'),
			$definition->string('description')
		);
	}

	/**
	 * Returns the set as a definition that `fromArray()` accepts, leaving
	 * out a label made from the name.
	 *
	 * @return array{name: string, label?: string, description?: string, targets: list<string>, fields: list<array<string, mixed>>}
	 */
	public function toArray(): array
	{
		$label = ucfirst(str_replace(['_', '-'], ' ', $this->name));

		return [
			'name'    => $this->name,
			...($this->label === $label ? [] : ['label' => $this->label]),
			...($this->description === '' ? [] : ['description' => $this->description]),
			'targets' => $this->targets,
			'fields'  => array_values(array_map(static fn (Field $field): array => $field->toArray(), $this->schema->fields))
		];
	}
}
