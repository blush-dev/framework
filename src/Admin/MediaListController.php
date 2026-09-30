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
use Blush\Content\ContentRepository;
use Blush\Core\Paths;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Media\MediaConfig;
use Blush\Media\MediaException;
use Blush\Media\MediaFile;
use Blush\Media\MediaMetadata;
use Blush\Media\MediaMetadataStore;
use Blush\Media\MediaResolver;
use Blush\Support\Filesystem;

/**
 * Answers `GET {path}/api/media` (D-246): the media files an entry can use,
 * for the editor's media picker. It needs `content.edit`.
 *
 * - `files`: the library (`user/media`), newest first, a page at a time
 *   (`page`, and `per`, 48 by default, at most 100), narrowed by `search`
 *   (text the path must contain) and `kind` (`image`, `video`, `audio`,
 *   `file` for any other kind, or `any`, the default).
 * - `beside`: with `entry` (an id the account may edit) in a page bundle
 *   (`name/index.md`), the media files in its folder, which it refers to
 *   by name; otherwise `null`.
 * - `upload`: when the account may upload (D-268), the largest file PHP
 *   takes (`limit`, in bytes, or `null`) and the `extensions` the library
 *   takes; otherwise `null`.
 *
 * `GET {path}/api/media/{path}` (`show()`) describes one library file, by
 * its path under `user/media`, and `PATCH` (`update()`, `media.upload`)
 * changes its `alt` and `caption` (D-269), either or both, and answers
 * with it. Pages don't read them: the editor fills them in on insert
 * (D-272).
 *
 * Each file has its `reference` (what to write: the library's URL path,
 * or a bundle file's name), `name`, `folder`, `url`, `mime`, `kind`,
 * `size`, `width` and `height` (images), `modified`, and its metadata,
 * `alt` and `caption` (`''` for none; `MediaMetadataStore`). Only the types
 * the site allows (`MediaConfig::$types`) are listed, without caption
 * tracks, and never hidden files.
 */
