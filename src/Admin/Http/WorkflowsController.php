<?php

/**
 * Admin workflows controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Http;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Cache\ContentVersion;
use Blush\Content\Record\EntryRecords;
use Blush\Content\Type\ContentTypes;
use Blush\Http\Response;
use Blush\Http\Status as HttpStatus;
use Blush\Plugin\Plugins;
use Blush\Storage\Record\RecordException;
use Blush\Translation\Messages;
use Blush\Translation\Text;
use Blush\Workflow\InvalidStatus;
use Blush\Workflow\InvalidWorkflow;
use Blush\Workflow\Status;
use Blush\Workflow\Statuses;
use Blush\Workflow\Workflow;
use Blush\Workflow\Workflows;
use Blush\Workflow\WorkflowWriter;

/**
 * Answers the Workflows screen (D-854), for accounts with
 * `site.settings`:
 *
 * - `GET workflows`: `{"workflows", "statuses", "problems"}`. Each
 *   workflow as `describe()` gives it; each status its `name`, `label`,
 *   `plural`, `tone`, `live`, `active`, `source` and `over` (as a
 *   workflow's), the `workflows` using it, and what may be done to it:
 *   `relabel` (one core or an extension registers: its label, plural,
 *   and tone only), `edit` (the site's own: everything), and `delete`
 *   (a site record; over a registered one, it reverts it). Core's own
 *   (`trash`, `scheduled`, `expired`) can't be changed. `problems` says
 *   what was left out (D-853).
 * - `GET workflows/{name}`: the workflow, with `counts`, how many
 *   entries of the types using it are stored with each status.
 * - `PUT workflows/{name}`: `{"label", "statuses", "initial",
 *   "transitions"}`, the site's record (new, or replacing one by its
 *   name); `POST workflows/{name}/customize` copies a core or extension
 *   workflow into one; `DELETE workflows/{name}` deletes it, reverting
 *   one it replaced. Each answers the workflow.
 * - `PUT statuses/{name}`: `{"label", "plural", "tone", "live",
 *   "active"}`; `DELETE statuses/{name}`. Each answers `GET workflows`.
 *
 * A change that can't be saved is a `422` with why, and nothing is
 * written. A change moves the content version, since what's live may
 * have changed.
 */
