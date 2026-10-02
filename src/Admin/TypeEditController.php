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
use Blush\Content\Http\AuthorsController;
use Blush\Content\Index\Indexer;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypeCache;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DataTypeWriter;
use Blush\Content\Type\InvalidContentType;
use Blush\Content\Type\TypeKind;
use Blush\Core\AppConfig;
use Blush\Core\Paths;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Routing\RouteCache;
use Blush\Support\Filesystem;

/**
 * Creates, changes, and deletes the content types the site defines in
 * `user/data/types` (D-311), and changes the collections and taxonomies
 * code defines through a file there (D-349), for accounts with
 * `site.settings`:
 *
 * - `POST types`: `{"name", "kind"` (`collection` or `taxonomy`),
 *   `"folder"`, `"set"`, `"index"`, `"authorsPage"}`; answers `201` with
 *   the type as `GET types/{name}` describes it.
 * - `PATCH types/{name}`: `{"set", "index", "authorsPage"}`; answers
 *   with the type.
 * - `DELETE types/{name}`: deletes its file (its entries stay); answers
 *   `{"deleted"}`.
 * - `POST types/{name}/reset`: deletes the file changing a code type,
 *   so it's as the code defines it; answers with the type.
 * - `POST types/refresh`: compiles the routes again (when the site keeps
 *   them compiled) and reindexes, so the next requests see the change.
 *
 * `set` holds the options to change (`DataTypeWriter`), including
 * `paths`, route keys' paths (D-350); `index: true`
 * gives a collection or taxonomy its index page (D-255), `{folder}/index.md`
 * titled with its plural name, when it has none, and `authorsPage: true`
 * gives a type with author archives its authors page (D-329),
 * `{folder}/_authors.md` titled with the authors type's plural name,
 * when it has none. A change that doesn't
 * fit (an unknown option, a field that isn't one, two types in one
 * folder) is a `422` with the reason, and nothing is written.
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
		private TypesController $types,
		private ContentTypeCache $cache,
		private RouteCache $routes,
		private Indexer $indexer,
		private ContentVersion $version,
		private AppConfig $app,
		private Paths $paths,
		private Filesystem $filesystem,
		private Permissions $permissions
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
			return self::error('Send a "name", a "kind" (collection or taxonomy), and "set" (options to values).', Status::BadRequest);
		}

		$folder = is_string($input['folder'] ?? null) ? $input['folder'] : null;

		/** @var array<string, mixed> $set */
		return $this->changed(fn (): ContentTypes => $this->writer->create($name, $kind, $folder, $set), $name, ($input['index'] ?? false) === true, ($input['authorsPage'] ?? false) === true, Status::Created);
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
		return $this->changed(fn (): ContentTypes => $this->writer->update($name, $set), $name, ($input['index'] ?? false) === true, ($input['authorsPage'] ?? false) === true);
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

		return $this->changed(fn (): ContentTypes => $this->writer->reset($name), $name, false, false);
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
	 * the authors page when asked, and answers with the type.
	 *
	 * @param Closure(): ContentTypes $change
	 */
	private function changed(Closure $change, string $name, bool $index, bool $authorsPage, Status $status = Status::Ok): ResponseInterface
	{
		try {
			$types = $change();
			$type  = $types->get($name);

			$this->compile();

			if ($index) {
				$this->addIndex($type);
			}

			if ($authorsPage) {
				$this->addAuthorsPage($types, $type);
			}
		} catch (InvalidContentType $error) {
			return self::error($error->getMessage(), Status::UnprocessableContent);
		}

		$this->version->bump();

		return Response::json($this->types->detail($types, $type), $status, ['Cache-Control' => 'no-store']);
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
		if ($type->folder === '' || ! $type->hasUrls()) {
			throw new InvalidContentType(sprintf('%s have no index page.', $type->labels->plural));
		}

		$folder = "{$this->paths->content}/{$type->folder}";

		if (glob("{$folder}/index.*") !== []) {
			return;
		}

		$title = json_encode($type->labels->plural, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '""';

		try {
			$this->filesystem->writeAtomic("{$folder}/index.md", "---\ntitle: {$title}\n---\n");
		} catch (Throwable $error) {
			throw new InvalidContentType(sprintf('The type was saved, but its index page couldn\'t be written in %s.', $this->paths->relative($folder)), previous: $error);
		}
	}

	/**
	 * Gives a type its authors page (D-329), unless it has one or has no
	 * author archives.
	 *
	 * @throws InvalidContentType
	 */
	private function addAuthorsPage(ContentTypes $types, ContentType $type): void
	{
		$authors = $types->authors();

		if ($authors === null || $type->folder === '' || ! $type->hasAuthorArchives()) {
			throw new InvalidContentType(sprintf('%s have no author archives.', $type->labels->plural));
		}

		$folder = "{$this->paths->content}/{$type->folder}";

		if (glob("{$folder}/" . AuthorsController::PAGE . '.*') !== []) {
			return;
		}

		$title = json_encode($authors->labels->plural, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '""';

		try {
			$this->filesystem->writeAtomic("{$folder}/" . AuthorsController::PAGE . '.md', "---\ntitle: {$title}\n---\n");
		} catch (Throwable $error) {
			throw new InvalidContentType(sprintf('The type was saved, but its authors page couldn\'t be written in %s.', $this->paths->relative($folder)), previous: $error);
		}
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
