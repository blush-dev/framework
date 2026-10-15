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
use Blush\Content\Events\EntriesExpired;
use Blush\Content\Events\EntriesWentLive;
use Blush\Event\Dispatcher;
use Blush\Job\Job;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;
use Blush\Job\JobStore;
use Blush\Translation\Messages;
use Blush\Translation\Text;
use Blush\Workflow\Status;

/**
 * Moves the content version on when a scheduled entry's time has come
 * (D-040), or an entry's expiry has (D-852), so it goes live or comes
 * off on time on a site nobody is visiting (requests do it by
 * themselves too), and announces the entries that went live
 * (`EntriesWentLive`, D-623) and those that expired (`EntriesExpired`).
 *
 * Each run keeps the scheduled entries it saw (by id, else by type,
 * language, and key) in the
 * jobs' `go-live` state, and the expired ones the same way; an entry it
 * saw scheduled that's published now went live, and one that's expired
 * now and wasn't then expired. The first run only looks, so a site's
 * past posts are never announced.
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
		private readonly ClockInterface $clock,
		private readonly Messages $messages
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): Text
	{
		return $this->messages->t('job.go_live_job.label', 'Put scheduled entries live, and take expired ones off');
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
			$this->content->query()->any()->anyLanguage()->status(Status::SCHEDULED)->get()->all()
		);

		$expired = array_map(
			self::keyOf(...),
			$this->content->query()->any()->anyLanguage()->status(Status::EXPIRED)->get()->all()
		);

		$this->store->saveState(self::STATE, ['scheduled' => $scheduled, 'expired' => $expired, 'checked' => $now->getTimestamp()]);

		$checked = $state['checked'] ?? null;
		$since   = is_int($checked) ? DateTimeImmutable::createFromTimestamp($checked) : null;
		$seen    = is_array($state['scheduled'] ?? null) ? array_filter($state['scheduled'], is_string(...)) : null;
		$gone    = is_array($state['expired'] ?? null) ? array_filter($state['expired'], is_string(...)) : null;

		$live = $seen === null ? [] : array_values(array_filter(
			array_map($this->entry(...), array_values(array_diff($seen, $scheduled))),
			static fn (?Entry $entry): bool => $entry?->isLive() === true
		));

		// Expired since the last look, and still expired.
		$ended = $gone === null ? [] : array_values(array_filter(
			array_map($this->entry(...), array_values(array_diff($expired, $gone))),
			static fn (?Entry $entry): bool => $entry?->status->is(Status::EXPIRED) === true
		));

		if ($live !== []) {
			/** @var list<Entry> $live */
			$this->events->dispatch(new EntriesWentLive($live, $since, $now));
		}

		if ($ended !== []) {
			/** @var list<Entry> $ended */
			$this->events->dispatch(new EntriesExpired($ended, $since, $now));
		}

		return JobResult::done(match (true) {
			$live === [] && $ended === [] => $this->messages->t('job.go_live_job.version', 'The content version is {version}.', version: $version),
			$ended === []                 => $this->messages->t('job.go_live_job.went_live', 'The content version is {version}. {count, plural, one {# entry} other {# entries}} went live.', version: $version, count: count($live)),
			default                       => $this->messages->t('job.go_live_job.changed', 'The content version is {version}. {live, plural, one {# entry} other {# entries}} went live, and {expired, plural, one {# entry} other {# entries}} expired.', version: $version, live: count($live), expired: count($ended))
		});
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
