<?php

/**
 * Log config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Log;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;

/**
 * Logging settings, from `config/log.php`.
 */
final readonly class LogConfig implements Config
{
	/**
	 * @param string $file The log file for the `File` driver, relative to `storage/logs` unless absolute.
	 * @throws InvalidConfig
	 */
	public function __construct(
		public LogDriver $driver = LogDriver::File,
		public Level $level = Level::Warning,
		public string $file = 'blush.log',
		public string $channel = 'blush'
	) {
		if ($file === '' || str_contains($file, "\0")) {
			throw new InvalidConfig('LogConfig "file" must be a file name or path.');
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['driver', 'level', 'file', 'channel']);

		return new static(
			driver: $values->enum('driver', LogDriver::class, LogDriver::File),
			level: $values->enum('level', Level::class, Level::Warning),
			file: $values->string('file', 'blush.log'),
			channel: $values->string('channel', 'blush')
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'driver'  => $this->driver,
			'level'   => $this->level,
			'file'    => $this->file,
			'channel' => $this->channel
		];
	}
}
