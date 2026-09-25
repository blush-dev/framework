<?php

/**
 * Config redirect source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing\Sources;

use Override;
use Blush\Routing\Redirect;
use Blush\Routing\RedirectSource;
use Blush\Routing\RouteConfig;

/**
 * The redirects listed in `config/routes.php`.
 */
final readonly class ConfigRedirects implements RedirectSource
{
	public function __construct(private RouteConfig $config)
	{}

	/**
	 * @inheritDoc
	 * @return list<Redirect>
	 */
	#[Override]
	public function redirects(): iterable
	{
		return $this->config->redirects;
	}
}
