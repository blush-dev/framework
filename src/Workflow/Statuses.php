<?php

/**
 * Statuses.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Workflow;

use Blush\Container\Container;
use Blush\Extension\DefinitionClash;
use Blush\Support\RequestMemo;
use Blush\Translation\Messages;

/**
 * Every status by name (D-735, D-850): core's (`CoreStatuses`), then
 * extensions' (`StatusSource`), with core's own names (`trash`,
 * `scheduled`) listed beside them so tabs and labels work. When two
 * sources register a status by one name, or one registers a core name,
 * the first is kept and the clash is reported (D-597), so the site
 * keeps loading. A name nothing registers (a plugin turned off) gets
 * `Status::unknown()`: not live, kept as written.
 */
final readonly class Statuses
{
	public function __construct(
		private CoreStatuses $core,
		private Container $container,
		private Messages $messages,
		private RequestMemo $memo
	) {}

	/**
	 * Returns a subject's statuses by name: the registered ones, then
	 * core's own.
	 *
	 * @return array<string, Status>
	 */
	public function all(Subject $subject = Subject::Entries): array
	{
		return $this->load()[0][$subject->value] ?? [];
	}

	/**
	 * Returns a subject's registered statuses by name, without core's
	 * own: what a workflow may use.
	 *
	 * @return array<string, Status>
	 */
	public function registered(Subject $subject = Subject::Entries): array
	{
		return array_filter($this->all($subject), static fn (Status $status): bool => ! $status->isCore());
	}

	/**
	 * Returns a status by name, or `null` when there's none.
	 */
	public function find(string $name, Subject $subject = Subject::Entries): ?Status
	{
		return $this->all($subject)[$name] ?? null;
	}

	/**
	 * Returns a status by name, or one made for a name nothing
	 * registers.
	 */
	public function get(string $name, Subject $subject = Subject::Entries): Status
	{
		return $this->find($name, $subject) ?? Status::unknown($name, $subject);
	}

	/**
	 * Returns the names of a subject's live statuses.
	 *
	 * @return list<string>
	 */
	public function live(Subject $subject = Subject::Entries): array
	{
		return array_keys(array_filter($this->all($subject), static fn (Status $status): bool => $status->live));
	}

	/**
	 * Returns the names of a subject's statuses that aren't live, the
	 * trash and core's other own names left out: what an entry may have
	 * for changing it not to change the site.
	 *
	 * @return list<string>
	 */
	public function offline(Subject $subject = Subject::Entries): array
	{
		return array_keys(array_filter($this->registered($subject), static fn (Status $status): bool => ! $status->live));
	}

	/**
	 * Returns the names of a subject's statuses "any status" leaves out:
	 * the trash, and registered ones that aren't active.
	 *
	 * @return list<string>
	 */
	public function inactive(Subject $subject = Subject::Entries): array
	{
		return array_keys(array_filter($this->all($subject), static fn (Status $status): bool => ! $status->active));
	}

	/**
	 * Returns an entry's status at a Unix time, from the one it's
	 * stored with (`''` for none, so published): a live status whose
	 * `published` time is still to come is scheduled.
	 */
	public function at(string $stored, ?int $published, int $now): Status
	{
		$status = $this->get($stored === '' ? Status::PUBLISHED : $stored);

		return $status->live && $published !== null && $published > $now ? $this->get(Status::SCHEDULED) : $status;
	}

	/**
	 * Returns the statuses two sources registered by one name, or that
	 * a source registered with a core name.
	 *
	 * @return list<DefinitionClash>
	 */
	public function clashes(): array
	{
		return $this->load()[1];
	}

	/**
	 * Loads the statuses, keyed by subject and name, and the clashes.
	 *
	 * @return array{array<string, array<string, Status>>, list<DefinitionClash>}
	 */
	private function load(): array
	{
		/** @var array{array<string, array<string, Status>>, list<DefinitionClash>} */
		return $this->memo->remember('workflow|statuses', function (): array {
			$statuses = [];
			$sources  = [];
			$clashes  = [];

			foreach ($this->sources() as $source) {
				foreach ($source->statuses() as $status) {
					$kept = $status->isCore() ? self::class : $sources[$status->subject->value][$status->name] ?? null;

					if ($kept !== null) {
						$clashes[] = new DefinitionClash('status', $status->name, $kept, $source::class);

						continue;
					}

					$statuses[$status->subject->value][$status->name] = $status;
					$sources[$status->subject->value][$status->name]  = $source::class;
				}
			}

			foreach ($this->coreOwn() as $status) {
				$statuses[$status->subject->value][$status->name] = $status;
			}

			return [$statuses, $clashes];
		});
	}

	/**
	 * Returns the sources: core's first, then those extensions tagged.
	 *
	 * @return iterable<StatusSource>
	 * @throws InvalidStatus When a tagged service isn't a source.
	 */
	private function sources(): iterable
	{
		yield $this->core;

		foreach ($this->container->tagged(StatusSource::TAG) as $source) {
			if (! $source instanceof StatusSource) {
				throw new InvalidStatus(sprintf('Services tagged "%s" must implement %s; %s does not.', StatusSource::TAG, StatusSource::class, get_debug_type($source)));
			}

			yield $source;
		}
	}

	/**
	 * Returns core's own statuses, which nothing registers.
	 *
	 * @return list<Status>
	 */
	private function coreOwn(): array
	{
		return [
			new Status(
				Status::SCHEDULED,
				$this->messages->t('workflow.status.scheduled', 'Scheduled'),
				$this->messages->t('workflow.status.scheduled_plural', 'Scheduled'),
				tone: Tone::Accent
			),
			new Status(
				Status::TRASH,
				$this->messages->t('workflow.status.trash', 'Trash'),
				$this->messages->t('workflow.status.trash_plural', 'Trash'),
				active: false,
				tone: Tone::Danger
			)
		];
	}
}
