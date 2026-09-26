<?php

/**
 * Host files factory.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export\Host;

use Throwable;
use Blush\Container\Container;
use Blush\Export\ExportException;

/**
 * Builds a registered host format through the container, so a format's
 * constructor can ask for services.
 */
final readonly class HostFilesFactory
{
	public function __construct(
		private HostFilesRegistry $registry,
		private Container $container
	) {}

	/**
	 * Builds a format.
	 *
	 * @throws ExportException When the format is unknown or can't be built.
	 */
	public function make(string $name): HostFiles
	{
		$class = $this->registry->get($name);

		if ($class === null) {
			throw new ExportException(sprintf(
				'Unknown host format "%s" in ExportConfig "hosts"; registered formats: %s.',
				$name,
				implode(', ', array_keys($this->registry->all()))
			));
		}

		try {
			return $this->container->make($class);
		} catch (Throwable $error) {
			throw new ExportException(sprintf('Unable to build the "%s" host format: %s', $name, $error->getMessage()), 0, $error);
		}
	}
}
