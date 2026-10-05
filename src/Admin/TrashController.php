<?php

/**
 * Admin trash controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use DateTimeInterface;
use JsonException;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\InvalidContentType;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\DocumentEditor;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\TrashedEntry;
use Blush\Content\Writer\WriteException;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Support\Slug;

/**
 * The admin's trash (D-237), under `{path}/api/trash`:
 *
 * - `GET trash?type=`: the trashed entries the account may handle, most
 *   recently trashed first, optionally of one type.
 * - `GET trash/{id}`: one of them with its front matter and body, to
 *   look at before restoring it (D-276).
 * - `POST trash/restore` (`{"id"}`): brings one back **as a draft**, so a
 *   trashed entry is never republished without someone choosing to.
 * - `POST trash/delete` (`{"id"}`): deletes one for good.
 * - `POST trash/empty` (`{"type"?}`): deletes for good every trashed
 *   entry the account may handle (of one type, when given).
 *
 * Trashed entries aren't in the index, so ownership is read from the
 * file's author field: an account handles its own with its type's
 * `delete` and everyone's with `delete.others` (D-359).
 */
final readonly class TrashController
{
	public function __construct(
		private ContentWriter $writer,
		private ContentTypes $types,
		private Permissions $permissions
	) {}

	/**
	 * Lists the trash.
	 */
	public function index(ServerRequestInterface $request): ResponseInterface
	{
		$account = self::account($request);
		$type    = $request->getQueryParams()['type'] ?? null;

		if ($type !== null && (! is_string($type) || ! $this->types->has($type))) {
			return self::json(['error' => 'There is no such content type.'], Status::BadRequest);
		}

		return self::json(['trash' => array_map(
			fn (TrashedEntry $trashed): array => $this->describe($account, $trashed),
			$this->handled($account, $type)
		)]);
	}

	/**
	 * Answers one trashed entry, with its front matter and body.
	 */
	public function show(ServerRequestInterface $request, string $id): ResponseInterface
	{
		$account = self::account($request);
		$trashed = $this->find($account, $id);

		if ($trashed === null) {
			return self::json(['error' => 'That isn\'t in the trash, or you can\'t see it.'], Status::NotFound);
		}

		try {
			$file = $this->writer->loadTrashed($trashed->id);
		} catch (WriteException $e) {
			return self::json(['error' => $e->getMessage()], Status::UnprocessableContent);
		}

		return self::json([
			...$this->describe($account, $trashed),
			'frontMatter' => (object) $file->frontMatter,
			'body'        => substr($file->body, strlen(DocumentEditor::gap($file->body)))
		]);
	}

	/**
	 * Restores a trashed entry as a draft.
	 */
	public function restore(ServerRequestInterface $request): ResponseInterface
	{
		$account = self::account($request);
		$trashed = $this->find($account, self::input($request)['id'] ?? null);

		if ($trashed === null) {
			return self::json(['error' => 'That isn\'t in the trash, or you can\'t restore it.'], Status::NotFound);
		}

		try {
			$result = $this->writer->restore($trashed->id, new EntryChanges(set: ['status' => 'draft']));
		} catch (WriteException $e) {
			return self::json(['error' => $e->getMessage()], Status::Conflict);
		}

		return self::json(['path' => $result->path]);
	}

	/**
	 * Deletes a trashed entry for good.
	 */
	public function delete(ServerRequestInterface $request): ResponseInterface
	{
		$account = self::account($request);
		$trashed = $this->find($account, self::input($request)['id'] ?? null);

		if ($trashed === null) {
			return self::json(['error' => 'That isn\'t in the trash, or you can\'t delete it.'], Status::NotFound);
		}

		try {
			$this->writer->purge($trashed->id);
		} catch (WriteException $e) {
			return self::json(['error' => $e->getMessage()], Status::UnprocessableContent);
		}

		return new Response(Status::NoContent, ['Cache-Control' => 'no-store']);
	}

	/**
	 * Deletes for good every trashed entry the account may handle.
	 */
	public function empty(ServerRequestInterface $request): ResponseInterface
	{
		$account = self::account($request);
		$type    = self::input($request)['type'] ?? null;

		if ($type !== null && (! is_string($type) || ! $this->types->has($type))) {
			return self::json(['error' => 'There is no such content type.'], Status::BadRequest);
		}

		$deleted = 0;

		foreach ($this->handled($account, $type) as $trashed) {
			try {
				$this->writer->purge($trashed->id);
				$deleted++;
			} catch (WriteException $e) {
				return self::json(['error' => $e->getMessage(), 'deleted' => $deleted], Status::UnprocessableContent);
			}
		}

		return self::json(['deleted' => $deleted]);
	}

	/**
	 * Returns the trashed entries the account may handle, of a type.
	 *
	 * @return list<TrashedEntry>
	 */
	private function handled(Account $account, ?string $type): array
	{
		return array_values(array_filter(
			$this->writer->trashed(),
			fn (TrashedEntry $trashed): bool => ($type === null || $this->typeOf($trashed) === $type) && $this->mayHandle($account, $trashed)
		));
	}

	/**
	 * Finds a trashed entry the account may handle.
	 */
	private function find(Account $account, mixed $id): ?TrashedEntry
	{
		return is_string($id) ? array_find($this->handled($account, null), static fn (TrashedEntry $trashed): bool => $trashed->id === $id) : null;
	}

	/**
	 * Whether the account may restore or delete a trashed entry: anyone's
	 * with its type's `delete.others`, its own with `delete` (D-359).
	 */
	private function mayHandle(Account $account, TrashedEntry $trashed): bool
	{
		$type = $this->typeOf($trashed);

		// A file whose type is gone is for whoever deletes anyone's
		// entries of every type.
		if ($type === null) {
			return $this->permissions->can($account, ContentAction::DeleteOthers->on(ContentAction::EVERY));
		}

		if ($this->permissions->can($account, ContentAction::DeleteOthers, $type)) {
			return true;
		}

		return $account->author !== null
			&& $this->permissions->can($account, ContentAction::Delete, $type)
			&& in_array($account->author, $this->authors($trashed), true);
	}

	/**
	 * Returns the profile slugs a trashed entry's file credits in its
	 * type's main byline, the first people field (D-351).
	 *
	 * @return list<string>
	 */
	private function authors(TrashedEntry $trashed): array
	{
		$type  = $this->typeOf($trashed);
		$field = $type === null || $this->types->profiles() === null ? null : array_first($this->types->get($type)->people);
		$keys  = $field === null ? [] : [$field->field, ...$field->aliases];

		foreach ($keys as $key) {
			$value = $trashed->frontMatter[$key] ?? null;

			if (is_string($value) || is_array($value)) {
				$slugs = array_map(static fn (mixed $slug): string => is_scalar($slug) ? Slug::from((string) $slug) : '', is_array($value) ? $value : [$value]);

				return array_values(array_filter($slugs, static fn (string $slug): bool => $slug !== ''));
			}
		}

		return [];
	}

	/**
	 * Returns a trashed entry's type, from where it lived, or `null`.
	 */
	private function typeOf(TrashedEntry $trashed): ?string
	{
		try {
			return $this->types->forFile($trashed->entry)->name;
		} catch (InvalidContentType) {
			return null;
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function describe(Account $account, TrashedEntry $trashed): array
	{
		$authors = $this->authors($trashed);

		return [
			'id'      => $trashed->id,
			'entry'   => $trashed->entry,
			'title'   => $trashed->title(),
			'type'    => $this->typeOf($trashed),
			'bundle'  => $trashed->bundle,
			'trashed' => $trashed->trashed->format(DateTimeInterface::ATOM),
			'authors' => $authors,
			'own'     => $account->author !== null && in_array($account->author, $authors, true)
		];
	}

	/**
	 * @return array<mixed>
	 */
	private static function input(ServerRequestInterface $request): array
	{
		try {
			$input = json_decode((string) $request->getBody(), true, 8, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return [];
		}

		return is_array($input) ? $input : [];
	}

	private static function account(ServerRequestInterface $request): Account
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account ? $account : throw new LogicException('The trash needs the Authenticate middleware.');
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
