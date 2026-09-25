<?php

/**
 * Redirect.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

use Blush\Http\Status;

/**
 * A redirect from an old path to a new location. Redirects are only
 * consulted when nothing else answers a request (just before a 404), so
 * they can never shadow a live page.
 *
 * `from` is a route pattern, so `{name}` placeholders (with optional
 * constraints) capture segments that `to` can reuse:
 *
 *     new Redirect('/blog/{slug}', '/archives/{slug}');
 *     new Redirect('/feed', 'https://feeds.example.com/main', Status::Found);
 *
 * `to` is a path or an absolute URL. The request's query string is kept
 * unless `to` has its own.
 */
final readonly class Redirect
{
	/**
	 * The statuses a redirect may use.
	 */
	private const array STATUSES = [
		Status::MovedPermanently,
		Status::Found,
		Status::SeeOther,
		Status::TemporaryRedirect,
		Status::PermanentRedirect
	];

	/**
	 * @throws InvalidRoute
	 */
	public function __construct(
		public string $from,
		public string $to,
		public Status $status = Status::MovedPermanently
	) {
		if (! str_starts_with($from, '/')) {
			throw new InvalidRoute(sprintf('The redirect path "%s" must start with "/".', $from));
		}

		if ($to === '') {
			throw new InvalidRoute(sprintf('The redirect from "%s" needs a target.', $from));
		}

		if (! in_array($status, self::STATUSES, true)) {
			throw new InvalidRoute(sprintf(
				'The redirect from "%s" must use 301, 302, 303, 307, or 308; %d given.',
				$from,
				$status->value
			));
		}
	}

	/**
	 * Rebuilds a redirect from `toArray()` output.
	 *
	 * @param  array<mixed> $data
	 * @throws InvalidRoute
	 */
	public static function fromArray(array $data): self
	{
		$from   = $data['from'] ?? null;
		$to     = $data['to'] ?? null;
		$status = Status::tryFrom(is_int($data['status'] ?? null) ? $data['status'] : Status::MovedPermanently->value);

		if (! is_string($from) || ! is_string($to) || $status === null) {
			throw new InvalidRoute('A redirect array needs string "from" and "to" values and a valid "status".');
		}

		return new self($from, $to, $status);
	}

	/**
	 * Returns the redirect as exportable values.
	 *
	 * @return array{from: string, to: string, status: int}
	 */
	public function toArray(): array
	{
		return ['from' => $this->from, 'to' => $this->to, 'status' => $this->status->value];
	}
}
