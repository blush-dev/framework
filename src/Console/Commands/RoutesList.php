<?php

/**
 * Routes list command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Routing\CompiledRoute;
use Blush\Routing\Redirect;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteTable;

/**
 * Lists the routes the site serves, in precedence order, followed by its
 * redirects and any routes shadowed by a higher-priority one. Outside
 * development this is the cached table, which is what's being served.
 */
#[Command('routes:list', 'List the routes and redirects.')]
final readonly class RoutesList
{
	public function __construct(private RouteTable $routes)
	{}

	public function __invoke(Output $output): ExitCode
	{
		$routes = $this->routes->routes();

		if ($routes === []) {
			$output->comment('No routes are defined.');
		} else {
			$output->table(
				['Method', 'Path', 'Name', 'Handler', 'Source'],
				array_map(static fn (CompiledRoute $route): array => [
					implode('|', $route->methods),
					$route->path(),
					$route->name ?? '',
					$route->handlerName(),
					$route->priority->label()
				], $routes)
			);
		}

		$redirects = $this->routes->redirects();

		if ($redirects !== []) {
			$output->newLine();
			$output->table(
				['From', 'To', 'Status'],
				array_map(static fn (Redirect $redirect): array => [
					$redirect->from,
					$redirect->to,
					$redirect->status->value
				], $redirects)
			);
		}

		foreach ($this->routes->shadowed() as $shadowed) {
			$output->warning(sprintf(
				'%s %s (%s, from %s) is shadowed by %s.',
				$shadowed['method'],
				$shadowed['path'],
				$shadowed['handler'],
				RoutePriority::from($shadowed['priority'])->label(),
				$shadowed['by']
			));
		}

		return ExitCode::Success;
	}
}
