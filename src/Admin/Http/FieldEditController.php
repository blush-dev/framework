<?php

/**
 * Admin field edit controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Http;

use Closure;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Activity\Actor;
use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Cache\ContentVersion;
use Blush\Container\Attributes\Defer;
use Blush\Content\Editing\FieldMoves;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypeCache;
use Blush\Content\Type\ContentTypeLoader;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DataFieldWriter;
use Blush\Content\Type\InvalidContentType;
use Blush\Core\AppConfig;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Makes, changes, and deletes the fields of the site's field library in
 * `user/data/fields` (D-858), for accounts with `site.settings`:
 *
 * - `POST fields/library`: `{"field"}`, with its `name`; answers `201`
 *   with the field as `GET fields/library/{name}` describes it.
 * - `PATCH fields/library/{name}`: `{"field"}`, the whole field; its key
 *   stays. Answers with the field, and `moved`: a field put somewhere
 *   new inside an Object (into one, out of one) has its values moved in
 *   the entries of every type that carries it (D-780, `FieldMoves`), and
 *   `moved` says how many `entries` were changed, how many `kept` a value
 *   already at the new place, and the `failed` ones' reasons, by entry
 *   id.
 * - `DELETE fields/library/{name}`: deletes its file and takes it off
 *   every type's layout (entries keep their values); answers
 *   `{"deleted"}`.
 *
 * `field` is the field as the admin's forms send it (`DataFieldWriter`).
 * A field that doesn't fit (one that isn't a field, or one a type that
 * carries it already has by its key) is a `422` with the reason, and
 * nothing is written.
 *
 * As with types (`TypeEditController`), a change is answered from the
 * types it made; compiled types are written again, and the admin then
 * asks for `types/refresh`, since a field's values are part of the index.
 */
final readonly class FieldEditController
{
	/**
	 * @param Closure(): ContentTypeLoader $loader
	 */
	public function __construct(
		private DataFieldWriter $writer,
		private FieldsController $fields,
		private ContentTypeCache $cache,
		private ContentVersion $version,
		private AppConfig $app,
		private Permissions $permissions,
		private FieldMoves $moves,
		#[Defer(ContentTypeLoader::class)] private Closure $loader,
		private Messages $messages
	) {}

	public function create(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return $this->forbidden();
		}

		$field = self::input($request)['field'] ?? null;

		if (! is_array($field) || ! is_string($field['name'] ?? null)) {
			return self::error('Send a "field" with its "name".', Status::BadRequest);
		}

		return $this->changed(fn (): ContentTypes => $this->writer->create($field), $field['name'], Status::Created);
	}

	public function update(ServerRequestInterface $request, string $name): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return $this->forbidden();
		}

		$field = self::input($request)['field'] ?? null;

		if (! is_array($field)) {
			return self::error('Send the "field".', Status::BadRequest);
		}

		// The field as saved now, read again: types loaded earlier may be
		// from before another change.
		try {
			$before = ($this->loader)()->load()->library->find($name)?->field;
		} catch (InvalidContentType $error) {
			return self::error($error->getMessage(), Status::UnprocessableContent);
		}

		$account = $request->getAttribute(Account::class);

		return $this->changed(fn (): ContentTypes => $this->writer->update($name, $field), $name, then: function (ContentTypes $types) use ($name, $before, $account): array {
			$after = $types->library->find($name);

			if ($after === null || $before === null || ! $account instanceof Account) {
				return [];
			}

			$moved = $this->moves->apply(
				Actor::admin($account),
				array_map(static fn (ContentType $type): string => $type->name, FieldsController::typesWith($types, $name)),
				FieldMoves::between([$before], [$after->field])
			);

			return ['moved' => [
				'entries' => count($moved['moved']),
				'kept'    => count($moved['kept']),
				'failed'  => (object) $moved['failed']
			]];
		});
	}

	public function delete(ServerRequestInterface $request, string $name): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return $this->forbidden();
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
	 * Makes a change, then compiles the types and answers with the field.
	 *
	 * @param Closure(): ContentTypes                         $change
	 * @param ?Closure(ContentTypes): array<string, mixed>    $then   What's done once it's changed, and more to answer with.
	 */
	private function changed(Closure $change, string $name, Status $status = Status::Ok, ?Closure $then = null): ResponseInterface
	{
		try {
			$types = $change();
			$field = $types->library->find($name) ?? throw new InvalidContentType((string) $this->messages->t('admin.field_edit_controller.not_read', 'The "{name}" field was saved, but the field library isn\'t read (FieldConfig "library").', name: $name));

			$this->compile();
		} catch (InvalidContentType $error) {
			return self::error($error->getMessage(), Status::UnprocessableContent);
		}

		$more = $then === null ? [] : $then($types);

		$this->version->bump();

		return Response::json([...$this->fields->detail($types, $field), ...$more], $status, ['Cache-Control' => 'no-store']);
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

	private function forbidden(): ResponseInterface
	{
		return self::error($this->messages->t('admin.field_edit_controller.forbidden', 'You aren\'t allowed to change the field library.'), Status::Forbidden);
	}

	private static function error(string|Text $message, Status $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
