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
use Blush\Storage\Record\RecordException;
use Blush\Support\RequestMemo;
use Blush\Translation\Messages;

/**
 * Every status by name (D-735, D-850): core's (`CoreStatuses`), then
 * extensions' (`StatusSource`), then the site's own (`WorkflowTables`,
 * D-853), with core's own names (`trash`, `scheduled`, `expired`)
 * listed beside them so tabs and labels work. When two sources register
 * a status by one name, or one registers a core name, the first is kept
 * and the clash is reported (D-597), so the site keeps loading. A site
 * record by a registered status's name sets only its label, plural, and
 * tone, never what it does; one that can't be read, or names one of
 * core's own, is left out and said in `problems()`. A name nothing
 * registers (a plugin turned off) gets `Status::unknown()`: not live,
 * kept as written.
 */
final readonly class Statuses
{
	public function __construct(
		private CoreStatuses $core,
		private Container $container,
		private Messages $messages,
		private RequestMemo $memo,
		private ?WorkflowTables $tables = null
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
	 * `date` is still to come is scheduled, and one whose `expires` has
	 * come is expired (D-852).
	 */
	public function at(string $stored, ?int $date, int $now, ?int $expires = null): Status
	{
		$status = $this->get($stored === '' ? Status::PUBLISHED : $stored);

		return match (true) {
			! $status->live                       => $status,
			$date !== null && $date > $now        => $this->get(Status::SCHEDULED),
			$expires !== null && $expires <= $now => $this->get(Status::EXPIRED),
			default                               => $status
		};
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
	 * Says why each of the site's status records was left out (D-853).
	 *
	 * @return list<string>
	 */
	public function problems(): array
	{
		return $this->load()[2];
	}

	/**
	 * Returns where a status comes from (D-854): its `source`, the class
	 * of the source that registered it, `site` for the site's own, or
	 * `core` for core's own names; and `over`, the class of the source a
	 * site's record relabels, or `null`. `null` for a status there isn't.
	 *
	 * @return ?array{source: string, over: ?string}
	 */
	public function origin(string $name, Subject $subject = Subject::Entries): ?array
	{
		return $this->load()[4][$subject->value][$name] ?? null;
	}

	/**
	 * Returns a status as core or an extension registers it, before the
	 * site's record of it (D-854), or `null` for one they don't.
	 */
	public function registeredBy(string $name, Subject $subject = Subject::Entries): ?Status
	{
		return $this->load()[3][$subject->value][$name] ?? null;
	}

	/**
	 * Builds the status a site's record would make (D-854), over the
	 * statuses core and extensions register, as `load()` would read it.
	 *
	 * @param  array<string, mixed> $data
	 * @throws InvalidStatus When the record can't be one.
	 */
	public function define(string $name, array $data): Status
	{
		return $this->fromRecord($name, $data, $this->load()[3]);
	}

	/**
	 * Loads the statuses, keyed by subject and name, the clashes, why any
	 * of the site's records were left out, the statuses before the site's
	 * records, and where each comes from.
	 *
	 * @return array{array<string, array<string, Status>>, list<DefinitionClash>, list<string>, array<string, array<string, Status>>, array<string, array<string, array{source: string, over: ?string}>>}
	 */
	private function load(): array
	{
		/** @var array{array<string, array<string, Status>>, list<DefinitionClash>, list<string>, array<string, array<string, Status>>, array<string, array<string, array{source: string, over: ?string}>>} */
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

			$problems = [];
			$base     = $statuses;
			$origins  = array_map(static fn (array $classes): array => array_map(static fn (string $class): array => ['source' => $class, 'over' => null], $classes), $sources);

			foreach ($this->siteRecords() as $name => $data) {
				try {
					$status = $this->fromRecord($name, $data, $base);
				} catch (InvalidStatus $e) {
					$problems[] = sprintf('The site\'s "%s" status is left out: %s', $name, $e->getMessage());

					continue;
				}

				$statuses[$status->subject->value][$status->name] = $status;
				$origins[$status->subject->value][$status->name]  = ['source' => 'site', 'over' => $sources[$status->subject->value][$status->name] ?? null];
			}

			foreach ($this->coreOwn() as $status) {
				$statuses[$status->subject->value][$status->name] = $status;
				$origins[$status->subject->value][$status->name]  = ['source' => 'core', 'over' => null];
			}

			return [$statuses, $clashes, $problems, $base, $origins];
		});
	}

	/**
	 * Returns the site's status records, by name, or none when they can't
	 * be read.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function siteRecords(): array
	{
		try {
			return $this->tables?->statuses()->all() ?? [];
		} catch (RecordException) {
			return [];
		}
	}

	/**
	 * Builds a status from the site's record of it: over a registered one
	 * by its name, its label, plural, and tone; else a status of its own.
	 *
	 * @param  array<string, mixed>                        $data
	 * @param  array<string, array<string, Status>>        $registered
	 * @throws InvalidStatus When the record can't be one.
	 */
	private function fromRecord(string $name, array $data, array $registered): Status
	{
		$text    = static fn (string $key): string => is_string($data[$key] ?? null) ? trim($data[$key]) : (array_key_exists($key, $data) && $data[$key] !== null ? throw new InvalidStatus(sprintf('"%s" is text.', $key)) : '');
		$subject = Subject::tryFrom(is_string($data['subject'] ?? null) ? $data['subject'] : Subject::Entries->value) ?? throw new InvalidStatus('"subject" is entries or responses.');
		$tone    = array_key_exists('tone', $data) ? (Tone::tryFrom(is_string($data['tone']) ? $data['tone'] : '') ?? throw new InvalidStatus(sprintf('"tone" is one of %s.', implode(', ', array_column(Tone::cases(), 'value'))))) : null;

		if (in_array($name, Status::CORE, true)) {
			throw new InvalidStatus(sprintf('"%s" is one of core\'s own, which it works out itself.', $name));
		}

		$existing = $registered[$subject->value][$name] ?? null;

		if ($existing !== null) {
			return new Status(
				$name,
				$text('label') === '' ? $existing->label : $text('label'),
				$text('plural') === '' ? ($text('label') === '' ? $existing->plural : '') : $text('plural'),
				live: $existing->live,
				active: $existing->active,
				subject: $subject,
				tone: $tone ?? $existing->tone
			);
		}

		foreach (['live', 'active'] as $key) {
			if (array_key_exists($key, $data) && ! is_bool($data[$key])) {
				throw new InvalidStatus(sprintf('"%s" is true or false.', $key));
			}
		}

		return new Status(
			$name,
			$text('label'),
			$text('plural'),
			live: ($data['live'] ?? false) === true,
			active: ($data['active'] ?? true) === true,
			subject: $subject,
			tone: $tone ?? Tone::Neutral
		);
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
				Status::EXPIRED,
				$this->messages->t('workflow.status.expired', 'Expired'),
				$this->messages->t('workflow.status.expired_plural', 'Expired')
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
