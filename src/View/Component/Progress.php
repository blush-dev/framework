<?php

/**
 * Progress component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Component;

use Override;
use Blush\Core\AppConfig;

/**
 * How far along something is, as a `<progress>` bar (D-175, D-188):
 * `::progress[Reading challenge]{value=12 max=50}`. `max` is 100 by
 * default, so a plain `value` is a percentage. Without a `value`, the bar
 * is indeterminate (work under way, amount unknown). The value is kept
 * between 0 and `max`, and an invalid `max` is 100.
 *
 * The template gets the attribute values (`$valueAttribute`, or `null`
 * when indeterminate, and `$maxAttribute`) and text for people:
 * `$percent` ("24%"), `$valueText`, and `$maxText`, in the site's number
 * format.
 */
final class Progress extends Component
{
	/**
	 * @inheritDoc
	 */
	public const ComponentContent CONTENT = ComponentContent::Text;

	/**
	 * The `value` attribute, or `null` for an indeterminate bar.
	 */
	public readonly ?string $valueAttribute;

	/**
	 * The `max` attribute.
	 */
	public readonly string $maxAttribute;

	/**
	 * The value as a percentage of `max`, or `''` when indeterminate.
	 */
	public readonly string $percent;

	/**
	 * The value for people, or `''` when indeterminate.
	 */
	public readonly string $valueText;

	/**
	 * The maximum for people.
	 */
	public readonly string $maxText;

	public function __construct(
		AppConfig $app,
		public readonly ?float $value = null,
		public readonly float $max = 100,
		public readonly string $label = ''
	) {
		$numbers = new MeasureNumbers($app->locale);
		$limit   = $max > 0 ? $max : 100.0;
		$amount  = $value === null ? null : max(0.0, min($limit, $value));

		$this->valueAttribute = $amount === null ? null : MeasureNumbers::attribute($amount);
		$this->maxAttribute   = MeasureNumbers::attribute($limit);
		$this->percent        = $amount === null ? '' : $numbers->percent($amount / $limit);
		$this->valueText      = $amount === null ? '' : $numbers->number($amount);
		$this->maxText        = $numbers->number($limit);
	}

	/**
	 * Returns whether the bar is out of 100, so its value reads best as a
	 * percentage.
	 */
	public function isPercent(): bool
	{
		return $this->maxAttribute === '100';
	}
}
