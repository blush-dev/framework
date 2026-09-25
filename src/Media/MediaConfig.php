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
 *   or published. The default is 1.x's images, audio, and video (D-078).
 */
final readonly class MediaConfig implements Config
{
	/**
	 * The MIME types allowed by default, as in 1.x.
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
		'video/webm'
	];

	/**
	 * The URL path, with a leading slash and no trailing one.
	 */
	public string $url;

	/**
	 * @param  list<string> $types Allowed MIME types.
	 * @throws InvalidConfig
	 */
	public function __construct(
		string $url = '/media',
		public array $types = self::DEFAULT_TYPES
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
		$values->assertKnownKeys(['url', 'types']);

		return new static(
			url: $values->string('url', '/media'),
			types: $values->stringList('types', self::DEFAULT_TYPES)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return ['url' => $this->url, 'types' => $this->types];
	}
}
