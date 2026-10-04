<?php

/**
 * Publish config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Publish;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;
use Blush\Core\Framework;
use Blush\Env\Env;

/**
 * Publishing settings, from `config/publish.php`. When a site has none,
 * `fromEnv()` reads `PUBLISH_SECRET`, `PUBLISH_GIT`, `PUBLISH_REMOTE`, and
 * `PUBLISH_BRANCH`:
 *
 *     return new PublishConfig(secret: $env->get('PUBLISH_SECRET'), git: true);
 *
 * - `secret` signs webhook requests. The webhook route exists only when
 *   it's set, and it must be at least 32 characters. Keep it in `.env`.
 * - `git` runs `git pull --ff-only` in `user/` on every publish (hosts
 *   with git only), from `remote` and `branch` when given.
 * - `path` is the webhook's URL path.
 * - `tolerance` is how many seconds a signed request stays valid.
 * - `maxAttempts` unsigned or badly signed requests from one address
 *   within `lockout` seconds lock it out of the webhook
 *   (`WebhookThrottle`).
 */
final readonly class PublishConfig implements Config
{
	/**
	 * The shortest secret accepted.
	 */
	public const int MIN_SECRET = 32;

	/**
	 * The webhook path.
	 */
	public string $path;

	/**
	 * @throws InvalidConfig
	 */
	public function __construct(
		public ?string $secret = null,
		public bool $git = false,
		public ?string $remote = null,
		public ?string $branch = null,
		string $path = '/_' . Framework::BINARY . '/publish',
		public int $tolerance = 300,
		public string $gitBinary = 'git',
		public int $maxAttempts = 10,
		public int $lockout = 900
	) {
		if ($secret !== null && strlen($secret) < self::MIN_SECRET) {
			throw new InvalidConfig(sprintf('PublishConfig "secret" must be at least %d characters.', self::MIN_SECRET));
		}

		foreach (['remote' => $remote, 'branch' => $branch] as $name => $value) {
			if ($value !== null && preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]*$#', $value) !== 1) {
				throw new InvalidConfig(sprintf('PublishConfig "%s" must be a plain git name; "%s" given.', $name, $value));
			}
		}

		$path = '/' . trim($path, '/');

		if (preg_match('#^(/[A-Za-z0-9._~-]+)+$#', $path) !== 1 || str_contains($path, '/.')) {
			throw new InvalidConfig(sprintf('PublishConfig "path" must be a URL path, such as "/_blush/publish"; "%s" given.', $path));
		}

		if ($tolerance < 1) {
			throw new InvalidConfig(sprintf('PublishConfig "tolerance" must be at least 1 second; %d given.', $tolerance));
		}

		if ($maxAttempts < 1 || $lockout < 1) {
			throw new InvalidConfig('PublishConfig "maxAttempts" and "lockout" must be at least 1.');
		}

		$this->path = $path;
	}

	/**
	 * Builds the config from the `PUBLISH_*` variables. An empty secret
	 * means none.
	 *
	 * @throws InvalidConfig
	 */
	public static function fromEnv(Env $env): self
	{
		$secret = $env->get('PUBLISH_SECRET');

		return new self(
			secret: $secret === '' ? null : $secret,
			git: $env->bool('PUBLISH_GIT', false),
			remote: $env->get('PUBLISH_REMOTE') ?: null,
			branch: $env->get('PUBLISH_BRANCH') ?: null
		);
	}

	/**
	 * Returns whether the webhook is on.
	 */
	public function hasWebhook(): bool
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
		$values->assertKnownKeys(['secret', 'git', 'remote', 'branch', 'path', 'tolerance', 'gitBinary', 'maxAttempts', 'lockout']);

		return new static(
			secret: $values->nullableString('secret'),
			git: $values->bool('git', false),
			remote: $values->nullableString('remote'),
			branch: $values->nullableString('branch'),
			path: $values->string('path', '/_' . Framework::BINARY . '/publish'),
			tolerance: $values->int('tolerance', 300),
			gitBinary: $values->string('gitBinary', 'git'),
			maxAttempts: $values->int('maxAttempts', 10),
			lockout: $values->int('lockout', 900)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'secret'      => $this->secret,
			'git'         => $this->git,
			'remote'      => $this->remote,
			'branch'      => $this->branch,
			'path'        => $this->path,
			'tolerance'   => $this->tolerance,
			'gitBinary'   => $this->gitBinary,
			'maxAttempts' => $this->maxAttempts,
			'lockout'     => $this->lockout
		];
	}
}
