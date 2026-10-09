<?php

/**
 * Go-live job.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job\Jobs;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;
use Blush\Cache\ContentVersion;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Events\EntriesWentLive;
use Blush\Content\Status;
use Blush\Event\Dispatcher;
use Blush\Job\Job;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;
use Blush\Job\JobStore;

/**
 * Moves the content version on when a scheduled entry's time has come
 * (D-040), so it goes live on time on a site nobody is visiting
 * (requests do it by themselves too), and announces the entries that
 * went live (`EntriesWentLive`, D-623).
 *
 * Each run keeps the scheduled entries it saw (by id, else by type,
 * language, and key) in the
 * jobs' `go-live` state; an entry it saw scheduled that's published now
 * went live. The first run only looks, so a site's past posts are never
 * announced.
 */
final class GoLiveJob extends Job
{
	/**
	 * The state that keeps the scheduled entries last seen.
	 */
	private const string STATE = 'go-live';

	public function __construct(
		private readonly ContentVersion $version,
		private readonly Entries $content,
		private readonly JobStore $store,
		private readonly Dispatcher $events,
		private readonly ClockInterface $clock
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): string
	{
		return 'Put scheduled entries live';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function handle(JobRecord $job): JobResult
	{
		$version = $this->version->current();
		$now     = DateTimeImmutable::createFromInterface($this->clock->now());
		$state   = $this->store->state(self::STATE);

		$scheduled = array_map(
			self::keyOf(...),
			$this->content->query()->any()->anyLanguage()->status(Status::Scheduled)->get()->all()
		);

		$this->store->saveState(self::STATE, ['scheduled' => $scheduled, 'checked' => $now->getTimestamp()]);

		$seen = is_array($state['scheduled'] ?? null) ? array_filter($state['scheduled'], is_string(...)) : null;

		if ($seen === null) {
			return JobResult::done(sprintf('The content version is %s.', $version));
		}

		$live = array_values(array_filter(
			array_map($this->entry(...), array_values(array_diff($seen, $scheduled))),
			static fn (?Entry $entry): bool => $entry?->status === Status::Published
		));

		if ($live !== []) {
			$checked = $state['checked'] ?? null;

			/** @var list<Entry> $live */
			$this->events->dispatch(new EntriesWentLive(
				$live,
				is_int($checked) ? DateTimeImmutable::createFromTimestamp($checked) : null,
				$now
			));
		}

		return JobResult::done(sprintf(
			'The content version is %s.%s',
			$version,
			$live === [] ? '' : sprintf(' %d %s went live.', count($live), count($live) === 1 ? 'entry' : 'entries')
		));
	}

	/**
	 * Returns how an entry is kept in the state: its id, else its type,
	 * language, and key.
	 */
	private static function keyOf(Entry $entry): string
	{
		return $entry->id ?? "key:{$entry->type->name}:{$entry->language}:{$entry->key}";
	}

	/**
	 * Finds an entry by the key it was kept under.
	 */
	private function entry(string $key): ?Entry
	{
		if (! str_starts_with($key, 'key:')) {
			return $this->content->find($key);
		}

		[, $type, $language, $named] = explode(':', $key, 4) + ['', '', '', ''];

		return $this->content->named($type, $named, $language);
	}
}
