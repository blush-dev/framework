<?php

/**
 * Log level.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Log;

use Psr\Log\LogLevel;

/**
 * The PSR-3 (RFC 5424) log levels, backed by their PSR-3 strings.
 */
enum Level: string
{
	case Emergency = LogLevel::EMERGENCY;
	case Alert     = LogLevel::ALERT;
	case Critical  = LogLevel::CRITICAL;
	case Error     = LogLevel::ERROR;
	case Warning   = LogLevel::WARNING;
	case Notice    = LogLevel::NOTICE;
	case Info      = LogLevel::INFO;
	case Debug     = LogLevel::DEBUG;

	/**
	 * The RFC 5424 severity: 0 (emergency) is the most severe, 7 (debug)
	 * the least.
	 */
	public function severity(): int
	{
		return match ($this) {
			self::Emergency => 0,
			self::Alert     => 1,
			self::Critical  => 2,
			self::Error     => 3,
			self::Warning   => 4,
			self::Notice    => 5,
			self::Info      => 6,
			self::Debug     => 7
		};
	}

	/**
	 * Whether a message at this level passes a minimum-level threshold.
	 */
	public function passes(self $threshold): bool
	{
		return $this->severity() <= $threshold->severity();
	}
}
