<?php

/**
 * Get type tool.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tool\Tools;

use Override;
use Blush\Auth\ContentAction;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\Relations;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Editing\EntryEditor;
use Blush\Field\Field;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolEntries;
use Blush\Tool\Tool;
use Blush\Tool\ToolResult;

/**
 * `get_type` (D-755): one content type in full, for writing an entry of
 * it: its fields and their JSON Schema (D-206), its statuses, its
 * relationships, and its address, with what this connection may do. Never
 * a file path (D-481).
 */
final class GetType extends Tool
{
	public function __construct(
		private readonly ToolEntries $entries,
		private readonly ContentTypes $types,
		private readonly Relations $relations,
		private readonly ContentUrls $urls
	) {}

	#[Override]
	public function title(): string
	{
		return 'Get a Content Type';
	}

	#[Override]
	public function description(): string
	{
		return 'Gets one content type in full: its fields (names, kinds, which are required, what each is for), a JSON Schema of an entry\'s fields, the statuses an entry can have, its relationships to other types (such as tags or authors, set by the related entries\' slugs), and what this connection may do with it. Call it before create_entry or update_entry.';
	}

	#[Override]
	public function input(): array
	{
		return [
			'type'                 => 'object',
			'properties'           => ['name' => ['type' => 'string', 'description' => 'The type\'s name, as list_types gives it.']],
			'required'             => ['name'],
			'additionalProperties' => false
		];
	}

	#[Override]
	public function handle(array $input, ToolContext $context): ToolResult
	{
		$type       = $this->entries->type($context, self::text($input, 'name'));
		$schema     = $this->types->schema($type->name);
		$collection = $this->urls->collection($type);

		return new ToolResult([
			'name'          => $type->name,
			'label'         => $type->labels->singular,
			'plural'        => $type->labels->plural,
			'kind'          => $type->kind()->value,
			'description'   => $type->description,
			'url'           => $collection === null ? null : $this->urls->absolute($collection),
			'statuses'      => EntryEditor::STATUSES,
			'fields'        => array_values(array_map(static fn (Field $field): array => [
				'name'        => $field->name,
				'type'        => $field->type(),
				'label'       => $field->label,
				'description' => $field->description,
				'required'    => $field->required
			], $schema->fields)),
			'schema'        => $schema->jsonSchema(),
			'relationships' => array_values(array_map(fn (Relation $relation): array => [
				'name'     => $relation->name,
				'label'    => $relation->label,
				'field'    => $relation->field,
				'to'       => $relation->to,
				'multiple' => $relation->multiple,
				'min'      => $relation->min,
				'max'      => $relation->max,
				'creates'  => $relation->create && $this->creates($context, $relation)
			], array_filter($this->relations->for($type->name), static fn (Relation $relation): bool => ! $relation->kind->isWithinType()))),
			'can'           => [
				'create'  => $context->can(ContentAction::Create, $type->name),
				'edit'    => $context->can(ContentAction::Edit, $type->name),
				'publish' => $context->can(ContentAction::Publish, $type->name),
				'trash'   => $context->can(ContentAction::Delete, $type->name)
			]
		]);
	}

	/**
	 * Whether a relation's new targets are written as they're typed for
	 * this connection: it may create and publish them (D-596).
	 */
	private function creates(ToolContext $context, Relation $relation): bool
	{
		return array_all($relation->to, static fn (string $to): bool => $context->can(ContentAction::Create, $to) && $context->can(ContentAction::Publish, $to));
	}
}
