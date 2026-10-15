<?php

/**
 * Workflows.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Workflow;

use Blush\Container\Container;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Extension\DefinitionClash;
use Blush\Storage\Record\RecordException;
use Blush\Support\RequestMemo;

/**
 * Every workflow by name (D-735, D-851): core's (`CoreWorkflows`), then
 * extensions' (`WorkflowSource`), then the site's own (`WorkflowTables`,
 * D-853), each of which replaces one by its name, `standard` too; and
 * each type's (`for()`). When two sources register a workflow by one
 * name, the first is kept and the clash is reported (D-597). A workflow
 * that doesn't fit the registered statuses (`Workflow::problem()`), or a
 * site record that can't be read, is left out softly (a site record
 * leaving the workflow it would replace), its types use `standard`, and
 * `problems()` says why, so the site keeps loading.
 */
final readonly class Workflows
{
	public function __construct(
		private CoreWorkflows $core,
		private Statuses $statuses,
		private ContentTypes $types,
		private Container $container,
		private RequestMemo $memo,
		private ?WorkflowTables $tables = null
	) {}

	/**
	 * Returns a subject's workflows by name.
	 *
	 * @return array<string, Workflow>
	 */
	public function all(Subject $subject = Subject::Entries): array
	{
		return array_filter($this->load()[0], static fn (Workflow $workflow): bool => $workflow->subject === $subject);
	}

	/**
	 * Returns a workflow by name, or `null` when there's none (or it was
	 * left out).
	 */
	public function find(string $name): ?Workflow
	{
		return $this->load()[0][$name] ?? null;
	}

	/**
	 * Returns core's standard workflow.
	 */
	public function standard(): Workflow
	{
		/** @var Workflow */
		return $this->load()[0][Workflow::STANDARD];
	}

	/**
	 * Returns a type's workflow: the one it names, or `standard` for a
	 * type that names none, or one there isn't.
	 */
	public function for(ContentType|string $type): Workflow
	{
		$type = is_string($type) ? $this->types->find($type) : $type;
		$name = $type?->workflow;
		$flow = $name === null ? null : $this->find($name);

		return $flow !== null && $flow->subject === Subject::Entries ? $flow : $this->standard();
	}

	/**
	 * Returns the types that use a workflow, by name.
	 *
	 * @return list<string>
	 */
	public function typesUsing(string $workflow): array
	{
		return array_values(array_map(
			static fn (ContentType $type): string => $type->name,
			array_filter($this->types->all(), fn (ContentType $type): bool => $this->for($type)->name === $workflow)
		));
	}

	/**
	 * Says what's wrong with the workflows the sources and types name, by
	 * workflow or type, so the site can be told: workflows left out, and
	 * types naming one there isn't.
	 *
	 * @return list<string>
	 */
	public function problems(): array
	{
		$problems = $this->load()[1];

		foreach ($this->types->all() as $type) {
			if ($type->workflow !== null && $this->find($type->workflow)?->subject !== Subject::Entries) {
				$problems[] = sprintf('The "%s" type names a workflow there isn\'t, "%s", so it uses "standard".', $type->name, $type->workflow);
			}
		}

		return $problems;
	}

	/**
	 * Returns where a workflow comes from (D-854): its `source`, the class
	 * of the source that registered it, or `site` for the site's own; and
	 * `over`, the class of the source a site's record replaces, or `null`.
	 * `null` for a workflow there isn't.
	 *
	 * @return ?array{source: string, over: ?string}
	 */
	public function origin(string $name): ?array
	{
		return $this->load()[3][$name] ?? null;
	}

	/**
	 * Returns the workflows two sources registered by one name.
	 *
	 * @return list<DefinitionClash>
	 */
	public function clashes(): array
	{
		return $this->load()[2];
	}

	/**
	 * Loads the workflows by name, why any were left out, the clashes,
	 * and where each comes from.
	 *
	 * @return array{array<string, Workflow>, list<string>, list<DefinitionClash>, array<string, array{source: string, over: ?string}>}
	 */
	private function load(): array
	{
		/** @var array{array<string, Workflow>, list<string>, list<DefinitionClash>, array<string, array{source: string, over: ?string}>} */
		return $this->memo->remember('workflow|workflows', function (): array {
			$workflows = [];
			$sources   = [];
			$problems  = [];
			$clashes   = [];
			$origins   = [];

			foreach ($this->sources() as $source) {
				foreach ($source->workflows() as $workflow) {
					$kept = $sources[$workflow->name] ?? null;

					if ($kept !== null) {
						$clashes[] = new DefinitionClash('workflow', $workflow->name, $kept, $source::class);

						continue;
					}

					$sources[$workflow->name] = $source::class;
					$problem                  = $workflow->problem($this->statuses->registered($workflow->subject));

					if ($problem !== null) {
						$problems[] = sprintf('The "%s" workflow is left out: %s.', $workflow->name, $problem);

						continue;
					}

					$workflows[$workflow->name] = $workflow;
					$origins[$workflow->name]   = ['source' => $source::class, 'over' => null];
				}
			}

			foreach ($this->siteRecords() as $name => $data) {
				try {
					$workflow = Workflow::fromArray($name, $data);
				} catch (InvalidWorkflow $e) {
					$problems[] = sprintf('The site\'s "%s" workflow is left out: %s', $name, $e->getMessage());

					continue;
				}

				$problem = $workflow->problem($this->statuses->registered($workflow->subject));

				if ($problem !== null) {
					$problems[] = sprintf('The site\'s "%s" workflow is left out: %s.', $name, $problem);

					continue;
				}

				$workflows[$name] = $workflow;
				$origins[$name]   = ['source' => 'site', 'over' => $origins[$name]['source'] ?? null];
			}

			return [$workflows, $problems, $clashes, $origins];
		});
	}

	/**
	 * Returns the site's workflow records, by name, or none when they
	 * can't be read.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function siteRecords(): array
	{
		try {
			return $this->tables?->workflows()->all() ?? [];
		} catch (RecordException) {
			return [];
		}
	}

	/**
	 * Returns the sources: core's first, then those extensions tagged.
	 *
	 * @return iterable<WorkflowSource>
	 * @throws InvalidWorkflow When a tagged service isn't a source.
	 */
	private function sources(): iterable
	{
		yield $this->core;

		foreach ($this->container->tagged(WorkflowSource::TAG) as $source) {
			if (! $source instanceof WorkflowSource) {
				throw new InvalidWorkflow(sprintf('Services tagged "%s" must implement %s; %s does not.', WorkflowSource::TAG, WorkflowSource::class, get_debug_type($source)));
			}

			yield $source;
		}
	}
}
