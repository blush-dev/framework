<?php

/**
 * Date archive controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Http;

use DateTimeImmutable;
use IntlDateFormatter;
use IntlDatePatternGenerator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Content\ContentRepository;
use Blush\Content\Query\InvalidQuery;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
use Blush\Http\NotFound;

/**
 * Serves a type's date archives (`{type}.collection.year` down to
 * `.second`, and their `.paged` forms): the type's listed entries
 * published in the period, read in the site timezone. A date that
 * doesn't exist, or a period with no entries, is a 404.
 */
final class DateArchiveController extends ContentController
{
	/**
	 * The title of each archive level, as 1.x had them (`December 3, 2025
	 * @ 14:30`): the date as an ICU skeleton, written the way the page's
	 * language writes it (D-462; `3 de diciembre de 2025` in Spanish),
	 * then any time as digits.
	 *
	 * @var array<string, array{string, string}>
	 */
	private const array TITLES = [
		'year'   => ['y', ''],
		'month'  => ['yMMMM', ''],
		'day'    => ['yMMMMd', ''],
		'hour'   => ['yMMMMd', 'H'],
		'minute' => ['yMMMMd', 'H:i'],
		'second' => ['yMMMMd', 'H:i:s']
	];

	public function __construct(
		ContentRepository $content,
		ContentTypes $types,
		ContentUrls $urls,
		PageRenderer $renderer,
		private readonly AppConfig $app
	) {
		parent::__construct($content, $types, $urls, $renderer);
	}

	/**
	 * @throws NotFound
	 * @throws InvalidQuery
	 */
	public function __invoke(
		ServerRequestInterface $request,
		string $type,
		int $year,
		?int $month = null,
		?int $day = null,
		?int $hour = null,
		?int $minute = null,
		?int $second = null,
		int $page = 1,
		?string $language = null
	): ResponseInterface {
		$contentType = $this->type($type);
		$parts       = array_filter(compact('year', 'month', 'day', 'hour', 'minute', 'second'), static fn (?int $part): bool => $part !== null);

		if (! self::isValid($year, $month, $day, $hour, $minute, $second)) {
			throw new NotFound('There is no such date.');
		}

		if ($page === 1 && self::isPaged($request)) {
			return self::redirect($request, $this->urls->date($contentType, $parts, 1, $language) ?? '/');
		}

		$query = $this->query($contentType->listingArguments())->date($year, $month, $day, $hour, $minute, $second)->language($language);

		$entries = $this->paginate($query, $page);

		if ($entries->total() === 0) {
			throw new NotFound('No entries were published then.');
		}

		$date = new DateTimeImmutable(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month ?? 1, $day ?? 1, $hour ?? 0, $minute ?? 0, $second ?? 0));

		return $this->renderer->render(new ContentPage(
			kind: PageKind::Date,
			title: $this->title($date, array_key_last($parts), $language),
			type: $contentType,
			entries: $entries,
			date: $parts,
			pageUrl: fn (int $number): ?string => $this->urls->date($contentType, $parts, $number, $language),
			language: $language,
			alternateUrl: fn (string $code): ?string => $this->listsPage($query, $page, $code) ? $this->urls->date($contentType, $parts, $page, $code) : null
		), $request);
	}

	/**
	 * Returns an archive's title in a language (the default for `null`).
	 */
	private function title(DateTimeImmutable $date, string $level, ?string $language): string
	{
		[$skeleton, $time] = self::TITLES[$level] ?? self::TITLES['year'];

		$locale    = ($language === null ? null : $this->app->languages->find($language)?->locale) ?? $this->app->locale;
		$pattern   = new IntlDatePatternGenerator($locale)->getBestPattern($skeleton);
		$formatter = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $date->getTimezone(), null, $pattern ?: null);
		$title     = $formatter->format($date);
		$title     = is_string($title) ? mb_ucfirst($title) : $date->format('Y');

		return $time === '' ? $title : "{$title} @ {$date->format($time)}";
	}

	/**
	 * Returns whether the date parts name a real moment.
	 */
	private static function isValid(int $year, ?int $month, ?int $day, ?int $hour, ?int $minute, ?int $second): bool
	{
		return ($month === null || ($month >= 1 && $month <= 12))
			&& ($day === null || checkdate($month ?? 1, $day, $year))
			&& ($hour === null || $hour <= 23)
			&& ($minute === null || $minute <= 59)
			&& ($second === null || $second <= 59);
	}
}
