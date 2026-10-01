<?php

/**
 * Embedded reader factory.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Embedded;

use Throwable;
use Blush\Container\Container;
use Blush\Media\MediaException;

/**
 * Builds registered embedded metadata readers through the container, so
 * a reader's constructor can ask for services.
 */
final readonly class EmbeddedReaderFactory
{
	public function __construct(
		private EmbeddedReaderRegistry $registry,
		private Container $container
	) {}

	/**
	 * Builds a reader, or returns `null` when none is registered under the
	 * key.
	 *
	 * @throws MediaException When it can't be built.
	 */
	public function make(string $key): ?EmbeddedReader
	{
		$class = $this->registry->get($key);

		if ($class === null) {
			return null;
		}

		try {
			return $this->container->make($class);
		} catch (Throwable $error) {
			throw new MediaException(sprintf('Unable to build the "%s" embedded metadata reader: %s', $key, $error->getMessage()), 0, $error);
		}
	}
}
