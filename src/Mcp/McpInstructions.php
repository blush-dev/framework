<?php

/**
 * MCP instructions.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp;

use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;

/**
 * What the MCP server tells an app when it connects (D-753): a few lines
 * on what the site is (its name, description, and types) and how to
 * work with it, which every client reads.
 */
final readonly class McpInstructions
{
	public function __construct(
		private AppConfig $app,
		private ContentTypes $types
	) {}

	/**
	 * Returns the instructions.
	 */
	public function text(): string
	{
		$types = implode(', ', array_map(
			static fn (ContentType $type): string => sprintf('%s (%s)', $type->labels->plural, $type->name),
			array_values($this->types->all())
		));

		$about = $this->app->description === '' ? '' : " {$this->app->description}";

		return implode("\n", [
			sprintf('This is %s, a site at %s.%s', $this->app->name, $this->app->origin(), $about),
			"Its content types are: {$types}.",
			'Entries are found with search_entries and read with get_entry, which answers each entry\'s fields, its Markdown body, and its revision.',
			'Before writing an entry, call get_type for its fields. New entries start as drafts; set_status publishes one, when the account may.',
			'Changes need the revision get_entry answered, so no one\'s change is lost; when one is refused as changed, read the entry again.',
			'For a small change to a body, use edit_body with a passage copied exactly from it, rather than sending the whole body back.',
			'Every change is listed in the site\'s activity log, where a person can undo it.'
		]);
	}
}
