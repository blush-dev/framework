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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Content\Query\InvalidQuery;
use Blush\Http\NotFound;

/**
 * Serves a type's date archives (`{type}.collection.year` down to
 * `.second`, and their `.paged` forms): the type's collected entries
 * published in the period, read in the site timezone. A date that
 * doesn't exist, or a period with no entries, is a 404.
 */
final class DateArchiveController extends ContentController
{
	/**
	 * The title format for each archive level, as 1.x had them.
	 */
	private const array TITLES = [
		'year'   => 'Y',
		'month'  => 'F Y',
		'day'    => 'F j, Y',
		'hour'   => 'F j, Y @ H',
		'minute' => 'F j, Y @ H:i',
		'second' => 'F j, Y @ H:i:s'
	];

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
		int $page = 1
	): ResponseInterface {
		$contentType = $this->type($type);
		$parts       = array_filter(compact('year', 'month', 'day', 'hour', 'minute', 'second'), static fn (?int $part): bool => $part !== null);

		if (! self::isValid($year, $month, $day, $hour, $minute, $second)) {
			throw new NotFound('There is no such date.');
		}

		if ($page === 1 && self::isPaged($request)) {
			return self::redirect($request, $this->urls->date($contentType, $parts) ?? '/');
		}

		$query = $this->query(
			['type' => $contentType->collect === false ? $contentType->name : $contentType->collect],
			$contentType->collection
		)->date($year, $month, $day, $hour, $minute, $second);

		$entries = $this->paginate($query, $page);

		if ($entries->total() === 0) {
			throw new NotFound('No entries were published then.');
		}

		$date = new DateTimeImmutable(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month ?? 1, $day ?? 1, $hour ?? 0, $minute ?? 0, $second ?? 0));

		return $this->renderer->render(new ContentPage(
			kind: PageKind::Date,
			title: $date->format(self::TITLES[array_key_last($parts)]),
			type: $contentType,
			entries: $entries,
			date: $parts,
			pageUrl: fn (int $number): ?string => $this->urls->date($contentType, $parts, $number)
		), $request);
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
