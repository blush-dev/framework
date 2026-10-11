<?php

/**
 * API types controller.
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
use Blush\Api\ApiError;
use Blush\Api\ApiQuery;
use Blush\Api\ApiResponses;
use Blush\Api\EntryResource;
use Blush\Content\Relation\Relations;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentType;
use Blush\Field\Field;
use Blush\Field\Fields\ListField;

/**
 * Answers `/types` and `/types/{name}` (D-736): the public content
 * types, by name. Each says its kind, labels, description, address
 * (its collection's, when it has one), whether its entries have
 * addresses, the site's own fields (name, type, label, description,
 * whether it's required, and a list's item type), and the relations its
 * entries list.
 */
final readonly class TypesController
{
	public function __construct(
		private ApiResponses $responses,
		private ApiEntries $entries,
		private Relations $relations,
		private ContentUrls $urls
	) {}

	public function index(ServerRequestInterface $request): ResponseInterface
	{
		return $this->responses->answer($request, function () use ($request): array {
			$query   = ApiQuery::from($request, ['page', 'per_page']);
			$types   = array_values($this->entries->types());
			$page    = $query->page();
			$perPage = $query->perPage();

			return $this->responses->page(
				$request,
				array_map($this->type(...), array_slice($types, ($page - 1) * $perPage, $perPage)),
				count($types),
				$page,
				$perPage
			);
		});
	}

	public function show(ServerRequestInterface $request, string $name): ResponseInterface
	{
		return $this->responses->answer($request, function () use ($request, $name): array {
			ApiQuery::from($request, []);

			$type = $this->entries->types()[$name] ?? throw ApiError::notFound(sprintf('There is no "%s" type.', $name));

			return $this->type($type);
		});
	}

	/**
	 * Returns a type as the API sends it.
	 *
	 * @return array<string, mixed>
	 */
	private function type(ContentType $type): array
	{
		$collection = $this->urls->collection($type);

		return [
			'name'        => $type->name,
			'kind'        => $type->kind()->value,
			'label'       => $type->labels->singular,
			'plural'      => $type->labels->plural,
			'description' => $type->description,
			'url'         => $collection === null ? null : $this->urls->absolute($collection),
			'urls'        => $this->entries->hasUrls($type),
			'fields'      => array_values(array_map(static fn (Field $field): array => [
				'name'        => $field->name,
				'type'        => $field->type(),
				'label'       => $field->label,
				'description' => $field->description,
				'required'    => $field->required,
				...($field instanceof ListField ? ['item' => $field->item->type()] : [])
			], $type->schema->fields)),
			'relations'   => array_keys(array_filter($this->relations->for($type->name), EntryResource::lists(...)))
		];
	}
}
