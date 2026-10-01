<?php

/**
 * Admin field set edit controller.
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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Cache\ContentVersion;
use Blush\Content\Type\ContentTypeCache;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DataFieldSetWriter;
use Blush\Content\Type\InvalidContentType;
use Blush\Core\AppConfig;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Creates, changes, and deletes the field sets the site defines in
 * `user/data/fields` (D-337), for accounts with `site.settings`:
 *
 * - `POST fields/sets`: `{"name", "set"}`; answers `201` with the set as
 *   `GET fields/sets/{name}` describes it.
 * - `PATCH fields/sets/{name}`: `{"set"}`; answers with the set.
 * - `DELETE fields/sets/{name}`: deletes its file (entries keep their
 *   values for its fields); answers `{"deleted"}`.
 *
 * `set` holds the keys to change (`label`, `description`, `targets`, and
 * `fields`; `DataFieldSetWriter`). A change that doesn't fit (an unknown
 * key, a field that isn't one, a field another on a target already has)
 * is a `422` with the reason, and nothing is written.
 *
 * As with types (`TypeEditController`), a change is answered from the
 * types it made; compiled types are written again, and the admin then
 * asks for `types/refresh`, since a set's fields are part of the index.
 */
final readonly class FieldSetEditController
{
	public function __construct(
		private DataFieldSetWriter $writer,
		private FieldSetsController $sets,
		private ContentTypeCache $cache,
		private ContentVersion $version,
		private AppConfig $app,
		private Permissions $permissions
	) {}

	public function create(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::forbidden();
		}

		$input = self::input($request);
		$name  = $input['name'] ?? null;
		$set   = $input['set'] ?? [];

		if (! is_string($name) || ! is_array($set) || ($set !== [] && array_is_list($set))) {
			return self::error('Send a "name" and "set" (keys to values).', Status::BadRequest);
		}

		/** @var array<string, mixed> $set */
		return $this->changed(fn (): ContentTypes => $this->writer->create($name, $set), $name, Status::Created);
	}

	public function update(ServerRequestInterface $request, string $name): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::forbidden();
		}

		$set = self::input($request)['set'] ?? [];

		if (! is_array($set) || ($set !== [] && array_is_list($set))) {
			return self::error('Send "set" (keys to values).', Status::BadRequest);
		}

		/** @var array<string, mixed> $set */
		return $this->changed(fn (): ContentTypes => $this->writer->update($name, $set), $name);
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

	/**
	 * Makes a change, then compiles the types and answers with the set.
	 *
	 * @param Closure(): ContentTypes $change
	 */
	private function changed(Closure $change, string $name, Status $status = Status::Ok): ResponseInterface
	{
		try {
			$types = $change();
			$set   = $types->sets->find($name) ?? throw new InvalidContentType(sprintf('The "%s" field set was saved, but a set of that name from elsewhere is used.', $name));

			$this->compile();
		} catch (InvalidContentType $error) {
			return self::error($error->getMessage(), Status::UnprocessableContent);
		}

		$this->version->bump();

		return Response::json($this->sets->detail($types, $set), $status, ['Cache-Control' => 'no-store']);
	}

	/**
	 * Writes the compiled types again, when the site keeps them compiled.
	 *
	 * @throws InvalidContentType
	 */
	private function compile(): void
	{
		if (! $this->app->environment->isDevelopment() && is_file($this->cache->path())) {
			$this->cache->write();
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
		return self::error('You aren\'t allowed to change field sets.', Status::Forbidden);
	}

	private static function error(string $message, Status $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
