<?php

/**
 * Workflow.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Workflow;

use Blush\Translation\Text;

/**
 * The statuses a type's entries move through, and the transitions
 * between them (D-735, D-851). A type names its own (`workflow:`), and
 * core's `standard` (draft and published; Publish and Unpublish) is
 * every other type's. Statuses are one list (D-733): a workflow picks
 * from them by name, so `status: draft` means the same in every type.
 *
 * The trash and scheduling are core's, outside every workflow: any
 * entry can be moved to the trash and restored to `initial`, and a live
 * status with a date still to come is scheduled.
 *
 *     new Workflow('editorial', 'Editorial', ['draft', 'acme_in_review', 'published'], 'draft', [
 *         new Transition('submit', 'Submit for Review', ['draft'], 'acme_in_review'),
 *         new Transition('publish', 'Publish', ['acme_in_review'], 'published', capability: false),
 *         new Transition('unpublish', 'Unpublish', ['published'], 'draft', capability: false)
 *     ]);
 */
final readonly class Workflow
{
	/**
	 * Core's workflow, every type's unless it names another.
	 */
	public const string STANDARD = 'standard';

	/**
	 * What people call it.
	 */
	public string|Text $label;

	/**
	 * @param string           $name        Lowercase letters, digits, and underscores.
	 * @param string|Text      $label       What people call it; `''` for one made from the name.
	 * @param list<string>     $statuses    Its statuses, by name, in the order lists show them.
	 * @param string           $initial     The status new, copied, and restored entries start at.
	 * @param list<Transition> $transitions Its transitions, in the order the editor offers them.
	 * @param Subject          $subject     What it's for.
	 * @throws InvalidWorkflow When the name isn't one.
	 */
	public function __construct(
		public string $name,
		string|Text $label = '',
		public array $statuses = [],
		public string $initial = '',
		public array $transitions = [],
		public Subject $subject = Subject::Entries
	) {
		if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
			throw new InvalidWorkflow(sprintf('A workflow name is lowercase letters, digits, and underscores, starting with a letter; "%s" isn\'t.', $name));
		}

		$this->label = (string) $label === '' ? ucwords(str_replace('_', ' ', $name)) : $label;
	}

	/**
	 * Builds a workflow from a record of it (D-853), as `toArray()` gives
	 * it without the name: `label`, `statuses`, `initial`, `transitions`
	 * (each `name`, `label`, `from`, `to`, `capability`), and `subject`.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidWorkflow When the record can't be one.
	 */
	public static function fromArray(string $name, array $data): self
	{
		$names = static function (mixed $value, string $key): array {
			if (! is_array($value) || ! array_is_list($value) || ! array_all($value, static fn (mixed $item): bool => is_string($item))) {
				throw new InvalidWorkflow(sprintf('"%s" is a list of statuses\' names.', $key));
			}

			return array_values(array_filter($value, is_string(...)));
		};

		$transitions = $data['transitions'] ?? [];

		if (! is_array($transitions) || ! array_is_list($transitions)) {
			throw new InvalidWorkflow('"transitions" is a list.');
		}

		return new self(
			$name,
			is_string($data['label'] ?? null) ? $data['label'] : '',
			$names($data['statuses'] ?? null, 'statuses'),
			is_string($data['initial'] ?? null) ? $data['initial'] : '',
			array_map(static function (mixed $transition) use ($names): Transition {
				if (! is_array($transition) || ! is_string($transition['name'] ?? null) || ! is_string($transition['to'] ?? null)) {
					throw new InvalidWorkflow('Each transition has a "name" and a "to".');
				}

				return new Transition(
					$transition['name'],
					is_string($transition['label'] ?? null) ? $transition['label'] : '',
					$names($transition['from'] ?? [], 'from'),
					$transition['to'],
					($transition['capability'] ?? true) !== false
				);
			}, $transitions),
			Subject::tryFrom(is_string($data['subject'] ?? null) ? $data['subject'] : Subject::Entries->value) ?? throw new InvalidWorkflow('"subject" is entries or responses.')
		);
	}

	/**
	 * Returns whether a status is one of its own.
	 */
	public function has(string $status): bool
	{
		return in_array($status, $this->statuses, true);
	}

	/**
	 * Returns a transition by name, or `null`.
	 */
	public function transition(string $name): ?Transition
	{
		return array_find($this->transitions, static fn (Transition $transition): bool => $transition->name === $name);
	}

	/**
	 * Returns the transitions from a status, in order. A status that
	 * isn't one of its own (D-851) is offered those from `initial`.
	 *
	 * @return list<Transition>
	 */
	public function from(string $status): array
	{
		$from = $this->has($status) ? $status : $this->initial;

		return array_values(array_filter($this->transitions, static fn (Transition $transition): bool => $transition->leaves($from) && $transition->to !== $status));
	}

	/**
	 * Returns the transition from a status to another, or `null` when
	 * there's none: the first listed, when more than one would do.
	 */
	public function between(string $from, string $to): ?Transition
	{
		return array_find($this->from($from), static fn (Transition $transition): bool => $transition->to === $to);
	}

	/**
	 * Says what's wrong with it among some registered statuses, or
	 * `null` when nothing is: a status it names that isn't registered,
	 * an `initial` it doesn't list, two transitions by one name, or a
	 * transition from or to a status it doesn't list.
	 *
	 * @param array<string, Status> $registered
	 */
	public function problem(array $registered): ?string
	{
		$missing = array_values(array_filter($this->statuses, static fn (string $status): bool => ! isset($registered[$status])));

		if ($missing !== []) {
			return sprintf('it names statuses nothing registers: %s', implode(', ', $missing));
		}

		if ($this->statuses === [] || ! $this->has($this->initial)) {
			return sprintf('its "initial" status, "%s", isn\'t one of its statuses', $this->initial);
		}

		$names = array_map(static fn (Transition $transition): string => $transition->name, $this->transitions);

		if (count($names) !== count(array_unique($names))) {
			return 'two of its transitions have one name';
		}

		foreach ($this->transitions as $transition) {
			$outside = array_values(array_filter([...$transition->from, $transition->to], fn (string $status): bool => ! $this->has($status)));

			if ($outside !== []) {
				return sprintf('its "%s" transition names statuses it doesn\'t list: %s', $transition->name, implode(', ', array_unique($outside)));
			}
		}

		return null;
	}

	/**
	 * Returns it as plain data, for the admin and the API.
	 *
	 * @return array{name: string, label: string, statuses: list<string>, initial: string, transitions: list<array<string, mixed>>, subject: string}
	 */
	public function toArray(): array
	{
		return [
			'name'        => $this->name,
			'label'       => (string) $this->label,
			'statuses'    => $this->statuses,
			'initial'     => $this->initial,
			'transitions' => array_map(static fn (Transition $transition): array => $transition->toArray(), $this->transitions),
			'subject'     => $this->subject->value
		];
	}
}