final readonly class WorkflowsController
{
	public function __construct(
		private Statuses $statuses,
		private Workflows $workflows,
		private WorkflowWriter $writer,
		private ContentTypes $types,
		private EntryRecords $records,
		private Plugins $plugins,
		private Permissions $permissions,
		private ContentVersion $version,
		private Messages $messages
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		return $this->allowed($request) ? self::json($this->listing()) : $this->forbidden();
	}

	public function show(ServerRequestInterface $request, string $name): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return $this->forbidden();
		}

		$workflow = $this->workflows->find($name);

		return $workflow === null
			? self::error($this->messages->t('admin.workflows_controller.not_found', 'There\'s no "{name}" workflow.', name: $name), HttpStatus::NotFound)
			: self::json([...$this->describe($workflow), 'counts' => (object) $this->counts($workflow)]);
	}

	public function save(ServerRequestInterface $request, string $name): ResponseInterface
	{
		return $this->write($request, fn (): Workflow => $this->writer->saveWorkflow($name, self::input($request)));
	}

	public function customize(ServerRequestInterface $request, string $name): ResponseInterface
	{
		return $this->write($request, fn (): Workflow => $this->writer->customize($name));
	}

	public function delete(ServerRequestInterface $request, string $name): ResponseInterface
	{
		return $this->write($request, function () use ($name): ?Workflow {
			$this->writer->deleteWorkflow($name);

			return $this->workflows->find($name);
		});
	}

	public function saveStatus(ServerRequestInterface $request, string $name): ResponseInterface
	{
		return $this->write($request, function () use ($request, $name): null {
			$this->writer->saveStatus($name, self::input($request));

			return null;
		}, listing: true);
	}

	public function deleteStatus(ServerRequestInterface $request, string $name): ResponseInterface
	{
		return $this->write($request, function () use ($name): null {
			$this->writer->deleteStatus($name);

			return null;
		}, listing: true);
	}

	/**
	 * Makes a change, and answers the workflow it left (`deleted` for
	 * none), or the listing.
	 *
	 * @param callable(): ?Workflow $change
	 */
	private function write(ServerRequestInterface $request, callable $change, bool $listing = false): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return $this->forbidden();
		}

		try {
			$workflow = $change();
		} catch (InvalidWorkflow | InvalidStatus $e) {
			return self::error($e->getMessage(), HttpStatus::UnprocessableContent);
		}

		$this->version->bump();

		return self::json(match (true) {
			$listing           => $this->listing(),
			$workflow === null => ['deleted' => true],
			default            => [...$this->describe($workflow), 'counts' => (object) $this->counts($workflow)]
		});
	}

	/**
	 * The Workflows screen's two tabs, and what was left out.
	 *
	 * @return array<string, mixed>
	 */
	private function listing(): array
	{
		$workflows = array_values($this->workflows->all());

		return [
			'workflows' => array_map($this->describe(...), $workflows),
			'statuses'  => array_values(array_map(fn (Status $status): array => $this->status($status, $workflows), $this->statuses->all())),
			'problems'  => [...$this->statuses->problems(), ...$this->workflows->problems()]
		];
	}

	/**
	 * Describes a workflow: its definition (`Workflow::toArray()`), where
	 * it's from (`source`, and `over`, what a site record replaces), the
	 * `types` using it (`{"name", "label"}`), and whether it's the site's
	 * to edit (`editable`) or a core or extension one to `customize`.
	 *
	 * @return array<string, mixed>
	 */
	private function describe(Workflow $workflow): array
	{
		$origin = $this->workflows->origin($workflow->name) ?? ['source' => 'site', 'over' => null];

		return [
			...$workflow->toArray(),
			'source'    => $this->source($origin['source']),
			'over'      => $origin['over'] === null ? null : $this->source($origin['over']),
			'types'     => array_map(fn (string $name): array => ['name' => $name, 'label' => (string) ($this->types->find($name)->labels->plural ?? $name)], $this->workflows->typesUsing($workflow->name)),
			'editable'  => $origin['source'] === 'site',
			'customize' => $origin['source'] !== 'site'
		];
	}

	/**
	 * Describes a status for the Statuses tab.
	 *
	 * @param  list<Workflow>       $workflows
	 * @return array<string, mixed>
	 */
	private function status(Status $status, array $workflows): array
	{
		$origin = $this->statuses->origin($status->name, $status->subject) ?? ['source' => 'core', 'over' => null];
		$core   = $origin['source'] === 'core';
		$own    = $origin['source'] === 'site' && $origin['over'] === null;

		return [
			...$status->toArray(),
			'source'    => $this->source($origin['source']),
			'over'      => $origin['over'] === null ? null : $this->source($origin['over']),
			'workflows' => array_values(array_map(static fn (Workflow $workflow): string => $workflow->name, array_filter($workflows, static fn (Workflow $workflow): bool => $workflow->has($status->name)))),
			'relabel'   => ! $core && ! $own,
			'edit'      => $own,
			'delete'    => $origin['source'] === 'site'
		];
	}

	/**
	 * Says where a definition is from: `core`, `site`, or a `plugin` by its
	 * label.
	 *
	 * @return array{kind: string, label: string}
	 */
	private function source(string $source): array
	{
		return match (true) {
			$source === 'site'                                 => ['kind' => 'site', 'label' => (string) $this->messages->t('admin.workflows_controller.site', 'Site')],
			$source === 'core', str_starts_with($source, 'Blush\\') => ['kind' => 'core', 'label' => 'Blush'],
			default                                            => ['kind' => 'plugin', 'label' => $this->plugins->owning($source)->label ?? $source]
		};
	}

	/**
	 * Returns how many entries of the types using a workflow are stored
	 * with each status, by name.
	 *
	 * @return array<string, int>
	 */
	private function counts(Workflow $workflow): array
	{
		$types = $this->workflows->typesUsing($workflow->name);

		if ($types === []) {
			return [];
		}

		try {
			$found = $this->records->entries()->where('type', 'in', $types)->countBy('status');
		} catch (RecordException) {
			return [];
		}

		$counts = [];

		foreach ($found as $row) {
			$counts[(string) $row['value']] = $row['count'];
		}

		return $counts;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function input(ServerRequestInterface $request): array
	{
		try {
			$input = json_decode((string) $request->getBody(), true, 64, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return [];
		}

		return is_array($input) ? array_filter($input, static fn (mixed $value, int|string $key): bool => is_string($key), ARRAY_FILTER_USE_BOTH) : [];
	}

	private function allowed(ServerRequestInterface $request): bool
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account && $this->permissions->can($account, Capability::SiteSettings);
	}

	private function forbidden(): ResponseInterface
	{
		return self::error($this->messages->t('admin.workflows_controller.forbidden', 'You aren\'t allowed to change workflows.'), HttpStatus::Forbidden);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data): ResponseInterface
	{
		return Response::json($data, headers: ['Cache-Control' => 'no-store']);
	}

	private static function error(string|Text $message, HttpStatus $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
