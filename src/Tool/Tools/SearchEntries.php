<?php

/**
 * Search entries tool.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tool\Tools;

use DateTimeImmutable;
use Exception;
use Override;
use Blush\Api\Http\EntriesController;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Query\Query;
use Blush\Content\Relation\Relations;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
use Blush\Storage\Record\Order;
use Blush\Support\Uuid;
use Blush\Tool\Tool;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolEntries;
use Blush\Tool\ToolError;
use Blush\Tool\ToolResult;
use Blush\Translation\Messages;
use Blush\Translation\Text;
use Blush\Workflow\Statuses;

/**
 * `search_entries` (D-755): the API's filters (D-740) as typed input,
 * answering stubs without bodies, 20 at a time by default and at most
 * 50, with a cursor for the next page. Every status the connection may
 * see but the trash unless `status` says otherwise.
 */
final class SearchEntries extends Tool
{
	public const int LIMIT = 20;

	public const int MOST = 50;

	public function __construct(
		private readonly ToolEntries $entries,
		private readonly Entries $content,
		private readonly Relations $relations,
		private readonly ContentTypes $types,
		private readonly AppConfig $app,
		private readonly Messages $messages,
		private readonly Statuses $statuses
	) {}

	#[Override]
	public function title(): Text
	{
		return $this->messages->t('tool.search_entries.title', 'Search Entries');
	}

	#[Override]
	public function description(): Text
	{
		return $this->messages->t('tool.search_entries.description', 'Finds entries (posts, pages, and the site\'s other types) by words, type, status, relationship (such as a tag or an author), parent page, and dates. Answers short summaries (id, type, title, status, address, dates, excerpt) without bodies; read one in full with get_entry. Finds drafts and scheduled entries this connection may see as well as published ones, but not the trash unless status asks for it.');
	}

