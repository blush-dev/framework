<?php

/**
 * Admin config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;

/**
 * Admin settings, from `config/admin.php` (D-013, D-219):
 *
 *     return new AdminConfig(enabled: true);
 *
 * - `enabled` turns the admin on. It's off by default, and while it's
 *   off, none of its routes exist.
 * - `path` is where it lives; its JSON API is under `{path}/api`.
 * - `app` is the folder of a built admin front end (with Vite's
 *   `.vite/manifest.json`) to serve in place of the bundled one (D-222).
 */
final readonly class AdminConfig implements Config
{
	/**
	 * The admin's path.
	 */
	public string $path;

	/**
	 * @throws InvalidConfig
	 */
	public function __construct(
		public bool $enabled = false,
		string $path = '/admin',
		public ?string $app = null
	) {
		if ($app !== null && ! str_starts_with($app, '/') && preg_match('#^[A-Za-z]:[/\\\\]#', $app) !== 1) {
			throw new InvalidConfig(sprintf('AdminConfig "app" must be an absolute path; "%s" given.', $app));
		}

		$path = '/' . trim($path, '/');

		if (preg_match('#^(/[A-Za-z0-9._~-]+)+$#', $path) !== 1 || str_contains($path, '/.')) {
			throw new InvalidConfig(sprintf('AdminConfig "path" must be a URL path, such as "/admin"; "%s" given.', $path));
		}

		$this->path = $path;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['enabled', 'path', 'app']);

		return new static(
			enabled: $values->bool('enabled', false),
			path: $values->string('path', '/admin'),
			app: $values->nullableString('app')
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return ['enabled' => $this->enabled, 'path' => $this->path, 'app' => $this->app];
	}
}
