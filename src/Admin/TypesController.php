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
use Blush\Content\Http\RelatedController;
use Blush\Content\Relation\Relation;
use Blush\Content\ContentConfig;
use Blush\Content\Entries;
use Blush\Content\Source\ContentFiles;
use Blush\Content\Type\Collection;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DataTypeWriter;
use Blush\Content\Type\InvalidContentType;
use Blush\Content\Type\Tree;
use Blush\Content\Type\TypeOrigin;
use Blush\Content\Type\TypeRouteKeys;
use Blush\Content\Type\TypeUrls;
use Blush\Content\Writer\WriteException;
use Blush\Feed\FeedConfig;
use Blush\Feed\FeedFormat;
use Blush\Content\Type\DateArchives;
use Blush\Content\Type\Profiles;
use Blush\Field\Field;
use Blush\Field\FieldSet;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Answers `GET {path}/api/types` (D-233, D-234): the site's content types, so
 * the admin can list each type's entries and offer to create one. A type
 * is described by its name, its `labels` for people (D-278),
 * its `description` (`''` for none) and `icon` (`null` for its kind's),
 * its kind (`collection`, `tree`, or `profiles`), whether it's dated (its
 * new entries get a publish date and a dated file name), whether its
 * entries credit `authors` (D-329), whether they're `terms` (a classify
 * relation files entries under them, D-593), whether they nest by a
 * `parent` (`hierarchical`), and their default `order`. A term type adds
 * the `types` its relation files (empty for every type), which places it
 * in the admin's navigation; the profiles type adds the `types` that
 * credit people. Each also has its `origin` (`built-in`,
 * `extension`, or `data`), its `folder` (`_` and its name, D-683), its
 * URL `prefix` (a tree in a folder's own, `null` for one without URLs),
 * and how many `fields` it defines (D-250).
 * Term types and the profiles type come last. Beside them, `authors`
 * names the authors type, which accounts' authors belong to and the
 * admin lists with people rather than content, or `null` when the site
 * has none.
 *
 * `GET {path}/api/types/{name}` (`show()`) adds the type's own fields
 * (`Field::toArray()`), the field `sets` attached to it (`name`,
 * `label`, and how many `fields`, D-337), the `taxonomies` (term types)
 * that file it, its `relations` (every relation definition from or to
 * it, as `RelationsController` describes them, D-593), whether it's
 * `public`, has a `feed`, is in the `sitemap` and `llms.txt` (`llms`,
 * D-398), and is `editable` (types
 * in `user/data/types`, D-311, and collections from code,
 * through a file there, D-349), whether it's `overridden` (a code type a
 * file changes) and the options that file sets (`overrides`), whether
 * its `fieldsEditable` (none is a field class of the code's own), its
 * `dateArchives`, its own file name pattern (`filename`, D-511, D-514), its folder pattern (`folders`, D-629, `null` for none), the prefix it has without one of its own, its name (`defaultPrefix`, D-683), the data
 * `file` it's defined or changed in (`null` for the rest), its `routes`
 * (each route key it answers at, with its `path` and `default` relative
 * to the prefix, the placeholders it `requires` and `allows`, and
 * whether it's at the site's `root`, as the home type's feeds are,
 * D-350), its `index` page (`{"id", "type", "path", "title"}`, or `null`),
 * its `byline` relation as it names one (`null` for its only credit),
 * its `credits` (the credit relations from it), and its `archivePages`:
 * each relation archive under it (D-602) with its `relation`, `label`,
 * `word`, and list `page` (`{"id", "type", "path", "title"}`, or
 * `null`).
 * The list adds whether types can be created here (`create`: data types
 * are read), whether they may set URLs (`urls`), and whether content is
 * kept in files (`files`), which alone have file name and folder
 * patterns (D-683).
 *
 * `detail()` describes a type among any set of types, so a change can be
 * answered with the types it made (`TypeEditController`).
 */
final readonly class TypesController
{
	public function __construct(
		private ContentTypes $types,
		private ContentConfig $config,
		private DataTypeWriter $writer,
		private Entries $content,
		private FeedConfig $feeds,
		private ContentFiles $files
	) {}

	public function __invoke(): ResponseInterface
	{
		$types = array_values($this->types->all());

		usort($types, fn (ContentType $a, ContentType $b): int => [$this->types->isTermType($a->name), $a->labels->plural] <=> [$this->types->isTermType($b->name), $b->labels->plural]);

		$types = array_map(fn (ContentType $type): array => $this->summary($this->types, $type), $types);

		return Response::json([
			'types'   => $types,
			'authors' => $this->types->profiles()?->name,
			'create'  => $this->config->dataTypes,
			'urls'    => $this->config->dataTypeUrls,
			'files'   => $this->files->kept()
		], headers: ['Cache-Control' => 'no-store']);
	}

	public function show(string $name): ResponseInterface
	{
		$type = $this->types->find($name);

		if ($type === null) {
			return Response::json(['error' => sprintf('There\'s no "%s" content type.', $name)], Status::NotFound, ['Cache-Control' => 'no-store']);
		}

		return Response::json($this->detail($this->types, $type), headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Describes a type among a set of types, as `show()` does.
	 *
	 * @return array<string, mixed>
	 */
	public function detail(ContentTypes $types, ContentType $type): array
	{
		$name       = $type->name;
		$taxonomies = array_values(array_map(
			static fn (Relation $relation): string => $relation->name,
			array_filter($types->classifications(), static fn (Relation $relation): bool => $relation->name !== $name && $relation->isFrom($name))
		));
		$relations  = array_filter($types->relations(), static fn (Relation $relation): bool => in_array($name, [...$relation->from, ...$relation->to], true) || ($relation->from === [] && ! $relation->isTo($name)));

		$data     = $types->origin($name) === TypeOrigin::Data;
		$editable = $types->isEditable($name) && $this->config->dataTypes;

		try {
			$file = $data || $types->isOverridden($name) ? $this->writer->location($name) : null;
		} catch (InvalidContentType) {
			$file = null;
		}

		return [
			...$this->summary($types, $type),
			'public'       => $type->public,
			'feed'         => $type->hasFeed(),
			'sitemap'      => $type->sitemap,
			'llms'         => $type->llms,
			'editable'     => $editable && ($file !== null || ! $data),
			'overridden'   => $types->isOverridden($name),
			'overrides'    => $types->isOverridden($name) ? $this->overrides($name) : [],
			'fieldsEditable' => $data || $this->writer->fieldsEditable($type),
			'routes'       => $this->routes($types, $type),
			'taxonomies'   => $taxonomies,
			'relations'    => array_values(array_map(fn (Relation $relation): array => RelationsController::describe($types, $relation, $this->config->dataTypes), $relations)),
			'fields'       => array_values(array_map(static fn (Field $field): array => array_diff_key($field->toArray(), ['class' => true]), $type->schema->fields)),
			'dateArchives' => $type->dateArchives->value,
			'filename'     => $type->filename?->pattern,
			'folders'      => $type->folders?->pattern,
			'defaultPrefix' => $type->name,
			'file'         => $file,
			'index'        => $this->index($type),
			'byline'       => $type->byline,
			'credits'      => array_keys($types->credits($name)),
			'archivePages' => array_values(array_map(fn (Relation $relation): array => [
				'relation' => $relation->name,
				'label'    => $relation->label === '' ? ucfirst(str_replace('_', ' ', $relation->name)) : $relation->label,
				'word'     => (string) ($relation->inverse === false ? $relation->name : $relation->inverse->archive),
				'page'     => $this->page($type, RelatedController::word($relation), $relation->label === '' ? ucfirst(str_replace('_', ' ', $relation->name)) : $relation->label)
			], $types->relationArchives($type))),
			'sets'         => array_map(static fn (FieldSet $set): array => [
				'name'   => $set->name,
				'label'  => $set->label,
				'fields' => count($set->schema->fields)
			], $types->setsFor($name))
		];
	}

	/**
	 * The options a record changing a code type sets, by their 2.x names.
	 *
	 * @return list<string>
	 */
	private function overrides(string $name): array
	{
		try {
			$keys = array_map(strval(...), array_keys($this->writer->data($name)));
		} catch (InvalidContentType) {
			return [];
		}

		$names = [];

		foreach ($keys as $key) {
			$names[] = array_find_key(DataTypeWriter::OPTIONS, static fn (array $old, string $name): bool => in_array($key, $old, true)) ?? $key;
		}

		return array_values(array_unique($names));
	}

	/**
	 * The route keys a type answers at (D-350), each with its path and
	 * default, relative to the prefix, and the placeholders it requires
	 * and allows.
	 *
	 * A relation archive's key (D-596) names its relation's label as
	 * `relation`, else `null`.
	 *
	 * @return list<array{key: string, path: string, default: string, requires: list<string>, allows: list<string>, root: bool, relation: ?string}>
	 */
	private function routes(ContentTypes $types, ContentType $type): array
	{
		if ($type->urls === false) {
			return [];
		}

		$home       = $type->name === $types->home;
		$feeds      = array_map(static fn (FeedFormat $format): string => $format->routeSuffix(), $this->feeds->formats);
		$taxonomies = array_keys($types->termTypes());
		$archives   = $types->relationArchives($type);
		$relations  = array_keys($archives);
		$defaults   = [...TypeUrls::DEFAULT_PATHS, ...$types->relationPaths($type)];
		$routes     = [];

		foreach (TypeRouteKeys::keys($type, $home, $feeds, $types->hasTermPages($type->name), $relations) as $key) {
			$params   = TypeRouteKeys::params($type, $key, $taxonomies, $relations);
			$relation = $archives[strstr($key, '.', true) ?: $key] ?? null;
			$routes[] = [
				'key'      => $key,
				'path'     => $type->urls->path($key) ?? '',
				'default'  => $defaults[$key] ?? '',
				'requires' => $params['required'],
				'allows'   => $params['optional'],
				'root'     => $home && str_starts_with($key, 'collection.feed'),
				'relation' => $relation === null ? null : ($relation->label === '' ? ucfirst(str_replace('_', ' ', $relation->name)) : $relation->label)
			];
		}

		return $routes;
	}

	/**
	 * A collection's or tree's index page (D-255): its landing page.
	 *
	 * @return ?array{id: ?string, type: string, path: string, title: string}
	 */
	private function index(ContentType $type): ?array
	{
		return $this->page($type, 'index', $type->labels->plural);
	}

	/**
	 * A page a type keeps at a key (`index` for its landing page), as
	 * it's stored, so a type just created has it: `{"id", "type", "path",
	 * "title"}` (`id` is `null` until it has one), titled with its own
	 * title or the fallback, or `null`.
	 *
	 * @return ?array{id: ?string, type: string, path: string, title: string}
	 */
	private function page(ContentType $type, string $key, string $fallback): ?array
	{
		if ($type->folder === '') {
			return null;
		}

		try {
			$page = $this->content->editableAt($type, $key);
		} catch (WriteException) {
			return null;
		}

		$title = $page?->frontMatter['title'] ?? null;

		return $page === null ? null : [
			'id'    => $page->id === '' ? null : $page->id,
			'type'  => $type->name,
			'path'  => $page->path ?? '',
			'title' => is_string($title) && $title !== '' ? $title : $fallback
		];
	}

	/**
	 * A type as the list describes it.
	 *
	 * @return array<string, mixed>
	 */
	private function summary(ContentTypes $types, ContentType $type): array
	{
		return [
			'name'        => $type->name,
			'labels'      => $type->labels->all(),
			'description' => $type->description,
			'icon'        => $type->icon,
			'kind'        => $type->kind()->value,
			'dated'       => $type->dateArchives !== DateArchives::None,
			'authors'     => $types->credits($type->name) !== [],
			'terms'       => $types->classification($type->name) !== null,
			'hierarchical' => $types->nestsByParent($type->name),
			'order'       => $type instanceof Collection ? $type->order->value : null,
			...($types->classification($type->name) !== null ? ['types' => $types->classification($type->name)->from] : []),
			...($type instanceof Profiles ? ['types' => array_keys($types->crediting())] : []),
			'origin'      => $types->origin($type->name)->value,
			'folder'      => $type->folder,
			'prefix'      => match (true) {
				$type->hasUrls()                              => '/' . $type->prefix(),
				$type instanceof Tree && ! $type->atRoot()    => '/' . $type->pagePath(),
				default                                       => null
			},
			'fields'      => count($type->schema->fields)
		];
	}
}
