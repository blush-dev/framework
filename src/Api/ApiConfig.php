<?php

/**
 * API config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Api;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;

/**
 * The site's API (D-736, D-741), from `config/api.php`:
 *
 *     return new ApiConfig(enabled: true, path: '/data');
 *
 * - `enabled` serves the API; off by default, and off, every address
 *   under it is the site's own 404, so a site doesn't show it has one.
 * - `path` is where it's served, the version added after it
 *   (`/api` serves `/api/v1`).
 * - `maxAge` is how long, in seconds, browsers and shared caches may
 *   keep an answer.
 * - `access` is who may read it (D-745): `ApiAccess::Anyone`, or
 *   `ApiAccess::Token` for requests with an API token only.
 * - `maxWrites` is how many changes a token may make through it in an
 *   hour (D-762).
 */
final readonly class ApiConfig implements Config
{
	/**
	 * The version every address carries (D-741).
	 */
	public const string VERSION = 'v1';

	/**
	 * @throws InvalidConfig
	 */
	public function __construct(
		public bool $enabled = false,
		public string $path = '/api',
		public int $maxAge = 60,
		public ApiAccess $access = ApiAccess::Anyone,
		public int $maxWrites = 600
	) {
		if (preg_match('#^(?:/[a-z0-9][a-z0-9._-]*)+$#', $path) !== 1) {
			throw new InvalidConfig(sprintf('ApiConfig "path" must be a path of lowercase letters, digits, dots, dashes, and underscores, such as "/api", without a trailing slash; "%s" given.', $path));
		}

		if ($maxAge < 0) {
			throw new InvalidConfig('ApiConfig "maxAge" must be 0 or more.');
		}

		if ($maxWrites < 1) {
			throw new InvalidConfig('ApiConfig "maxWrites" must be 1 or more.');
		}
	}

	/**
	 * Returns the path the version's addresses start with: `/api/v1`.
	 */
	public function base(): string
	{
		return $this->path . '/' . self::VERSION;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['enabled', 'path', 'maxAge', 'access', 'maxWrites']);

		return new static(
			enabled: $values->bool('enabled', false),
			path: $values->string('path', '/api'),
			maxAge: $values->int('maxAge', 60),
			access: $values->enum('access', ApiAccess::class, ApiAccess::Anyone),
			maxWrites: $values->int('maxWrites', 600)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return ['enabled' => $this->enabled, 'path' => $this->path, 'maxAge' => $this->maxAge, 'access' => $this->access->value, 'maxWrites' => $this->maxWrites];
	}
}
