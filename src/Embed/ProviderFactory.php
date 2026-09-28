<?php

/**
 * Provider factory.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed;

use Throwable;
use Blush\Container\Container;

/**
 * Builds registered embed providers through the container, so a
 * provider's constructor can ask for services.
 */
final readonly class ProviderFactory
{
	public function __construct(
		private ProviderRegistry $registry,
		private Container $container
	) {}

	/**
	 * Builds a provider, or returns `null` when none is registered under
	 * the name.
	 *
	 * @throws EmbedException When it can't be built.
	 */
	public function make(string $name): ?EmbedProvider
	{
		$class = $this->registry->get($name);

		if ($class === null) {
			return null;
		}

		try {
			return $this->container->make($class);
		} catch (Throwable $error) {
			throw new EmbedException(sprintf('Unable to build the "%s" embed provider: %s', $name, $error->getMessage()), 0, $error);
		}
	}
}
