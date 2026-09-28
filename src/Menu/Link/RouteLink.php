<?php

/**
 * Route menu link.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu\Link;

use Override;
use Blush\Routing\UrlGenerationException;
use Blush\Routing\UrlGenerator;

/**
 * Links to a named route, with any `params` it takes:
 *
 * ```yaml
 * - route: feed
 *   label: Feed
 * ```
 *
 * Routes bring no label, so the item names itself.
 */
final class RouteLink extends MenuLink
{
	public function __construct(private readonly UrlGenerator $router)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function keys(): array
	{
		return ['params'];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function validate(mixed $value, array $item): ?string
	{
		$params = $item['params'] ?? [];

		if (! is_array($params) || ($params !== [] && array_is_list($params)) || ! array_all($params, static fn (mixed $param): bool => is_scalar($param))) {
			return 'has "params" that aren\'t a map of names to values.';
		}

		return parent::validate($value, $item);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function resolve(string $value, array $item, string $locale): LinkTarget
	{
		$name = trim($value);

		if (! $this->router->has($name)) {
			throw new UnresolvedLink(sprintf('No route "%s".', $name));
		}

		/** @var array<string, scalar> $params Checked by `validate()`. */
		$params = $item['params'] ?? [];

		try {
			return new LinkTarget($this->router->to($name, $params));
		} catch (UrlGenerationException $error) {
			throw new UnresolvedLink(sprintf('The "%s" route: %s', $name, $error->getMessage()), 0, $error);
		}
	}
}
