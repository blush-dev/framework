<?php

/**
 * Workflow writer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Workflow;

use Blush\Content\Type\ContentTypes;
use Blush\Storage\Record\RecordException;
use Blush\Support\RequestMemo;
use Blush\Translation\Messages;

/**
 * Writes the site's own statuses and workflows (D-853, D-854), for the
 * Workflows screen: each checked as `Statuses` and `Workflows` will read
 * it, and refused, saying why, rather than written to be left out. Keys
 * are never renamed: files, types, and roles name them.
 *
 * - **A status** over one core or an extension registers keeps only its
 *   `label`, `plural`, and `tone`; the site's own keeps what it does
 *   too. One a workflow uses can't be deleted.
 * - **A workflow** names registered statuses, and replaces one by its
 *   name. `customize()` copies a core or extension workflow into a site
 *   record; deleting the record reverts it, and deleting the site's own
 *   is refused while types use it.
 *
 * Each write forgets the request's memo, so what's read after it is
 * the new definitions.
 */
final readonly class WorkflowWriter
{
	public function __construct(
		private WorkflowTables $tables,
		private Statuses $statuses,
		private Workflows $workflows,
		private ContentTypes $types,
		private RequestMemo $memo,
		private Messages $messages
	) {}

	/**
	 * Saves the site's record of a status.
	 *
	 * @param  array<string, mixed> $data
	 * @throws InvalidStatus When it can't be one, or can't be written.
	 */
	public function saveStatus(string $name, array $data): Status
	{
		$status = $this->statuses->define($name, $data);
		$base   = $this->statuses->registeredBy($name, $status->subject);

		// Over another, only what differs from it is kept; a plural that's
		// the label is left to follow it, as it's read.
		$relabeled = $base !== null && (string) $status->label !== (string) $base->label;
		$plural    = (string) $status->plural;

		$record = array_filter($base !== null ? [
			'label'  => $relabeled ? (string) $status->label : null,
			'plural' => $plural === (string) $base->plural || ($relabeled && $plural === (string) $status->label) ? null : $plural,
			'tone'   => $status->tone === $base->tone ? null : $status->tone->value
		] : [
			'label'   => (string) $status->label,
			'plural'  => $plural === (string) $status->label ? null : $plural,
			'tone'    => $status->tone === Tone::Neutral ? null : $status->tone->value,
			'live'    => $status->live ?: null,
			'active'  => $status->active ? null : false,
			'subject' => $status->subject === Subject::Entries ? null : $status->subject->value
		], static fn (mixed $value): bool => $value !== null);

		try {
			$this->tables->statuses()->save($name, $record);
		} catch (RecordException $e) {
			throw new InvalidStatus($e->getMessage(), previous: $e);
		}

		$this->memo->forget();

		return $this->statuses->get($name, $status->subject);
	}

	/**
	 * Deletes the site's record of a status: one of its own, unless a
	 * workflow uses it, or one over a registered status, which reverts it.
	 *
	 * @throws InvalidStatus When there's no record, or a workflow uses it.
	 */
	public function deleteStatus(string $name): void
	{
		$origin = $this->statuses->origin($name);

		if ($origin === null || $origin['source'] !== 'site') {
			throw new InvalidStatus((string) $this->messages->t('workflow.writer.no_status', 'The site has no "{name}" status of its own.', name: $name));
		}

		$using = array_values(array_filter($this->workflows->all(), static fn (Workflow $workflow): bool => $workflow->has($name)));

		if ($origin['over'] === null && $using !== []) {
			throw new InvalidStatus((string) $this->messages->t(
				'workflow.writer.status_used',
				'{count, plural, one {The {workflows} workflow uses it} other {The {workflows} workflows use it}}; take it out of {count, plural, one {it} other {them}} first.',
				count: count($using),
				workflows: implode(', ', array_map(static fn (Workflow $workflow): string => (string) $workflow->label, $using))
			));
		}

		try {
			$this->tables->statuses()->delete($name);
		} catch (RecordException $e) {
			throw new InvalidStatus($e->getMessage(), previous: $e);
		}

		$this->memo->forget();
	}

	/**
	 * Saves the site's record of a workflow, which replaces one by its
	 * name.
	 *
	 * @param  array<string, mixed> $data
	 * @throws InvalidWorkflow When it can't be one, or can't be written.
	 */
	public function saveWorkflow(string $name, array $data): Workflow
	{
		$workflow = Workflow::fromArray($name, $data);
		$problem  = $workflow->problem($this->statuses->registered($workflow->subject));

		if ($problem !== null) {
			throw new InvalidWorkflow((string) $this->messages->t('workflow.writer.workflow_problem', 'The workflow can\'t be saved: {problem}.', problem: $problem));
		}

		$record = $workflow->toArray();

		unset($record['name']);

		if ($workflow->subject === Subject::Entries) {
			unset($record['subject']);
		}

		try {
			$this->tables->workflows()->save($name, $record);
		} catch (RecordException $e) {
			throw new InvalidWorkflow($e->getMessage(), previous: $e);
		}

		$this->memo->forget();

		return $this->workflows->find($name) ?? $workflow;
	}

	/**
	 * Copies a core or extension workflow into a site record, to edit.
	 *
	 * @throws InvalidWorkflow When there's no such workflow, or it's already the site's.
	 */
	public function customize(string $name): Workflow
	{
		$workflow = $this->workflows->find($name);

		if ($workflow === null || ($this->workflows->origin($name)['source'] ?? null) === 'site') {
			throw new InvalidWorkflow((string) $this->messages->t('workflow.writer.not_customizable', 'There\'s no "{name}" workflow from Blush or a plugin to customize.', name: $name));
		}

		return $this->saveWorkflow($name, $workflow->toArray());
	}

	/**
	 * Deletes the site's record of a workflow: over one from core or an
	 * extension, which reverts it, or one of its own, unless types use it.
	 *
	 * @throws InvalidWorkflow When there's no record, or types use it.
	 */
	public function deleteWorkflow(string $name): void
	{
		$origin = $this->workflows->origin($name);

		if (! $this->tables->workflows()->has($name)) {
			throw new InvalidWorkflow((string) $this->messages->t('workflow.writer.no_workflow', 'The site has no "{name}" workflow of its own.', name: $name));
		}

		$using = $this->workflows->typesUsing($name);

		if ($origin !== null && $origin['over'] === null && $using !== []) {
			throw new InvalidWorkflow((string) $this->messages->t(
				'workflow.writer.workflow_used',
				'{types} {count, plural, one {uses it} other {use it}}; choose another workflow in {count, plural, one {its} other {their}} Type Settings first.',
				count: count($using),
				types: implode(', ', array_map(fn (string $type): string => (string) ($this->types->find($type)->labels->plural ?? $type), $using))
			));
		}

		try {
			$this->tables->workflows()->delete($name);
		} catch (RecordException $e) {
			throw new InvalidWorkflow($e->getMessage(), previous: $e);
		}

		$this->memo->forget();
	}
}
