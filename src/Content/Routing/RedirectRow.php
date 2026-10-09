<?php

/**
 * Redirect row.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Routing;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Blush\Http\Status;
use Blush\Routing\InvalidRoute;
use Blush\Routing\Redirect;

/**
 * A row of the `redirects` table as it's kept (D-678, D-686): the old
 * path in `from`, and where it leads, either `to` (a path or a whole
 * address, as a `Redirect`'s) or `entry`, an entry's id, so the row
 * follows the entry to wherever it's moved. With it, its `status`, and,
 * when known, when it was `added`, by which account (`by`, its id), and
 * how (`via`, `null` for by hand).
 *
 *     {"from": "/old-about", "entry": "0199…", "status": 301, "added": "2026-10-09T14:02:11+00:00", "by": "0199…"}
 *     {"from": "/blog/{slug}", "to": "/archives/{slug}"}
 */
final readonly class RedirectRow
{
	/**
	 * @throws InvalidRoute When it has neither a target nor both, or a status a redirect can't use.
	 */
	public function __construct(
		public string $from,
		public ?string $to = null,
		public ?string $entry = null,
		public Status $status = Status::MovedPermanently,
		public ?DateTimeImmutable $added = null,
		public ?string $by = null,
		public ?RedirectOrigin $via = null
	) {
		if (($to === null || $to === '') === ($entry === null || $entry === '')) {
			throw new InvalidRoute(sprintf('The redirect from "%s" needs a "to" or an "entry", and not both.', $from));
		}

		// Checks the path and status as the route table will.
		new Redirect($from, $to ?? '/', $status);
	}

	/**
	 * Reads a row as the table keeps it.
	 *
	 * @param  array<mixed> $data
	 * @throws InvalidRoute When it isn't one.
	 */
	public static function fromArray(array $data): self
	{
		$from   = $data['from'] ?? null;
		$to     = $data['to'] ?? null;
		$entry  = $data['entry'] ?? null;
		$status = $data['status'] ?? Status::MovedPermanently->value;
		$added  = $data['added'] ?? null;
		$by     = $data['by'] ?? null;
		$via    = $data['via'] ?? null;

		if (! is_string($from) || ! (is_string($to) || $to === null) || ! (is_string($entry) || $entry === null) || ! is_int($status) || Status::tryFrom($status) === null) {
			throw new InvalidRoute('A redirect needs a string "from", a string "to" or "entry", and a valid "status".');
		}

		if (! (is_string($by) || $by === null) || ! (is_string($via) || $via === null) || ($via !== null && RedirectOrigin::tryFrom($via) === null)) {
			throw new InvalidRoute(sprintf('The redirect from "%s" has a "by" or "via" that isn\'t one.', $from));
		}

		try {
			$date = is_string($added) ? new DateTimeImmutable($added) : null;
		} catch (Exception) {
			throw new InvalidRoute(sprintf('The redirect from "%s" has an "added" date that isn\'t one.', $from));
		}

		return new self($from, $to, $entry, Status::from($status), $date, $by, $via === null ? null : RedirectOrigin::from($via));
	}

	/**
	 * Returns the row as the table keeps it, leaving out what it hasn't.
	 *
	 * @return array<string, string|int>
	 */
	public function toArray(): array
	{
		return array_filter([
			'from'   => $this->from,
			'to'     => $this->to,
			'entry'  => $this->entry,
			'status' => $this->status->value,
			'added'  => $this->added?->format(DateTimeInterface::ATOM),
			'by'     => $this->by,
			'via'    => $this->via?->value
		], static fn (string|int|null $value): bool => $value !== null);
	}

	/**
	 * Whether it's permanent (301 or 308).
	 */
	public function isPermanent(): bool
	{
		return $this->status === Status::MovedPermanently || $this->status === Status::PermanentRedirect;
	}

	/**
	 * Returns a copy leading somewhere else: a path or address, or an
	 * entry by its id.
	 *
	 * @throws InvalidRoute
	 */
	public function leadingTo(?string $to, ?string $entry): self
	{
		return new self($this->from, $to, $entry, $this->status, $this->added, $this->by, $this->via);
	}

	/**
	 * Returns a copy with another status.
	 *
	 * @throws InvalidRoute
	 */
	public function withStatus(Status $status): self
	{
		return new self($this->from, $this->to, $this->entry, $status, $this->added, $this->by, $this->via);
	}
}
