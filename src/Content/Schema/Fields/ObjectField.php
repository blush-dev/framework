<?php

/**
 * Object field.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Schema\Fields;

use Override;
use Blush\Content\Schema\Field;
use Blush\Content\Schema\FieldContext;
use Blush\Content\Schema\FieldFactory;
use Blush\Content\Schema\InvalidSchema;
use Blush\Content\Schema\Schema;
use Blush\Content\Schema\Severity;
use Blush\Content\Schema\Violation;

/**
 * A map of named values with a schema of its own. Undeclared keys are kept
 * unless the object is closed, so an object with no fields holds any map,
 * such as a page's `collection` query.
 */
final class ObjectField extends Field
{
	public function __construct(string $name = '', public readonly Schema $schema = new Schema())
	{
		$this->name = $name;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function type(): string
	{
		return 'object';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function normalize(mixed $value, FieldContext $context): mixed
	{
		if (! is_array($value) || ($value !== [] && array_is_list($value))) {
			throw $this->invalid(sprintf('must be a map, not %s.', self::describe($value)));
		}

		$result = $this->schema->resolve($value, $context);
		$errors = $result->violations(Severity::Error);

		if ($errors !== []) {
			throw $this->invalid(implode('; ', array_map(static fn (Violation $violation): string => (string) $violation, $errors)));
		}

		return [...$result->extra, ...$result->values];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function hydrate(mixed $value, FieldContext $context): mixed
	{
		return is_array($value) ? [...$value, ...$this->schema->hydrate($value, $context)] : $value;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function definitionSchema(array $field): array
	{
		return [
			'fields'  => [
				'type'        => 'array',
				'description' => 'The fields in the group.',
				'items'       => ['allOf' => [$field, ['required' => ['name']]]]
			],
			'closed'  => ['type' => 'boolean', 'description' => 'Whether keys the group doesn\'t declare are errors.'],
			'default' => ['type' => 'object']
		];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function valueType(): array
	{
		return $this->schema->jsonSchema();
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data, FieldFactory $factory): static
	{
		$definition = self::definition($data);

		return self::withShared(
			new static($definition->string('name'), $factory->schema($definition->maps('fields'), $definition->bool('closed'))),
			$definition
		);
	}

	/**
	 * @inheritDoc
	 *
	 * @throws InvalidSchema
	 */
	#[Override]
	protected function options(): array
	{
		return $this->schema->toArray();
	}
}
