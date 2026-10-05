<?php

/**
 * Time component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component\Inline;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use IntlDateFormatter;
use IntlDatePatternGenerator;
use Override;
use Blush\Clock\DateFormat;
use Blush\Component\Component;
use Blush\Component\ComponentContent;
use Blush\Component\ComponentView;
use Blush\Core\AppConfig;
use Blush\Core\Framework;

/**
 * A date, time, or duration that machines can read (D-175, D-180):
 * `:time[next Tuesday]{datetime=2026-10-06}`. `datetime` is checked
 * against the forms HTML accepts: a year, month, date, local or global
 * date and time, time, or ISO duration (`PT2H30M`). A valid one is
 * `machine`; otherwise the element has no `datetime`.
 *
 * Without a label (`::time{datetime=2026-10-06}`), `text()` shows it in
 * the page's language and the site's time zone, or as written for a
 * duration or an invalid value (`formatted`). A date, a time, or both are shown with the
 * site's date and time formats (D-445: "October 6, 2026" in `en_US` by
 * default); a year or a month, which those would add a day to, with
 * ICU's best pattern for just those ("2026", "October 2026").
 */
final class Time extends Component
{
	/**
	 * @inheritDoc
	 */
	public const ComponentContent CONTENT = ComponentContent::Text;

	/**
	 * The forms of a valid `datetime`, with how each is shown: a date
	 * pattern skeleton, the site's `date`, `time`, or `datetime` formats,
	 * or `null` for a duration, shown as written.
	 *
	 * @var array<string, ?string>
	 */
	private const array FORMS = [
		'/^\d{4}$/'                                                                          => 'y',
		'/^\d{4}-\d{2}$/'                                                                    => 'yMMMM',
		'/^\d{4}-\d{2}-\d{2}$/'                                                              => 'date',
		'/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d{1,3})?)?(?:Z|[+-]\d{2}:?\d{2})?$/' => 'datetime',
		'/^\d{2}:\d{2}(?::\d{2}(?:\.\d{1,3})?)?$/'                                           => 'time',
		'/^P(?=\d|T\d)(?:\d+Y)?(?:\d+M)?(?:\d+W)?(?:\d+D)?(?:T(?=\d)(?:\d+H)?(?:\d+M)?(?:\d+(?:\.\d+)?S)?)?$/' => null
	];

	/**
	 * The valid `datetime`, or `null`.
	 */
	public readonly ?string $machine;

	// phpcs:disable -- PHPCS 4.0 doesn't tokenize property hooks yet.
	/**
	 * The date as people read it, shown without a label, in the page's
	 * language.
	 */
	public string $formatted {
		get => $this->date === null ? trim($this->datetime) : $this->format($this->date, $this->form);
	}
	// phpcs:enable

	/**
	 * The moment a valid date or time names, or `null` for a duration or
	 * an invalid value, shown as written.
	 */
	private readonly ?DateTimeImmutable $date;

	/**
	 * How the date is shown: a pattern skeleton, or the site's `date`,
	 * `time`, or `datetime` formats.
	 */
	private readonly string $form;

	public function __construct(
		private readonly AppConfig $app,
		public readonly string $datetime = '',
		public readonly string $label = ''
	) {
		$value    = trim($datetime);
		$skeleton = false;

		foreach (self::FORMS as $pattern => $form) {
			if (preg_match($pattern, $value) === 1) {
				$skeleton = $form;
				break;
			}
		}

		$date = is_string($skeleton) ? self::date($value, $app->timezone) : null;

		$this->machine = $skeleton === null || $date !== null ? $value : null;
		$this->date    = $date;
		$this->form    = is_string($skeleton) ? $skeleton : '';
	}

	/**
	 * Returns the text shown, as HTML: the content, else the label, else
	 * the formatted date, escaped.
	 */
	public function text(): string
	{
		return $this->contentOr($this->label !== '' ? $this->label : $this->formatted);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function rootAttributes(): array
	{
		return ['datetime' => $this->machine];
	}

	/**
	 * Returns the moment a valid date or time names, in the site's time
	 * zone, or `null` when it doesn't exist (such as February 30).
	 */
	private static function date(string $value, string $timezone): ?DateTimeImmutable
	{
		$zone  = new DateTimeZone($timezone);
		$parts = explode('-', substr($value, 0, 10));

		if (preg_match('/^\d{4}(-\d{2}){1,2}/', $value) === 1) {
			[$year, $month, $day] = [(int) $parts[0], (int) $parts[1], (int) ($parts[2] ?? 1)];

			if (! checkdate($month, $day, $year)) {
				return null;
			}
		}

		$normalized = match (true) {
			strlen($value) === 4 => "{$value}-01-01",
			strlen($value) === 7 => "{$value}-01",
			default              => $value
		};

		try {
			return new DateTimeImmutable($normalized, $zone)->setTimezone($zone);
		} catch (Exception) {
			return null;
		}
	}

	/**
	 * Formats a date in the page's locale, with the site's formats or a
	 * pattern skeleton.
	 */
	private function format(DateTimeImmutable $date, string $skeleton): string
	{
		$app     = $this->app;
		$locale  = $this->locale($app->locale);
		$formats = match ($skeleton) {
			'date'     => [$app->dateFormat, null],
			'time'     => [null, $app->timeFormat],
			'datetime' => [$app->dateFormat, $app->timeFormat],
			default    => null
		};

		if ($formats !== null) {
			return DateFormat::format($date, $locale, $app->timezone, ...$formats);
		}

		$pattern   = new IntlDatePatternGenerator($locale)->getBestPattern($skeleton);
		$formatter = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $app->timezone, null, $pattern ?: null);

		return $formatter->format($date) ?: $date->format('c');
	}

	/**
	 * Renders the framework's template for it, `resources/components/time.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): ComponentView
	{
		return $this->view(Framework::path('resources/components/time.php'));
	}
}
