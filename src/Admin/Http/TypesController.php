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

namespace Blush\Admin\Http;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Role;
use Blush\Auth\Roles;
use Blush\Container\Container;
use Blush\Content\Http\RelatedController;
use Blush\Content\Relation\InvalidRelation;
use Blush\Content\Relation\Relation;
use Blush\Content\ContentConfig;
use Blush\Content\Entries;
use Blush\Content\Source\ContentFiles;
use Blush\Content\Type\BuiltInType;
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
use Blush\Field\ControlDefaults;
use Blush\Field\FieldFactory;
use Blush\Field\FormField;
use Blush\Field\Field;
use Blush\Field\LibraryField;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Plugin\Plugins;
use Blush\Translation\Messages;
use Blush\Workflow\Workflow;
use Blush\Workflow\Workflows;

/**
 * Answers `GET {path}/api/types` (D-233, D-234): the site's content types, so
 * the admin can list each type's entries and offer to create one. A type
 * is described by its name, its `labels` for display (D-278),
 * its `description` (`''` for none) and `icon` (`null` for its kind's),
 * its kind (`collection`, `tree`, or `profiles`), whether it's dated (its
 * new entries get a date and a dated file name), whether its
 * entries credit `authors` (D-329), whether they're `terms` (a classify
 * relation files entries under them, D-593), whether they nest by a
 * `parent` (`hierarchical`), their default `order`, and their place in
 * the admin's menu (D-726): `position` (`null` for none) and the type
 * it sits `under` (`null` for the top level, or for an `under` the menu
 * can't follow, `ContentTypes::menuParent()`). A term type adds
 * the `types` its relation files (empty for every type), which places it
 * in the admin's navigation; the profiles type adds the `types` that
 * credit profiles. Each also has its `origin` (`built-in`,
 * `extension`, or `data`), its `folder` (`_` and its name, D-683), its
 * URL `prefix` (a tree in a folder's own, `null` for one without URLs),
 * and how many `fields` it has of its own and from the library (D-250,
 * D-858).
 * Term types and the profiles type come last. Beside them, `authors`
 * names the authors type, which accounts' authors belong to and the
 * admin lists under Users rather than content, or `null` when the site
 * has none.
 *
 * `GET {path}/api/types/{name}` (`show()`) adds the fields its code
 * gives it (`ownFields`, as forms take them, each with its control), its
 * `fields` (the library fields it carries, in sections, as its
 * definition writes them, D-858, D-863), whether they're
 * `fieldsEditable` (any type's, when data types are read), the field `library` (each
 * field's `name`, `label`, `description`, whether it's `required`, its
 * `type`, and its `control`), the `taxonomies` (term types)
 * that file it, its `relations` (every relation definition from or to
 * it, as `RelationsController` describes them, D-593), whether it's
 * `public`, has a `feed`, is in the `sitemap` and `llms.txt` (`llms`,
 * D-398), whether it's `placeable` (its place in the admin's menu can be
 * changed, D-726: any type but the profiles type, even one that isn't
 * otherwise editable, when data types are read), and is `editable` (types
 * in `user/data/types`, D-311, and collections from code,
 * through a file there, D-349), whether it's `overridden` (a code type a
 * file changes) and the options that file sets (`overrides`), its
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
		private ContentFiles $files,
		private ControlDefaults $defaults,
		private Messages $messages,
		private Workflows $workflows,
		private Roles $roles,
		private Container $container,
		private FieldFactory $fields
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
			return Response::json(['error' => $this->messages->t('admin.types_controller.not_found', 'There\'s no "{name}" content type.', name: $name)], Status::NotFound, ['Cache-Control' => 'no-store']);
		}

		return Response::json($this->detail($this->types, $type), headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Answers `POST types/routes` (D-870): the routes a type being made
	 * would have, as `show()` gives a type's `routes`, so a new type's
	 * addresses can be set before Create. Takes the draft as Create does
	 * (`name`, `kind`, `set`, `authors`) and, for a taxonomy, `files`:
	 * the type is built from it with the relationships Create makes (its
	 * place in Authors, a taxonomy's classify relationship), among the
	 * site's types. Answers `{"routes"}`, or a `422` with why the draft
	 * isn't a type yet.
	 */
	public function preview(ServerRequestInterface $request): ResponseInterface
	{
		try {
			$input = json_decode((string) $request->getBody(), true, 64, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$input = [];
		}

		$input   = is_array($input) ? $input : [];
		$name    = is_string($input['name'] ?? null) && preg_match('/^[a-z][a-z0-9_]*$/', $input['name']) === 1 && $this->types->find($input['name']) === null ? $input['name'] : 'draft';
		$kind    = ($input['kind'] ?? 'collection') === 'tree' ? 'tree' : 'collection';
		$set     = is_array($input['set'] ?? null) ? $input['set'] : [];
		$files   = is_array($input['files'] ?? null) ? array_values(array_filter($input['files'], is_string(...))) : [];
		$routes  = ($set['routes'] ?? true) !== false;
		$prefix  = is_string($set['prefix'] ?? null) && trim($set['prefix'], '/ ') !== '' ? trim($set['prefix'], '/ ') : null;
		$draft   = ['name' => $name, 'kind' => $kind];

		foreach (['public', 'dateArchives', 'hierarchical', 'order', 'feed'] as $option) {
			if (array_key_exists($option, $set) && $set[$option] !== null) {
				$draft[$option] = $set[$option];
			}
		}

		if ($kind === 'tree') {
			$draft['prefix'] = $prefix;
		} else {
			$draft['urls'] = $routes ? array_filter(['prefix' => $prefix], static fn (?string $value): bool => $value !== null) : false;
		}

		try {
			$type      = ContentType::fromArray($draft, $this->fields, $this->messages);
			$relations = $this->types->relations();
			$profiles  = $this->types->profiles();
			$authors   = $relations[Relation::AUTHORS] ?? null;

			if (($input['authors'] ?? false) === true && $profiles !== null && $kind !== 'tree') {
				$relations[Relation::AUTHORS] = $authors === null
					? Relation::authors([$name], $profiles->name, $this->messages)
					: ($authors->from === [] ? $authors : Relation::fromArray([...$authors->toArray(), 'from' => [...$authors->from, $name]], $this->messages));
			}

			if (($input['taxonomy'] ?? false) === true && $kind === 'collection') {
				$relations[$name] = Relation::fromArray(['name' => $name, 'kind' => 'classify', 'from' => $files, 'to' => [$name], 'create' => true], $this->messages);
			}
		} catch (InvalidContentType | InvalidRelation $error) {
			return Response::json(['error' => $error->getMessage()], Status::UnprocessableContent, ['Cache-Control' => 'no-store']);
		}

		$types = new ContentTypes([...$this->types->all(), $name => $type], [], $this->types->home, $this->types->library, [], $relations, messages: $this->messages);

		return Response::json(['routes' => $this->routes($types, $type)], headers: ['Cache-Control' => 'no-store']);
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
			// The workflows a type may use (D-851), for its select.
			'workflows'    => array_values(array_map(static fn (Workflow $workflow): array => ['name' => $workflow->name, 'label' => (string) $workflow->label], $this->workflows->all())),
			'public'       => $type->public,
			'feed'         => $type->hasFeed(),
			'sitemap'      => $type->sitemap,
			'llms'         => $type->llms,
			'editable'     => $editable && ($file !== null || ! $data),
			'placeable'    => ! $type instanceof Profiles && $this->config->dataTypes,
			'overridden'   => $types->isOverridden($name),
			'overrides'    => $types->isOverridden($name) ? $this->overrides($name) : [],
			'routes'       => $this->routes($types, $type),
			'taxonomies'   => $taxonomies,
			'relations'    => array_values(array_map(fn (Relation $relation): array => RelationsController::describe($types, $relation, $this->config->dataTypes), $relations)),
			'ownFields'    => array_map(static fn (FormField $field): array => $field->toArray(), FormField::all($type->schema, $type->controls, $this->defaults)),
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
			'fields'       => $type->layout->toArray(),
			'fieldsEditable' => $this->config->dataTypes,
			// The optional core fields it has (D-866).
			// The labels it sets, the rest made from its names (D-866).
			'labelsSet'    => (object) $type->labels->toArray($type->name, BuiltInType::labelsFor($type->kind(), $type->name, $this->messages)),
			'supports'     => ['image' => $type->image, 'summary' => $type->summary, 'subtitle' => $type->subtitle, 'position' => $type->position, 'visibility' => $type->visibility, 'expires' => $type->expires],
			'listing'      => (object) $type->listing->toArray(),
			'feedSettings' => $type->feed === false ? null : (object) $type->feed->toArray(),
			'hasRoutes'    => $type->hasUrls(),
			'code'         => $this->code($name),
			'roles'        => $this->roles($name),
			'library'      => array_values(array_map(fn (LibraryField $field): array => [
				'name'        => $field->name(),
				'label'       => $field->field->label,
				'description' => $field->field->description,
				'required'    => $field->field->required,
				'type'        => $field->field->type(),
				'control'     => FormField::of($field->field, $field->controls, $this->defaults)->control::name()
			], $types->library->all()))
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
			'taxonomy'       => $types->classification($type->name) !== null,
			'hierarchical' => $types->nestsByParent($type->name),
			'order'       => $type instanceof Collection ? $type->order->value : null,
			'adminPosition' => $type->adminPosition,
			'adminUnder'  => $types->menuParent($type->name),
			// Its workflow (D-851), and its statuses, which its list's tabs
			// show.
			'workflow'    => $this->workflows->for($type)->name,
			// Whether its entries may expire (D-852).
			'expires'     => $type->expires,
			// Whether its entries choose a visibility (D-855), and
			// whether it can: only a public type's do.
			'visibility'  => $type->visibility,
			'statuses'    => $this->workflows->for($type)->statuses,
			...($types->classification($type->name) !== null ? ['types' => $types->classification($type->name)->from] : []),
			...($type instanceof Profiles ? ['types' => array_keys($types->crediting())] : []),
			'origin'      => $types->origin($type->name)->value,
			'public'      => $type->public,
			'folder'      => $type->folder,
			'prefix'      => match (true) {
				$type->hasUrls()                              => '/' . $type->prefix(),
				$type instanceof Tree && ! $type->atRoot()    => '/' . $type->pagePath(),
				default                                       => null
			},
			'fields'      => count($type->schema->fields) + count($type->layout->names()),
			'entries'     => $this->content->query()->type($type->name)->anyStatus()->count(),
			// The plugin that defines it, and how many options a file here
			// changes (D-866).
			'plugin'      => $this->plugin($types, $type->name),
			'changed'     => $types->isOverridden($type->name) ? count($this->overrides($type->name)) : 0
		];
	}

	/**
	 * The label of the plugin that defines a type, or `null` for a type
	 * that isn't a plugin's (or whose plugin can't be told).
	 */
	private function plugin(ContentTypes $types, string $name): ?string
	{
		$source = $types->source($name);

		if ($source === null || ! $this->container->has(Plugins::class)) {
			return null;
		}

		return $this->container->make(Plugins::class)->owning($source)?->label;
	}

	/**
	 * A plugin type's options as its code defines them, to show what a
	 * file here changes (D-866); `null` for a type that isn't a plugin's.
	 *
	 * @return ?array<string, mixed>
	 */
	private function code(string $name): ?array
	{
		try {
			$code = $this->writer->codeType($name);
		} catch (InvalidContentType) {
			return null;
		}

		return $code === null ? null : array_diff_key($code->toArray(), ['fields' => true, ContentType::OWN_FIELDS => true]);
	}

	/**
	 * Who can use a type (D-866): the labels of the roles that grant
	 * every type (`content.*.…`, or everything), of those that name it,
	 * and of those that name other types but not it.
	 *
	 * @return array{every: list<string>, named: list<string>, others: list<string>}
	 */
	private function roles(string $name): array
	{
		$out = ['every' => [], 'named' => [], 'others' => []];

		foreach ($this->roles->all() as $role) {
			$caps  = $role->capabilities;
			$label = (string) $role->label;

			if (in_array(Role::ALL, $caps, true) || array_any($caps, static fn (string $cap): bool => str_starts_with($cap, 'content.*.'))) {
				$out['every'][] = $label;
			} elseif (array_any($caps, static fn (string $cap): bool => str_starts_with($cap, "content.{$name}."))) {
				$out['named'][] = $label;
			} elseif (array_any($caps, static fn (string $cap): bool => str_starts_with($cap, 'content.'))) {
				$out['others'][] = $label;
			}
		}

		return $out;
	}
}
