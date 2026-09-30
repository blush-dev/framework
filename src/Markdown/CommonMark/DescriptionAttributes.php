<?php

/**
 * Description attributes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Attributes\Util\AttributesHelper;
use League\CommonMark\Extension\DescriptionList\Node\Description;
use League\CommonMark\Node\Block\Paragraph;

/**
 * Moves attributes written at the end of a definition (`: A definition.
 * {.note}`) from its paragraph to the `<dd>` when the list is tight, since
 * a tight definition's paragraph isn't rendered as a `<p>` and they'd be
 * lost (D-282). This is what league/commonmark does for a tight list's
 * items. A loose definition keeps them on its `<p>`, as a loose list item
 * does. Runs after the attributes extension has placed them.
 */
final readonly class DescriptionAttributes
{
	/**
	 * Moves each tight definition's paragraph attributes to it.
	 */
	public function __invoke(DocumentParsedEvent $event): void
	{
		$descriptions = [];
		$walker       = $event->getDocument()->walker();

		while (($step = $walker->next()) !== null) {
			$node = $step->getNode();

			if ($step->isEntering() && $node instanceof Description && $node->isTight()) {
				$descriptions[] = $node;
			}
		}

		foreach ($descriptions as $description) {
			foreach ($description->children() as $child) {
				$attributes = $child instanceof Paragraph ? $child->data->get('attributes', []) : [];

				if (! $child instanceof Paragraph || ! is_array($attributes) || $attributes === []) {
					continue;
				}

				$description->data->set('attributes', AttributesHelper::mergeAttributes($description, $child));
				$child->data->set('attributes', []);
			}
		}
	}
}