	#[Override]
	public function input(): array
	{
		$date = ['type' => 'string', 'description' => 'A date, such as 2026-01-31, in the site\'s timezone unless it says otherwise.'];

		return [
			'type'                 => 'object',
			'properties'           => [
				'search'           => ['type' => 'string', 'description' => 'Words the entries have.', 'maxLength' => 200],
				'type'             => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Types to search, by name.'],
				'status'           => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => array_keys($this->statuses->all())], 'description' => 'Statuses to find; every one but trash when left out.'],
				'related'          => ['type' => 'object', 'description' => 'Relationships to filter by, by name, each to a list of the related entries\' slugs or ids, such as {"tags": ["baking"]}. Entries linked to any of them are found.', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]],
				'parent'           => ['type' => 'string', 'description' => 'The id of a page whose pages to find.'],
				'date_after'  => $date,
				'date_before' => $date,
				'updated_after'    => $date,
				'sort'             => ['type' => 'string', 'enum' => array_merge(...array_map(static fn (string $key): array => [$key, "-{$key}"], EntriesController::SORTS)), 'description' => 'What to sort by, with - first for newest or last first; one type\'s own order, else newest published first, when left out.'],
				'limit'            => ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MOST, 'description' => sprintf('How many to answer; %d when left out.', self::LIMIT)],
				'cursor'           => ['type' => 'string', 'description' => 'The next_cursor a search answered, for the next page.']
			],
			'additionalProperties' => false
		];
	}

	#[Override]
	public function output(): array
	{
		return [
			'type'       => 'object',
			'properties' => [
				'entries'     => ['type' => 'array', 'items' => ['type' => 'object']],
				'total'       => ['type' => 'integer'],
				'next_cursor' => ['type' => ['string', 'null']]
			]
		];
	}

	#[Override]
	public function handle(array $input, ToolContext $context): ToolResult
	{
		$statuses = self::strings($input, 'status');
		$search   = $this->entries->query($context, $statuses === [] ? null : $statuses);
		$types    = self::strings($input, 'type');

		foreach ($types as $type) {
			$this->entries->type($context, $type);
		}

		if ($types !== []) {
			$search = $search->type(...$types);
		}

		$related = is_array($input['related'] ?? null) ? $input['related'] : [];

		foreach ($related as $name => $values) {
			$search = $this->related($search, (string) $name, is_array($values) ? array_values(array_filter($values, is_string(...))) : []);
		}

		if (isset($input['parent'])) {
			$search = $search->whereParent($this->entries->find($context, self::text($input, 'parent')));
		}

		$updated = $this->date($input, 'updated_after');
		$search  = $search->search(self::optional($input, 'search'))->updatedSince($updated?->getTimestamp());

		[$key, $order] = $this->sort($input, $types);
		$search        = $search->orderBy($key, $order);

		$limit  = is_int($input['limit'] ?? null) ? $input['limit'] : self::LIMIT;
		$page   = $this->page($input['cursor'] ?? null);
		$after  = $this->date($input, 'date_after');
		$before = $this->date($input, 'date_before');

		if ($after === null && $before === null) {
			$paginator = $search->paginate($limit, $page);
			$found     = $paginator->all();
			$total     = $paginator->total();
		} else {
			$all   = array_values(array_filter([...$search->get()], static fn (Entry $entry): bool => $entry->date !== null && ($after === null || $entry->date >= $after) && ($before === null || $entry->date < $before)));
			$found = array_slice($all, ($page - 1) * $limit, $limit);
			$total = count($all);
		}

		return new ToolResult([
			'entries'     => array_map($this->entries->stub(...), $found),
			'total'       => $total,
			'next_cursor' => $page * $limit < $total ? (string) ($page + 1) : null
		], $this->messages->t('tool.search_entries.found', 'Found {count, plural, one {# entry} other {# entries}}.', count: $total));
	}

	/**
	 * Returns a query limited to entries linking, through relationships
	 * by a name, to any of the targets given by id or slug.
	 *
	 * @param  list<string> $values
	 * @throws ToolError When no relationship has the name.
	 */
	private function related(Query $search, string $name, array $values): Query
	{
		$keys = [];

		foreach ($this->relations->relations as $relation) {
			if ($relation->name === $name && EntriesController::filters($relation)) {
				$keys[] = (string) $relation->termKey();
			}
		}

		if ($keys === []) {
			throw new ToolError((string) $this->messages->t('tool.search_entries.no_relationship', 'There\'s no "{name}" relationship to filter by; get_type lists a type\'s relationships.', name: $name));
		}

		$slugs = array_map(fn (string $value): string => Uuid::isValid($value) ? ($this->content->find($value)->slug ?? "\0") : $value, $values);

		return $search->whereAnyTerm(array_values(array_unique($keys)), ...$slugs);
	}

	/**
	 * Returns the sort asked for, or the default: one type's own order,
	 * else newest first by date.
	 *
	 * @param  array<string, mixed> $input
	 * @param  list<string>         $types
	 * @return array{string, Order}
	 */
	private function sort(array $input, array $types): array
	{
		$sort = $input['sort'] ?? null;

		if (is_string($sort)) {
			return [ltrim($sort, '-'), str_starts_with($sort, '-') ? Order::Desc : Order::Asc];
		}

		$type = count($types) === 1 ? $this->types->find($types[0]) : null;

		return $type === null ? ['date', Order::Desc] : $type->order();
	}

	/**
	 * Reads a date from the input, in the site's timezone.
	 *
	 * @param  array<string, mixed> $input
	 * @throws ToolError When it isn't one.
	 */
	private function date(array $input, string $key): ?DateTimeImmutable
	{
		$value = $input[$key] ?? null;

		if (! is_string($value) || trim($value) === '') {
			return null;
		}

		try {
			return new DateTimeImmutable($value, $this->app->timezone());
		} catch (Exception) {
			throw new ToolError((string) $this->messages->t('tool.search_entries.not_a_date', '"{field}" must be a date, such as 2026-01-31; "{value}" isn\'t one.', field: $key, value: $value));
		}
	}

	/**
	 * Reads a cursor: the page it names.
	 *
	 * @throws ToolError When it isn't one a search answered.
	 */
	private function page(mixed $cursor): int
	{
		if ($cursor === null) {
			return 1;
		}

		return is_string($cursor) && ctype_digit($cursor) && (int) $cursor >= 1
			? (int) $cursor
			: throw new ToolError((string) $this->messages->t('tool.search_entries.cursor', '"cursor" must be the next_cursor a search answered.'));
	}
}
