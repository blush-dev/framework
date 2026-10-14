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
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Links to a named route, with any `params` it takes:
 *
 * ```json
 * {"route": "feed", "label": "Feed"}
 * ```
 *
 * Routes bring no label, so the item names itself.
 */
final class RouteLink extends MenuLink
{
	public function __construct(
		private readonly UrlGenerator $router,
		Messages $messages
	) {
		parent::__construct($messages);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function itemSchema(string $key, array $text): array
	{
		return [
			$key     => [
				'type'        => 'string',
				'minLength'   => 1,
				'description' => 'Links to a named route, such as home.feed (bin/blush routes:list lists them). Needs a label.'
			],
			'params' => [
				'type'                 => 'object',
				'description'          => 'The route\'s parameters, by name.',
				'additionalProperties' => ['type' => ['string', 'number', 'boolean']]
			]
		];
	}

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
	public function validate(string $key, mixed $value, array $item): ?Text
	{
		$params = $item['params'] ?? [];

		if (! is_array($params) || ($params !== [] && array_is_list($params)) || ! array_all($params, static fn (mixed $param): bool => is_scalar($param))) {
			return $this->messages->t('menu.route_link.params', '"{link}" has "params" that aren\'t a map of names to values.', link: $key);
		}

		return parent::validate($key, $value, $item);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function resolve(string $value, array $item, string $locale): LinkTarget
	{
		$name = trim($value);

		if (! $this->router->has($name)) {
			throw new UnresolvedLink((string) $this->messages->t('menu.route_link.missing', 'No route "{route}".', route: $name));
		}

		/** @var array<string, scalar> $params Checked by `validate()`. */
		$params = $item['params'] ?? [];

		try {
			return new LinkTarget($this->router->to($name, $params));
		} catch (UrlGenerationException $error) {
			throw new UnresolvedLink((string) $this->messages->t('menu.route_link.unbuilt', 'The "{route}" route: {problem}', route: $name, problem: $error->getMessage()), 0, $error);
		}
	}
}
