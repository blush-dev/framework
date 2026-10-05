<?php

/**
 * Media upload controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Throwable;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Blush\Auth\Account;
use Blush\Auth\Permissions;
use Blush\Core\Paths;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Media\MediaConfig;
use Blush\Media\Index\MediaLibrary;
use Blush\Media\MediaException;
use Blush\Media\MediaKind;
use Blush\Media\MediaMetadata;
use Blush\Media\MediaMetadataStore;
use Blush\Media\MediaResolver;
use Blush\Support\UrlPath;
use Blush\Support\Uuid;

/**
 * Answers `POST {path}/api/media` (D-268): one file, as the multipart
 * field `file`, added to the library. It needs `media.upload`.
 *
 * The file goes in the folder the upload rules give its kind
 * (`MediaUploads`, D-406; `user/media/{year}/{month}/` by default, by the
 * site's clock), under its own name made safe for a URL (letters, digits, dots, hyphens,
 * and underscores; spaces become hyphens), with `-2`, `-3`, … before the
 * extension when the name is taken, so nothing is ever replaced. Only the
 * types the library lists (`MediaListController::EXTENSIONS`) that the
 * site allows are accepted, judged by the extension and then by the
 * contents, the way the media resolver serves them: the file is written
 * hidden first, and takes its name only once its contents pass.
 *
 * A kind the rules turn off, or every upload turned off, is refused, as
 * is a file larger than its kind's largest, and one of a kind the account
 * may not upload (`media.{kind}.upload`, D-407). The file's metadata
 * records who uploaded it (`owner`), which decides who may edit or
 * delete it, and gives the file its id (D-487). Answers 201 with the file as
 * `GET media` describes one. PHP has the last word on size
 * (`upload_max_filesize`, `post_max_size`); `limit()` reads it, and
 * `largest()` says the most any upload may be, for the picker to show.
 */
