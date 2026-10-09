<?php

/**
 * Admin media list controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use JsonException;
use SplFileInfo;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\AccountProfiles;
use Blush\Auth\Accounts;
use Blush\Auth\Capability;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Core\Paths;
use Blush\Field\Field;
use Blush\Field\FieldSet;
use Blush\Field\FieldContext;
use Blush\Field\InvalidField;
use Blush\Field\Violation;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Media\Embedded\EmbeddedMetadataReader;
use Blush\Media\MediaArtwork;
use Blush\Media\MediaConfig;
use Blush\Media\MediaException;
use Blush\Media\MediaFile;
use Blush\Core\AppConfig;
use Blush\Media\Index\MediaLibrary;
use Blush\Media\Index\MediaQuery;
use Blush\Media\Index\MediaRecord;
use Blush\Media\MediaKind;
use Blush\Media\MediaMetadata;
use Blush\Media\MediaSchemas;
use Blush\Media\MediaMetadataStore;
use Blush\Media\MediaResolver;
use Blush\Media\MediaUsage;
use Blush\Support\Filesystem;
use Blush\Support\UrlPath;

/**
 * Answers `GET {path}/api/media` (D-246): the media files an entry can use,
 * for the editor's media picker and the Media screen. It needs to edit
 * entries of some type, or a media capability (D-407).
 *
 * - `files`: the library (`user/media`), from the media index (D-288),
 *   newest first, a page at a time (`page`, and `per`, 48 by default, at
 *   most 100), narrowed by `search` (text its path or metadata must
 *   contain, in any case), `kind` (`image`, `video`, `audio`,
  *   `document`, `file` for any other kind, or `any`, the default),
 *   `missing=alt` (images without alt text), and `mine=1` (the files the
 *   account uploaded, D-407).
 * - `indexing`: the id of the job reading the rest of the library while
 *   it catches up (D-626), for the admin to follow, else `null`.
 * - `upload`: when the account may upload some kind (D-268, D-407), the
 *   largest file it may upload (`limit`, in bytes, or `null`: the upload
 *   rules' largest, within PHP's, D-406) and the `extensions` it may
 *   upload; otherwise `null`.
 *
 * `GET {path}/api/media/{path}` (`show()`) describes one file, by its
 * path under `user/media`, with its metadata fields (D-287): the
 * `fields` its kind has (`MediaSchemas`, described as a content type's
 * are), the field `sets` attached to its kind (D-341: `name`, `label`,
 * `description`, and the names of their `fields`, which the screen
 * groups), their `values`, keys the file keeps that aren't fields
 * (`extra`), what doesn't fit (`violations`), who uploaded it (`uploader`:
 * `username` and `name`, or `null`), what the account `may` do to it
 * (`edit`, `delete`), and the entries that use it (`usedIn`: `id`, `path`,
 * `title`, `type`; `MediaUsage`). `PATCH` (`update()`, `media.edit`, and
 * `media.edit.others` for a file that isn't the account's) changes them: `set` (field names to values; an empty
 * one removes it) and `remove` (field names), each value checked by its
 * field, and answers with the file. `alt` and `caption` given on their
 * own, as before, are set too. It also has what the file says about
 * itself (`embedded`, D-289): the values read from its EXIF, IPTC, and
 * XMP, and whether it has a location, never the location itself.
 *
 * `GET {path}/api/media-artwork/{path}` (`artwork()`, D-551) answers with
 * the picture a sound or video carries, such as its cover art, which
 * `embedded`'s `artwork` describes.
 *
 * A sound's or video's artwork (D-581) is a library image, by id: `GET
 * media/{path}` answers with it as `artwork` (`id`, and the `image`:
 * `path`, `reference`, `url`, `name`, `title`, or `null` when the id is
 * no image in the library; `null` for none), and an image with the
 * files that show it (`artworkFor`: `path`, `name`, `title`, `kind`).
 * `PUT media-artwork/{path}` (`setArtwork()`, by whoever may change the
 * file's details) links `{"image": "{id}"}`, or with `{"from": "file"}`
 * adds the picture the file carries to the library first
 * (`MediaArtwork::adopt()`, which also takes uploading images, and
 * which `may.addArtwork` says the account can do); `DELETE` takes it
 * off, leaving the image. Both answer with the file. Deleting an image
 * takes it off the files that showed it.
 *
 * `DELETE` (`delete()`, D-407, `media.delete`, and `media.delete.others`
 * for a file that isn't the account's) removes the file, its metadata,
 * and a copy `media:publish --copy` made, and answers `{"deleted"}`; the
 * admin asks first, naming the entries that use it.
 *
 * Each file has its `reference` (what to write: the library's URL path),
 * `name`, `folder`, `url`, `mime`, `kind`,
 * `size`, `width` and `height` (images and videos), `duration` (sound
 * and video, in seconds, when known, D-291), `modified`, and its metadata,
 * `title` (D-290), `alt`, and `caption` (`''` for none; `MediaMetadataStore`),
 * its uploader's username (`owner`, `''` for none), its `id` (D-487,
 * `''` for a file with none), and how many sizes an image has
 * (`sizeCount`, D-488), and a sound's or video's artwork to show as its
 * thumbnail (`artworkUrl`, D-583: its library image's URL, else the
 * address the picture it carries is served at, else `null`;
 * `MediaArtwork::url()`). The library lists one item per image, never its
 * sizes; `GET media/{path}` for an image lists its `sizes` (`path`,
 * `reference`, `name`, `url`, `width`, `height`, `size`), and for a size
 * names its `original` (`path`, `reference`, `name`, `title`), whose
 * details it answers and goes by: a size's details can't be changed
 * (422), and deleting an image deletes its sizes, while deleting a size
 * takes it off its image's list. `usedIn` counts the sizes' uses too. Only the types
 * the site allows (`MediaConfig::$types`) are listed, without caption
 * tracks, and never hidden files.
 */
