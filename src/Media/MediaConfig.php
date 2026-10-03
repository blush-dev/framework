<?php

/**
 * Media config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;

/**
 * The site's media settings, from `config/media.php`:
 *
 *     return new MediaConfig(url: '/user/media');
 *
 * - `url` is the URL path `user/media` is served at, which `media:publish`
 *   links (or copies) into the public folder. jtcom's 1.x URLs use
 *   `/user/media`.
 * - `types` is the MIME allowlist: only these files are resolved, served,
 *   or published. The default is 1.x's images, audio, and video (D-078),
 *   plus WebVTT caption tracks for videos (D-179).
 * - `autoIndex` (the default) refreshes the media index incrementally on
 *   the first use in each development request (D-288); elsewhere,
 *   `media:index`, publishing, or the admin refreshes it.
 * - `uploads` is what the admin may upload, how large, and where it goes
 *   (`MediaUploads`, D-406); the Media settings screen saves it in
 *   `user/data/settings.json`.
 *
 * A file's metadata fields are the built-in ones (`MediaSchemas`) and
 * those of the field sets attached to its kind, `media:image` and so on
 * (D-341); they're not set here.
 */
final readonly class MediaConfig implements Config
{
	/**
	 * The MIME types allowed by default: 1.x's, plus `text/vtt` and PDFs
	 * (D-406). Other documents (`MediaKind::DOCUMENT_TYPES`) are known, and
	 * allowed once listed here.
	 *
	 * @var list<string>
	 */
	public const array DEFAULT_TYPES = [
		'image/apng',
		'image/avif',
		'image/gif',
		'image/jpeg',
		'image/png',
		'image/svg+xml',
		'image/webp',
		'audio/mpeg',
		'audio/wav',
		'audio/ogg',
		'video/mp4',
		'video/ogg',
		'video/webm',
		'text/vtt',
		'application/pdf'
	];

	/**
	 * The URL path, with a leading slash and no trailing one.
	 */
	public string $url;

	/**
	 * @param  list<string> $types     Allowed MIME types.
	 * @param  bool         $autoIndex Whether development requests refresh the media index.
	 * @param  MediaUploads $uploads   What may be uploaded, and where it goes.
	 * @throws InvalidConfig
	 */
	public function __construct(
		string $url = '/media',
		public array $types = self::DEFAULT_TYPES,
		public bool $autoIndex = true,
		public MediaUploads $uploads = new MediaUploads()
	) {
		$url = '/' . trim($url, '/');

		if ($url === '/' || preg_match('#^(/[A-Za-z0-9._~-]+)+$#', $url) !== 1 || str_contains($url, '/.')) {
			throw new InvalidConfig(sprintf('MediaConfig "url" must be a path below the site root, such as "/media"; "%s" given.', $url));
		}

		$this->url = $url;
	}

	/**
	 * Returns whether a MIME type is allowed.
	 */
	public function allows(string $mime): bool
	{
		return in_array(strtolower($mime), $this->types, true);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['url', 'types', 'autoIndex', 'uploads']);

		$uploads = $data['uploads'] ?? [];

		return new static(
			url: $values->string('url', '/media'),
			types: $values->stringList('types', self::DEFAULT_TYPES),
			autoIndex: $values->bool('autoIndex', true),
			uploads: match (true) {
				$uploads instanceof MediaUploads => $uploads,
				is_array($uploads)               => MediaUploads::fromArray($uploads),
				default                          => throw new InvalidConfig('MediaConfig "uploads" must be a MediaUploads.')
			}
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return ['url' => $this->url, 'types' => $this->types, 'autoIndex' => $this->autoIndex, 'uploads' => $this->uploads->toArray()];
	}
}
