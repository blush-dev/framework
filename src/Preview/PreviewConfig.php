<?php

/**
 * Preview config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Preview;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;
use Blush\Core\Framework;
use Blush\Env\Env;

/**
 * Preview link settings, from `config/preview.php` (D-226). When a site
 * has none, `fromEnv()` reads `APP_SECRET`.
 *
 * - `secret` signs preview links. Preview links exist only when it's set,
 *   and it must be at least 32 characters. Changing it ends every link
 *   given out. Keep it in `.env`; `init` writes one.
 * - `lifetime` is how many seconds a link works, a week by default.
 * - `path` is the preview URL's path.
 */
final readonly class PreviewConfig implements Config
{
	/**
	 * The shortest secret accepted.
	 */
	public const int MIN_SECRET = 32;

	/**
	 * The preview path.
	 */
	public string $path;

	/**
	 * @throws InvalidConfig
	 */
	public function __construct(
		public ?string $secret = null,
		public int $lifetime = 604800,
		string $path = '/_' . Framework::BINARY . '/preview'
	) {
		if ($secret !== null && strlen($secret) < self::MIN_SECRET) {
			throw new InvalidConfig(sprintf('PreviewConfig "secret" must be at least %d characters.', self::MIN_SECRET));
		}

		if ($lifetime < 60) {
			throw new InvalidConfig(sprintf('PreviewConfig "lifetime" must be at least 60 seconds; %d given.', $lifetime));
		}

		$path = '/' . trim($path, '/');

		if (preg_match('#^(/[A-Za-z0-9._~-]+)+$#', $path) !== 1 || str_contains($path, '/.')) {
			throw new InvalidConfig(sprintf('PreviewConfig "path" must be a URL path, such as "/_blush/preview"; "%s" given.', $path));
		}

		$this->path = $path;
	}

	/**
	 * Builds the config from `APP_SECRET`. An empty secret means none.
	 *
	 * @throws InvalidConfig
	 */
	public static function fromEnv(Env $env): self
	{
		$secret = $env->get('APP_SECRET');

		return new self(secret: $secret === '' ? null : $secret);
	}

	/**
	 * Whether preview links are on.
	 */
	public function isEnabled(): bool
	{
		return $this->secret !== null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['secret', 'lifetime', 'path']);

		return new static(
			secret: $values->nullableString('secret'),
			lifetime: $values->int('lifetime', 604800),
			path: $values->string('path', '/_' . Framework::BINARY . '/preview')
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return ['secret' => $this->secret, 'lifetime' => $this->lifetime, 'path' => $this->path];
	}
}
