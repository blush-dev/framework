<?php

/**
 * Media uploads.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use DateTimeInterface;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;

/**
 * What the admin may upload, and where it goes (D-406), as `MediaConfig`'s
 * `uploads`:
 *
 *     new MediaUploads(maxSize: 24, kinds: [
 *         'audio' => new MediaUploadRule(path: 'audio'),
 *         'file'  => new MediaUploadRule(enabled: false)
 *     ])
 *
 * - `enabled` turns every upload off; files already in `user/media` are
 *   still served.
 * - `maxSize` is the largest file in megabytes, `null` for whatever PHP
 *   accepts (`upload_max_filesize`, `post_max_size`), which is always the
 *   most.
 * - `path` is the folder under `user/media` a file goes in: a plain folder
 *   (`uploads`), or a pattern of the tokens `{year}`, `{month}`, `{day}`
 *   (by the site's clock and time zone), `{kind}` (`images`, `videos`,
 *   `audio`, `documents`, `files`). Empty puts files straight in
 *   `user/media`. Never `{ext}` (D-674): an image's renditions in other
 *   formats belong beside it.
 * - `kinds` are rules for a kind (`MediaUploadRule`, by `MediaKind`
 *   value): it may be turned off, or have its own largest file or path.
 *
 * Only the types `MediaConfig` allows can be uploaded at all; these rules
 * narrow them, and never change what's served. SVG is never uploaded
 * (`REFUSED`, D-497): it can carry script, and icons come from icon packs. A file keeps the address
 * it was uploaded at when the path changes.
 */
