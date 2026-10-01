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
use Blush\Content\Schema\FieldFactory;
use Blush\Content\Schema\FieldRegistrar;
use Blush\Content\Schema\FieldRegistry;

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
 * - `fields` adds metadata fields, for every kind of file or one
 *   (`MediaFieldSet`, D-287), beside the built-in ones (`MediaSchemas`);
 *   a field with a built-in's name replaces it. In array form, a map of
 *   `all`, `image`, `video`, `audio`, and `file` to lists of field
 *   definitions.
 */
final readonly class MediaConfig implements Config
{
	/**
	 * The MIME types allowed by default: 1.x's, plus `text/vtt`.
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
		'text/vtt'
	];

	/**
	 * The URL path, with a leading slash and no trailing one.
	 */
	public string $url;

	/**
	 * @param  list<string>        $types  Allowed MIME types.
	 * @param  list<MediaFieldSet> $fields    Metadata fields beyond the built-in ones.
	 * @param  bool                $autoIndex Whether development requests refresh the media index.
	 * @throws InvalidConfig
	 */
	public function __construct(
		string $url = '/media',
		public array $types = self::DEFAULT_TYPES,
		public array $fields = [],
		public bool $autoIndex = true
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
		$values->assertKnownKeys(['url', 'types', 'fields', 'autoIndex']);

		$fields = $data['fields'] ?? [];

		if (! is_array($fields)) {
			throw new InvalidConfig('MediaConfig "fields" must be a map of kinds to field definitions.');
		}

		$registry = new FieldRegistry();
		new FieldRegistrar($registry)->register();

		return new static(
			url: $values->string('url', '/media'),
			types: $values->stringList('types', self::DEFAULT_TYPES),
			fields: MediaSchemas::setsFromArray($fields, new FieldFactory($registry), 'MediaConfig "fields"'),
			autoIndex: $values->bool('autoIndex', true)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		$fields = [];

		foreach ($this->fields as $set) {
			foreach ($set->fields as $field) {
				$fields[$set->kind->value ?? 'all'][] = $field->toArray();
			}
		}

		return ['url' => $this->url, 'types' => $this->types, 'fields' => $fields, 'autoIndex' => $this->autoIndex];
	}
}
