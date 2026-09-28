<?php

/**
 * Meter component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

use Blush\Core\AppConfig;

/**
 * A measurement within a known range, as a `<meter>` gauge (D-175, D-188):
 * `::meter[Battery]{value=62 low=20 high=80 optimum=100}`. It isn't for
 * progress (use `progress`). `min` is 0 and `max` 100 by default; `low`,
 * `high`, and `optimum` mark the range's poor and good parts, so browsers
 * can color the gauge. Values are kept inside `min`–`max`, `low` is never
 * above `high`, and a `max` not above `min` makes the range 0–100.
 *
 * The template gets each attribute (`$valueAttribute`, `$minAttribute`,
 * `$maxAttribute`, and `$lowAttribute`, `$highAttribute`, and
 * `$optimumAttribute`, or `null`) and text for people: `$percent` (of the
 * range), `$valueText`, and `$maxText`.
 */
final class Meter extends Component
{
	/**
	 * @inheritDoc
	 */
	public const ComponentContent CONTENT = ComponentContent::Text;

	/**
	 * The `value` attribute.
	 */
	public readonly string $valueAttribute;

	/**
	 * The `min` attribute.
	 */
	public readonly string $minAttribute;

	/**
	 * The `max` attribute.
	 */
	public readonly string $maxAttribute;

	/**
	 * The `low` attribute, or `null`.
	 */
	public readonly ?string $lowAttribute;

	/**
	 * The `high` attribute, or `null`.
	 */
	public readonly ?string $highAttribute;

	/**
	 * The `optimum` attribute, or `null`.
	 */
	public readonly ?string $optimumAttribute;

	/**
	 * The value's place in the range, as a percentage.
	 */
	public readonly string $percent;

	/**
	 * The value for people.
	 */
	public readonly string $valueText;

	/**
	 * The maximum for people.
	 */
	public readonly string $maxText;

	public function __construct(
		AppConfig $app,
		public readonly float $value = 0,
		public readonly float $min = 0,
		public readonly float $max = 100,
		public readonly ?float $low = null,
		public readonly ?float $high = null,
		public readonly ?float $optimum = null,
		public readonly string $label = ''
	) {
		$numbers = new MeasureNumbers($app->locale);
		[$from, $to] = $max > $min ? [$min, $max] : [0.0, 100.0];

		$clamp = static fn (?float $number): ?float => $number === null ? null : max($from, min($to, $number));
		$low   = $clamp($low);
		$high  = $clamp($high);

		if ($low !== null && $high !== null && $low > $high) {
			[$low, $high] = [$high, $low];
		}

		$amount = (float) $clamp($value);

		$this->valueAttribute   = MeasureNumbers::attribute($amount);
		$this->minAttribute     = MeasureNumbers::attribute($from);
		$this->maxAttribute     = MeasureNumbers::attribute($to);
		$this->lowAttribute     = $low === null ? null : MeasureNumbers::attribute($low);
		$this->highAttribute    = $high === null ? null : MeasureNumbers::attribute($high);
		$this->optimumAttribute = $optimum === null ? null : MeasureNumbers::attribute((float) $clamp($optimum));
		$this->percent          = $numbers->percent(($amount - $from) / ($to - $from));
		$this->valueText        = $numbers->number($amount);
		$this->maxText          = $numbers->number($to);
	}

	/**
	 * Returns whether the range is 0–100, so its value reads best as a
	 * percentage.
	 */
	public function isPercent(): bool
	{
		return $this->minAttribute === '0' && $this->maxAttribute === '100';
	}
}
