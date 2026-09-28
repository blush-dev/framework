<?php

/**
 * Paths.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Core;

use InvalidArgumentException;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;

/**
 * Every well-known filesystem location of a site, as absolute paths without
 * trailing slashes. Built from the project root with the standard layout
 * (see `paths.md`); any location can be overridden, which is how the public
 * directory is relocated to a host's fixed `public_html` (D-046).
 *
 * Paths are never read from `user/` data, only from the developer's
 * bootstrap and config (D-039).
 */
final readonly class Paths
{
	/**
	 * The default location of each path, relative to the root.
	 */
	private const array DEFAULTS = [
		'config'     => 'config',
		'user'       => 'user',
		'content'    => 'user/content',
		'media'      => 'user/media',
		'data'       => 'user/data',
		'themes'     => 'user/themes',
		'extensions' => 'user/extensions',
		'public'     => 'public',
		'resources'  => 'resources',
		'storage'    => 'storage',
		'cache'      => 'storage/cache',
		'index'      => 'storage/index',
		'logs'       => 'storage/logs',
		'sessions'   => 'storage/sessions',
		'export'     => 'storage/export',
		'vendor'     => 'vendor'
	];

	public function __construct(
		public string $root,
		public string $config,
		public string $user,
		public string $content,
		public string $media,
		public string $data,
		public string $themes,
		public string $extensions,
		public string $public,
		public string $resources,
		public string $storage,
		public string $cache,
		public string $index,
		public string $logs,
		public string $sessions,
		public string $export,
		public string $vendor
	) {
		foreach ($this->toArray() as $name => $path) {
			if (! self::isAbsolute($path)) {
				throw new InvalidArgumentException(sprintf(
					'The "%s" path must be absolute; "%s" given.',
					$name,
					$path
				));
			}
		}
	}

	/**
	 * Builds the standard layout under a project root. `$overrides` maps a
	 * path name to a location; a relative location is resolved against
	 * the root.
	 *
	 * @param array<string, string> $overrides
	 */
	public static function fromRoot(string $root, array $overrides = []): self
	{
		return self::fromArray(['root' => $root, ...$overrides]);
	}

	/**
	 * Builds paths from an array with a required `root` and optional
	 * overrides for any other path name. Relative locations resolve
	 * against the root. Unknown names are rejected.
	 *
	 * @param array<string, string> $data
	 */
	public static function fromArray(array $data): self
	{
		$filesystem = new Filesystem();

		if (! isset($data['root'])) {
			throw new InvalidArgumentException('Paths require a "root".');
		}

		$root = rtrim($filesystem->normalize($data['root']), '/');
		unset($data['root']);

		$unknown = array_diff_key($data, self::DEFAULTS);

		if ($unknown !== []) {
			throw new InvalidArgumentException(sprintf(
				'Unknown path name(s): %s.',
				implode(', ', array_keys($unknown))
			));
		}

		$paths = ['root' => $root];

		foreach ([...self::DEFAULTS, ...$data] as $name => $location) {
			$paths[$name] = rtrim($filesystem->normalize(
				self::isAbsolute($location) ? $location : "{$root}/{$location}"
			), '/');
		}

		return new self(...$paths);
	}

	/**
	 * Returns the paths as an array keyed by name.
	 *
	 * @return array<string, string>
	 */
	public function toArray(): array
	{
		return [
			'root'       => $this->root,
			'config'     => $this->config,
			'user'       => $this->user,
			'content'    => $this->content,
			'media'      => $this->media,
			'data'       => $this->data,
			'themes'     => $this->themes,
			'extensions' => $this->extensions,
			'public'     => $this->public,
			'resources'  => $this->resources,
			'storage'    => $this->storage,
			'cache'      => $this->cache,
			'index'      => $this->index,
			'logs'       => $this->logs,
			'sessions'   => $this->sessions,
			'export'     => $this->export,
			'vendor'     => $this->vendor
		];
	}

	/**
	 * Whether a path is absolute (POSIX, or a Windows drive path).
	 */
	private static function isAbsolute(string $path): bool
	{
		return str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[/\\\\]#', $path) === 1;
	}

	/**
	 * Joins a relative path onto one of the base paths, confined to it.
	 * `$paths->join($paths->content, 'posts/hello.md')`.
	 *
	 * @throws FilesystemException When the result escapes the base.
	 */
	public function join(string $base, string $relative): string
	{
		return new Filesystem()->confine($base, $relative);
	}

	/**
	 * Returns a path relative to the project root, for display. Paths
	 * outside the root are returned unchanged.
	 */
	public function relative(string $path): string
	{
		return str_starts_with($path, $this->root . '/')
			? substr($path, strlen($this->root) + 1)
			: $path;
	}
}
