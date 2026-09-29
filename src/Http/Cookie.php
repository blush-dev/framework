<?php

/**
 * Cookie.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use NoDiscard;

/**
 * A cookie to set, as a `Set-Cookie` header (`Response::withCookie()`).
 * The defaults are the safe ones: `HttpOnly`, `Secure`, `SameSite=Strict`,
 * and the whole site's path. The value is URL-encoded, as PHP decodes
 * cookies. `$expires` is a Unix timestamp; `null` makes a session cookie
 * that lasts until the browser closes.
 *
 * A name starting `__Host-` must be `Secure`, on `/`, with no domain, which
 * browsers enforce; the constructor checks it too.
 */
final readonly class Cookie
{
	/**
	 * @throws InvalidArgumentException For an invalid name, domain, or path.
	 */
	public function __construct(
		public string $name,
		public string $value = '',
		public ?int $expires = null,
		public string $path = '/',
		public ?string $domain = null,
		public bool $secure = true,
		public bool $httpOnly = true,
		public SameSite $sameSite = SameSite::Strict
	) {
		if (preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/', $name) !== 1) {
			throw new InvalidArgumentException(sprintf('"%s" is not a valid cookie name.', $name));
		}

		if (preg_match('/[;,\s]/', $path . ($domain ?? '')) === 1) {
			throw new InvalidArgumentException('A cookie\'s path and domain can\'t hold ";", ",", or spaces.');
		}

		if (str_starts_with($name, '__Host-') && (! $secure || $path !== '/' || $domain !== null)) {
			throw new InvalidArgumentException(sprintf('The "%s" cookie must be Secure, on "/", with no domain.', $name));
		}

		if ($sameSite === SameSite::None && ! $secure) {
			throw new InvalidArgumentException(sprintf('The "%s" cookie must be Secure to use SameSite=None.', $name));
		}
	}

	/**
	 * Returns a copy with another value.
	 */
	#[NoDiscard]
	public function withValue(string $value): self
	{
		return clone($this, ['value' => $value]);
	}

	/**
	 * Returns a copy that tells the browser to delete the cookie.
	 */
	#[NoDiscard]
	public function expired(): self
	{
		return clone($this, ['value' => '', 'expires' => 0]);
	}

	/**
	 * Returns the `Set-Cookie` header's value.
	 */
	public function header(): string
	{
		$parts = [$this->name . '=' . rawurlencode($this->value)];

		if ($this->expires !== null) {
			$parts[] = 'Expires=' . DateTimeImmutable::createFromTimestamp($this->expires)
				->setTimezone(new DateTimeZone('UTC'))
				->format('D, d M Y H:i:s \G\M\T');
		}

		if ($this->expires === 0) {
			$parts[] = 'Max-Age=0';
		}

		$parts[] = "Path={$this->path}";

		if ($this->domain !== null) {
			$parts[] = "Domain={$this->domain}";
		}

		if ($this->secure) {
			$parts[] = 'Secure';
		}

		if ($this->httpOnly) {
			$parts[] = 'HttpOnly';
		}

		$parts[] = "SameSite={$this->sameSite->value}";

		return implode('; ', $parts);
	}
}
