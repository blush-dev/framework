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
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
use Blush\Menu\Menus;
use Blush\Region\Regions;
use Blush\Routing\UrlGenerator;
use Blush\Component\ComponentFactory;
use Blush\Component\ComponentRegistry;
use Blush\Component\ComponentVariants;

/**
 * The services every `Views` shares, whatever its theme chain: what
 * templates reach through `Template` (URLs, content, routes, the app
 * config, menus, regions), context providers, and components.
 */
final readonly class ViewServices
{
	public function __construct(
		public ContentUrls $urls,
		public ContentRepository $content,
		public ContentTypes $types,
		public UrlGenerator $router,
		public AppConfig $app,
		public ContextProviders $providers,
		public ComponentRegistry $components,
		public ComponentFactory $factory,
		public ComponentVariants $variants,
		public Menus $menus,
		public Regions $regions,
		public ?ContentCache $cache = null
	) {}
}