final readonly class MediaListController
{
	private const int PER = 48;

	private const int MAX_PER = 100;

	private const int MAX_BESIDE = 200;

	/**
	 * MIME types by extension, to narrow a large library before reading
	 * any file; the page's files are then checked by their contents. An
	 * upload must have one of them too (`MediaUploadController`).
	 *
	 * @var array<string, string>
	 */
	public const array EXTENSIONS = [
		'apng' => 'image/apng',
		'avif' => 'image/avif',
		'gif'  => 'image/gif',
		'jpeg' => 'image/jpeg',
		'jpg'  => 'image/jpeg',
		'png'  => 'image/png',
		'svg'  => 'image/svg+xml',
		'webp' => 'image/webp',
		'mp3'  => 'audio/mpeg',
		'oga'  => 'audio/ogg',
		'ogg'  => 'audio/ogg',
		'wav'  => 'audio/wav',
		'm4v'  => 'video/mp4',
		'mp4'  => 'video/mp4',
		'ogv'  => 'video/ogg',
		'webm' => 'video/webm'
	];

	private const array KINDS = ['any', 'image', 'video', 'audio', 'file'];

	/**
	 * The kinds a `file` isn't.
	 */
	private const array PLAYABLE = ['image', 'video', 'audio'];

	public function __construct(
		private Paths $paths,
		private MediaConfig $config,
		private MediaResolver $resolver,
		private ContentRepository $content,
		private Permissions $permissions,
		private MediaUploadController $uploads,
		private MediaMetadataStore $metadata
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
		$entry  = $query['entry'] ?? '';

		if (! is_string($search) || ! is_string($entry) || ! is_string($kind) || ! in_array($kind, self::KINDS, true) || ! self::counts($page, 1_000_000) || ! self::counts($per, self::MAX_PER)) {
			return self::error('That isn\'t a valid media list.', Status::BadRequest);
		}

		$beside = null;

		if ($entry !== '') {
			$found = $this->content->find($entry);

			if ($found === null || ! $this->permissions->can($account, Capability::ContentEdit, $found)) {
				return self::error(sprintf('There\'s no "%s" entry you may edit.', $entry), Status::NotFound);
			}

			$beside = $this->beside($entry);
		}

		$candidates = $this->library(trim($search), $kind);
		$per        = (int) $per;
		$page       = (int) $page;
		$files      = [];

		foreach (array_slice($candidates, ($page - 1) * $per, $per) as $relative => $reference) {
			$file = $this->resolver->resolve($reference);

			if ($file !== null) {
				$files[] = self::describe($file, $reference, (string) $relative, $this->metadata->find($file));
			}
		}

		return Response::json([
			'search' => $search,
			'kind'   => $kind,
			'total'  => count($candidates),
			'page'   => $page,
			'pages'  => max(1, (int) ceil(count($candidates) / $per)),
			'per'    => $per,
			'files'  => $files,
			'beside' => $beside,
			'upload' => $this->permissions->can($account, Capability::MediaUpload)
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

		return Response::json(self::describe($file, $reference, trim($path, '/'), $this->metadata->find($file)), headers: ['Cache-Control' => 'no-store']);
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
			$input = json_decode((string) $request->getBody(), true, 4, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$input = null;
		}

		if (! is_array($input) || array_any(['alt', 'caption'], static fn (string $key): bool => array_key_exists($key, $input) && ! is_string($input[$key]))) {
			return self::error('Send "alt" and "caption", as text, in a JSON object.', Status::BadRequest);
		}

		$current = $this->metadata->find($file);
		$next    = new MediaMetadata(
			is_string($input['alt'] ?? null) ? $input['alt'] : $current->alt,
			is_string($input['caption'] ?? null) ? $input['caption'] : $current->caption
		);

		try {
			$this->metadata->save($file, $next);
		} catch (MediaException $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		return Response::json(self::describe($file, $reference, trim($path, '/'), $next), headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * A library file by its path under `user/media`, with its reference,
	 * or `null` when there's no such file the library lists.
	 *
	 * @return array{?MediaFile, string}
	 */
	private function libraryFile(string $path): array
	{
		$reference = $this->config->url . '/' . implode('/', array_map(rawurlencode(...), explode('/', trim($path, '/'))));
		$file      = $this->guess(new SplFileInfo($path)) === null ? null : $this->resolver->resolve($reference);

		return [$file !== null && str_starts_with($file->path, $this->paths->media . '/') ? $file : null, $reference];
	}

	/**
	 * Whether a MIME type is of a kind the list is narrowed to.
	 */
	private static function isKind(string $mime, string $kind): bool
	{
		$type = strstr($mime, '/', true) ?: $mime;

		return match ($kind) {
			'any'   => true,
			'file'  => ! in_array($type, self::PLAYABLE, true),
			default => $type === $kind
		};
	}

	/**
	 * The library's files that may match, newest first, as relative path
	 * => reference.
	 *
	 * @return array<string, string>
	 */
	private function library(string $search, string $kind): array
	{
		$found = [];

		foreach (new Filesystem()->files($this->paths->media) as $relative => $file) {
			$relative = (string) $relative;
			$mime     = $this->guess($file);

			if ($mime === null || ! self::isKind($mime, $kind)) {
				continue;
			}

			if ($search !== '' && ! str_contains(mb_strtolower($relative), mb_strtolower($search))) {
				continue;
			}

			$found[$relative] = $file->getMTime();
		}

		uksort($found, static fn (string $a, string $b): int => [$found[$b], $a] <=> [$found[$a], $b]);

		$references = [];

		foreach (array_keys($found) as $relative) {
			$references[$relative] = $this->config->url . '/' . implode('/', array_map(rawurlencode(...), explode('/', $relative)));
		}

		return $references;
	}

	/**
	 * The media files in a page bundle's folder, or `null` when the entry
	 * isn't a bundle.
	 *
	 * @return ?list<array<string, mixed>>
	 */
	private function beside(string $entry): ?array
	{
		if (! str_starts_with(basename($entry), 'index.') || ! str_contains($entry, '/')) {
			return null;
		}

		$folder = dirname($entry);
		$files  = [];

		foreach (glob($this->paths->content . '/' . $folder . '/*') ?: [] as $path) {
			$name = basename($path);

			if (count($files) >= self::MAX_BESIDE || ! is_file($path) || $this->guess(new SplFileInfo($path)) === null) {
				continue;
			}

			$file = $this->resolver->resolve($name, $folder);

			if ($file !== null) {
				$files[] = self::describe($file, $name, $name, $this->metadata->find($file));
			}
		}

		return $files;
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
	public static function describe(MediaFile $file, string $reference, string $relative, MediaMetadata $metadata): array
	{
		$folder = dirname($relative);

		return [
			'reference' => $reference,
			'name'      => basename($relative),
			'folder'    => $folder === '.' ? '' : $folder,
			'url'       => $file->url,
			'mime'      => $file->mime,
			'kind'      => $file->type(),
			'size'      => $file->size,
			'width'     => $file->width,
			'height'    => $file->height,
			'modified'  => date(DATE_ATOM, (int) filemtime($file->path)),
			'alt'       => $metadata->alt,
			'caption'   => $metadata->caption
		];
	}

	private static function error(string $message, Status $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
