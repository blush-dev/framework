<?php

/**
 * Directive region item.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Region\Item;

use Override;
use Blush\Directive\DirectiveName;
use Blush\Region\RegionException;
use Blush\Region\RegionRender;
use Blush\Translation\LocaleMap;

/**
 * A directive (D-532), with the item's other keys as its props (text in
 * them may be locale maps, D-202):
 *
 * ```json
 * {"directive": "menu", "name": "social"}
 * ```
 *
 * It renders in the page's context, so a menu marks the current item.
 */
final class DirectiveItem extends RegionItem
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function itemSchema(string $key, array $text): array
	{
		return [$key => [
			'type'        => 'string',
			'pattern'     => '^' . DirectiveName::SYNTAX . '$',
			'description' => 'Shows a directive, such as menu or acme/tabs. The item\'s other keys are its props.'
		]];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function validate(mixed $value, array $item): ?string
	{
		return is_string($value) && DirectiveName::parse(trim($value)) !== null
			? null
			: 'must be a directive name, such as "menu" or "acme/tabs".';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function render(mixed $value, array $item, RegionRender $render): string
	{
		$name = self::text($value);

		if (! $render->views->hasDirective($name)) {
			throw new RegionException(sprintf('No directive "%s".', $name));
		}

		unset($item['directive']);

		$props = LocaleMap::resolve($item, $render->locale, $render->defaultLocale);

		return $render->views->directive($name, $props, '', $render->context);
	}
}
