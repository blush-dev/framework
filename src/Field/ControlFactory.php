<?php

/**
 * Control factory.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

/**
 * Builds the controls field definitions choose (`control`, D-795),
 * looking each up by its key in the registry: it parts a field set's or
 * a type's definitions into fields and their controls (`split()`, where
 * a definition naming only a control gets the control's type, D-769),
 * and nests the admin's forms' shape into theirs (`nest()`).
 *
 * Controls are value objects, so their classes' own `fromArray()` builds
 * them rather than the container.
 */
final readonly class ControlFactory
{
	public function __construct(private ControlRegistry $registry)
	{}

	/**
	 * Returns a factory of the core controls, for code that builds fields
	 * outside the container (tests, tools).
	 */
	public static function core(): self
	{
		$registry = new ControlRegistry();
		new ControlRegistrar($registry)->register();

		return new self($registry);
	}



	/**
	 * Splits field definitions as a field set's file writes them (D-795)
	 * into the fields' own definitions and the controls chosen for them,
	 * by path: a field's `control` is an object of the control's `name`
	 * and its options, beside the field's keys, and a group's fields and a
	 * list's item have their own. A definition naming only a control gets
	 * the control's type and the options it implies (D-769).
	 *
	 * @param  array<array-key, mixed> $definitions A list, or a map by name.
	 * @return array{list<array<array-key, mixed>>, FieldControls}
	 * @throws InvalidSchema
	 */
	public function split(array $definitions, string $parent = ''): array
	{
		$fields   = [];
		$controls = [];

		foreach (FieldFactory::definitions($definitions) as $definition) {
			$name = is_string($definition['name'] ?? null) ? $definition['name'] : '';

			[$fields[], $found] = $this->splitOne($definition, FieldControls::path($parent, $name));
			$controls           = [...$controls, ...$found->controls];
		}

		return [$fields, new FieldControls($controls)];
	}

	/**
	 * Splits one definition at a path.
	 *
	 * @param  array<array-key, mixed> $definition
	 * @return array{array<array-key, mixed>, FieldControls}
	 * @throws InvalidSchema
	 */
	private function splitOne(array $definition, string $path): array
	{
		$name     = is_string($definition['name'] ?? null) ? $definition['name'] : '';
		$chosen   = $definition['control'] ?? null;
		$controls = [];

		unset($definition['control']);

		if ($chosen !== null) {
			if (! is_array($chosen) || ! is_string($chosen['name'] ?? null)) {
				throw new InvalidSchema(sprintf('Field "%s" "control" must be an object with the control\'s "name", such as {"name": "radios"}.', $name));
			}

			$class            = $this->classOf(['name' => $name, 'control' => $chosen['name']]) ?? throw new InvalidSchema(sprintf('Field "%s" names no control.', $name));
			$definition       = $class::complete($definition);
			$controls[$path]  = $class::fromArray([...$chosen, 'name' => $name]);
		}

		if (is_array($definition['fields'] ?? null)) {
			[$definition['fields'], $nested] = $this->split($definition['fields'], $path);
			$controls                        = [...$controls, ...$nested->controls];
		}

		if (is_array($definition['item'] ?? null)) {
			[$definition['item'], $item] = $this->splitOne($definition['item'], FieldControls::path($path, FieldControls::ITEM));
			$controls                    = [...$controls, ...$item->controls];
		}

		return [$definition, new FieldControls($controls)];
	}

	/**
	 * Nests field definitions as the admin's forms send them (a control's
	 * key in `control`, its options beside the field's) into the shape a
	 * set's file writes (`split()`), a group's fields and a list's item
	 * too.
	 *
	 * @param  array<array-key, mixed> $definitions
	 * @return list<array<array-key, mixed>>
	 * @throws InvalidSchema
	 */
	public function nest(array $definitions): array
	{
		return array_map($this->nestOne(...), FieldFactory::definitions($definitions));
	}

	/**
	 * Nests one definition.
	 *
	 * @param  array<array-key, mixed> $definition
	 * @return array<array-key, mixed>
	 * @throws InvalidSchema
	 */
	private function nestOne(array $definition): array
	{
		if (is_array($definition['fields'] ?? null)) {
			$definition['fields'] = $this->nest($definition['fields']);
		}

		if (is_array($definition['item'] ?? null)) {
			$definition['item'] = $this->nestOne($definition['item']);
		}

		$class = is_string($definition['control'] ?? null) ? $this->classOf($definition) : null;

		if ($class === null) {
			return $definition;
		}

		$options = array_intersect_key($definition, $class::definitionSchema());

		return [...array_diff_key($definition, $options), 'control' => ['name' => $class::name(), ...$options]];
	}

	/**
	 * Returns every control class, by key.
	 *
	 * @return array<string, class-string<Control>>
	 */
	public function all(): array
	{
		return $this->registry->all();
	}


	/**
	 * Returns the class of the control a definition names, or `null`.
	 *
	 * @param  array<array-key, mixed> $data
	 * @return class-string<Control>|null
	 * @throws InvalidSchema
	 */
	private function classOf(array $data): ?string
	{
		$name  = $data['control'] ?? null;
		$field = is_string($data['name'] ?? null) ? $data['name'] : '';

		if ($name === null) {
			return null;
		}

		if (! is_string($name)) {
			throw new InvalidSchema(sprintf('Field "%s" "control" must be a string.', $field));
		}

		return $this->registry->get($name) ?? throw new InvalidSchema(sprintf(
			'Field "%s" has an unknown control "%s"; controls are %s.',
			$field,
			$name,
			implode(', ', array_keys($this->registry->all()))
		));
	}
}
