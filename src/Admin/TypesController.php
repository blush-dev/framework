<?php

/**
 * Admin content types controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Psr\Http\Message\ResponseInterface;
use Blush\Auth\AuthConfig;
use Blush\Content\Schema\Field;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DateArchives;
use Blush\Content\Type\Taxonomy;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Answers `GET {path}/api/types` (D-233, D-234): the site's content types, so
 * the admin can list each type's entries and offer to create one. A type
 * is described by its name, its `label` and `singular` names for people,
 * its `description` (`''` for none) and `icon` (`null` for its kind's),
 * its kind (`collection`, `taxonomy`, or `pages`), and whether it's dated
 * (its new entries get a publish date and a dated file name). A taxonomy
 * adds the `types` its terms group (empty for every type), which places it
 * in the admin's navigation, and whether it's `hierarchical`. Each also has its `origin` (`built-in`,
 * `extension`, `config`, or `data`), its `folder`, its URL `prefix` (or
 * `null` without URLs), and how many `fields` it defines (D-250).
 * Taxonomies come last. Beside them, `authors` names the type accounts'
 * authors belong to (`AuthConfig::$authorTaxonomy`), which the admin
 * lists with people rather than content, or `null` when it's disabled.
 *
 * `GET {path}/api/types/{name}` (`show()`) adds the type's own fields
 * (`Field::toArray()`), the `taxonomies` that group it, whether it's
 * `public`, has a `feed`, is in the `sitemap`, and is `editable` (only
 * `user/data/types` types will be).
 */
final readonly class TypesController
{
	public function __construct(
		private ContentTypes $types,
		private AuthConfig $auth
	) {}

	public function __invoke(): ResponseInterface
	{
		$types = array_values(array_map($this->summary(...), $this->types->all()));

		usort($types, static fn (array $a, array $b): int => [$a['kind'] === 'taxonomy', $a['label']] <=> [$b['kind'] === 'taxonomy', $b['label']]);

		return Response::json([
			'types'   => $types,
			'authors' => $this->types->find($this->auth->authorTaxonomy) instanceof Taxonomy ? $this->auth->authorTaxonomy : null
		], headers: ['Cache-Control' => 'no-store']);
	}

	public function show(string $name): ResponseInterface
	{
		$type = $this->types->find($name);

		if ($type === null) {
			return Response::json(['error' => sprintf('There\'s no "%s" content type.', $name)], Status::NotFound, ['Cache-Control' => 'no-store']);
		}

		$taxonomies = array_values(array_map(
			static fn (Taxonomy $taxonomy): string => $taxonomy->name,
			array_filter($this->types->taxonomies(), static fn (Taxonomy $taxonomy): bool => $taxonomy->name !== $name && ($taxonomy->types === [] || in_array($name, $taxonomy->types, true)))
		));

		return Response::json([
			...$this->summary($type),
			'public'     => $type->public,
			'feed'       => $type->hasFeed(),
			'sitemap'    => $type->sitemap,
			'editable'   => $this->types->origin($name)->isEditable(),
			'taxonomies' => $taxonomies,
			'fields'     => array_values(array_map(static fn (Field $field): array => array_diff_key($field->toArray(), ['class' => true]), $type->schema->fields))
		], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * A type as the list describes it.
	 *
	 * @return array<string, mixed>
	 */
	private function summary(ContentType $type): array
	{
		return [
			'name'        => $type->name,
			'label'       => $type->label,
			'singular'    => $type->singular,
			'description' => $type->description,
			'icon'        => $type->icon,
			'kind'        => $type->kind()->value,
			'dated'       => $type->dateArchives !== DateArchives::None,
			...($type instanceof Taxonomy ? ['types' => $type->types, 'hierarchical' => $type->hierarchical] : []),
			'origin'      => $this->types->origin($type->name)->value,
			'folder'      => $type->folder,
			'prefix'      => $type->hasUrls() ? '/' . $type->prefix() : null,
			'fields'      => count($type->schema->fields)
		];
	}
}
