<?php

/**
 * View region item.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Region\Item;

use Override;
use Blush\Region\RegionException;
use Blush\Region\RegionRender;
use Blush\Translation\LocaleMap;
use Blush\View\ViewFinder;

/**
 * A partial from the site or the theme chain, with the item's other keys
 * as its data (text in them may be locale maps, D-202):
 *
 * ```json
 * {"view": "partials/newsletter", "heading": "Get new posts by email"}
 * ```
 */
final class ViewItem extends RegionItem
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function itemSchema(string $key, array $text): array
	{
		return [$key => [
			'type'        => 'string',
			'pattern'     => '^[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*$',
			'description' => 'Shows a template part from the site or theme, such as partials/newsletter. The item\'s other keys are its data.'
		]];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function validate(mixed $value, array $item): ?string
	{
		return is_string($value) && ViewFinder::isValidName(trim($value))
			? null
			: 'must be a view name, such as "partials/newsletter".';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function render(mixed $value, array $item, RegionRender $render): string
	{
		$name = self::text($value);

		if (! $render->views->exists($name)) {
			throw new RegionException(sprintf('No view "%s".', $name));
		}

		unset($item['view']);

		return $render->views->partial($name, LocaleMap::resolve($item, $render->locale, $render->defaultLocale), $render->context);
	}
}
