<?php

/**
 * Media editor.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Closure;
use SplFileInfo;
use Throwable;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Blush\Activity\Activity;
use Blush\Activity\ActivityAction;
use Blush\Activity\ActivityException;
use Blush\Activity\ActivityLog;
use Blush\Activity\Actor;
use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Content\Editing\EditRefused;
use Blush\Core\AppConfig;
use Blush\Core\Paths;
use Blush\Field\Field;
use Blush\Field\FieldContext;
use Blush\Field\InvalidField;
use Blush\Media\Index\MediaLibrary;
use Blush\Support\Uuid;
use Blush\Support\UrlPath;

/**
 * The one write path for the media library (D-756): uploads and changes
 * to a file's details, by the same rules for the admin, the MCP server's
 * tools, and later the API's writes, each recorded in the activity log
 * (D-758) by who made it and how, with what a change replaced, for Undo.
 *
 * **Uploads** (D-268, D-406) take only the types the site allows, never
 * SVG or another type that can carry script (D-497), of the kinds the
 * upload rules allow and the account may upload (D-407), up to each
 * kind's size; they go in their kind's folder under a name made safe for
 * a URL, checked again by what's in the file, given an owner, a date, and
 * an id (D-487), and listed at once. With `addArtwork` on (D-581), a
 * sound's or video's picture goes into the library too.
 *
 * **Details** (alt text, a caption, a title, and the other fields of a
 * file's kind, D-287) are changed by whose file it is (D-407); a size of
 * an image goes by the image's (D-488).
 *
 * Every write throws `EditRefused` when it's refused, saying why.
 */
