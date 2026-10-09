<?php

/**
 * Admin type editing controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Closure;
use JsonException;
use Throwable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Cache\ContentVersion;
use Blush\Content\Http\RelatedController;
use Blush\Content\Index\Indexer;
use Blush\Content\Entries;
use Blush\Content\Relation\DataRelationWriter;
use Blush\Content\Relation\InvalidRelation;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\RelationChanges;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypeCache;
use Blush\Content\Type\ContentTypeLoader;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DataTypeWriter;
use Blush\Content\Type\InvalidContentType;
use Blush\Content\Type\Tree;
use Blush\Content\Type\TypeKind;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\WriteException;
use Blush\Core\AppConfig;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Routing\RouteCache;

/**
 * Creates, changes, and deletes the content types the site defines in
 * `user/data/types` (D-311), and changes the collections code defines
 * through a file there (D-349), and the relations the site defines in
 * `user/data/relations` (D-593), for accounts with `site.settings`:
 *
 * - `POST types`: `{"name", "kind"` (`collection` or `tree`),
 *   `"set"`, `"index"`, `"listPages"`, `"authors"}`, kept in `_` and its
 *   name (D-683); answers `201` with
 *   the type as `GET types/{name}` describes it.
 * - `PATCH types/{name}`: `{"set", "index", "listPages"}`; answers
 *   with the type.
 * - `DELETE types/{name}`: deletes its file (its entries stay); answers
 *   `{"deleted"}`.
 * - `POST types/{name}/reset`: deletes the file changing a code type,
 *   so it's as the code defines it; answers with the type.
 * - `POST types/refresh`: compiles the routes again (when the site keeps
 *   them compiled) and reindexes, so the next requests see the change.
 * - `POST relations`: a relation's definition (`Relation::fromArray()`,
 *   with its `name`); `PATCH relations/{name}`: its whole definition
 *   again; both answer with the relation as `RelationsController`
 *   describes it (`201` for a new one). A change to a relation entries
 *   use is checked first (D-600, `RelationChanges`): it's refused (`422`)
 *   when it points at another type while entries have values, or takes
 *   one where an entry has several; `rewrite: true` moves values to a
 *   new key (else the old keys become aliases), and `strip: true`
 *   removes the values of types it no longer files. `POST
 *   relations/{name}/check` answers what a definition would do
 *   (`RelationCheck`) without saving it. `DELETE relations/{name}`
 *   deletes its file; entries keep their values unless `?strip=1`
 *   removes them first; answers `{"deleted", "stripped"}`. `GET
 *   relations/{name}/uses` answers how many entries have values
 *   (`{"entries"}`).
 *
 * `set` holds the options to change (`DataTypeWriter`), including
 * `paths`, route keys' paths (D-350); `index: true`
 * gives a collection its index page (D-255), `{folder}/index.md`
 * titled with its plural name, when it has none, and `listPages` (names
 * of relations with archives under it, D-602) gives each its list page,
 * `{folder}/_{word}.md` titled with the relation's label, when it has
 * none. A new type with `authors: true` is added to the `authors` credit
 * relation's `from` (written when there's none, D-602). A change that doesn't
 * fit (an unknown option, a field that isn't one, a reserved name) is a
 * `422` with the reason, and nothing is written.
 *
 * A change is answered from the types it made, since this request still
 * holds the ones it started with. When the site keeps its types
 * compiled, the compiled types are written again, so the next request
 * has the change; the index notices its types changed and rebuilds
 * itself, and the admin then asks for `types/refresh`, which runs with
 * the new types, for the routes.
 */
