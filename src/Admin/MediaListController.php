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
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Core\Paths;
use Blush\Field\Field;
use Blush\Field\FieldContext;
use Blush\Field\InvalidField;
use Blush\Field\Violation;
use Blush\Http\Response;
use Blush\Http\Status;
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
use Blush\Support\Filesystem;
use Blush\Support\UrlPath;

/**
 * Answers `GET {path}/api/media` (D-246): the media files an entry can use,
 * for the editor's media picker. It needs `content.edit`.
 *
 * - `files`: the library (`user/media`), from the media index (D-288),
 *   newest first, a page at a time (`page`, and `per`, 48 by default, at
 *   most 100), narrowed by `search` (text its path or metadata must
 *   contain, in any case), `kind` (`image`, `video`, `audio`, `file` for
 *   any other kind, or `any`, the default), and `missing=alt` (images
 *   without alt text).
 * - `upload`: when the account may upload (D-268), the largest file PHP
 *   takes (`limit`, in bytes, or `null`) and the `extensions` the library
 *   takes; otherwise `null`.
 *
 * `GET {path}/api/media/{path}` (`show()`) describes one file, by its
 * path under `user/media`, with its metadata fields (D-287): the
 * `fields` its kind has (`MediaSchemas`, described as a content type's
 * are), their `values`, keys the file keeps that aren't fields
 * (`extra`), and what doesn't fit (`violations`). `PATCH` (`update()`,
 * `media.upload`) changes them: `set` (field names to values; an empty
 * one removes it) and `remove` (field names), each value checked by its
 * field, and answers with the file. `alt` and `caption` given on their
 * own, as before, are set too. It also has what the file says about
 * itself (`embedded`, D-289): the values read from its EXIF, IPTC, and
 * XMP, and whether it has a location, never the location itself.
 *
 * Each file has its `reference` (what to write: the library's URL path),
 * `name`, `folder`, `url`, `mime`, `kind`,
 * `size`, `width` and `height` (images and videos), `duration` (sound
 * and video, in seconds, when known, D-291), `modified`, and its metadata,
 * `title` (D-290), `alt`, and `caption` (`''` for none; `MediaMetadataStore`). Only the types
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

	private const array KINDS = ['any', 'image', 'video', 'audio', 'file'];

	public function __construct(
		private Paths $paths,
		private MediaConfig $config,
		private MediaResolver $resolver,
		private Permissions $permissions,
		private MediaUploadController $uploads,
		private MediaMetadataStore $metadata,
		private MediaSchemas $schemas,
		private AppConfig $app,
		private MediaLibrary $library
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, Capability::ContentEdit)) {
			return self::error('You aren\'t allowed to use media.', Status::Forbidden);
		}

		$query  = $request->getQueryParams();
		$search = $query['search'] ?? '';
		$kind   = $query['kind'] ?? 'any';
		$page   = $query['page'] ?? '1';
		$per    = $query['per'] ?? (string) self::PER;
		$missing = $query['missing'] ?? '';

		if (! is_string($search) || ! is_string($kind) || ! in_array($kind, self::KINDS, true) || ! in_array($missing, ['', 'alt'], true) || ! self::counts($page, 1_000_000) || ! self::counts($per, self::MAX_PER)) {
			return self::error('That isn\'t a valid media list.', Status::BadRequest);
		}

		$per   = (int) $per;
		$page  = (int) $page;
		$found = $this->library->query(new MediaQuery(
			search: trim($search),
			kind: $kind === 'any' ? null : MediaKind::from($kind),
			missingAlt: $missing === 'alt',
			page: $page,
			per: $per
		));

		$files = array_map(fn (MediaRecord $record): array => self::describe($record->file($this->paths), $record->url, $record->key, $record->metadata(), $record->duration()), $found->records);

		return Response::json([
			'search'  => $search,
			'kind'    => $kind,
			'missing' => $missing,
			'total'   => $found->total,
			'page'    => $page,
			'pages'   => max(1, (int) ceil($found->total / $per)),
			'per'     => $per,
			'files'   => $files,
			'upload'  => $this->permissions->can($account, Capability::MediaUpload)
				? ['limit' => MediaUploadController::limit(), 'extensions' => $this->uploads->extensions()]
				: null
		], headers: ['Cache-Control' => 'no-store']);
	}

	public function show(ServerRequestInterface $request, string $path): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, Capability::ContentEdit)) {
			return self::error('You aren\'t allowed to use media.', Status::Forbidden);
		}

		[$file, $reference] = $this->libraryFile($path);

		if ($file === null) {
			return self::error(sprintf('There\'s no "%s" in the media library.', $path), Status::NotFound);
		}

		return Response::json($this->details($file, $reference, trim($path, '/'), $this->metadata->find($file)), headers: ['Cache-Control' => 'no-store']);
	}

	public function update(ServerRequestInterface $request, string $path): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, Capability::MediaUpload)) {
			return self::error('You aren\'t allowed to change media.', Status::Forbidden);
		}

		[$file, $reference] = $this->libraryFile($path);

		if ($file === null) {
			return self::error(sprintf('There\'s no "%s" in the media library.', $path), Status::NotFound);
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
			$this->library->refresh();
		} catch (MediaException $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		return Response::json($this->details($file, $reference, trim($path, '/'), $this->metadata->find($file)), headers: ['Cache-Control' => 'no-store']);
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
	private function details(MediaFile $file, string $reference, string $relative, MediaMetadata $metadata): array
	{
		$schema = $this->schemas->forFile($file);
		$result = $schema->resolve($metadata->values, new FieldContext($this->app->timezone()));
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

		return [
			...self::describe($file, $reference, $relative, $metadata, $record?->duration()),
			'embedded'   => ['values' => (object) ($embedded->values ?? []), 'location' => $embedded?->location !== null],
			'fields'     => array_values(array_map(static fn (Field $field): array => $field->toForm(), $schema->fields)),
			'values'     => $values,
			'extra'      => $result->extra,
			'violations' => array_map(static fn (Violation $violation): array => [
				'field'    => $violation->field,
				'message'  => $violation->message,
				'severity' => $violation->severity->value
			], $result->violations)
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
	public static function describe(MediaFile $file, string $reference, string $relative, MediaMetadata $metadata, ?float $duration = null): array
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
			'caption'   => $metadata->caption
		];
	}

	private static function error(string $message, Status $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
