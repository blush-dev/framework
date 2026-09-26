<?php

/**
 * APCu cache store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Cache;

use APCUIterator;
use Override;
use Psr\Clock\ClockInterface;
use Blush\Core\Paths;

/**
 * Keeps entries in APCu shared memory, prefixed by the site (a hash of
 * its root) and the namespace, so sites and namespaces sharing one APCu
 * pool stay apart. Needs the `apcu` extension, enabled; the CLI has its
 * own pool (unless `apc.enable_cli` is on), so a CLI `cache:clear`
 * doesn't reach the web server's entries. They're keyed by the content
 * version, which the CLI does change.
 */
final class ApcuStore extends Store
{
	private readonly string $prefix;

	/**
	 * @throws CacheException When APCu isn't available.
	 */
	public function __construct(string $namespace, ClockInterface $clock, Paths $paths)
	{
		parent::__construct($namespace, $clock);

		if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
			throw new CacheException('The "apcu" cache driver needs the APCu extension, enabled.');
		}

		$this->prefix = 'blush.' . hash('xxh32', $paths->root) . ".{$namespace}.";
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function read(string $key): ?Item
	{
		$data = apcu_fetch($this->prefix . $key, $success);

		return $success ? Item::fromArray($data) : null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function write(string $key, Item $item): bool
	{
		$ttl = $item->expires === 0 ? 0 : max(1, $item->expires - $this->now());

		return apcu_store($this->prefix . $key, $item->toArray(), $ttl) === true;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function remove(string $key): bool
	{
		apcu_delete($this->prefix . $key);

		return true;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function flush(): bool
	{
		return apcu_delete(new APCUIterator('/^' . preg_quote($this->prefix, '/') . '/', APC_ITER_KEY)) !== false;
	}
}