final readonly class MediaUploads
{
	/**
	 * Types no upload may be, whatever the site allows (D-497): SVG, and,
	 * so a type added to `MediaResolver::EXTENSIONS` can't let them in,
	 * the markup a browser may run script in, scripts, and Office files
	 * with macros (D-499). Files already in `user/media` are still served.
	 *
	 * @var list<string>
	 */
	public const array REFUSED = [
		'image/svg+xml',
		'text/html',
		'application/xhtml+xml',
		'text/xml',
		'application/xml',
		'text/xsl',
		'application/xslt+xml',
		'text/javascript',
		'application/javascript',
		'application/x-javascript',
		'application/x-httpd-php',
		'application/x-php',
		'text/x-php',
		'application/vnd.ms-word.document.macroenabled.12',
		'application/vnd.ms-excel.sheet.macroenabled.12',
		'application/vnd.ms-powerpoint.presentation.macroenabled.12'
	];

	/**
	 * Whether a type is one no upload may be, `REFUSED` (D-499).
	 */
	public static function refuses(string $mime): bool
	{
		return in_array(strtolower($mime), self::REFUSED, true);
	}

	/**
	 * The default path: a folder for each year and month, as 1.x had.
	 */
	public const string DEFAULT_PATH = '{year}/{month}';

	/**
	 * The tokens a path may hold.
	 *
	 * @var list<string>
	 */
	public const array TOKENS = ['year', 'month', 'day', 'kind'];

	/**
	 * The largest size that can be set, in megabytes.
	 */
	public const int MAX_SIZE = 100_000;

	/**
	 * The path, without slashes at its ends.
	 */
	public string $path;

	/**
	 * Rules by kind, only for kinds that have some, in `MediaKind`'s order.
	 *
	 * @var array<string, MediaUploadRule>
	 */
	public array $kinds;

	/**
	 * @param  array<array-key, mixed> $kinds Rules (`MediaUploadRule`, or their arrays) by `MediaKind` value.
	 * @throws InvalidConfig
	 */
	public function __construct(
		public bool $enabled = true,
		public ?int $maxSize = null,
		string $path = self::DEFAULT_PATH,
		array $kinds = []
	) {
		self::assertSize($maxSize);

		$this->path = trim($path, '/ ') === '' ? '' : self::checkPath($path);

		$rules = [];

		foreach ($kinds as $kind => $rule) {
			MediaKind::tryFrom((string) $kind) ?? throw new InvalidConfig(sprintf('MediaUploads "kinds" are by kind: %s; "%s" isn\'t one.', implode(', ', array_column(MediaKind::cases(), 'value')), $kind));

			$rule = match (true) {
				$rule instanceof MediaUploadRule => $rule,
				is_array($rule)                  => MediaUploadRule::fromArray($rule),
				default                          => throw new InvalidConfig(sprintf('MediaUploads "kinds.%s" must be a MediaUploadRule.', $kind))
			};

			$rules[(string) $kind] = $rule;
		}

		$ordered = [];

		foreach (MediaKind::cases() as $kind) {
			if (isset($rules[$kind->value]) && ! $rules[$kind->value]->isDefault()) {
				$ordered[$kind->value] = $rules[$kind->value];
			}
		}

		$this->kinds = $ordered;
	}

	/**
	 * A kind's own rules, or the rules of a kind with none.
	 */
	public function rule(MediaKind $kind): MediaUploadRule
	{
		return $this->kinds[$kind->value] ?? new MediaUploadRule();
	}

	/**
	 * Whether files of a kind may be uploaded.
	 */
	public function allows(MediaKind $kind): bool
	{
		return $this->enabled && $this->rule($kind)->enabled;
	}

	/**
	 * The largest file of a kind, in bytes, or `null` for PHP's limit.
	 */
	public function maxBytes(MediaKind $kind): ?int
	{
		$size = $this->rule($kind)->maxSize ?? $this->maxSize;

		return $size === null ? null : $size * 1024 * 1024;
	}

	/**
	 * The path pattern a kind uses.
	 */
	public function pattern(MediaKind $kind): string
	{
		return $this->rule($kind)->path ?? $this->path;
	}

	/**
	 * The folder under `user/media` a file goes in, its tokens filled in:
	 * `2026/10`, or empty for `user/media` itself.
	 */
	public function folder(MediaKind $kind, DateTimeInterface $now): string
	{
		return strtr($this->pattern($kind), [
			'{year}'  => $now->format('Y'),
			'{month}' => $now->format('m'),
			'{day}'   => $now->format('d'),
			'{kind}'  => $kind->folder()
		]);
	}

	/**
	 * Checks a path and returns it without slashes at its ends.
	 *
	 * @throws InvalidConfig
	 */
	public static function checkPath(string $path): string
	{
		$path   = trim($path, '/ ');
		$tokens = implode('|', self::TOKENS);

		if (str_contains($path, '{ext}')) {
			throw new InvalidConfig(sprintf('An upload path can\'t use {ext}: an image\'s renditions in other formats belong in its folder, beside it; "%s" sorts them by extension.', $path));
		}

		foreach (explode('/', $path) as $segment) {
			if (preg_match('/^(?:[A-Za-z0-9_-]|\.(?!\.)|\{(?:' . $tokens . ')\})+$/', $segment) !== 1 || str_starts_with($segment, '.')) {
				throw new InvalidConfig(sprintf(
					'An upload path is folder names of letters, digits, dots, hyphens, and underscores, and the tokens {%s}, such as "uploads" or "{year}/{month}"; "%s" isn\'t one.',
					implode('}, {', self::TOKENS),
					$path
				));
			}
		}

		return mb_strlen($path) > 200 ? throw new InvalidConfig('An upload path can be at most 200 characters.') : $path;
	}

	/**
	 * @throws InvalidConfig
	 */
	public static function assertSize(?int $size): void
	{
		if ($size !== null && ($size < 1 || $size > self::MAX_SIZE)) {
			throw new InvalidConfig(sprintf('The largest file is from 1 to %s MB, or none for the server\'s limit.', number_format(self::MAX_SIZE)));
		}
	}

	/**
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidConfig
	 */
	public static function fromArray(array $data): self
	{
		$values = new ConfigValues($data, 'MediaUploads');
		$values->assertKnownKeys(['enabled', 'maxSize', 'path', 'kinds']);

		$size  = $data['maxSize'] ?? null;
		$kinds = $data['kinds'] ?? [];

		return new self(
			enabled: $values->bool('enabled', true),
			maxSize: $size === null || is_int($size) ? $size : throw new InvalidConfig('MediaUploads "maxSize" must be a whole number of megabytes, or null.'),
			path: $values->string('path', self::DEFAULT_PATH),
			kinds: is_array($kinds) ? $kinds : throw new InvalidConfig('MediaUploads "kinds" must be rules by kind.')
		);
	}

	/**
	 * @return array{enabled: bool, maxSize: ?int, path: string, kinds: array<string, array{enabled: bool, maxSize: ?int, path: ?string}>}
	 */
	public function toArray(): array
	{
		return [
			'enabled' => $this->enabled,
			'maxSize' => $this->maxSize,
			'path'    => $this->path,
			'kinds'   => array_map(static fn (MediaUploadRule $rule): array => $rule->toArray(), $this->kinds)
		];
	}
}
