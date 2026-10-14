<?php

/**
 * Repeating group control.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field\Controls;

use Override;
use Blush\Field\Control;
use Blush\Field\ControlSection;
use Blush\Field\Definition;
use Blush\Field\Field;
use Blush\Field\Fields\BoolField;
use Blush\Field\Fields\ListField;
use Blush\Field\Fields\MarkdownField;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * A list of items, each with the same fields: a list field whose items
 * are a group, or whose items are one value each that fits on a line
 * (text, a number, a date; D-777), each item then one box. A definition
 * that names only the control may write the fields on the field
 * (`fields`); they become its item's. What each item
 * is called (`itemLabel`, for "Add Ingredient") and the keys that sum an
 * item up in one line when it's closed (`summary`) are its own.
 */
final class Repeater extends Control
{
	/**
	 * @param list<string> $summary
	 */
	public function __construct(
		public readonly string $itemLabel = '',
		public readonly array $summary = []
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function name(): string
	{
		return 'repeater';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function label(Messages $messages): Text
	{
		return $messages->t('field.repeater.label', 'Repeating Group');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function description(Messages $messages): Text
	{
		return $messages->t('field.repeater.description', 'A list of items, each with the same fields.');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function section(): ControlSection
	{
		return ControlSection::Structure;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function type(): string
	{
		return 'list';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function shape(): string
	{
		return 'list:object';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function complete(array $data): array
	{
		$data = parent::complete($data);

		if (($data['type'] ?? null) === 'list' && ! isset($data['item']) && isset($data['fields'])) {
			$data['item'] = ['type' => 'object', 'fields' => $data['fields']];
			unset($data['fields']);
		}

		return $data;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fits(Field $field): bool
	{
		return $field instanceof ListField
			&& ! $field->item instanceof ListField
			&& ! $field->item instanceof MarkdownField
			&& ! $field->item instanceof BoolField;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$definition = new Definition($data, sprintf('Field "%s"', is_string($data['name'] ?? null) ? $data['name'] : ''));

		return new static($definition->string('itemLabel'), $definition->strings('summary'));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function options(): array
	{
		return array_filter(['itemLabel' => $this->itemLabel, 'summary' => $this->summary], static fn (mixed $value): bool => $value !== '' && $value !== []);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function definitionSchema(): array
	{
		return [
			'itemLabel' => ['type' => 'string', 'description' => 'What each item is called, for "Add Ingredient".'],
			'summary'   => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'The keys of the fields that sum an item up in one line when it\'s closed.']
		];
	}
}
