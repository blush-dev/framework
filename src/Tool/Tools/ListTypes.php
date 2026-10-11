<?php

/**
 * List types tool.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tool\Tools;

use Override;
use Blush\Content\Type\ContentType;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolEntries;
use Blush\Tool\Tool;
use Blush\Tool\ToolResult;

/**
 * `list_types` (D-755): each content type the connection sees, briefly,
 * for an early call; `get_type` has one in full.
 */
final class ListTypes extends Tool
{
	public function __construct(private readonly ToolEntries $entries)
	{}

	#[Override]
	public function title(): string
	{
		return 'List Content Types';
	}

	#[Override]
	public function description(): string
	{
		return 'Lists the site\'s content types (posts, pages, and the site\'s own): each one\'s name, label, kind (a dated collection, or a tree of pages), a line about it, and how many entries this connection can see. Call it first to learn what the site has; call get_type before writing an entry of a type.';
	}

	#[Override]
	public function input(): array
	{
		return ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false];
	}

	#[Override]
	public function output(): array
	{
		return [
			'type'       => 'object',
			'properties' => [
				'types' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'name'        => ['type' => 'string'],
							'label'       => ['type' => 'string'],
							'plural'      => ['type' => 'string'],
							'kind'        => ['type' => 'string'],
							'description' => ['type' => 'string'],
							'entries'     => ['type' => 'integer']
						]
					]
				]
			]
		];
	}

	#[Override]
	public function handle(array $input, ToolContext $context): ToolResult
	{
		$types = array_map(fn (ContentType $type): array => [
			'name'        => $type->name,
			'label'       => $type->labels->singular,
			'plural'      => $type->labels->plural,
			'kind'        => $type->kind()->value,
			'description' => $type->description,
			'entries'     => $this->entries->query($context)->type($type->name)->count()
		], array_values($this->entries->types($context)));

		return new ToolResult(['types' => $types]);
	}
}
