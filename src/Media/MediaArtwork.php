<?php

/**
 * Media artwork.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Throwable;
use Psr\Clock\ClockInterface;
use Blush\Core\Paths;
use Blush\Media\Embedded\Artwork;
use Blush\Media\Embedded\EmbeddedMetadataReader;
use Blush\Media\Index\MediaLibrary;
use Blush\Media\Index\MediaRecord;
use Blush\Support\Filesystem;
use Blush\Support\UrlPath;
use Blush\Support\Uuid;

/**
 * A sound's or video's artwork (D-581): an image in the media library,
 * named by its id in the file's metadata (`artwork`), so it has its own
 * alt text, title, and sizes, and can be used anywhere; else the picture
 * the file carries (D-551), read from the file each time and served at
 * `MediaConfig::artworkUrl()` (D-575). Files are never written to: the
 * library image is a link, not the file's own picture.
 *
 * `adopt()` adds the picture a file carries to the library as an image
 * of its own, beside the file (`song-artwork.jpg`), or finds the image
 * that already has the same bytes, so an album's tracks share one; then
 * links it. `link()` names any library image; `unlink()` takes it off,
 * leaving the image in the library, and `forget()` takes a deleted
 * image off every file that showed it.
 */
final readonly class MediaArtwork
{
	/**
	 * The file extension each picture type is written with.
	 *
	 * @var array<string, string>
	 */
	private const array EXTENSIONS = [
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/gif'  => 'gif',
		'image/webp' => 'webp'
	];

	public function __construct(
		private Paths $paths,
		private MediaConfig $config,
		private MediaResolver $resolver,
		private MediaLibrary $library,
		private MediaMetadataStore $metadata,
		private EmbeddedMetadataReader $embedded,
		private ClockInterface $clock
	) {}

	/**
	 * Returns whether files of a kind have artwork: sounds and videos.
	 */
	public static function has(MediaKind $kind): bool
	{
		return $kind === MediaKind::Audio || $kind === MediaKind::Video;
	}

	/**
	 * Returns the library's record for a media reference (a `src`), or
	 * `null` when it isn't in `user/media` or the library can't be read.
	 */
	public function record(string $reference): ?MediaRecord
	{
		$file = $reference === '' ? null : $this->resolver->resolve($reference);
		$root = $this->paths->media . '/';

		if ($file === null || ! str_starts_with($file->path, $root)) {
			return null;
		}

		try {
			return $this->library->find(substr($file->path, strlen($root)));
		} catch (MediaException) {
			return null;
		}
	}

	/**
	 * Returns the library image a file links as its artwork, or `null`
	 * for none, or one that's no longer an image in the library.
	 */
	public function image(MediaRecord $record): ?MediaRecord
	{
		$id = $record->metadata()->artwork;

		if ($id === '') {
			return null;
		}

		try {
			$image = $this->library->findId($id);
		} catch (MediaException) {
			return null;
		}

		return $image !== null && $image->kind() === MediaKind::Image ? $image : null;
	}

	/**
	 * Returns whether a file carries a picture of its own.
	 */
	public static function carries(MediaRecord $record): bool
	{
		return is_string($record->embedded?->values['artwork'] ?? null);
	}

	/**
	 * Returns a file's artwork URL path: its library image's, else the
	 * one the picture it carries is served at (versioned by when the
	 * file changed), else `''`.
	 */
	public function url(MediaRecord $record): string
	{
		$image = self::has($record->kind()) ? $this->image($record) : null;

		return match (true) {
			$image !== null            => $image->url,
			! self::has($record->kind()),
			! self::carries($record)   => '',
			default                    => $this->config->artworkUrl() . '/' . UrlPath::encode($record->key) . '?v=' . hash('crc32b', (string) $record->modified)
		};
	}

	/**
	 * Links a library image, by its id, as a file's artwork.
	 *
	 * @throws MediaException When the file can't have artwork, the id isn't an image in the library, or the metadata can't be written.
	 */
	public function link(MediaRecord $record, string $id): MediaRecord
	{
		if (! self::has($record->kind())) {
			throw new MediaException(sprintf('Only sounds and videos have artwork; %s is neither.', basename($record->key)));
		}

		$image = Uuid::isValid($id) ? $this->library->findId($id) : null;

		if ($image === null || $image->kind() !== MediaKind::Image || $image->isVariant()) {
			throw new MediaException('The artwork must be an image in the media library.');
		}

		$this->metadata->save($record->file($this->paths), [MediaMetadata::ARTWORK => (string) $image->id()]);
		$this->library->refresh([$record->key]);

		return $image;
	}

	/**
	 * Takes the artwork off a file, leaving the image in the library.
	 *
	 * @throws MediaException When the metadata can't be written.
	 */
	public function unlink(MediaRecord $record): void
	{
		$this->metadata->save($record->file($this->paths), [], [MediaMetadata::ARTWORK]);
		$this->library->refresh([$record->key]);
	}

	/**
	 * Takes an image, by its id, off every file that shows it as artwork,
	 * as deleting it does, returning their keys.
	 *
	 * @return list<string>
	 * @throws MediaException
	 */
	public function forget(string $id): array
	{
		$keys = [];

		foreach ($this->library->withArtwork($id) as $record) {
			$this->metadata->save($record->file($this->paths), [], [MediaMetadata::ARTWORK]);
			$keys[] = $record->key;
		}

		if ($keys !== []) {
			$this->library->refresh($keys);
		}

		return $keys;
	}

	/**
	 * Adds the picture a file carries to the library and links it as the
	 * file's artwork: the image that already has its bytes, else a new
	 * one beside the file (`song-artwork.jpg`, or `-2` and on when that's
	 * taken), titled "Artwork for" the file's title, uploaded by `$owner`.
	 * Returns the image.
	 *
	 * @throws MediaException When the file carries no picture, or one the library can't take, or it can't be written.
	 */
	public function adopt(MediaRecord $record, string $owner): MediaRecord
	{
		if (! self::has($record->kind())) {
			throw new MediaException(sprintf('Only sounds and videos have artwork; %s is neither.', basename($record->key)));
		}

		$picture = $this->picture($record) ?? throw new MediaException(sprintf('%s carries no artwork the library takes as an image.', basename($record->key)));
		$image   = $this->same($picture->bytes) ?? $this->write($record, $picture->bytes, self::EXTENSIONS[$picture->mime], $owner);

		$this->metadata->save($record->file($this->paths), [MediaMetadata::ARTWORK => (string) $image->id()]);
		$this->library->refresh([$record->key]);

		return $image;
	}

	/**
	 * Returns whether the picture a file carries can be added to the
	 * library (`adopt()`): it's an image of a type the site allows.
	 */
	public function canAdopt(MediaRecord $record): bool
	{
		return self::has($record->kind()) && self::carries($record) && $this->picture($record) !== null;
	}

	/**
	 * The picture a file carries, when it's an image the library takes:
	 * its bytes are the type they say, one the site allows.
	 */
	private function picture(MediaRecord $record): ?Artwork
	{
		$file    = $record->file($this->paths);
		$picture = $this->embedded->artwork($file->path, $file->mime);
		$size    = $picture === null ? false : @getimagesizefromstring($picture->bytes);

		return $picture !== null && $size !== false && $size['mime'] === $picture->mime && isset(self::EXTENSIONS[$picture->mime]) && $this->config->allows($picture->mime) ? $picture : null;
	}

	/**
	 * The library image with these bytes, if there's one with an id.
	 *
	 * @throws MediaException
	 */
	private function same(string $bytes): ?MediaRecord
	{
		$hash = hash('sha256', $bytes);

		foreach ($this->library->snapshot()->records as $record) {
			if ($record->original === null && $record->size === strlen($bytes) && $record->kind() === MediaKind::Image && $record->id() !== null) {
				$path = $this->paths->media . '/' . $record->key;

				if (is_file($path) && hash_file('sha256', $path) === $hash) {
					return $record;
				}
			}
		}

		return null;
	}

	/**
	 * Writes a picture into the library beside the file it came from,
	 * with its metadata, and returns its record.
	 *
	 * @throws MediaException
	 */
	private function write(MediaRecord $record, string $bytes, string $ext, string $owner): MediaRecord
	{
		$source = $this->paths->media . '/' . $record->key;
		$target = $this->metadata->freePath(dirname($source), pathinfo($source, PATHINFO_FILENAME) . "-artwork.{$ext}");

		try {
			new Filesystem()->writeAtomic($target, $bytes);
		} catch (Throwable $error) {
			throw new MediaException('The artwork couldn\'t be saved to the media folder.', previous: $error);
		}

		$key   = substr($target, strlen($this->paths->media) + 1);
		$image = $this->resolver->fromKey($key);

		if ($image === null) {
			@unlink($target);

			throw new MediaException('The artwork couldn\'t be added to the library.');
		}

		$this->metadata->save($image, array_filter([
			'title'               => sprintf('Artwork for %s', $this->title($record)),
			MediaMetadata::OWNER  => $owner,
			MediaMetadata::ID     => Uuid::v7($this->clock->now())
		], static fn (string $value): bool => $value !== ''));

		$this->library->refresh([$key]);

		return $this->library->find($key) ?? throw new MediaException('The artwork couldn\'t be added to the library.');
	}

	/**
	 * What a file is called: the library's title, else the one it
	 * carries, else its file name.
	 */
	private function title(MediaRecord $record): string
	{
		$carried = $record->embedded?->values['title'] ?? null;

		return match (true) {
			$record->metadata()->title !== '' => $record->metadata()->title,
			is_string($carried) && trim($carried) !== '' => MediaMetadata::line($carried),
			default => basename($record->key)
		};
	}
}
