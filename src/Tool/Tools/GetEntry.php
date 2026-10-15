<?php

/**
 * Get entry tool.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tool\Tools;

use Override;
use Blush\Api\ApiEntries;
use Blush\Content\Entry\Entry;
use Blush\Content\Relation\Referrers;
use Blush\Content\Relation\Relation;
use Blush\Core\AppConfig;
use Blush\Routing\RouteTable;
use Blush\Tool\Tool;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolEntries;
use Blush\Tool\ToolError;
use Blush\Tool\ToolResult;
use Blush\Translation\Messages;
use Blush\Translation\Text;
use Blush\Workflow\Status;

/**
 * `get_entry` (D-755): one entry in full, by id or by its address (people
 * paste addresses): its fields, its Markdown body as written, its
 * relationships as stubs, and, for one the connection may edit, its
 * revision and links to its editor and a preview. With
 * `include: ["linked_from"]`, what links to it (D-608). An address that
 * redirects says where.
 */
final class GetEntry extends Tool
{
	public function __construct(
		private readonly ToolEntries $entries,
		private readonly Referrers $referrers,
		private readonly RouteTable $routes,
		private readonly AppConfig $app,
		private readonly Messages $messages
	) {}

	#[Override]
	public function title(): Text
	{
		return $this->messages->t('tool.get_entry.title', 'Get an Entry');
	}

	#[Override]
	public function description(): Text
	{
		return $this->messages->t('tool.get_entry.description', 'Gets one entry in full, by its id or its address on the site: its fields, its Markdown body exactly as written, its relationships, and, when this connection may edit it, its revision (which update_entry, edit_body, set_status, and trash_entry need) and links to edit and preview it. Add include: ["linked_from"] to list the entries that link to it, before trashing or unpublishing it.');
	}

	#[Override]
	public function input(): array
	{
		return [
			'type'                 => 'object',
			'properties'           => [
				'id'      => ['type' => 'string', 'description' => 'The entry\'s id.'],
				'url'     => ['type' => 'string', 'description' => 'The entry\'s address on the site, a path or a full URL, when there\'s no id.'],
				'include' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['linked_from']]]
			],
			'additionalProperties' => false
		];
	}

	#[Override]
	public function handle(array $input, ToolContext $context): ToolResult
	{
		$id  = self::optional($input, 'id');
		$url = self::optional($input, 'url');

		if ($id === null && $url === null) {
			throw new ToolError((string) $this->messages->t('tool.get_entry.id_or_url', 'Send the entry\'s "id", or its "url".'));
		}

		if ($id === null) {
			$this->redirected((string) $url);
		}

		$entry = $this->entries->find($context, $id, $url);
		$item  = $this->entries->item($context, $entry);

		if (in_array('linked_from', (array) ($input['include'] ?? []), true)) {
			$item['linked_from'] = $this->linkedFrom($context, $entry);
		}

		return new ToolResult($item);
	}

	/**
	 * Says where an address redirects, when it does and has no entry.
	 *
	 * @throws ToolError
	 */
	private function redirected(string $url): void
	{
		$path     = ApiEntries::path($url, $this->app->origin());
		$redirect = $path === null ? null : $this->routes->redirectFor($path);

		if ($redirect !== null) {
			$to = preg_match('#^[a-z][a-z0-9+.-]*://#i', $redirect->to) === 1 ? $redirect->to : $this->app->absoluteUrl($redirect->to);

			throw new ToolError((string) $this->messages->t('tool.get_entry.redirects', '"{url}" redirects to {to}; ask for that address instead.', url: $url, to: $to));
		}
	}

	/**
	 * Returns what links to an entry, of what the connection sees, each
	 * with the relationships it links through.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function linkedFrom(ToolContext $context, Entry $entry): array
	{
		$linked = [];

		foreach ($this->referrers->sources($entry) as $source) {
			if ($source['entry']->status->is(Status::TRASH) || ! $this->entries->sees($context, $source['entry'])) {
				continue;
			}

			$linked[] = [
				...$this->entries->stub($source['entry']),
				'through' => array_map(static fn (Relation $relation): string => $relation->label === '' ? $relation->name : $relation->label, $source['relations'])
			];
		}

		return $linked;
	}
}
