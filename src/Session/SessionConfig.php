<?php

/**
 * Session config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Session;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;
use Blush\Core\Framework;

/**
 * Session settings, from `config/session.php`. Sessions only exist on the
 * routes that start them (the admin's), never on public pages.
 *
 * - `cookie` names the session cookie. Over HTTPS it gets the `__Host-`
 *   prefix, which browsers only accept from the site itself.
 * - `idle` is how many seconds a session lasts without a request.
 * - `lifetime` is how many seconds it lasts at most, however busy.
 * - `secure` forces the cookie's `Secure` flag on or off; `null` sets it
 *   when the request came over HTTPS.
 */
final readonly class SessionConfig implements Config
{
	/**
	 * @throws InvalidConfig
	 */
	public function __construct(
		public string $cookie = Framework::BINARY . '_session',
		public int $idle = 7200,
		public int $lifetime = 43200,
		public ?bool $secure = null
	) {
		if (preg_match('/^[A-Za-z0-9_]+$/', $cookie) !== 1) {
			throw new InvalidConfig(sprintf('SessionConfig "cookie" must be letters, digits, and "_"; "%s" given.', $cookie));
		}

		if ($idle < 60 || $lifetime < $idle) {
			throw new InvalidConfig('SessionConfig "idle" must be at least 60 seconds, and "lifetime" at least "idle".');
		}
	}

	/**
	 * Returns the cookie's name, with the `__Host-` prefix when secure.
	 */
	public function cookieName(bool $secure): string
	{
		return $secure ? "__Host-{$this->cookie}" : $this->cookie;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['cookie', 'idle', 'lifetime', 'secure']);

		$secure = $data['secure'] ?? null;

		return new static(
			cookie: $values->string('cookie', Framework::BINARY . '_session'),
			idle: $values->int('idle', 7200),
			lifetime: $values->int('lifetime', 43200),
			secure: $secure === null ? null : $values->bool('secure', false)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'cookie'   => $this->cookie,
			'idle'     => $this->idle,
			'lifetime' => $this->lifetime,
			'secure'   => $this->secure
		];
	}
}