final readonly class TypeEditController
{
	public function __construct(
		private DataTypeWriter $writer,
		private DataRelationWriter $relations,
		private TypesController $types,
		private ContentTypeCache $cache,
		private RouteCache $routes,
		private Indexer $indexer,
		private ContentVersion $version,
		private AppConfig $app,
		private Entries $content,
		private Permissions $permissions,
		private ContentTypeLoader $loader,
		private RelationChanges $changes
	) {}

	public function create(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::forbidden();
		}

		$input = self::input($request);
		$name  = $input['name'] ?? null;
		$kind  = TypeKind::tryFrom(is_string($input['kind'] ?? null) ? $input['kind'] : TypeKind::Collection->value);
		$set   = $input['set'] ?? [];

		if (! is_string($name) || $kind === null || ! is_array($set) || ($set !== [] && array_is_list($set))) {
			return self::error('Send a "name", a "kind" (collection or tree), and "set" (options to values).', Status::BadRequest);
		}

		/** @var array<string, mixed> $set */
		$authors = ($input['authors'] ?? false) === true;

		return $this->changed(fn (): ContentTypes => $authors ? $this->credit($this->writer->create($name, $kind, $set), $name) : $this->writer->create($name, $kind, $set), $name, ($input['index'] ?? false) === true, self::listPages($input), Status::Created);
	}

	public function update(ServerRequestInterface $request, string $name): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::forbidden();
		}

		$input = self::input($request);
		$set   = $input['set'] ?? [];

		if (! is_array($set) || ($set !== [] && array_is_list($set))) {
			return self::error('Send "set" (options to values).', Status::BadRequest);
		}

		/** @var array<string, mixed> $set */
		return $this->changed(fn (): ContentTypes => $this->writer->update($name, $set), $name, ($input['index'] ?? false) === true, self::listPages($input));
	}

	public function delete(ServerRequestInterface $request, string $name): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::forbidden();
		}

		try {
			$this->writer->delete($name);
			$this->compile();
		} catch (InvalidContentType $error) {
			return self::error($error->getMessage(), Status::UnprocessableContent);
		}

		$this->version->bump();

		return Response::json(['deleted' => $name], headers: ['Cache-Control' => 'no-store']);
	}

	public function reset(ServerRequestInterface $request, string $name): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::forbidden();
		}

		return $this->changed(fn (): ContentTypes => $this->writer->reset($name), $name, false, []);
	}

	public function createRelation(ServerRequestInterface $request): ResponseInterface
	{
		return $this->relationChanged($request, null, Status::Created);
	}

	public function updateRelation(ServerRequestInterface $request, string $name): ResponseInterface
	{
		return $this->relationChanged($request, $name, Status::Ok);
	}

	public function deleteRelation(ServerRequestInterface $request, string $name): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::forbidden();
		}

		$strip    = in_array($request->getQueryParams()['strip'] ?? null, ['1', 'true'], true);
		$stripped = [];

		try {
			$existing = $this->existing($name);

			// Removed from the files first, while the relation still says
			// where its values are (D-600).
			if ($strip && $existing !== null && $this->relations->location($name) !== null) {
				$stripped = $this->changes->strip($existing);
			}

			$this->relations->delete($name);
			$this->compile();
		} catch (InvalidContentType | WriteException $error) {
			return self::error($error->getMessage(), Status::UnprocessableContent);
		}

		$this->version->bump();

		return Response::json(['deleted' => $name, 'stripped' => count($stripped)], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Answers how many entries have values in a relation (D-600).
	 */
	public function relationUses(ServerRequestInterface $request, string $name): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::forbidden();
		}

		try {
			$existing = $this->existing($name);
		} catch (InvalidContentType $error) {
			return self::error($error->getMessage(), Status::UnprocessableContent);
		}

		return $existing === null
			? self::error(sprintf('There\'s no "%s" relation.', $name), Status::NotFound)
			: Response::json(['entries' => count($this->changes->uses($existing))], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Answers what replacing a relation's definition would do to the
	 * entries using it, without saving it (D-600).
	 */
	public function checkRelation(ServerRequestInterface $request, string $name): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::forbidden();
		}

		try {
			$existing = $this->existing($name);
			$proposed = Relation::fromArray([...self::input($request), 'name' => $name]);
		} catch (InvalidRelation | InvalidContentType $error) {
			return self::error($error->getMessage(), Status::UnprocessableContent);
		}

		return $existing === null
			? self::error(sprintf('There\'s no "%s" relation.', $name), Status::NotFound)
			: Response::json($this->changes->check($existing, $proposed)->toArray(), headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Returns a relation's definition as the site has it now, or `null`.
	 *
	 * @throws InvalidContentType
	 */
	private function existing(string $name): ?Relation
	{
		return $this->loader->load()->relations()[$name] ?? null;
	}

	public function refresh(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::forbidden();
		}

		try {
			$routes = $this->compiled() && is_file($this->routes->path());

			if ($routes) {
				$this->routes->write();
			}

			$report = $this->indexer->index();
		} catch (Throwable $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		$this->version->bump();

		return Response::json(['routes' => $routes, 'indexed' => $report->total], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Makes a change, then compiles the types, adds the index page and
	 * relation archives' list pages when asked, and answers with the type.
	 *
	 * @param Closure(): ContentTypes $change
	 * @param list<string>            $listPages The relation archives to give list pages.
	 */
	private function changed(Closure $change, string $name, bool $index, array $listPages, Status $status = Status::Ok): ResponseInterface
	{
		try {
			$types = $change();
			$type  = $types->get($name);

			$this->compile();

			if ($index) {
				$this->addIndex($type);
			}

			foreach ($listPages as $field) {
				$this->addListPage($types, $type, $field);
			}
		} catch (InvalidContentType $error) {
			return self::error($error->getMessage(), Status::UnprocessableContent);
		}

		$this->version->bump();

		return Response::json($this->types->detail($types, $type), $status, ['Cache-Control' => 'no-store']);
	}

	/**
	 * Creates a relation (`$name` is `null`) or replaces one's definition,
	 * then compiles the types and answers with the relation.
	 */
	private function relationChanged(ServerRequestInterface $request, ?string $name, Status $status): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::forbidden();
		}

		$input = self::input($request);

		try {
			$relation = Relation::fromArray($name === null ? $input : [...$input, 'name' => $name]);
			$existing = $name === null || $this->relations->location($name) === null ? null : $this->existing($name);

			// What entries already use is checked, and the files fitted to
			// the change (D-600).
			if ($existing !== null) {
				$relation = $this->changes->apply($existing, $relation, ($input['rewrite'] ?? false) === true, ($input['strip'] ?? false) === true);
			}

			$types = $name === null ? $this->relations->create($relation) : $this->relations->update($relation);

			$this->compile();
		} catch (InvalidRelation | InvalidContentType | WriteException $error) {
			return self::error($error->getMessage(), Status::UnprocessableContent);
		}

		$this->version->bump();

		return Response::json(RelationsController::describe($types, $types->relations()[$relation->name] ?? $relation, true), $status, ['Cache-Control' => 'no-store']);
	}

	/**
	 * Writes the compiled types again, when the site keeps them compiled.
	 *
	 * @throws InvalidContentType
	 */
	private function compile(): void
	{
		if ($this->compiled() && is_file($this->cache->path())) {
			$this->cache->write();
		}
	}

	private function compiled(): bool
	{
		return ! $this->app->environment->isDevelopment();
	}

	/**
	 * Gives a type its index page, unless it has one or can't have one.
	 *
	 * @throws InvalidContentType
	 */
	private function addIndex(ContentType $type): void
	{
		if ($type->folder === '' || ! ($type->hasUrls() || $type instanceof Tree)) {
			throw new InvalidContentType(sprintf('%s have no index page.', $type->labels->plural));
		}

		$this->addPage($type, 'index', $type->labels->plural, 'index');
	}

	/**
	 * Gives a type a relation archive's list page (`_cooks`, D-602),
	 * titled with the relation's label, unless it has one.
	 *
	 * @throws InvalidContentType
	 */
	private function addListPage(ContentTypes $types, ContentType $type, string $name): void
	{
		$relation = $types->relationArchives($type)[$name] ?? null;

		if ($type->folder === '' || $relation === null) {
			throw new InvalidContentType(sprintf('%s have no "%s" archives.', $type->labels->plural, $name));
		}

		$label = $relation->label === '' ? ucfirst(str_replace('_', ' ', $relation->name)) : $relation->label;

		$this->addPage($type, RelatedController::word($relation), $label, mb_strtolower($label));
	}

	/**
	 * Writes a page a type keeps at a key (`index` for its landing page),
	 * unless it has one.
	 *
	 * @throws InvalidContentType
	 */
	private function addPage(ContentType $type, string $key, string $title, string $what): void
	{
		try {
			if ($this->content->editableAt($type, $key) !== null) {
				return;
			}

			$this->content->createAt($type, $key, new EntryChanges(set: ['title' => $title]));
		} catch (WriteException $error) {
			throw new InvalidContentType(sprintf('The type was saved, but its %s page couldn\'t be written: %s', $what, $error->getMessage()), previous: $error);
		}
	}

	/**
	 * Adds a new type to the `authors` credit relation's `from` (D-602),
	 * writing the relation when the site has none, and returns the types
	 * with it.
	 *
	 * @throws InvalidContentType
	 */
	private function credit(ContentTypes $types, string $name): ContentTypes
	{
		$profiles = $types->profiles();
		$authors  = $types->relations()[Relation::AUTHORS] ?? null;

		try {
			if ($profiles === null) {
				throw new InvalidContentType('The site has no profiles type, so nothing can credit authors.');
			}

			if ($authors === null) {
				return $this->relations->create(Relation::authors([$name], $profiles->name));
			}

			if ($authors->from === [] || in_array($name, $authors->from, true)) {
				return $types;
			}

			return $this->relations->update(Relation::fromArray([...$authors->toArray(), 'from' => [...$authors->from, $name]]));
		} catch (InvalidRelation $error) {
			throw new InvalidContentType($error->getMessage(), previous: $error);
		}
	}

	/**
	 * Returns the relation archives a request asks list pages for
	 * (`listPages`, by relation name).
	 *
	 * @param  array<array-key, mixed> $input
	 * @return list<string>
	 */
	private static function listPages(array $input): array
	{
		return is_array($input['listPages'] ?? null) ? array_values(array_unique(array_filter($input['listPages'], is_string(...)))) : [];
	}

	/**
	 * The request's JSON object, or an empty array.
	 *
	 * @return array<array-key, mixed>
	 */
	private static function input(ServerRequestInterface $request): array
	{
		try {
			$input = json_decode((string) $request->getBody(), true, 64, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return [];
		}

		return is_array($input) ? $input : [];
	}

	private function allowed(ServerRequestInterface $request): bool
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account && $this->permissions->can($account, Capability::SiteSettings);
	}

	private static function forbidden(): ResponseInterface
	{
		return self::error('You aren\'t allowed to change content types.', Status::Forbidden);
	}

	private static function error(string $message, Status $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
