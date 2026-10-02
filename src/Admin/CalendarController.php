<?php

/**
 * Admin calendar controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use DateTimeImmutable;
use DateTimeInterface;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Query\Order;
use Blush\Content\Status;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\TypeKind;
use Blush\Core\AppConfig;
use Blush\Http\Response;
use Blush\Http\Status as HttpStatus;

/**
 * Answers `GET {path}/api/calendar` (D-368): a month of the entries the
 * account may edit that have a published date, placed on it in the site's
 * timezone, so the admin's calendar shows what went out and what's
 * queued. A draft with a date is on its day too. The query string
 * narrows it:
 *
 * - `month`: `YYYY-MM`, the site's current month by default.
 * - `status`: `draft`, `scheduled`, `published`, or `any` (the default).
 * - `type`: a content type's name.
 *
 * Entries come in date order. Without a `type`, it holds pages and
 * collections' entries: terms and profiles have dates (the admin writes
 * one when it makes them), but adding a topic or a person isn't
 * publishing something on a day, so they're there only when their type
 * is asked for. Landing pages (a type's index page) are never on it:
 * they're the type's archive.
 * A month holds at most `MAX` entries; `total` says how many there were.
 */
final readonly class CalendarController
{
	/**
	 * The most entries a month answers.
	 */
	public const int MAX = 500;

	public function __construct(
		private ContentRepository $content,
		private ContentTypes $types,
		private EntryHandles $handles,
		private Permissions $permissions,
		private AppConfig $app,
		private ClockInterface $clock
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account) {
			return self::json(['error' => 'Sign in first.'], HttpStatus::Unauthorized);
		}

		$params   = $request->getQueryParams();
		$timezone = $this->app->timezone();
		$now      = $this->clock->now()->setTimezone($timezone);
		$month    = $params['month'] ?? $now->format('Y-m');

		if (! is_string($month) || preg_match('/^([0-9]{4})-(0[1-9]|1[0-2])$/', $month, $parts) !== 1) {
			return self::json(['error' => '"month" must be a year and month, like 2026-10.'], HttpStatus::BadRequest);
		}

		$value  = $params['status'] ?? 'any';
		$status = is_string($value) ? Status::tryFrom($value) : null;

		if ($status === null && $value !== 'any') {
			return self::json(['error' => '"status" must be draft, scheduled, published, or any.'], HttpStatus::BadRequest);
		}

		$type = $params['type'] ?? null;

		if ($type !== null && (! is_string($type) || ! $this->types->has($type))) {
			return self::json(['error' => 'There is no such content type.'], HttpStatus::BadRequest);
		}

		$query = $this->content->query()->any()->withLanding(false)->date((int) $parts[1], (int) $parts[2]);
		$query = $status === null ? $query : $query->status($status);
		$query = $query->type(...($type === null ? $this->dated() : [$type]));
		$query = $this->permissions->restrict($account, ContentAction::Edit, $query)->orderBy('published', Order::Asc);

		$entries = $query->limit(self::MAX)->get()->all();
		$total   = count($entries) < self::MAX ? count($entries) : $query->count();

		return self::json([
			'month'   => $month,
			'today'   => $now->format('Y-m-d'),
			'status'  => $status->value ?? 'any',
			'type'    => $type,
			'total'   => $total,
			'entries' => array_values(array_filter(array_map(
				fn (Entry $entry): ?array => $entry->published === null ? null : $this->describe($entry, $entry->published->setTimezone($timezone)),
				$entries
			)))
		]);
	}

	/**
	 * Returns the names of the types a calendar shows when none is asked
	 * for: pages and collections.
	 *
	 * @return list<string>
	 */
	private function dated(): array
	{
		$names = [];

		foreach ($this->types->all() as $type) {
			if ($type->kind() === TypeKind::Pages || $type->kind() === TypeKind::Collection) {
				$names[] = $type->name;
			}
		}

		return $names;
	}

	/**
	 * Returns what the calendar shows of an entry: its `day` of the month
	 * and `time` (`HH:MM`) are in the site's timezone, whatever the
	 * browser's.
	 *
	 * @return array<string, mixed>
	 */
	private function describe(Entry $entry, DateTimeImmutable $published): array
	{
		return [
			'id'        => $entry->id,
			'handle'    => $this->handles->of($entry),
			'title'     => $entry->title,
			'type'      => $entry->type->name,
			'status'    => $entry->status->value,
			'published' => $published->format(DateTimeInterface::ATOM),
			'day'       => (int) $published->format('j'),
			'time'      => $published->format('H:i')
		];
	}

	/**
	 * Returns a JSON answer the browser won't cache.
	 *
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, HttpStatus $status = HttpStatus::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
