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

use Blush\Asset\AssetCollector;
use Blush\Asset\Assets;
use Blush\Cache\ContentCache;
use Blush\Content\Entries;
use Blush\Content\Relation\EntryRelations;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
use Blush\Menu\Menus;
use Blush\Region\Regions;
use Blush\Routing\UrlGenerator;
use Blush\Settings\SiteSettings;
use Blush\View\Engine\ViewEngines;
use Blush\Component\ComponentRegistry;
use Blush\Directive\DirectiveRegistry;
use Blush\Directive\DirectiveVariants;

/**
 * The services every `Views` shares, whatever its theme chain: what
 * templates reach through `Template` (URLs, content, routes, the app
 * config, menus, regions, the site settings field sets add, links
 * between entries), context
 * providers, directives and components (D-532), the view engines
 * (D-502), and assets: what prints them into a page's head, and what
 * collects the handles rendering asks for (D-570, D-572).
 */
final readonly class ViewServices
{
	public function __construct(
		public ContentUrls $urls,
		public Entries $content,
		public ContentTypes $types,
		public UrlGenerator $router,
		public AppConfig $app,
		public ContextProviders $providers,
		public DirectiveRegistry $directives,
		public ComponentRegistry $components,
		public RenderableFactory $factory,
		public DirectiveVariants $variants,
		public Menus $menus,
		public Regions $regions,
		public SiteSettings $site,
		public ViewEngines $engines,
		public Assets $assets,
		public AssetCollector $collector,
		public EntryRelations $relations,
		public ?ContentCache $cache = null
	) {}
}