final readonly class MediaEditor
{
	public function __construct(
		private Paths $paths,
		private MediaConfig $config,
		private MediaResolver $resolver,
		private Permissions $permissions,
		private ClockInterface $clock,
		private MediaMetadataStore $metadata,
		private MediaSchemas $schemas,
		private MediaLibrary $library,
		private MediaArtwork $artwork,
		private AppConfig $app,
		private ActivityLog $activity,
		private LoggerInterface $logger
	) {}

	/**
	 * Returns a copy whose permissions are narrowed to a token's
	 * capabilities (`null` for none), as the MCP server's are (D-744).
	 *
	 * @param  ?list<string> $capabilities
	 * @throws \Blush\Auth\AuthException For a name that isn't a capability's.
	 */
	public function limitedTo(?array $capabilities): self
	{
		return clone($this, ['permissions' => $this->permissions->limitedTo($capabilities)]);
	}

	/**
	 * Adds a file to the library. `$place` puts what was sent at a path in
	 * the media folder (an upload's `moveTo()`, or bytes written).
	 *
	 * @param  Closure(string): void $place
	 * @throws EditRefused
	 */
	public function upload(Actor $actor, string $name, ?int $size, Closure $place): UploadedMedia
	{
		$account = $actor->account;

		if (! array_any(MediaKind::cases(), fn (MediaKind $kind): bool => $this->permissions->mayUpload($account, $kind))) {
			throw EditRefused::forbidden('You aren\'t allowed to upload media.');
		}

		$uploads = $this->config->uploads;

		if (! $uploads->enabled) {
			throw EditRefused::forbidden('Uploads are turned off on the Media settings screen.');
		}

		$name      = self::safeName($name);
		$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		$mime      = MediaResolver::EXTENSIONS[$extension] ?? null;

		if ($mime !== null && MediaUploads::refuses($mime)) {
			throw EditRefused::unprocessable(match ($mime) {
				'image/svg+xml' => sprintf('%s can\'t be uploaded: SVG files can carry script. For icons, add an icon pack.', $name),
				default         => sprintf('%s can\'t be uploaded: files of this type can carry script.', $name)
			});
		}

		if ($mime === null || ! $this->config->allows($mime)) {
			throw EditRefused::unprocessable(sprintf('%s isn\'t a type the library takes: %s.', $name, implode(', ', $this->extensions())));
		}

		$kind = MediaKind::fromMime($mime);

		if (! $this->permissions->mayUpload($account, $kind)) {
			throw EditRefused::forbidden(sprintf('%s can\'t be uploaded: you aren\'t allowed to upload %s.', $name, mb_strtolower($kind->label())));
		}

		if (! $uploads->allows($kind)) {
			throw EditRefused::unprocessable(sprintf('%s can\'t be uploaded: uploads of %s are turned off on the Media settings screen.', $name, mb_strtolower($kind->label())));
		}

		$most = $uploads->maxBytes($kind);

		if ($most !== null && ($size ?? 0) > $most) {
			throw EditRefused::tooLarge(sprintf('%s is larger than %s may be (up to %s).', $name, mb_strtolower($kind->label()), self::size($most)));
		}

		$folder = rtrim($this->paths->media . '/' . $uploads->folder($kind, $this->clock->now()), '/');
		$hidden = $folder . '/.upload-' . bin2hex(random_bytes(8)) . '.' . $extension;

		try {
			if (! is_dir($folder) && ! @mkdir($folder, 0775, true) && ! is_dir($folder)) {
				throw EditRefused::failed('The media folder can\'t be written to.');
			}

			$place($hidden);
		} catch (EditRefused $e) {
			throw $e;
		} catch (Throwable $e) {
			@unlink($hidden);

			throw EditRefused::failed('The file couldn\'t be saved to the media folder.', $e);
		}

		$actual = MediaResolver::mimeOf($hidden);

		if (MediaUploads::refuses($actual) || ! $this->config->allows($actual) || ! $uploads->allows(MediaKind::fromMime($actual)) || ! $this->permissions->mayUpload($account, MediaKind::fromMime($actual))) {
			@unlink($hidden);

			throw EditRefused::unprocessable(sprintf('%s isn\'t what its name says it is, or isn\'t a type the library takes.', $name));
		}

		$target = $this->metadata->freePath($folder, $name);

		if (! @rename($hidden, $target)) {
			@unlink($hidden);

			throw EditRefused::failed('The file couldn\'t be saved to the media folder.');
		}

		$relative  = substr($target, strlen($this->paths->media) + 1);
		$reference = $this->config->url . '/' . UrlPath::encode($relative);
		$file      = $this->resolver->resolve($reference);

		if ($file === null) {
			@unlink($target);

			throw EditRefused::unprocessable(sprintf('%s couldn\'t be added to the library.', $name));
		}

		// Who uploaded it decides who may change it (D-407), when orders
		// the library (D-709), and every file has an id (D-487). A file
		// whose metadata can't be written stays, with none of them;
		// `media:ids` and `media:dates` can add the id and the date.
		$now = $this->clock->now();
		$id  = Uuid::v7($now);

		try {
			$this->metadata->save($file, [MediaMetadata::OWNER => $account->id, MediaMetadata::UPLOADED => MediaMetadata::utc($now), MediaMetadata::ID => $id]);
		} catch (MediaException) {
			$id = null;
		}

		// The library lists it at once. An index that can't be written is
		// refreshed next time; the upload itself worked.
		try {
			$this->library->refresh();
		} catch (MediaException) {
		}

		// The picture a sound or video carries goes into the library as
		// its artwork, when the site asks and the account may upload
		// images (D-581). The upload worked either way.
		if ($this->config->addArtwork && MediaArtwork::has($kind) && $uploads->allows(MediaKind::Image) && $this->permissions->mayUpload($account, MediaKind::Image)) {
			try {
				$record = $this->library->find($relative);

				if ($record !== null && MediaArtwork::carries($record)) {
					$this->artwork->adopt($record, $account->id);
				}
			} catch (MediaException) {
			}
		}

		if ($id !== null) {
			$this->log($actor, ActivityAction::MediaUpload, $id, basename($relative), sprintf('Uploaded %s', basename($relative)));
		}

		return new UploadedMedia($file, $reference, $relative, $id);
	}

	/**
	 * Adds a file to the library from its contents, as an app sends one
	 * (D-756).
	 *
	 * @throws EditRefused
	 */
	public function uploadBytes(Actor $actor, string $name, string $bytes): UploadedMedia
	{
		return $this->upload($actor, $name, strlen($bytes), static function (string $path) use ($bytes): void {
			if (@file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) {
				throw new MediaException('The file couldn\'t be written.');
			}
		});
	}

	/**
	 * Changes a file's details, by its key (its path under `user/media`):
	 * fields to `set` and `remove`, each of its kind's fields (D-287).
	 *
	 * @param  array<string, mixed> $set
	 * @param  list<string>         $remove
	 * @throws EditRefused
	 */
	public function update(Actor $actor, string $relative, array $set, array $remove = []): MediaFile
	{
		$relative = trim($relative, '/');
		$file     = $this->libraryFile($relative);

		if ($file === null) {
			throw EditRefused::notFound(sprintf('There\'s no "%s" in the media library.', $relative));
		}

		if (! $this->permissions->mayChangeMedia($actor->account, Capability::MediaEdit, $this->describedBy($file, $relative)->owner)) {
			throw EditRefused::forbidden('You aren\'t allowed to change this file\'s details.');
		}

		$original = $this->originalOf($relative);

		if ($original !== null) {
			throw EditRefused::unprocessable(sprintf('%s is a size of %s, whose details it goes by; change them there.', basename($relative), $original));
		}

		$schema  = $this->schemas->forFile($file);
		$context = new FieldContext($this->app->timezone());
		$values  = [];

		foreach ([...array_map(strval(...), array_keys($set)), ...$remove] as $key) {
			if ($schema->field($key) === null) {
				throw EditRefused::unprocessable(sprintf('"%s" isn\'t a field of %s files.', $key, MediaKind::fromMime($file->mime)->value), $key);
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
				throw EditRefused::unprocessable(sprintf('%s %s', $field->label !== '' ? $field->label : ucfirst($field->name), $error->getMessage()), $field->name);
			}
		}

		$was    = $this->metadata->find($file);
		$before = [];

		foreach ([...array_keys($values), ...$remove] as $key) {
			$before[$key] = $was->values[$key] ?? null;
		}

		try {
			$this->metadata->save($file, $values, $remove, $schema);
			$this->library->refresh([$relative]);
		} catch (MediaException $error) {
			throw EditRefused::failed($error->getMessage(), $error);
		}

		$id = $this->metadata->find($file)->id;

		if ($id !== '' && $before !== []) {
			$changed = array_keys($before);

			$this->log($actor, ActivityAction::MediaUpdate, $id, basename($relative), sprintf('Changed %s on %s', self::fieldNames($changed), basename($relative)), ['key' => $relative, 'values' => $before]);
		}

		return $file;
	}

	/**
	 * Undoes a change to a file's details (D-758): puts back what it
	 * replaced, and records that as a change of its own.
	 *
	 * @throws EditRefused
	 */
	public function undo(Actor $actor, Activity $activity): MediaFile
	{
		$before = $activity->before ?? [];
		$key    = is_string($before['key'] ?? null) ? $before['key'] : null;
		$values = is_array($before['values'] ?? null) ? $before['values'] : null;

		if (! $activity->canUndo() || $key === null || $values === null) {
			throw EditRefused::unprocessable($activity->undone !== null ? 'That change was already undone.' : 'That change can\'t be undone.');
		}

		$set    = [];
		$remove = [];

		foreach ($values as $name => $value) {
			if ($value === null) {
				$remove[] = (string) $name;
			} else {
				$set[(string) $name] = $value;
			}
		}

		return $this->update($actor->undoing($activity), $key, $set, $remove);
	}

	/**
	 * The file extensions that can be uploaded: the types the site allows
	 * but SVG, of the kinds the upload rules allow, and, given an account,
	 * the kinds it may upload (D-407).
	 *
	 * @return list<string>
	 */
	public function extensions(?Account $account = null): array
	{
		return array_keys(array_filter(MediaResolver::EXTENSIONS, fn (string $mime): bool => ! MediaUploads::refuses($mime) && $this->config->allows($mime) && $this->uploadable(MediaKind::fromMime($mime), $account)));
	}

	/**
	 * Whether files of a kind can be uploaded: the upload rules allow it,
	 * and the account, given one, may.
	 */
	public function uploadable(MediaKind $kind, ?Account $account): bool
	{
		return $this->config->uploads->allows($kind) && ($account === null || $this->permissions->mayUpload($account, $kind));
	}

	/**
	 * The details a file goes by: its own, or for a size of an image
	 * (D-488), the image's.
	 */
	public function describedBy(MediaFile $file, string $relative): MediaMetadata
	{
		$original = $this->originalOf($relative);
		$image    = $original === null ? null : $this->resolver->fromKey($original);

		return $this->metadata->find($image ?? $file);
	}

	/**
	 * The key of the image a file is a size of, or `null`.
	 */
	public function originalOf(string $relative): ?string
	{
		try {
			return $this->library->find($relative)?->original;
		} catch (MediaException) {
			return null;
		}
	}

	/**
	 * A file name made safe for a URL: letters, digits, hyphens, and
	 * underscores, then its extension, never starting with a dot, and
	 * never empty. Dots before the extension become hyphens, so no name
	 * carries a second extension a server might run (D-499).
	 */
	public static function safeName(string $name): string
	{
		$name      = basename(str_replace('\\', '/', $name));
		$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		$base      = pathinfo($name, PATHINFO_FILENAME);

		$base = (string) preg_replace('/[\s.]+/u', '-', trim($base));
		$base = (string) preg_replace('/[^A-Za-z0-9_-]+/', '', $base);
		$base = trim((string) preg_replace('/-{2,}/', '-', $base), '-_');

		if ($base === '') {
			$base = 'file';
		}

		return $extension === '' ? $base : "{$base}.{$extension}";
	}

	/**
	 * Says a size in bytes as people read it.
	 */
	public static function size(int $bytes): string
	{
		return $bytes >= 1024 ** 2 ? round($bytes / 1024 ** 2, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
	}

	/**
	 * A file the library has, by its key, or `null`.
	 */
	private function libraryFile(string $relative): ?MediaFile
	{
		$mime = MediaResolver::EXTENSIONS[strtolower(new SplFileInfo($relative)->getExtension())] ?? null;

		return $mime !== null && $this->config->allows($mime)
			? $this->resolver->resolve($this->config->url . '/' . UrlPath::encode($relative))
			: null;
	}

	/**
	 * Records a change in the activity log. A log that can't be written
	 * is logged; the change stands.
	 *
	 * @param ?array<string, mixed> $before
	 */
	private function log(Actor $actor, ActivityAction $action, string $id, string $title, string $summary, ?array $before = null): void
	{
		try {
			$this->activity->record($actor, $action, $id, $title, $summary, $before);
		} catch (ActivityException $e) {
			$this->logger->warning('A change to "{title}" couldn\'t be added to the activity log: {problem}', ['title' => $title, 'problem' => $e->getMessage()]);
		}
	}

	/**
	 * Names changed fields as people read them: "alt text and caption".
	 *
	 * @param list<string> $names
	 */
	private static function fieldNames(array $names): string
	{
		$names = array_map(static fn (string $name): string => match ($name) {
			'alt'   => 'alt text',
			default => str_replace('_', ' ', $name)
		}, $names);

		$last = array_pop($names);

		return $names === [] ? (string) $last : implode(', ', $names) . ' and ' . $last;
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
}
