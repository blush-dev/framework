<?php

/**
 * Mail service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mail;

use Override;
use Psr\Log\LoggerInterface;
use Blush\Container\ServiceResolver;
use Blush\Core\AppConfig;
use Blush\Core\ServiceProvider;

/**
 * Binds the transport in effect (`MailTransport`: `MailConfig`'s, else
 * PHP's own mail where the server has it, D-848) and the mailer for it
 * (D-832, D-846), unless a plugin for a mail service or a test
 * (`Testing\RecordingMailer`) binds its own.
 */
final class MailServiceProvider extends ServiceProvider
{
	/**
	 * Binds the mailer.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singletonIf(MailTransport::class, static fn (ServiceResolver $resolver): MailTransport => new MailTransport($resolver->make(MailConfig::class), MailTransport::hasNativeMail()));

		$this->container->singletonIf(Mailer::class, static function (ServiceResolver $resolver): Mailer {
			$config    = $resolver->make(MailConfig::class);
			$transport = $resolver->make(MailTransport::class);

			return match ($transport->scheme()) {
				null    => new UnconfiguredMailer(),
				'log'   => new LogMailer($config, $resolver->make(AppConfig::class), $resolver->make(LoggerInterface::class)),
				default => new SymfonyMailer($transport, $config, $resolver->make(AppConfig::class))
			};
		});
	}
}