final readonly class MediaListController
{
	private const int PER = 48;

	private const int MAX_PER = 100;

	/**
	 * MIME types by extension (`MediaResolver::EXTENSIONS`); an upload
	 * must have one of them (`MediaUploadController`).
	 *
	 * @var array<string, string>
	 */
	public const array EXTENSIONS = MediaResolver::EXTENSIONS;

	private const array KINDS = ['any', 'image', 'video', 'audio', 'document', 'file'];

	public function __construct(
		private Paths $paths,
		private MediaConfig $config,
		private MediaResolver $resolver,
		private Permissions $permissions,
		private MediaUploadController $uploads,
		private MediaMetadataStore $metadata,
		private MediaSchemas $schemas,
		private AppConfig $app,
		private MediaLibrary $library,
		private MediaUsage $usage,
		private Accounts $accounts,
		private AccountProfiles $profiles,
		private EmbeddedMetadataReader $embedded,
		private MediaArtwork $artworks
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->usesLibrary($account)) {
			return self::error('You aren\'t allowed to use media.', Status::Forbidden);
		}

		$query  = $request->getQueryParams();
		$search = $query['search'] ?? '';
		$kind   = $query['kind'] ?? 'any';
		$page   = $query['page'] ?? '1';
		$per    = $query['per'] ?? (string) self::PER;
		$missing = $query['missing'] ?? '';
		$mine    = $query['mine'] ?? '';

		if (! is_string($search) || ! is_string($kind) || ! in_array($kind, self::KINDS, true) || ! in_array($missing, ['', 'alt'], true) || ! in_array($mine, ['', '0', '1'], true) || ! self::counts($page, 1_000_000) || ! self::counts($per, self::MAX_PER)) {
			return self::error('That isn\'t a valid media list.', Status::BadRequest);
		}

		$per   = (int) $per;
		$page  = (int) $page;
		$found = $this->library->query(new MediaQuery(
			search: trim($search),
			kind: $kind === 'any' ? null : MediaKind::from($kind),
			missingAlt: $missing === 'alt',
			owner: $mine === '1' ? $account->id : null,
			page: $page,
			per: $per
		));

		$files = array_map(fn (MediaRecord $record): array => self::describe($record->file($this->paths), $record->url, $record->key, $record->metadata(), $record->duration(), count($this->library->sizes($record->key)), $this->artworks->url($record)), $found->records);

		return Response::json([
			'search'   => $search,
			'kind'     => $kind,
			'missing'  => $missing,
			'mine'     => $mine === '1',
			'total'    => $found->total,
			'page'     => $page,
			'pages'    => max(1, (int) ceil($found->total / $per)),
			'per'      => $per,
			'files'    => $files,
			'indexing' => $this->library->indexing()?->id,
			'upload'   => $this->uploads->extensions($account) !== []
				? ['limit' => $this->uploads->largest($account), 'extensions' => $this->uploads->extensions($account)]
				: null
		], headers: ['Cache-Control' => 'no-store']);
	}

	public function show(ServerRequestInterface $request, string $path): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->usesLibrary($account)) {
			return self::error('You aren\'t allowed to use media.', Status::Forbidden);
		}

		[$file, $reference] = $this->libraryFile($path);

		if ($file === null) {
			return self::error(sprintf('There\'s no "%s" in the media library.', $path), Status::NotFound);
		}

		return Response::json($this->details($file, $reference, trim($path, '/'), $this->describedBy($file, trim($path, '/')), $account), headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * `GET media-artwork/{path}`: the picture a sound or video carries
	 * (D-551), such as its cover art, read from the file each time; 404
	 * when it has none.
	 */
	public function artwork(ServerRequestInterface $request, string $path): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->usesLibrary($account)) {
			return self::error('You aren\'t allowed to use media.', Status::Forbidden);
		}

		[$file] = $this->libraryFile($path);
		$found  = $file === null ? null : $this->embedded->artwork($file->path, $file->mime);

		if ($found === null) {
			return self::error(sprintf('There\'s no artwork in "%s".', $path), Status::NotFound);
		}

		return new Response(Status::Ok, [
			'Content-Type'           => $found->mime,
			'Cache-Control'          => 'private, max-age=3600',
			'X-Content-Type-Options' => 'nosniff'
		], $found->bytes);
	}

	/**
	 * `PUT media-artwork/{path}`: links a library image as a sound's or
	 * video's artwork (D-581), or adds the one it carries to the library
	 * and links that.
	 */
	public function setArtwork(ServerRequestInterface $request, string $path): ResponseInterface
	{
		$target = $this->artworkTarget($request, $path);

		if ($target instanceof ResponseInterface) {
			return $target;
		}

		[$account, $file, $reference, $record] = $target;

		try {
			$input = json_decode((string) $request->getBody(), true, 4, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$input = null;
		}

		$image = is_array($input) ? $input['image'] ?? null : null;
		$from  = is_array($input) ? $input['from'] ?? null : null;

		if (! is_string($image) && $from !== 'file') {
			return self::error('Send the library image\'s id as "image", or "from": "file" for the artwork the file carries.', Status::BadRequest);
		}

		if (! is_string($image) && ! $this->mayAddArtwork($account, $record)) {
			return self::error('You aren\'t allowed to add images to the library, or this file carries no artwork.', Status::Forbidden);
		}

		try {
			is_string($image) ? $this->artworks->link($record, $image) : $this->artworks->adopt($record, $account->id);
		} catch (MediaException $error) {
			return self::error($error->getMessage(), Status::UnprocessableContent);
		}

		return Response::json($this->details($file, $reference, $record->key, $this->metadata->find($file), $account), headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * `DELETE media-artwork/{path}`: takes a sound's or video's artwork
	 * off (D-581), leaving the image in the library.
	 */
	public function removeArtwork(ServerRequestInterface $request, string $path): ResponseInterface
	{
		$target = $this->artworkTarget($request, $path);

		if ($target instanceof ResponseInterface) {
			return $target;
		}

		[$account, $file, $reference, $record] = $target;

		try {
			$this->artworks->unlink($record);
		} catch (MediaException $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		return Response::json($this->details($file, $reference, $record->key, $this->metadata->find($file), $account), headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * The sound or video whose artwork a request changes, with the
	 * account changing it, or the response refusing it in the account's
	 * place.
	 *
	 * @return ResponseInterface|array{Account, MediaFile, string, MediaRecord}
	 */
	private function artworkTarget(ServerRequestInterface $request, string $path): ResponseInterface|array
	{
		$account  = $request->getAttribute(Account::class);
		$relative = trim($path, '/');

		if (! $account instanceof Account) {
			return self::error('You aren\'t allowed to change media.', Status::Forbidden);
		}

		[$file, $reference] = $this->libraryFile($path);

		try {
			$record = $file === null ? null : $this->library->find($relative);
		} catch (MediaException $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		if ($file === null || $record === null) {
			return self::error(sprintf('There\'s no "%s" in the media library.', $path), Status::NotFound);
		}

		if (! MediaArtwork::has($record->kind())) {
			return self::error('Only sounds and videos have artwork.', Status::UnprocessableContent);
		}

		if (! $this->permissions->mayChangeMedia($account, Capability::MediaEdit, $this->metadata->find($file)->owner)) {
			return self::error('You aren\'t allowed to change this file\'s details.', Status::Forbidden);
		}

		return [$account, $file, $reference, $record];
	}

	/**
	 * Whether an account may add the artwork a file carries to the
	 * library: the account may upload images, which the upload rules
	 * allow, and it carries an image the library takes.
	 */
	private function mayAddArtwork(Account $account, ?MediaRecord $record): bool
	{
		return $record !== null
			&& $this->config->uploads->allows(MediaKind::Image)
			&& $this->permissions->mayUpload($account, MediaKind::Image)
			&& $this->artworks->canAdopt($record);
	}

	public function update(ServerRequestInterface $request, string $path): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account) {
			return self::error('You aren\'t allowed to change media.', Status::Forbidden);
		}

		[$file, $reference] = $this->libraryFile($path);

		if ($file === null) {
			return self::error(sprintf('There\'s no "%s" in the media library.', $path), Status::NotFound);
		}

		if (! $this->permissions->mayChangeMedia($account, Capability::MediaEdit, $this->describedBy($file, trim($path, '/'))->owner)) {
			return self::error('You aren\'t allowed to change this file\'s details.', Status::Forbidden);
		}

		$original = $this->originalOf(trim($path, '/'));

		if ($original !== null) {
			return self::error(sprintf('%s is a size of %s, whose details it goes by; change them there.', basename($path), $original), Status::UnprocessableContent);
		}

		try {
			$input = json_decode((string) $request->getBody(), true, 16, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$input = null;
		}

		$set    = is_array($input) ? ($input['set'] ?? []) : null;
		$remove = is_array($input) ? ($input['remove'] ?? []) : null;

		if (! is_array($input) || ! is_array($set) || ($set !== [] && array_is_list($set)) || ! is_array($remove) || ! array_is_list($remove) || array_any($remove, static fn (mixed $key): bool => ! is_string($key))) {
			return self::error('Send "set" (field names to values) and "remove" (field names) in a JSON object.', Status::BadRequest);
		}

		// `alt` and `caption` on their own, as before.
		foreach (['alt', 'caption'] as $key) {
			if (array_key_exists($key, $input) && ! array_key_exists($key, $set)) {
				$set[$key] = $input[$key];
			}
		}

		$schema  = $this->schemas->forFile($file);
		$context = new FieldContext($this->app->timezone());
		$values  = [];
		$names   = array_values(array_filter($remove, is_string(...)));

		foreach ([...array_map(strval(...), array_keys($set)), ...$names] as $key) {
			if ($schema->field($key) === null) {
				return self::json422(sprintf('"%s" isn\'t a field of %s files.', $key, MediaKind::fromMime($file->mime)->value), $key);
			}
		}

		foreach ($set as $key => $value) {
			$field = $schema->field((string) $key);

			if ($field === null) {
				continue;
			}

			try {
				$values[$field->name] = self::stored($field, $value, $context);
			} catch (InvalidField $error) {
				return self::json422(sprintf('%s %s', $field->label !== '' ? $field->label : ucfirst($field->name), $error->getMessage()), $field->name);
			}
		}

		try {
			$this->metadata->save($file, $values, $names, $schema);
			$this->library->refresh([trim($path, '/')]);
		} catch (MediaException $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		return Response::json($this->details($file, $reference, trim($path, '/'), $this->metadata->find($file), $account), headers: ['Cache-Control' => 'no-store']);
	}

	public function delete(ServerRequestInterface $request, string $path): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account) {
			return self::error('You aren\'t allowed to delete media.', Status::Forbidden);
		}

		[$file] = $this->libraryFile($path);

		if ($file === null) {
			return self::error(sprintf('There\'s no "%s" in the media library.', $path), Status::NotFound);
		}

		$relative = trim($path, '/');

		if (! $this->permissions->mayChangeMedia($account, Capability::MediaDelete, $this->describedBy($file, $relative)->owner)) {
			return self::error('You aren\'t allowed to delete this file.', Status::Forbidden);
		}

		// An image goes with its sizes (D-488).
		$sizes = array_map(static fn (MediaRecord $size): string => $size->key, $this->sizesOf($relative));

		foreach ([$relative, ...$sizes] as $key) {
			$target = "{$this->paths->media}/{$key}";

			if (is_file($target) && ! @unlink($target)) {
				return self::error($key === $relative ? 'The file couldn\'t be deleted from the media folder.' : sprintf('%s couldn\'t be deleted from the media folder.', $key), Status::InternalServerError);
			}

			// A copy `media:publish --copy` made; a linked folder already
			// lost it with the file.
			$published = $this->paths->public . $this->config->url . '/' . $key;

			if (! is_link($this->paths->public . $this->config->url) && is_file($published)) {
				@unlink($published);
			}
		}

		try {
			// The files that showed it as their artwork no longer do (D-581).
			$id = $this->metadata->find($file)->id;

			if ($id !== '' && MediaKind::fromMime($file->mime) === MediaKind::Image) {
				$this->artworks->forget($id);
			}

			foreach ([$relative, ...$sizes] as $key) {
				$this->metadata->forget($key);
			}

			// A size leaves its image's list.
			$original = $this->originalOf($relative);
			$image    = $original === null ? null : $this->resolver->fromKey($original);

			if ($image !== null && isset($this->metadata->find($image)->sizes[$relative])) {
				$this->metadata->save($image, [MediaMetadata::SIZES => array_diff_key($this->metadata->find($image)->sizes, [$relative => true]) ?: null]);
			}

			$this->library->refresh($original === null ? [] : [$original]);
		} catch (MediaException) {
			// The file is gone; leftover metadata is `content:lint`'s to report.
		}

		return Response::json(['deleted' => $relative, 'sizes' => $sizes], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * The details a file goes by: its own, or for a size of an image
	 * (D-488), the image's.
	 */
	private function describedBy(MediaFile $file, string $relative): MediaMetadata
	{
		$original = $this->originalOf($relative);
		$image    = $original === null ? null : $this->resolver->fromKey($original);

		return $this->metadata->find($image ?? $file);
	}

	/**
	 * The key of the image a file is a size of, or `null`.
	 */
	private function originalOf(string $relative): ?string
	{
		try {
			return $this->library->find($relative)?->original;
		} catch (MediaException) {
			return null;
		}
	}

	/**
	 * An image's sizes.
	 *
	 * @return list<MediaRecord>
	 */
	private function sizesOf(string $relative): array
	{
		try {
			return $this->library->sizes($relative);
		} catch (MediaException) {
			return [];
		}
	}

	/**
	 * Whether the account may see the library: it edits entries of some
	 * type, or has a media capability (D-407).
	 */
	private function usesLibrary(Account $account): bool
	{
		return $this->permissions->can($account, ContentAction::Edit) || $this->permissions->usesMedia($account);
	}

	/**
	 * What a field's value is written as: `null` (removed) when it's
	 * empty, else the value the field makes of it when that's plain data,
	 * or as given (a date field's is an object). Alt text and a caption
	 * are one line (`MediaMetadata::line()`).
	 *
	 * @throws InvalidField When the field refuses it.
	 */
	private static function stored(Field $field, mixed $value, FieldContext $context): mixed
	{
		if ($value === null || $value === '' || $value === []) {
			return null;
		}

		$normalized = $field->normalize($value, $context);
		$value      = is_scalar($normalized) || is_array($normalized) ? $normalized : $value;

		if (in_array($field->name, ['title', 'alt', 'caption'], true)) {
			$value = MediaMetadata::line($value);
		}

		return $value === '' ? null : $value;
	}

	/**
	 * Describes one file with its metadata fields, their values, the keys
	 * that aren't fields, and what doesn't fit.
	 *
	 * @return array<string, mixed>
	 */
	private function details(MediaFile $file, string $reference, string $relative, MediaMetadata $metadata, Account $account): array
	{
		$owner = $metadata->owner === '' ? null : $this->accounts->findById($metadata->owner);

		$schema = $this->schemas->forFile($file);
		$result = $schema->resolve($metadata->fields(), new FieldContext($this->app->timezone()));
		$values = [];

		foreach ($schema->fields as $name => $field) {
			foreach ([$name, ...$field->aliases] as $key) {
				if (array_key_exists($key, $metadata->values)) {
					$values[$name] = $metadata->values[$key];
					break;
				}
			}
		}

		// What the file says about itself (D-289), from the media index:
		// never where it was taken, only whether it says.
		$record   = $this->library->find($relative);
		$embedded = $record?->embedded;
		$sizes    = $this->sizesOf($relative);
		$original = $record?->original === null ? null : $this->library->find($record->original);

		return [
			...self::describe($file, $reference, $relative, $metadata, $record?->duration(), count($sizes), $record === null ? '' : $this->artworks->url($record)),
			'embedded'   => ['values' => (object) ($embedded->values ?? []), 'location' => $embedded?->location !== null],
			'fields'     => array_values(array_map(static fn (Field $field): array => $field->toForm(), $schema->fields)),
			'sets'       => array_map(static fn (FieldSet $set): array => [
				'name'        => $set->name,
				'label'       => $set->label,
				'description' => $set->description,
				'fields'      => array_keys($set->schema->fields)
			], $this->schemas->setsFor(MediaKind::fromMime($file->mime))),
			'values'     => $values,
			'extra'      => $result->extra,
			'violations' => array_map(static fn (Violation $violation): array => [
				'field'    => $violation->field,
				'message'  => $violation->message,
				'severity' => $violation->severity->value
			], $result->violations),
			'uploader'   => $metadata->owner === '' ? null : ($owner === null ? ['username' => null, 'name' => 'A removed account'] : ['username' => $owner->username, 'name' => $this->profiles->displayName($owner)]),
			'may'        => [
				'edit'       => $original === null && $this->permissions->mayChangeMedia($account, Capability::MediaEdit, $metadata->owner),
				'delete'     => $this->permissions->mayChangeMedia($account, Capability::MediaDelete, $metadata->owner),
				'addArtwork' => $original === null && $this->permissions->mayChangeMedia($account, Capability::MediaEdit, $metadata->owner) && $this->mayAddArtwork($account, $record)
			],
			'artwork'    => MediaArtwork::has(MediaKind::fromMime($file->mime)) ? $this->artworkOf($metadata) : null,
			'artworkFor' => $metadata->id === '' || MediaKind::fromMime($file->mime) !== MediaKind::Image ? [] : array_map(static fn (MediaRecord $shown): array => [
				'path'  => $shown->key,
				'name'  => basename($shown->key),
				'title' => $shown->metadata()->title,
				'kind'  => $shown->kind()->value
			], $this->library->withArtwork($metadata->id)),
			'usedIn'     => $this->usage->entries($relative, ...array_map(static fn (MediaRecord $size): string => $size->key, $sizes)),
			'sizes'      => array_map(fn (MediaRecord $size): array => [
				'path'      => $size->key,
				'reference' => $size->url,
				'name'      => basename($size->key),
				'url'       => $size->url,
				'width'     => $size->width,
				'height'    => $size->height,
				'size'      => $size->size
			], $sizes),
			'original'   => $original === null ? null : [
				'path'      => $original->key,
				'reference' => $original->url,
				'name'      => basename($original->key),
				'title'     => $original->metadata()->title
			]
		];
	}

	/**
	 * A sound's or video's artwork (D-581): its id, and the library image
	 * it names, `null` when it names none that's there; `null` for none.
	 *
	 * @return ?array{id: string, image: ?array{path: string, reference: string, url: string, name: string, title: string}}
	 */
	private function artworkOf(MediaMetadata $metadata): ?array
	{
		if ($metadata->artwork === '') {
			return null;
		}

		$image = $this->library->findId($metadata->artwork);

		return [
			'id'    => $metadata->artwork,
			'image' => $image === null || $image->kind() !== MediaKind::Image ? null : [
				'path'      => $image->key,
				'reference' => $image->url,
				'url'       => $image->url,
				'name'      => basename($image->key),
				'title'     => $image->metadata()->title
			]
		];
	}

	private static function json422(string $message, string $field): ResponseInterface
	{
		return Response::json(['error' => $message, 'field' => $field], Status::UnprocessableContent, ['Cache-Control' => 'no-store']);
	}

	/**
	 * A file the media index lists, by its key: its path under
	 * `user/media`, with its reference (the URL it's served at),
	 * or `null` when there's no such file.
	 *
	 * @return array{?MediaFile, string}
	 */
	private function libraryFile(string $path): array
	{
		$path      = trim($path, '/');
		$reference = $this->config->url . '/' . UrlPath::encode($path);
		$file      = $this->guess(new SplFileInfo($path)) === null ? null : $this->resolver->resolve($reference);

		return [$file, $reference];
	}

	/**
	 * Returns whether a query value is a whole number from 1 to `$max`.
	 *
	 * @phpstan-assert-if-true string $value
	 */
	private static function counts(mixed $value, int $max): bool
	{
		return is_string($value) && ctype_digit($value) && (int) $value >= 1 && (int) $value <= $max;
	}

	/**
	 * A file's likely MIME type from its extension, if the site allows it.
	 */
	private function guess(SplFileInfo $file): ?string
	{
		$mime = self::EXTENSIONS[strtolower($file->getExtension())] ?? null;

		return $mime !== null && $this->config->allows($mime) ? $mime : null;
	}

	/**
	 * Describes a file for the admin.
	 *
	 * @return array<string, mixed>
	 */
	public static function describe(MediaFile $file, string $reference, string $relative, MediaMetadata $metadata, ?float $duration = null, int $sizes = 0, string $artwork = ''): array
	{
		$folder = dirname($relative);

		return [
			'reference' => $reference,
			'name'      => basename($relative),
			'folder'    => $folder === '.' ? '' : $folder,
			'url'       => $file->url,
			'mime'      => $file->mime,
			'kind'      => MediaKind::fromMime($file->mime)->value,
			'size'      => $file->size,
			'width'     => $file->width,
			'height'    => $file->height,
			'duration'  => $duration,
			'modified'  => date(DATE_ATOM, (int) filemtime($file->path)),
			'title'     => $metadata->title,
			'alt'       => $metadata->alt,
			'caption'   => $metadata->caption,
			'owner'     => $metadata->owner,
			'id'        => $metadata->id,
			'sizeCount' => $sizes,
			'artworkUrl' => $artwork === '' ? null : $artwork
		];
	}

	private static function error(string $message, Status $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
