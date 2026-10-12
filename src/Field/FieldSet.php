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
 * Sets are the site's, made in the admin (`user/data/fields`, D-790,
 * D-791): they register their fields and show them together, as HTML's
 * `<fieldset>` does. Code registers fields (`RegisteredField`) instead.
 * `kind:*` attaches a set to every place of a kind (`type:*`, D-786).
 *
 * Where a target takes the set's fields, they come after its own and the
 * fields code registers; a set that would declare a key again is left
 * off it (D-789), never an override. A set's targets are
 * all one kind of place, and `slot` names one of the slots that kind
 * offers (D-347), such as `details` or `content`, or is left out for the
 * kind's default; each admin screen decides where a slot goes. A slot
 * the kind doesn't have falls back to its default (`FieldTargets::slotFor()`),
 * so a set written for a later Blush still loads. A target that doesn't
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
	 * What a target may be: a kind, a colon, and a name (`type:post`), or
	 * `*` for every place of the kind (`type:*`, D-786).
	 */
	public const string TARGET_PATTERN = '/^[a-z]+:(?:\*|[a-z0-9][a-z0-9_-]*)$/';

	/**
	 * A target's name for every place of its kind.
	 */
	public const string EVERY = '*';

	/**
	 * The definition keys a set takes.
	 *
	 * @var list<string>
	 */
	private const array KEYS = ['name', 'label', 'description', 'targets', 'slot', 'position', 'fields'];

	/**
	 * Where a set without a `position` goes among the others.
	 */
	public const int POSITION = 10;

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
	 * @param  list<string>    $targets The places the set is attached to, all of one kind.
	 * @param  ?string         $slot    The slot its kind's places show it in (D-347), or `null` for the kind's default.
	 * @param  int             $position Where it goes among a place's sets, lowest first, then by label (D-768).
	 * @param  FieldControls   $controls The controls its fields are drawn with, by path (D-795).
	 * @throws InvalidSchema When the name, a target, the slot, or the fields aren't valid, or the targets mix kinds.
	 */
	public function __construct(
		public string $name,
		iterable $fields = [],
		public array $targets = [],
		string $label = '',
		public string $description = '',
		public ?string $slot = null,
		public int $position = self::POSITION,
		public FieldControls $controls = new FieldControls()
	) {
		if (preg_match(self::NAME_PATTERN, $name) !== 1) {
			throw new InvalidSchema(sprintf('"%s" can\'t be a field set\'s name; use lowercase letters, digits, "_", and "-".', $name));
		}

		foreach ($targets as $target) {
			if (preg_match(self::TARGET_PATTERN, $target) !== 1) {
				throw new InvalidSchema(sprintf('Field set "%s" target "%s" must be a kind and a name, such as "type:post".', $name, $target));
			}
		}

		// One kind of place a set: a field means one thing on an entry,
		// another in the site's settings (D-347).
		$kinds = array_values(array_unique(array_map(static fn (string $target): string => strstr($target, ':', true) ?: $target, $targets)));

		if (count($kinds) > 1) {
			throw new InvalidSchema(sprintf('Field set "%s" targets more than one kind of place (%s); a set\'s targets are all one kind, so make a set for each.', $name, implode(', ', $kinds)));
		}

		if ($slot !== null && preg_match(self::NAME_PATTERN, $slot) !== 1) {
			throw new InvalidSchema(sprintf('Field set "%s" slot "%s" must be a name, such as "details".', $name, $slot));
		}

		try {
			$this->schema = new Schema($fields);
		} catch (InvalidSchema $e) {
			throw new InvalidSchema(sprintf('Field set "%s": %s', $name, $e->getMessage()), previous: $e);
		}

		$this->label = $label === '' ? self::readable($name) : $label;
	}

	/**
	 * Returns the kind of place its targets are (`type`), or `null` for a
	 * set with none.
	 */
	public function kind(): ?string
	{
		$first = $this->targets[0] ?? null;

		return $first === null ? null : (strstr($first, ':', true) ?: null);
	}

	/**
	 * Returns whether the set is attached to every place of its kind
	 * (`type:*`).
	 */
	public function isEverywhere(): bool
	{
		$kind = $this->kind();

		return $kind !== null && in_array("{$kind}:" . self::EVERY, $this->targets, true);
	}

	/**
	 * Returns whether the set is attached to a target, by its key: named,
	 * or as one of every place of its kind.
	 */
	public function attachesTo(string $target): bool
	{
		return self::targets($this->targets, $target);
	}

	/**
	 * Returns whether a list of targets takes in a place, by its key:
	 * named, or as one of every place of its kind.
	 *
	 * @param list<string> $targets
	 */
	public static function targets(array $targets, string $target): bool
	{
		if (in_array($target, $targets, true)) {
			return true;
		}

		$kind = strstr($target, ':', true);

		return $kind !== false && in_array("{$kind}:" . self::EVERY, $targets, true);
	}

	/**
	 * Returns which of a kind's place names the set is attached to, such
	 * as the types it's on out of every type's name.
	 *
	 * @param  list<string> $names
	 * @return list<string>
	 */
	public function namesOf(string $kind, array $names): array
	{
		return array_values(array_filter($names, fn (string $name): bool => $this->attachesTo("{$kind}:{$name}")));
	}

	/**
	 * Builds a set from a definition, as written in `user/data/fields`.
	 * `targets` may be one target or a list, and `fields` a list of
	 * definitions with names or a map of names to them, each with the
	 * control it's drawn with as an object (`"control": {"name":
	 * "radios"}`, D-795).
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
			[$fields, $controls] = $factory->withControls($definition->listOrMap('fields'));
		} catch (InvalidSchema $e) {
			throw new InvalidSchema(sprintf('Field set "%s": %s', $name, $e->getMessage()), previous: $e);
		}

		return new self(
			$name,
			$fields,
			$definition->strings('targets'),
			$definition->string('label'),
			$definition->string('description'),
			$definition->nullableString('slot'),
			self::position($definition, $name),
			$controls
		);
	}

	/**
	 * Returns the set as a definition that `fromArray()` accepts, leaving
	 * out a label made from the name.
	 *
	 * @return array{name: string, label?: string, description?: string, targets: list<string>, slot?: string, position?: int, fields: list<array<string, mixed>>}
	 */
	public function toArray(): array
	{
		$label = self::readable($this->name);

		return [
			'name'    => $this->name,
			...($this->label === $label ? [] : ['label' => $this->label]),
			...($this->description === '' ? [] : ['description' => $this->description]),
			'targets' => $this->targets,
			...($this->slot === null ? [] : ['slot' => $this->slot]),
			...($this->position === self::POSITION ? [] : ['position' => $this->position]),
			'fields'  => $this->controls->definitions($this->schema->fields)
		];
	}

	/**
	 * Returns a set's name made readable.
	 */
	private static function readable(string $name): string
	{
		return ucfirst(str_replace(['_', '-'], ' ', $name));
	}

	/**
	 * Reads a set's position, a whole number.
	 *
	 * @throws InvalidSchema
	 */
	private static function position(Definition $definition, string $name): int
	{
		$position = $definition->number('position');

		return match (true) {
			$position === null => self::POSITION,
			is_int($position)  => $position,
			default            => throw new InvalidSchema(sprintf('Field set "%s" "position" must be a whole number.', $name))
		};
	}
}
