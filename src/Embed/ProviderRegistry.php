<?php

/**
 * Provider registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed;

use Blush\Support\Registry;

/**
 * Maps embed provider names to classes. An extension or site adds one
 * that needs code in a provider's `boot()` (a plain one can go in
 * `config/embed.php` instead):
 *
 *     $this->container->make(ProviderRegistry::class)->register('peertube', PeerTube::class);
 *
 * @extends Registry<EmbedProvider>
 */
final class ProviderRegistry extends Registry
{
	/**
	 * @inheritDoc
	 */
	protected const string CONTRACT = EmbedProvider::class;
}
