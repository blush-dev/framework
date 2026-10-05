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

use Override;
use Blush\Core\AppConfig;
use Blush\Core\Framework;

/**
 * A measurement within a known range, as a `<meter>` gauge (D-175, D-188):
 * `::meter[Battery]{value=62 low=20 high=80 optimum=100}`. It isn't for
 * progress (use `progress`). `min` is 0 and `max` 100 by default; `low`,
 * `high`, and `optimum` mark the range's poor and good parts, so browsers
 * can color the gauge. Values are kept inside `min`–`max`, `low` is never
 * above `high`, and a `max` not above `min` makes the range 0–100.
 *
 * Its template prints the gauge's attributes with `gaugeAttributes()`
 * and its value for people with `text()`. Each attribute
 * (`valueAttribute`, and so on) and text (`percent`, `valueText`, and
 * `maxText`, in the page's number format) is also a property.
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

	// phpcs:disable -- PHPCS 4.0 doesn't tokenize property hooks yet.
	/**
	 * The value's place in the range, as a percentage.
	 */
	public string $percent {
		get => $this->numbers()->percent(($this->amount - $this->from) / ($this->to - $this->from));
	}

	/**
	 * The value for people.
	 */
	public string $valueText {
		get => $this->numbers()->number($this->amount);
	}

	/**
	 * The maximum for people.
	 */
	public string $maxText {
		get => $this->numbers()->number($this->to);
	}
	// phpcs:enable

	/**
	 * The value kept inside the range.
	 */
	private readonly float $amount;

	/**
	 * The range's start: `min`, or 0 when the range is invalid.
	 */
	private readonly float $from;

	/**
	 * The range's end: `max`, or 100 when the range is invalid.
	 */
	private readonly float $to;

	public function __construct(
		private readonly AppConfig $app,
		public readonly float $value = 0,
		public readonly float $min = 0,
		public readonly float $max = 100,
		public readonly ?float $low = null,
		public readonly ?float $high = null,
		public readonly ?float $optimum = null,
		public readonly string $label = ''
	) {
		[$from, $to] = $max > $min ? [$min, $max] : [0.0, 100.0];

		$clamp = static fn (?float $number): ?float => $number === null ? null : max($from, min($to, $number));
		$low   = $clamp($low);
		$high  = $clamp($high);

		if ($low !== null && $high !== null && $low > $high) {
			[$low, $high] = [$high, $low];
		}

		$this->amount = (float) $clamp($value);
		$this->from   = $from;
		$this->to     = $to;

		$this->valueAttribute   = MeasureNumbers::attribute($this->amount);
		$this->minAttribute     = MeasureNumbers::attribute($from);
		$this->maxAttribute     = MeasureNumbers::attribute($to);
		$this->lowAttribute     = $low === null ? null : MeasureNumbers::attribute($low);
		$this->highAttribute    = $high === null ? null : MeasureNumbers::attribute($high);
		$this->optimumAttribute = $optimum === null ? null : MeasureNumbers::attribute((float) $clamp($optimum));
	}

	/**
	 * Returns whether the range is 0–100, so its value reads best as a
	 * percentage.
	 */
	public function isPercent(): bool
	{
		return $this->minAttribute === '0' && $this->maxAttribute === '100';
	}

	/**
	 * Returns the value for people: a percentage when the range is 0–100,
	 * else the theme's `measure.value` text ("62 of 80").
	 */
	public function text(): string
	{
		return $this->isPercent() ? $this->percent : $this->t('measure.value', value: $this->valueText, max: $this->maxText);
	}

	/**
	 * Returns the `<meter>` element's attributes, escaped: its class
	 * (`component-meter__gauge`), its range, and the theme's
	 * `meter.label` as its name when there's no label.
	 */
	public function gaugeAttributes(): string
	{
		return self::html([
			'class'      => $this->block() . '__gauge',
			'value'      => $this->valueAttribute,
			'min'        => $this->minAttribute,
			'max'        => $this->maxAttribute,
			'low'        => $this->lowAttribute,
			'high'       => $this->highAttribute,
			'optimum'    => $this->optimumAttribute,
			'aria-label' => trim($this->label) === '' ? $this->t('meter.label') : null
		]);
	}

	/**
	 * Returns number formatting in the page's locale.
	 */
	private function numbers(): MeasureNumbers
	{
		return new MeasureNumbers($this->locale($this->app->locale));
	}

	/**
	 * Renders the framework's template for it, `resources/components/meter.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): ComponentView
	{
		return $this->view(Framework::path('resources/components/meter.php'));
	}
}
