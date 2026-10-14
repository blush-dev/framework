<?php

/**
 * Mail config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mail;

use Override;
use Blush\Auth\Account;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;
use Blush\Core\AppConfig;
use Blush\Env\Env;

/**
 * Outgoing mail (D-832, D-846, D-848), from `config/mail.php`. When a
 * site has none, `fromEnv()` reads `MAIL_DSN`:
 *
 *     return new MailConfig(dsn: $env->get('MAIL_DSN'), fromName: 'The Daily');
 *
 * - `dsn`: the transport. `smtp://user:password@host:587` (what most
 *   sites should use: their host's mail server or a mail service),
 *   `native://default` (PHP's own `mail()`, through `sendmail_path`),
 *   `sendmail://default` (the server's `/usr/sbin/sendmail`), `log`
 *   (each message written to the site's log, for development), or
 *   `null` (sent nowhere). `native`, `sendmail`, `log`, and `null` alone
 *   are short for their full forms. Unset (`null`), the site falls back
 *   to PHP's own mail when PHP has a mail program (`MailTransport`);
 *   empty (`''`) turns mail off. Keep it in `.env`, since an SMTP one
 *   holds a password.
 * - `fromAddress` and `fromName`: who mail is from, saved on Settings →
 *   Email. Empty, the site's email address (`app.email`, D-723) and its
 *   name.
 */
final readonly class MailConfig implements Config
{
	/**
	 * The transport, in full; `null` when it isn't set (PHP's own mail,
	 * where there is one), or `''` when mail is turned off.
	 */
	public ?string $dsn;

	/**
	 * @throws InvalidConfig
	 */
	public function __construct(
		?string $dsn = null,
		public string $fromAddress = '',
		public string $fromName = ''
	) {
		$dsn = $dsn === null ? null : trim($dsn);
		$dsn = match ($dsn) {
			null       => null,
			''         => '',
			'native'   => 'native://default',
			'log'      => 'log://default',
			'null'     => 'null://null',
			'sendmail' => 'sendmail://default',
			default    => $dsn
		};

		if ($dsn !== null && $dsn !== '' && preg_match('#^[a-z][a-z0-9+.-]*://#', $dsn) !== 1) {
			throw new InvalidConfig('MailConfig "dsn" must be a transport such as "smtp://user:password@host:587", "native", "sendmail", "log", or "null", or empty for none.');
		}

		if ($fromAddress !== '' && ! Account::isValidEmail($fromAddress)) {
			throw new InvalidConfig(sprintf('MailConfig "fromAddress" must be an email address; "%s" given.', $fromAddress));
		}

		if (preg_match('/[\r\n]/', $fromName) === 1) {
			throw new InvalidConfig('MailConfig "fromName" must be one line.');
		}

		$this->dsn = $dsn;
	}

	/**
	 * Builds the config from `MAIL_DSN`: unset, PHP's own mail where
	 * there is one; empty, none.
	 *
	 * @throws InvalidConfig
	 */
	public static function fromEnv(Env $env): self
	{
		return new self(dsn: $env->get('MAIL_DSN'));
	}

	/**
	 * Returns who mail is from: the saved address and name, else the
	 * site's own.
	 *
	 * @throws MailException When there's no address to send from.
	 */
	public function sender(AppConfig $app): Address
	{
		$email = $this->fromAddress !== '' ? $this->fromAddress : $app->email;

		if ($email === '') {
			throw new MailException('Mail has no address to come from: set the site\'s email address on Settings → General, or a sender on Settings → Email.');
		}

		return new Address($email, $this->fromName !== '' ? $this->fromName : str_replace(["\r", "\n"], ' ', $app->name));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['dsn', 'fromAddress', 'fromName']);

		return new static(
			dsn: $values->nullableString('dsn'),
			fromAddress: $values->string('fromAddress', ''),
			fromName: $values->string('fromName', '')
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'dsn'         => $this->dsn,
			'fromAddress' => $this->fromAddress,
			'fromName'    => $this->fromName
		];
	}
}
