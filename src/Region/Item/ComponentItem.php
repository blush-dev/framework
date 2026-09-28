<?php

/**
 * Component region item.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Region\Item;

use Override;
use Blush\Component\ComponentName;
use Blush\Component\Slots;
use Blush\Region\RegionException;
use Blush\Region\RegionRender;
use Blush\Translation\LocaleMap;

/**
 * A component, with the item's other keys as its props (text in them may
 * be locale maps, D-202):
 *
 * ```yaml
 * - component: menu
 *   name: social
 * ```
 *
 * It renders in the page's context, so a menu marks the current item.
 */
final class ComponentItem extends RegionItem
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function validate(mixed $value, array $item): ?string
	{
		return is_string($value) && ComponentName::parse(trim($value)) !== null
			? null
			: 'must be a component name, such as "menu" or "acme/card".';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function render(mixed $value, array $item, RegionRender $render): string
	{
		$name = self::text($value);

		if (! $render->views->hasComponent($name)) {
			throw new RegionException(sprintf('No component "%s".', $name));
		}

		unset($item['component']);

		$props = LocaleMap::resolve($item, $render->locale, $render->defaultLocale);

		return $render->views->component($name, $props, '', new Slots(), $render->context);
	}
}
