<?php

/**
 * Admin relations controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Psr\Http\Message\ResponseInterface;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\RelationChanges;
use Blush\Content\Relation\RelationOrigin;
use Blush\Content\Type\ContentConfig;
use Blush\Content\Type\ContentTypes;
use Blush\Http\Response;

/**
 * Answers `GET {path}/api/relations` (D-593): the relations the site
 * defines (from extensions, config, and `user/data/relations`), each as
 * `describe()` gives it, with how many entries have a value in it
 * (`entries`) and whether it's still written as a taxonomy (`legacy`),
 * for the Relationships list (D-610). They're changed through
 * `TypeEditController` (`POST relations`, `PATCH` and `DELETE
 * relations/{name}`), with the types.
 */
final readonly class RelationsController
{
	public function __construct(
		private ContentTypes $types,
		private ContentConfig $config,
		private RelationChanges $changes
	) {}

	public function __invoke(): ResponseInterface
	{
		$relations = $this->types->relations();
		$counts    = $this->changes->counts($relations);

		return Response::json([
			'relations' => array_values(array_map(fn (Relation $relation): array => [
				...self::describe($this->types, $relation, $this->config->dataTypes),
				'entries' => $counts[$relation->name] ?? 0,
				'legacy'  => in_array($relation->name, $this->types->legacy, true)
			], $relations)),
			'create'    => $this->config->dataTypes
		], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Describes a relation for the admin: its definition (as
	 * `Relation::toArray()` names its options, every one given), its
	 * `origin`, whether it's `editable` (one in `user/data/relations`,
	 * when data types are read), and its `definition` as written
	 * (`Relation::toArray()`), which a change starts from, since a change
	 * sends the whole definition.
	 *
	 * @return array<string, mixed>
	 */
	public static function describe(ContentTypes $types, Relation $relation, bool $dataTypes): array
	{
		$origin  = $types->relationOrigin($relation->name) ?? RelationOrigin::Config;
		$inverse = $relation->inverse;

		return [
			'name'         => $relation->name,
			'kind'         => $relation->kind->value,
			'from'         => $relation->from,
			'to'           => $relation->to,
			'field'        => $relation->field,
			'aliases'      => $relation->aliases,
			'label'        => $relation->label,
			'singular'     => $relation->singular,
			'multiple'     => $relation->multiple,
			'ordered'      => $relation->ordered,
			'min'          => $relation->min,
			'max'          => $relation->max,
			'create'       => $relation->create,
			'symmetric'    => $relation->symmetric,
			'translations' => $relation->translations->value,
			'control'      => $relation->control?->value,
			'inverse'      => $inverse === false ? false : [
				'label'   => $inverse->label,
				'page'    => $inverse->page,
				'archive' => $inverse->archive,
				'types'   => $inverse->types,
				'max'     => $inverse->max
			],
			'definition'   => $relation->toArray(),
			'origin'       => $origin->value,
			'editable'     => $origin === RelationOrigin::Data && $dataTypes && ! in_array($relation->name, $types->legacy, true)
		];
	}
}
