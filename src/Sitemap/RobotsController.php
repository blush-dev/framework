<?php

/**
 * Robots controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Sitemap;

use Psr\Http\Message\ResponseInterface;
use Blush\Core\AppConfig;
use Blush\Http\Response;

/**
 * Serves `robots.txt`: `SitemapConfig::$robots` as written, or else one
 * that allows everything but the configured `disallow` paths, asks the
 * AI crawlers of each `blockAi` group to stay away (D-398), and points at
 * the sitemap. Outside production it disallows everything. A real
 * `public/robots.txt` file is served by the web server instead.
 */
final readonly class RobotsController
{
	public function __construct(
		private SitemapConfig $config,
		private AppConfig $app
	) {}

	public function __invoke(): ResponseInterface
	{
		if ($this->config->robots !== null) {
			return Response::text($this->config->robots);
		}

		if (! $this->app->environment->isProduction()) {
			return Response::text("User-agent: *\nDisallow: /\n");
		}

		$lines = ['User-agent: *'];

		foreach ($this->config->disallow === [] ? [''] : $this->config->disallow as $path) {
			$lines[] = rtrim("Disallow: {$path}");
		}

		foreach (AiCrawlerGroup::cases() as $group) {
			if (in_array($group, $this->config->blockAi, true)) {
				$lines[] = '';
				$lines[] = "# {$group->label()}";

				foreach ($group->agents() as $agent) {
					$lines[] = "User-agent: {$agent}";
				}

				$lines[] = 'Disallow: /';
			}
		}

		if ($this->config->enabled) {
			$lines[] = '';
			$lines[] = 'Sitemap: ' . $this->app->absoluteUrl('/sitemap');
		}

		return Response::text(implode("\n", $lines) . "\n");
	}
}
