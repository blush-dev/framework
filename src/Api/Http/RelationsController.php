<?php

/**
 * API relations controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Api\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Api\ApiEntries;
use Blush\Api\ApiQuery;
use Blush\Api\ApiResponses;
use Blush\Api\EntryResource;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\Relations;

/**
 * Answers `/relations` (D-736): the definitions of the relations entries
 * list under `relations`, so a client knows what each one means. Each
 * gives its name, kind, labels, the public types it's on (`from`) and
 * points at (`to`), whether an entry may have several targets and
 * whether their order means something, and what the targets' side is
 * called, or `null` when it isn't shown. The relations between a type's
 * own entries (a parent, a translation) aren't listed.
 */
final readonly class RelationsController
{
	public function __construct(
		private ApiResponses $responses,
		private ApiEntries $entries,
		private Relations $relations
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		return $this->responses->answer($request, function () use ($request): array {
			$query   = ApiQuery::from($request, ['page', 'per_page']);
			$public  = array_keys($this->entries->types());
			$items   = [];

			foreach ($this->relations->relations as $relation) {
				$from = $relation->from === [] ? $public : array_values(array_intersect($relation->from, $public));
				$to   = array_values(array_intersect($relation->to, $public));

				if (EntryResource::lists($relation) && $from !== [] && $to !== []) {
					$items[] = self::relation($relation, $from, $to);
				}
			}

			$page    = $query->page();
			$perPage = $query->perPage();

			return $this->responses->page($request, array_slice($items, ($page - 1) * $perPage, $perPage), count($items), $page, $perPage);
		});
	}

	/**
	 * Returns a relation as the API sends it.
	 *
	 * @param  list<string> $from
	 * @param  list<string> $to
	 * @return array<string, mixed>
	 */
	private static function relation(Relation $relation, array $from, array $to): array
	{
		return [
			'name'     => $relation->name,
			'kind'     => $relation->kind->value,
			'label'    => $relation->label === '' ? ucfirst(str_replace('_', ' ', $relation->name)) : $relation->label,
			'singular' => $relation->singular,
			'from'     => $from,
			'to'       => $to,
			'multiple' => $relation->multiple,
			'ordered'  => $relation->ordered,
			'inverse'  => $relation->inverse === false || $relation->inverse->label === '' ? null : $relation->inverse->label
		];
	}
}
