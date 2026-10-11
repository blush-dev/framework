<?php

/**
 * Enum field.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field\Fields;

use Override;
use Blush\Field\Control;
use Blush\Field\Controls\Select;
use Blush\Field\Field;
use Blush\Field\FieldContext;
use Blush\Field\FieldFactory;
use Blush\Field\InvalidSchema;

/**
 * One value from a fixed set of options. Matching ignores case, and the
 * option is stored as declared. An option may have a label for people
 * (`labels`, D-766), so its words can change without touching the values
 * entries keep; a definition writes such options as `{"value", "label"}`.
 */
final class EnumField extends Field
{
	/**
	 * @param  list<string>          $options
	 * @param  array<string, string> $labels Labels for people, by option.
	 * @throws InvalidSchema When there are no options.
	 */
	public function __construct(string $name = '', public readonly array $options = [], public readonly array $labels = [])
	{
		$this->name = $name;

		if ($options === []) {
			throw new InvalidSchema(sprintf('Field "%s" must list its options.', $name));
		}
	}

	/**
	 * Returns an option's label: its own, or the option.
	 */
	public function label(string $option): string
	{
		return $this->labels[$option] ?? $option;
	}

	/**
	 * @inheritDoc
	 *
	 * Forms get each option's label (`choices`).
	 */
	#[Override]
	public function toForm(): array
	{
		return [...parent::toForm(), 'options' => $this->options, 'choices' => array_combine($this->options, array_map($this->label(...), $this->options))];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function type(): string
	{
		return 'enum';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeLabel(): string
	{
		return 'Choice';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeDescription(): string
	{
		return 'One of a set of values.';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function defaultControl(): Control
	{
		return new Select();
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function normalize(mixed $value, FieldContext $context): mixed
	{
		if (is_string($value) || is_int($value)) {
			$match = array_find($this->options, static fn (string $option): bool => strcasecmp($option, trim((string) $value)) === 0);

			if ($match !== null) {
				return $match;
			}
		}

		throw $this->invalid(sprintf(
			'must be one of %s, not %s.',
			implode(', ', $this->options),
			is_string($value) ? "\"{$value}\"" : self::describe($value)
		));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function definitionSchema(array $field): array
	{
		return [
			'options' => [
				'type'        => 'array',
				'description' => 'The values allowed, each a value or a value with a label for people.',
				'items'       => ['anyOf' => [
					['type' => 'string'],
					['type' => 'object', 'properties' => ['value' => ['type' => 'string'], 'label' => ['type' => 'string']], 'required' => ['value']]
				]],
				'minItems'    => 1
			],
			'default' => ['type' => 'string']
		];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function valueType(): array
	{
		return ['enum' => $this->options];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data, FieldFactory $factory): static
	{
		$definition = self::definition($data);
		$options    = [];
		$labels     = [];

		foreach ($definition->listOrMap('options') as $option) {
			$value = is_array($option) ? ($option['value'] ?? null) : $option;
			$label = is_array($option) ? ($option['label'] ?? null) : null;

			if (! is_string($value) || ($label !== null && ! is_string($label))) {
				throw new InvalidSchema(sprintf('Field "%s" "options" must be values, or values with labels ({"value", "label"}).', $definition->string('name')));
			}

			$options[] = $value;

			if ($label !== null && $label !== $value) {
				$labels[$value] = $label;
			}
		}

		return self::withShared(new static($definition->string('name'), $options, $labels), $definition);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function options(): array
	{
		return ['options' => $this->labels === [] ? $this->options : array_map(fn (string $option): array => ['value' => $option, 'label' => $this->label($option)], $this->options)];
	}
}
