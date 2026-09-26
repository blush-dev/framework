<?php

/**
 * View services.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use Blush\Cache\ContentCache;
use Blush\Content\ContentRepository;
use Blush\Content\Routing\ContentUrls;
use Blush\Core\AppConfig;
use Blush\Routing\UrlGenerator;
use Blush\View\Component\ComponentFactory;
use Blush\View\Component\ComponentRegistry;

/**
 * The services every `Views` shares, whatever its theme chain: what
 * templates reach through `Template` (URLs, content, routes, the app
 * config), context providers, and components.
 */
final readonly class ViewServices
{
	public function __construct(
		public ContentUrls $urls,
		public ContentRepository $content,
		public UrlGenerator $router,
		public AppConfig $app,
		public ContextProviders $providers,
		public ComponentRegistry $components,
		public ComponentFactory $factory,
		public ?ContentCache $cache = null
	) {}
}
