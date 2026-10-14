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

namespace Blush\Admin\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Blush\Activity\Actor;
use Blush\Auth\Account;
use Blush\Auth\Permissions;
use Blush\Content\Editing\EditRefused;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Media\MediaArtwork;
use Blush\Media\MediaConfig;
use Blush\Media\MediaEditor;
use Blush\Media\Index\MediaLibrary;
use Blush\Media\MediaException;
use Blush\Media\MediaKind;
use Blush\Media\MediaMetadataStore;
use Blush\Media\MediaUploads;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Answers `POST {path}/api/media` (D-268): one file, as the multipart
 * field `file`, added to the library. It needs `media.upload`.
 *
 * The file goes in the folder the upload rules give its kind
 * (`MediaUploads`, D-406; `user/media/{year}/{month}/` by default, by the
 * site's clock), under its own name made safe for a URL (letters, digits, hyphens,
 * and underscores; spaces and dots become hyphens, so `shell.php.jpg`
 * can't be run by a server that reads every extension, D-499), with `-2`, `-3`, … before the
 * extension when the name is taken, so nothing is ever replaced. Only the
 * types the library lists (`MediaListController::EXTENSIONS`) that the
 * site allows are accepted, judged by the extension and then by the
 * contents, the way the media resolver serves them: the file is written
 * hidden first, and takes its name only once its contents pass.
 *
 * SVG, markup, scripts, and Office files with macros are refused whatever
 * the site allows (`MediaUploads::REFUSED`, D-497, D-499), by name and by
 * contents. A kind the rules turn off, or
 * every upload turned off, is refused, as
 * is a file larger than its kind's largest, and one of a kind the account
 * may not upload (`media.{kind}.upload`, D-407). The file's metadata
 * records who uploaded it (`owner`), which decides who may edit or
 * delete it, and gives the file its id (D-487). With `addArtwork` on
 * (D-581), a sound's or video's artwork is added to the library too.
 * Answers 201 with the file as
 * `GET media` describes one. PHP has the last word on size
 * (`upload_max_filesize`, `post_max_size`); `limit()` reads it, and
 * `largest()` says the most any upload may be, for the picker to show.
 */
final readonly class MediaUploadController
{
	public function __construct(
		private MediaEditor $editor,
		private MediaConfig $config,
		private Permissions $permissions,
		private MediaMetadataStore $metadata,
		private MediaLibrary $library,
		private MediaArtwork $artwork,
		private Messages $messages
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! array_any(MediaKind::cases(), fn (MediaKind $kind): bool => $this->permissions->mayUpload($account, $kind))) {
			return self::error($this->messages->t('admin.media_upload_controller.forbidden', 'You aren\'t allowed to upload media.'), Status::Forbidden);
		}

		$upload = $request->getUploadedFiles()['file'] ?? null;

		if (! $upload instanceof UploadedFileInterface) {
			// PHP drops the whole body when it's over `post_max_size`.
			$limit = self::limit();

			return self::error(
				$limit === null
					? $this->messages->t('admin.media_upload_controller.no_file', 'No file arrived. It may be larger than the site allows.')
					: $this->messages->t('admin.media_upload_controller.no_file_limit', 'No file arrived. It may be larger than the site allows (up to {size}).', size: $this->size($limit)),
				Status::BadRequest
			);
		}

		if ($upload->getError() !== UPLOAD_ERR_OK) {
			return $this->uploadError($upload->getError());
		}

		try {
			$uploaded = $this->editor->upload(Actor::admin($account), (string) $upload->getClientFilename(), $upload->getSize(), $upload->moveTo(...));
		} catch (EditRefused $e) {
			return self::error($e->getMessage(), $e->refusal->status());
		}

		try {
			$record = $this->library->find($uploaded->relative);
		} catch (MediaException) {
			$record = null;
		}

		return Response::json(MediaListController::describe($uploaded->file, $uploaded->reference, $uploaded->relative, $this->metadata->find($uploaded->file), artwork: $record === null ? '' : $this->artwork->url($record)), Status::Created, ['Cache-Control' => 'no-store']);
	}

	/**
	 * The file extensions that can be uploaded: the types the site allows
	 * but SVG,
	 * of the kinds the upload rules allow, and, given an account, the
	 * kinds it may upload (D-407).
	 *
	 * @return list<string>
	 */
	public function extensions(?Account $account = null): array
	{
		return $this->editor->extensions($account);
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
			if (! $this->editor->uploadable($kind, $account)) {
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

	private function size(int $bytes): Text
	{
		return $this->editor->size($bytes);
	}

	/**
	 * A file name made safe for a URL: letters, digits, hyphens, and
	 * underscores, then its extension, never starting with a dot, and
	 * never empty. Dots before the extension become hyphens, so no name
	 * carries a second extension a server might run (D-499).
	 */
	public static function safeName(string $name): string
	{
		return MediaEditor::safeName($name);
	}

	private function uploadError(int $error): ResponseInterface
	{
		$limit = self::limit();

		return match ($error) {
			UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => self::error(
				$limit === null
					? $this->messages->t('admin.media_upload_controller.too_large', 'The file is larger than the site allows.')
					: $this->messages->t('admin.media_upload_controller.too_large_limit', 'The file is larger than the site allows (up to {size}).', size: $this->size($limit)),
				Status::ContentTooLarge
			),
			UPLOAD_ERR_PARTIAL                        => self::error($this->messages->t('admin.media_upload_controller.partial', 'Only part of the file arrived. Try again.'), Status::BadRequest),
			UPLOAD_ERR_NO_FILE                        => self::error($this->messages->t('admin.media_upload_controller.no_file_sent', 'No file arrived.'), Status::BadRequest),
			default                                   => self::error($this->messages->t('admin.media_upload_controller.server', 'The server couldn\'t take the file. Its temporary folder may be missing or full.'), Status::InternalServerError)
		};
	}

	private static function error(string|Text $message, Status $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