final readonly class MediaUploadController
{
	public function __construct(
		private Paths $paths,
		private MediaConfig $config,
		private MediaResolver $resolver,
		private Permissions $permissions,
		private ClockInterface $clock,
		private MediaMetadataStore $metadata,
		private MediaLibrary $library
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! array_any(MediaKind::cases(), fn (MediaKind $kind): bool => $this->permissions->mayUpload($account, $kind))) {
			return self::error('You aren\'t allowed to upload media.', Status::Forbidden);
		}

		$upload = $request->getUploadedFiles()['file'] ?? null;

		if (! $upload instanceof UploadedFileInterface) {
			// PHP drops the whole body when it's over `post_max_size`.
			return self::error('No file arrived. It may be larger than the site allows' . self::limitText() . '.', Status::BadRequest);
		}

		if ($upload->getError() !== UPLOAD_ERR_OK) {
			return self::uploadError($upload->getError());
		}

		$uploads = $this->config->uploads;

		if (! $uploads->enabled) {
			return self::error('Uploads are turned off on the Media settings screen.', Status::Forbidden);
		}

		$name      = self::safeName((string) $upload->getClientFilename());
		$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		$mime      = MediaListController::EXTENSIONS[$extension] ?? null;

		if ($mime === null || ! $this->config->allows($mime)) {
			return self::error(sprintf('%s isn\'t a type the library takes: %s.', $name, implode(', ', $this->extensions())), Status::UnprocessableContent);
		}

		$kind = MediaKind::fromMime($mime);

		if (! $this->permissions->mayUpload($account, $kind)) {
			return self::error(sprintf('%s can\'t be uploaded: you aren\'t allowed to upload %s.', $name, mb_strtolower($kind->label())), Status::Forbidden);
		}

		if (! $uploads->allows($kind)) {
			return self::error(sprintf('%s can\'t be uploaded: uploads of %s are turned off on the Media settings screen.', $name, mb_strtolower($kind->label())), Status::UnprocessableContent);
		}

		$most = $uploads->maxBytes($kind);

		if ($most !== null && ($upload->getSize() ?? 0) > $most) {
			return self::error(sprintf('%s is larger than %s may be (up to %s).', $name, mb_strtolower($kind->label()), self::size($most)), Status::ContentTooLarge);
		}

		$folder = rtrim($this->paths->media . '/' . $uploads->folder($kind, $this->clock->now(), $extension), '/');
		$hidden = $folder . '/.upload-' . bin2hex(random_bytes(8)) . '.' . $extension;

		try {
			if (! is_dir($folder) && ! @mkdir($folder, 0775, true) && ! is_dir($folder)) {
				return self::error('The media folder can\'t be written to.', Status::InternalServerError);
			}

			$upload->moveTo($hidden);
		} catch (Throwable) {
			@unlink($hidden);

			return self::error('The file couldn\'t be saved to the media folder.', Status::InternalServerError);
		}

		$actual = MediaResolver::mimeOf($hidden);

		if (! $this->config->allows($actual) || ! $uploads->allows(MediaKind::fromMime($actual)) || ! $this->permissions->mayUpload($account, MediaKind::fromMime($actual))) {
			@unlink($hidden);

			return self::error(sprintf('%s isn\'t what its name says it is, or isn\'t a type the library takes.', $name), Status::UnprocessableContent);
		}

		$target = $this->free($folder, $name);

		if (! @rename($hidden, $target)) {
			@unlink($hidden);

			return self::error('The file couldn\'t be saved to the media folder.', Status::InternalServerError);
		}

		$relative  = substr($target, strlen($this->paths->media) + 1);
		$reference = $this->config->url . '/' . UrlPath::encode($relative);
		$file      = $this->resolver->resolve($reference);

		if ($file === null) {
			@unlink($target);

			return self::error(sprintf('%s couldn\'t be added to the library.', $name), Status::UnprocessableContent);
		}

		// Who uploaded it decides who may change it (D-407), and every
		// file has an id (D-487). A file whose metadata can't be written
		// stays, with neither; `media:ids` can add the id.
		try {
			$this->metadata->save($file, [MediaMetadata::OWNER => $account->username, MediaMetadata::ID => Uuid::v7($this->clock->now())]);
		} catch (MediaException) {
		}

		// The library lists it at once. An index that can't be written is
		// refreshed next time; the upload itself worked.
		try {
			$this->library->refresh();
		} catch (MediaException) {
		}

		return Response::json(MediaListController::describe($file, $reference, $relative, $this->metadata->find($file)), Status::Created, ['Cache-Control' => 'no-store']);
	}

	/**
	 * The file extensions that can be uploaded: the types the site allows,
	 * of the kinds the upload rules allow, and, given an account, the
	 * kinds it may upload (D-407).
	 *
	 * @return list<string>
	 */
	public function extensions(?Account $account = null): array
	{
		return array_keys(array_filter(MediaListController::EXTENSIONS, fn (string $mime): bool => $this->config->allows($mime) && $this->uploadable(MediaKind::fromMime($mime), $account)));
	}

	/**
	 * Whether files of a kind can be uploaded: the upload rules allow it,
	 * and the account, given one, may.
	 */
	private function uploadable(MediaKind $kind, ?Account $account): bool
	{
		return $this->config->uploads->allows($kind) && ($account === null || $this->permissions->mayUpload($account, $kind));
	}

	/**
	 * The most any upload may be, in bytes, or `null` for no limit: the
	 * largest the upload rules let a kind be that may be uploaded, within
	 * PHP's limit.
	 */
	public function largest(?Account $account = null): ?int
	{
		$uploads = $this->config->uploads;
		$rules   = null;

		foreach (MediaKind::cases() as $kind) {
			if (! $this->uploadable($kind, $account)) {
				continue;
			}

			$size = $uploads->maxBytes($kind);

			if ($size === null) {
				$rules = null;

				break;
			}

			$rules = max($rules ?? 0, $size);
		}

		$server = self::limit();

		return match (true) {
			$rules === null  => $server,
			$server === null => $rules,
			default          => min($rules, $server)
		};
	}

	/**
	 * The largest file PHP accepts, in bytes, or `null` for no limit: the
	 * smaller of `upload_max_filesize` and `post_max_size`.
	 */
	public static function limit(): ?int
	{
		$limits = array_filter(
			[self::bytes((string) ini_get('upload_max_filesize')), self::bytes((string) ini_get('post_max_size'))],
			static fn (int $bytes): bool => $bytes > 0
		);

		return $limits === [] ? null : min($limits);
	}

	/**
	 * Reads an ini size: `64M`, `2G`, `512K`, or bytes.
	 */
	private static function bytes(string $value): int
	{
		$value = trim($value);

		if (preg_match('/^(\d+)\s*([KMG]?)$/i', $value, $match) !== 1) {
			return 0;
		}

		return (int) $match[1] * match (strtoupper($match[2])) {
			'G'     => 1024 ** 3,
			'M'     => 1024 ** 2,
			'K'     => 1024,
			default => 1
		};
	}

	/**
	 * The limit as text for a message: ", up to 64 MB", or nothing.
	 */
	private static function limitText(): string
	{
		$limit = self::limit();

		return $limit === null ? '' : sprintf(' (up to %s)', self::size($limit));
	}

	private static function size(int $bytes): string
	{
		return $bytes >= 1024 ** 2 ? round($bytes / 1024 ** 2, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
	}

	/**
	 * A file name made safe for a URL: letters, digits, dots, hyphens, and
	 * underscores, never starting with a dot, and never empty.
	 */
	public static function safeName(string $name): string
	{
		$name      = basename(str_replace('\\', '/', $name));
		$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		$base      = pathinfo($name, PATHINFO_FILENAME);

		$base = (string) preg_replace('/\s+/u', '-', trim($base));
		$base = (string) preg_replace('/[^A-Za-z0-9._-]+/', '', $base);
		$base = trim((string) preg_replace('/-{2,}/', '-', $base), '.-_');

		if ($base === '') {
			$base = 'file';
		}

		return $extension === '' ? $base : "{$base}.{$extension}";
	}

	/**
	 * The first free path for a name in a folder: the name, else with
	 * `-2`, `-3`, … before its extension. A name with metadata left
	 * behind (its file was removed by hand) isn't free, so a new file
	 * doesn't take on another's alt text.
	 */
	private function free(string $folder, string $name): string
	{
		$extension = pathinfo($name, PATHINFO_EXTENSION);
		$base      = pathinfo($name, PATHINFO_FILENAME);
		$path      = "{$folder}/{$name}";
		$taken     = fn (string $path): bool => file_exists($path) || $this->metadata->has(substr($path, strlen($this->paths->media) + 1));

		for ($n = 2; $taken($path); $n++) {
			$path = "{$folder}/{$base}-{$n}" . ($extension === '' ? '' : ".{$extension}");
		}

		return $path;
	}

	private static function uploadError(int $error): ResponseInterface
	{
		return match ($error) {
			UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => self::error('The file is larger than the site allows' . self::limitText() . '.', Status::ContentTooLarge),
			UPLOAD_ERR_PARTIAL                        => self::error('Only part of the file arrived. Try again.', Status::BadRequest),
			UPLOAD_ERR_NO_FILE                        => self::error('No file arrived.', Status::BadRequest),
			default                                   => self::error('The server couldn\'t take the file. Its temporary folder may be missing or full.', Status::InternalServerError)
		};
	}

	private static function error(string $message, Status $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
