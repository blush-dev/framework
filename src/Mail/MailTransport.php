<?php

/**
 * Mail transport.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mail;

use Uri\Rfc3986\Uri;

/**
 * The transport the site's mail goes out through (D-848): the one
 * `MailConfig::$dsn` names, else PHP's own mail (`native://default`)
 * when PHP has a mail program (`sendmail_path`), as most hosts and DDEV
 * set up, so a site sends mail with no setup. An empty DSN turns mail
 * off. `MailServiceProvider` reads the server's PHP settings; a test
 * says whether it has one.
 */
final readonly class MailTransport
{
	/**
	 * PHP's own mail, the fallback.
	 */
	public const string NATIVE = 'native://default';

	public function __construct(
		private MailConfig $config,
		private bool $native
	) {}

	/**
	 * Whether PHP on this server has a mail program for `mail()`.
	 */
	public static function hasNativeMail(): bool
	{
		$path = ini_get('sendmail_path');

		return function_exists('mail') && is_string($path) && trim($path) !== '';
	}

	/**
	 * Returns the DSN mail goes out through, or `null` for none.
	 */
	public function dsn(): ?string
	{
		return match (true) {
			$this->config->dsn === '' => null,
			$this->config->dsn !== null => $this->config->dsn,
			$this->native              => self::NATIVE,
			default                    => null
		};
	}

	/**
	 * Whether the site can send mail.
	 */
	public function isSetUp(): bool
	{
		return $this->dsn() !== null;
	}

	/**
	 * Whether mail falls back to PHP's own, since no transport is set.
	 */
	public function isFallback(): bool
	{
		return $this->config->dsn === null && $this->native;
	}

	/**
	 * Whether mail is turned off on purpose (an empty DSN).
	 */
	public function isOff(): bool
	{
		return $this->config->dsn === '';
	}

	/**
	 * Returns the transport's scheme (`smtp`, `native`, `sendmail`,
	 * `log`, `null`), or `null` when there's none.
	 */
	public function scheme(): ?string
	{
		$dsn = $this->dsn();

		return $dsn === null ? null : (strstr($dsn, '://', true) ?: null);
	}

	/**
	 * Returns the transport as it's safe to show: without its password
	 * or any query, such as `smtp://jane@mail.example.com:587`.
	 */
	public function describe(): ?string
	{
		$dsn = $this->dsn();

		if ($dsn === null) {
			return null;
		}

		$uri    = Uri::parse($dsn);
		$scheme = $this->scheme() ?? '';

		if ($uri === null) {
			return "{$scheme}://";
		}

		$user = $uri->getUsername();
		$host = $uri->getHost() ?? '';
		$port = $uri->getPort();

		return $scheme . '://' . ($user === null || $user === '' ? '' : rawurldecode($user) . '@') . $host . ($port === null ? '' : ":{$port}");
	}
}
