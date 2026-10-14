<?php

/**
 * Admin site health.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Health;

use Blush\Admin\AdminConfig;
use Blush\Extension\ExtensionText;
use DateTimeInterface;
use DateTimeZone;
use Psr\Clock\ClockInterface;
use Blush\Cache\CacheConfig;
use Blush\Cache\CacheDriver;
use Blush\Content\Entries;
use Blush\Content\Lint\LintReport;
use Blush\Content\Source\ContentFiles;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageConfig;
use Blush\Core\AppConfig;
use Blush\Core\Framework;
use Blush\Core\Language;
use Blush\Core\Paths;
use Blush\Extension\ExtensionState;
use Blush\Extension\Requirement;
use Blush\Extension\RequirementKind;
use Blush\Extension\VersionConstraint;
use Blush\Media\Index\MediaLibrary;
use Blush\Media\Index\MediaQuery;
use Blush\Media\MediaConfig;
use Blush\Setup\CheckResult;
use Blush\Setup\CheckStatus;
use Blush\Setup\SetupChecks;
use Blush\Setup\SiteChecks;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Site Health's report (D-543, D-545): its checks, requirements, and
 * facts, as the web server's PHP sees them (`doctor` runs under the
 * command line's, which may differ). `run()` checks everything and keeps
 * the report (`HealthReportStore`), so the screen shows the last one at
 * once and checks again only when asked; `saved()` is that report, and
 * `current()` is it with the requirements and facts read again, since
 * they cost nothing and a kept copy goes stale unseen (D-615). It
 * keeps the content and media files' full report (`files`), which their
 * detail screens show (`files()`); checking those again (`checkFiles()`,
 * as after a fix) sums them up again in the report too (D-546).
 *
 * - `checked`: when, in ISO 8601.
 * - `areas`: `content`, `media`, `extensions`, `system`, and `accounts`,
 *   each with a `label` and `description`.
 * - `checks`: each with its `area`, `key`, `status` (`pass`, `warning`,
 *   or `failure`), `label`, `message`, and `hint`, and `link`, what the
 *   admin opens for it, or `null`. Content and media sum up
 *   `ContentHealth`, linking to their details; the rest are `doctor`'s
 *   (`SiteChecks`).
 * - `requirements`: what Blush needs against what's installed, in
 *   `group`s, each with its `name`, `why` (what uses it), `needs`,
 *   `installed`, and `status` (`pass`, `warning`, `failure`, or
 *   `optional` for an optional extension that isn't loaded). Only what
 *   Blush really needs: PHP and its required extensions, the optional
 *   ones by what uses them, the upload limits against the Media
 *   settings, and writable storage; and what the plugins, themes, and
 *   icon packs that are on ask of PHP, each saying which (D-641).
 * - `site` and `server`: facts for a bug report, each a `label`, a
 *   `value`, and whether it's `mono` (machine data).
 * - `files`: content and media files' `ContentHealth` report, notices
 *   included, with `at`, when it was made.
 *
 * Its words are kept as messages (D-836): each check's label, message,
 * and hint, and each problem's message, as `Text::toArray()` has them,
 * and they're turned into text when the report is read, in the
 * reader's language. A report kept before then shows its words as they
 * were kept, until it's checked again.
 */
final readonly class SiteHealth
{
	/**
	 * Optional extensions, each whether it's recommended; recommended
	 * ones warn when they're missing. What uses each is `optionalWhy()`.
	 *
	 * @var array<string, bool>
	 */
	private const array OPTIONAL = [
		'opcache'  => true,
		'fileinfo' => true,
		'zip'      => false,
		'exif'     => false,
		'zlib'     => false,
		'apcu'     => false
	];

	public function __construct(
		private ContentHealth $health,
		private SiteChecks $checks,
		private SetupChecks $setup,
		private AppConfig $app,
		private AdminConfig $admin,
		private CacheConfig $cache,
		private MediaConfig $media,
		private Paths $paths,
		private ExtensionState $extensions,
		private Entries $content,
		private ContentFiles $contentFiles,
		private StorageConfig $storage,
		private MediaLibrary $library,
		private ClockInterface $clock,
		private HealthReportStore $store,
		private IgnoredProblems $ignored,
		private ExtensionText $text,
		private Messages $messages
	) {}

	/**
	 * Checks everything, keeps the report, and returns it. `$server` is
	 * the request's server parameters, for the web server's name.
	 *
	 * @param  array<array-key, mixed> $server
	 * @return array<string, mixed>
	 */
	public function run(array $server = []): array
	{
		$report = [
			'checked'      => $this->clock->now()->format(DateTimeInterface::ATOM),
			'areas'        => $this->areas(),
			'checks'       => [...$this->summarizeFiles($files = $this->fileReport()), ...$this->site()],
			'files'        => $files,
			'requirements' => $this->requirements(),
			'site'         => $this->siteFacts(),
			'server'       => $this->serverFacts($server)
		];

		$this->store->save($report);

		return $this->readable($report);
	}

	/**
	 * Returns the last report, or `null` before the first.
	 *
	 * @return ?array<string, mixed>
	 */
	public function saved(): ?array
	{
		$report = $this->store->load();

		return $report === null ? null : $this->readable($report);
	}

	/**
	 * Returns the last report with its requirements and facts read now,
	 * or checks everything when there's no report.
	 *
	 * @param  array<array-key, mixed> $server
	 * @return array<string, mixed>
	 */
	public function current(array $server = []): array
	{
		$report = $this->store->load();

		if ($report === null) {
			return $this->run($server);
		}

		return $this->readable([
			...$report,
			'areas'        => $this->areas(),
			'requirements' => $this->requirements(),
			'site'         => $this->siteFacts(),
			'server'       => $this->serverFacts($server)
		]);
	}

	/**
	 * Returns the areas checked, each with its `label` and `description`,
	 * in the current language: a report keeps them, but they're read
	 * again with it, since they never change.
	 *
	 * @return list<array{key: string, label: Text, description: Text}>
	 */
	private function areas(): array
	{
		return [
			['key' => 'content', 'label' => $this->messages->t('admin.site_health.areas.content.label', 'Content'), 'description' => $this->messages->t('admin.site_health.areas.content.description', 'Entries\' files: their fields, ids, terms, parents, folders, and names')],
			['key' => 'media', 'label' => $this->messages->t('admin.site_health.areas.media.label', 'Media'), 'description' => $this->messages->t('admin.site_health.areas.media.description', 'Library files\' ids, details, and image sizes')],
			['key' => 'extensions', 'label' => $this->messages->t('admin.site_health.areas.extensions.label', 'Extensions'), 'description' => $this->messages->t('admin.site_health.areas.extensions.description', 'The theme, plugins, and icon packs that are on')],
			['key' => 'system', 'label' => $this->messages->t('admin.site_health.areas.system.label', 'System'), 'description' => $this->messages->t('admin.site_health.areas.system.description', 'PHP, settings, the public folder, and storage')],
			['key' => 'accounts', 'label' => $this->messages->t('admin.site_health.areas.accounts.label', 'Accounts'), 'description' => $this->messages->t('admin.site_health.areas.accounts.description', 'Who can always get back in')]
		];
	}

	/**
	 * Whether the last report has its content and media files, in their
	 * current shape, so checking them again can be left to a job.
	 */
	public function hasFiles(): bool
	{
		$report = $this->store->load();
		$files  = $report['files'] ?? null;

		return $report !== null && is_array($report['checks'] ?? null) && is_array($files) && ($files['version'] ?? null) === ContentHealth::VERSION;
	}

	/**
	 * Checks everything but the content and media files again, which a
	 * job checks a chunk at a time (`HealthCheckJob`, D-625), keeping the
	 * last report's files and their summary, and returns the report.
	 * The rest is cheap, and is checked here so it's the web server's
	 * PHP that's described, not cron's. Without a report, or one whose
	 * files are in an older shape, it checks everything.
	 *
	 * @param  array<array-key, mixed> $server
	 * @return array<string, mixed>
	 */
	public function checkSite(array $server = []): array
	{
		$report = $this->store->load();

		if ($report === null || ! $this->hasFiles() || ! is_array($report['checks'] ?? null)) {
			return $this->run($server);
		}

		$kept = array_values(array_filter($report['checks'], static fn (mixed $check): bool => is_array($check) && in_array($check['area'] ?? null, ['content', 'media'], true)));

		$report = [
			...$report,
			'checked'      => $this->clock->now()->format(DateTimeInterface::ATOM),
			'areas'        => $this->areas(),
			'checks'       => [...$kept, ...$this->site()],
			'requirements' => $this->requirements(),
			'site'         => $this->siteFacts(),
			'server'       => $this->serverFacts($server)
		];

		$this->store->save($report);

		return $this->readable($report);
	}

	/**
	 * Returns the last report's content and media files (`ContentHealth`,
	 * notices included, with `at`, when), checking first when there's no
	 * report, or its files are in an older shape (D-612).
	 *
	 * @param  array<array-key, mixed> $server
	 * @return array<string, mixed>
	 */
	public function files(array $server = []): array
	{
		$files = $this->store->load()['files'] ?? null;

		return is_array($files) && ($files['version'] ?? null) === ContentHealth::VERSION ? $this->readableFiles(array_filter($files, is_string(...), ARRAY_FILTER_USE_KEY)) : $this->checkFiles($server);
	}

	/**
	 * Checks content and media files again, keeping them and their
	 * summary in the last report (the rest of it as it was), and returns
	 * them. Without a report, it checks everything. `$lint` is a lint of
	 * the files already done, a chunk at a time (`HealthCheckJob`,
	 * D-625).
	 *
	 * @param  array<array-key, mixed> $server
	 * @return array<string, mixed>
	 */
	public function checkFiles(array $server = [], ?LintReport $lint = null): array
	{
		$report = $this->store->load();

		if ($report === null || ! is_array($report['checks'] ?? null)) {
			$files = $this->run($server)['files'];

			return is_array($files) ? array_filter($files, is_string(...), ARRAY_FILTER_USE_KEY) : [];
		}

		$files  = $this->fileReport($lint);
		$others = array_values(array_filter($report['checks'], static fn (mixed $check): bool => ! is_array($check) || ! in_array($check['area'] ?? null, ['content', 'media'], true)));

		$report['checks'] = [...$this->summarizeFiles($files), ...$others];
		$report['files']  = $files;
		$this->store->save($report);

		return $this->readableFiles($files);
	}

	/**
	 * Sums up the last report's content and media files again, without
	 * checking them, as after a problem is ignored or no longer is
	 * (D-613), and keeps it.
	 */
	public function resummarize(): void
	{
		$report = $this->store->load();
		$files  = $report['files'] ?? null;

		if ($report === null || ! is_array($report['checks'] ?? null) || ! is_array($files) || ($files['version'] ?? null) !== ContentHealth::VERSION) {
			return;
		}

		/** @var array{files: list<array{path: string, area: string, violations: list<array{field: string, message: string|array<string, mixed>, severity: string, kind: ?string}>}>, checked: int, metadata: int, ids: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, mediaIds: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, fileNames: list<array{count: int, items: list<array{path: string, to: string}>}>, folders: array{count: int, items: list<array{path: string, to: string}>}, terms: array{count: int, items: list<array{type: string, slug: string}>}, parents: array{count: int, items: list<array{type: string, key: string}>}, refs: array{count: int, items: list<array{path: string}>}, taxonomies: list<string>, typeFolders: list<array{name: string, from: string, to: string}>, mediaSizes: array{sizes: int, images: int, stale: int, items: list<array{key: string, unrecorded: int, stale: int}>}} $files */
		$others = array_values(array_filter($report['checks'], static fn (mixed $check): bool => ! is_array($check) || ! in_array($check['area'] ?? null, ['content', 'media'], true)));

		$report['checks'] = [...$this->summarize($this->withoutIgnored($files)), ...$others];
		$this->store->save($report);
	}

	/**
	 * Sums up a new files report, first forgetting the ignored problems
	 * it no longer finds (D-613).
	 *
	 * @param  array{files: list<array{path: string, area: string, violations: list<array{field: string, message: string|array<string, mixed>, severity: string, kind: ?string}>}>, checked: int, metadata: int, ids: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, mediaIds: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, fileNames: list<array{count: int, items: list<array{path: string, to: string}>}>, folders: array{count: int, items: list<array{path: string, to: string}>}, terms: array{count: int, items: list<array{type: string, slug: string}>}, parents: array{count: int, items: list<array{type: string, key: string}>}, refs: array{count: int, items: list<array{path: string}>}, taxonomies: list<string>, typeFolders: list<array{name: string, from: string, to: string}>, mediaSizes: array{sizes: int, images: int, stale: int, items: list<array{key: string, unrecorded: int, stale: int}>}} $files
	 * @return list<array<string, mixed>>
	 */
	private function summarizeFiles(array $files): array
	{
		$this->ignored->keep(ProblemKeys::all($files));

		return $this->summarize($this->withoutIgnored($files));
	}

	/**
	 * Returns a files report without its ignored problems, so they don't
	 * count (D-613).
	 *
	 * @param  array{files: list<array{path: string, area: string, violations: list<array{field: string, message: string|array<string, mixed>, severity: string, kind: ?string}>}>, checked: int, metadata: int, ids: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, mediaIds: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, fileNames: list<array{count: int, items: list<array{path: string, to: string}>}>, folders: array{count: int, items: list<array{path: string, to: string}>}, terms: array{count: int, items: list<array{type: string, slug: string}>}, parents: array{count: int, items: list<array{type: string, key: string}>}, refs: array{count: int, items: list<array{path: string}>}, taxonomies: list<string>, typeFolders: list<array{name: string, from: string, to: string}>, mediaSizes: array{sizes: int, images: int, stale: int, items: list<array{key: string, unrecorded: int, stale: int}>}} $files
	 * @return array{files: list<array{path: string, area: string, violations: list<array{field: string, message: string|array<string, mixed>, severity: string, kind: ?string}>}>, checked: int, metadata: int, ids: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, mediaIds: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, fileNames: list<array{count: int, items: list<array{path: string, to: string}>}>, folders: array{count: int, items: list<array{path: string, to: string}>}, terms: array{count: int, items: list<array{type: string, slug: string}>}, parents: array{count: int, items: list<array{type: string, key: string}>}, refs: array{count: int, items: list<array{path: string}>}, taxonomies: list<string>, typeFolders: list<array{name: string, from: string, to: string}>, mediaSizes: array{sizes: int, images: int, stale: int, items: list<array{key: string, unrecorded: int, stale: int}>}}
	 */
	private function withoutIgnored(array $files): array
	{
		$ignored = $this->ignored->all();

		if ($ignored === []) {
			return $files;
		}

		$out = static fn (string $key): bool => ! isset($ignored[$key]);

		foreach ($files['files'] as $index => $file) {
			$files['files'][$index]['violations'] = array_values(array_filter($file['violations'], static fn (array $violation): bool => $out(ProblemKeys::file($file['area'], $file['path'], $violation['field'], ProblemKeys::message($violation['message'])))));
		}

		$files['files'] = array_values(array_filter($files['files'], static fn (array $file): bool => $file['violations'] !== []));

		foreach (['content' => 'ids', 'media' => 'mediaIds'] as $area => $name) {
			$files[$name]['missing']    = array_values(array_filter($files[$name]['missing'], static fn (string $path): bool => $out(ProblemKeys::id($area, $path))));
			$files[$name]['duplicates'] = array_values(array_filter($files[$name]['duplicates'], static fn (array $shared): bool => $out(ProblemKeys::sharedId($area, $shared['id']))));
		}

		$files['terms']['items'] = array_values(array_filter($files['terms']['items'], static fn (array $item): bool => $out(ProblemKeys::of('content', 'terms', "{$item['type']}/{$item['slug']}"))));
		$files['terms']['count'] = count($files['terms']['items']);
		$files['parents']['items'] = array_values(array_filter($files['parents']['items'], static fn (array $item): bool => $out(ProblemKeys::of('content', 'parents', "{$item['type']}/{$item['key']}"))));
		$files['parents']['count'] = count($files['parents']['items']);
		$files['refs']['items']  = array_values(array_filter($files['refs']['items'], static fn (array $item): bool => $out(ProblemKeys::of('content', 'refs', $item['path']))));
		$files['refs']['count']  = count($files['refs']['items']);
		$files['folders']['items']  = array_values(array_filter($files['folders']['items'], static fn (array $item): bool => $out(ProblemKeys::of('content', 'folders', $item['path']))));
		$files['folders']['count']  = count($files['folders']['items']);

		foreach ($files['fileNames'] as $index => $names) {
			$files['fileNames'][$index]['items'] = array_values(array_filter($names['items'], static fn (array $item): bool => $out(ProblemKeys::of('content', 'names', $item['path']))));
			$files['fileNames'][$index]['count'] = count($files['fileNames'][$index]['items']);
		}

		$files['taxonomies'] = array_values(array_filter($files['taxonomies'], static fn (string $name): bool => $out(ProblemKeys::of('content', 'taxonomies', $name))));
		$files['typeFolders'] = array_values(array_filter($files['typeFolders'], static fn (array $item): bool => $out(ProblemKeys::of('content', 'types', $item['name']))));

		$files['mediaSizes']['items']  = array_values(array_filter($files['mediaSizes']['items'], static fn (array $item): bool => $out(ProblemKeys::of('media', 'sizes', $item['key']))));
		$files['mediaSizes']['images'] = count(array_filter($files['mediaSizes']['items'], static fn (array $item): bool => $item['unrecorded'] > 0));
		$files['mediaSizes']['stale']  = count(array_filter($files['mediaSizes']['items'], static fn (array $item): bool => $item['stale'] > 0));

		return $files;
	}

	/**
	 * Returns a new `ContentHealth` report, notices included (the admin
	 * hides them until asked), and when it was made (`at`).
	 *
	 * @return array{at: string, version: int, checked: int, metadata: int, strict: bool, counts: array{error: int, warning: int, notice: ?int}, files: list<array{path: string, area: string, violations: list<array{field: string, message: string|array<string, mixed>, severity: string, kind: ?string}>}>, entries: array<string, array{title: string, type: string, id: ?string}>, ids: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, mediaIds: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, fileNames: list<array{type: string, label: string, pattern: string, count: int, items: list<array{path: string, to: string}>, skipped: int}>, folders: array{count: int, items: list<array{path: string, to: string}>}, terms: array{count: int, items: list<array{type: string, label: string, slug: string, title: string, entries: int}>}, parents: array{count: int, items: list<array{type: string, label: string, key: string, title: string, pages: int}>}, refs: array{count: int, items: list<array{path: string, relations: list<string>}>}, taxonomies: list<string>, typeFolders: list<array{name: string, from: string, to: string}>, mediaSizes: array{sizes: int, images: int, stale: int, items: list<array{key: string, unrecorded: int, stale: int}>}}
	 */
	private function fileReport(?LintReport $lint = null): array
	{
		return ['at' => $this->clock->now()->format(DateTimeInterface::ATOM), ...$this->health->report(true, $lint)];
	}

	/**
	 * Returns how many checks in the last report aren't passing, or
	 * `null` before the first.
	 */
	public function issues(): ?int
	{
		$checks = $this->store->load()['checks'] ?? null;

		return is_array($checks) ? count(array_filter($checks, static fn (mixed $check): bool => is_array($check) && ($check['status'] ?? 'pass') !== 'pass')) : null;
	}

	/**
	 * Sums up content and media files' health, each linking to its area's
	 * details. Notices, when the report has them, don't count.
	 *
	 * @param  array{files: list<array{path: string, area: string, violations: list<array{field: string, message: string|array<string, mixed>, severity: string, kind: ?string}>}>, checked: int, metadata: int, ids: array{missing: list<string>, duplicates: list<mixed>}, mediaIds: array{missing: list<string>, duplicates: list<mixed>}, fileNames: list<array{count: int}>, folders: array{count: int}, terms: array{count: int}, parents: array{count: int}, refs: array{count: int}, taxonomies: list<string>, typeFolders: list<array{name: string, from: string, to: string}>, mediaSizes: array{sizes: int, images: int, stale: int}} $report
	 * @return list<array<string, mixed>>
	 */
	private function summarize(array $report): array
	{
		$checks = [];

		foreach (['content', 'media'] as $area) {
			$label    = $area === 'content' ? $this->messages->t('admin.site_health.checks.content_files', 'Content files') : $this->messages->t('admin.site_health.checks.media_details', 'Media details');
			$files    = array_filter($report['files'], static fn (array $file): bool => $file['area'] === $area);
			$worst    = array_map(static fn (array $file): string => in_array('error', array_column($file['violations'], 'severity'), true) ? 'error' : (in_array('warning', array_column($file['violations'], 'severity'), true) ? 'warning' : 'notice'), $files);
			$errors   = count(array_keys($worst, 'error', true));
			$warnings = count(array_keys($worst, 'warning', true));
			$checked  = $area === 'content' ? $report['checked'] : $report['metadata'];

			$checks[] = self::check($area, 'files', match (true) {
				$errors > 0 && $warnings > 0 => CheckResult::failure($label, $this->messages->t('admin.site_health.checks.files_errors_warnings', '{errors, plural, one {# file has errors} other {# files have errors}}, and {warnings, plural, one {# has warnings} other {# have warnings}}.', errors: $errors, warnings: $warnings)),
				$errors > 0                  => CheckResult::failure($label, $this->messages->t('admin.site_health.checks.files_errors', '{count, plural, one {# file has errors} other {# files have errors}}.', count: $errors)),
				$warnings > 0                => CheckResult::warning($label, $this->messages->t('admin.site_health.checks.files_warnings', '{count, plural, one {# file has warnings} other {# files have warnings}}.', count: $warnings)),
				default                      => CheckResult::pass($label, $this->messages->t('admin.site_health.checks.files_pass', '{count, plural, one {# file} other {# files}} checked, and nothing is wrong.', count: $checked))
			}, $area);
		}

		foreach (['content' => 'ids', 'media' => 'mediaIds'] as $area => $key) {
			$label   = $area === 'content' ? $this->messages->t('admin.site_health.checks.entry_ids', 'Entry ids') : $this->messages->t('admin.site_health.checks.media_ids', 'Media ids');
			$missing = count($report[$key]['missing']);
			$shared  = count($report[$key]['duplicates']);

			$checks[] = self::check($area, 'ids', $missing + $shared > 0
				? CheckResult::warning($label, match (true) {
					$missing > 0 && $shared > 0 => $this->messages->t('admin.site_health.checks.ids_missing_shared', '{missing, plural, one {# file has no id} other {# files have no id}}, and {shared, plural, one {# id is shared} other {# ids are shared}}.', missing: $missing, shared: $shared),
					$missing > 0                => $this->messages->t('admin.site_health.checks.ids_missing', '{count, plural, one {# file has no id} other {# files have no id}}.', count: $missing),
					default                     => $this->messages->t('admin.site_health.checks.ids_shared', '{count, plural, one {# id is shared} other {# ids are shared}}.', count: $shared)
				}, $this->messages->t('admin.site_health.checks.ids_hint', 'A file without an id can\'t be opened in the admin.'))
				: CheckResult::pass($label, $area === 'content' ? $this->messages->t('admin.site_health.checks.ids_pass_content', 'Every entry has an id of its own.') : $this->messages->t('admin.site_health.checks.ids_pass_media', 'Every media file has an id of its own.')), $area);
		}

		$folders = $report['folders']['count'];
		$label   = $this->messages->t('admin.site_health.checks.folders', 'Collection folders');

		$checks[] = self::check('content', 'folders', $folders > 0
			? CheckResult::warning($label, $this->messages->t('admin.site_health.checks.folders_warning', '{count, plural, one {# entry is not in the folder its type keeps it in} other {# entries are not in the folder their type keeps it in}}.', count: $folders))
			: CheckResult::pass($label, $this->messages->t('admin.site_health.checks.folders_pass', 'Every collection\'s entries are files in the folders it keeps them in.')), 'content');

		$terms = $report['terms']['count'];
		$label = $this->messages->t('admin.site_health.checks.terms', 'Terms and profiles');

		$checks[] = self::check('content', 'terms', $terms > 0
			? CheckResult::warning($label, $this->messages->t('admin.site_health.checks.terms_warning', '{count, plural, one {# term or profile entries name has no file, so the site leaves it out} other {# terms and profiles entries name have no file, so the site leaves them out}}.', count: $terms))
			: CheckResult::pass($label, $this->messages->t('admin.site_health.checks.terms_pass', 'Every term and profile entries name has a file.')), 'content');

		$parents = $report['parents']['count'];
		$label   = $this->messages->t('admin.site_health.checks.parents', 'Parent pages');

		$checks[] = self::check('content', 'parents', $parents > 0
			? CheckResult::warning($label, $this->messages->t('admin.site_health.checks.parents_warning', '{count, plural, one {# folder pages are kept in has no page, so the pages under it are at the top of their tree} other {# folders pages are kept in have no page, so the pages under them are at the top of their tree}}.', count: $parents))
			: CheckResult::pass($label, $this->messages->t('admin.site_health.checks.parents_pass', 'Every page is under a parent page that exists.')), 'content');

		$refs  = $report['refs']['count'];
		$label = $this->messages->t('admin.site_health.checks.refs', 'Links between entries');

		$checks[] = self::check('content', 'refs', $refs > 0
			? CheckResult::warning($label, $this->messages->t('admin.site_health.checks.refs_warning', '{count, plural, one {# file has links not filed with their ids, so a rename or move of what it link to can break them} other {# files have links not filed with their ids, so a rename or move of what they link to can break them}}.', count: $refs))
			: CheckResult::pass($label, $this->messages->t('admin.site_health.checks.refs_pass', 'Every link between entries is filed with its id.')), 'content');

		$taxonomies = count($report['taxonomies']);
		$label      = $this->messages->t('admin.site_health.checks.taxonomies', 'Taxonomies');

		$checks[] = self::check('content', 'taxonomies', $taxonomies > 0
			? CheckResult::warning($label, $this->messages->t('admin.site_health.checks.taxonomies_warning', '{count, plural, one {# content type is still written as a taxonomy, which Blush reads as a collection and its relation until it\'s migrated} other {# content types are still written as taxonomies, which Blush reads as collections and their relations until they\'re migrated}}.', count: $taxonomies))
			: CheckResult::pass($label, $this->messages->t('admin.site_health.checks.taxonomies_pass', 'Every content type is written as a collection or a tree.')), 'content');

		$named = count($report['typeFolders']);
		$label = $this->messages->t('admin.site_health.checks.types', 'Type folders');

		$checks[] = self::check('content', 'types', $named > 0
			? CheckResult::warning($label, $this->messages->t('admin.site_health.checks.types_warning', '{count, plural, one {# content type names its own folder, so its entries aren\'t found until it\'s moved into _ and its name} other {# content types name their own folder, so their entries aren\'t found until they\'re moved into _ and their name}}.', count: $named))
			: CheckResult::pass($label, $this->messages->t('admin.site_health.checks.types_pass', 'Every content type is kept in _ and its name.')), 'content');

		$renames = array_sum(array_column($report['fileNames'], 'count'));
		$label   = $this->messages->t('admin.site_health.checks.names', 'File names');

		$checks[] = self::check('content', 'names', $renames > 0
			? CheckResult::warning($label, $this->messages->t('admin.site_health.checks.names_warning', '{count, plural, one {# entry is} other {# entries are}} named by an older pattern than its type\'s.', count: $renames), $this->messages->t('admin.site_health.checks.names_hint', 'Older names keep working; renaming them changes no address.'))
			: CheckResult::pass($label, $this->messages->t('admin.site_health.checks.names_pass', 'Every entry is named by its type\'s pattern.')), 'content');

		$sizes = $report['mediaSizes'];
		$label = $this->messages->t('admin.site_health.checks.sizes', 'Image sizes');

		$checks[] = self::check('media', 'sizes', $sizes['images'] + $sizes['stale'] > 0
			? CheckResult::warning($label, match (true) {
				$sizes['images'] > 0 && $sizes['stale'] > 0 => $this->messages->t('admin.site_health.checks.sizes_unlisted_stale', '{images, plural, one {# image doesn\'t list its sizes} other {# images don\'t list their sizes}}, and {stale, plural, one {# lists files that aren\'t its sizes} other {# list files that aren\'t their sizes}}.', images: $sizes['images'], stale: $sizes['stale']),
				$sizes['images'] > 0                       => $this->messages->t('admin.site_health.checks.sizes_unlisted', '{count, plural, one {# image doesn\'t list its sizes} other {# images don\'t list their sizes}}.', count: $sizes['images']),
				default                                    => $this->messages->t('admin.site_health.checks.sizes_stale', '{count, plural, one {# image lists files that aren\'t its sizes} other {# images list files that aren\'t their sizes}}.', count: $sizes['stale'])
			})
			: CheckResult::pass($label, $this->messages->t('admin.site_health.checks.sizes_pass', 'Every image lists its sizes.')), 'media');

		return $checks;
	}

	/**
	 * Returns `doctor`'s checks: extensions, the system, and accounts.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function site(): array
	{
		$links  = ['theme' => 'themes', 'plugins' => 'plugins', 'icon-packs' => 'icon-packs', 'admin-themes' => 'admin-themes', 'owner' => 'account'];
		$checks = [];

		foreach ($this->checks->byArea() as $area => $results) {
			foreach ($results as $key => $result) {
				$checks[] = self::check($area, is_string($key) ? $key : "{$area}-{$key}", $result, is_string($key) && $result->status !== CheckStatus::Pass ? ($links[$key] ?? null) : null);
			}
		}

		return $checks;
	}

	/**
	 * Returns what Blush needs against what's installed.
	 *
	 * @return list<array{group: string|Text, name: string, why: string|Text, needs: string|Text, installed: string|Text, status: string}>
	 */
	private function requirements(): array
	{
		$extensions = $this->messages->t('admin.site_health.requirements.groups.extensions', 'PHP Extensions');
		$required   = $this->messages->t('admin.site_health.requirements.required', 'required');
		$missing    = $this->messages->t('admin.site_health.requirements.missing', 'missing');

		$rows = [[
			'group'     => 'PHP',
			'name'      => 'PHP',
			'why'       => '',
			'needs'     => $this->messages->t('admin.site_health.requirements.or_newer', '{version} or newer', version: SetupChecks::MINIMUM_PHP),
			'installed' => PHP_VERSION,
			'status'    => version_compare(PHP_VERSION, SetupChecks::MINIMUM_PHP, '>=') ? 'pass' : 'failure'
		]];

		[$asked, $suggested] = $this->asked();

		foreach (SetupChecks::EXTENSIONS as $extension) {
			$loaded = extension_loaded($extension);
			$rows[] = ['group' => $extensions, 'name' => $extension, 'why' => $this->setup->why($extension), 'needs' => $required, 'installed' => $loaded ? $this->loadedWord() : $missing, 'status' => $loaded ? 'pass' : 'failure'];

			// A plugin asking for versions this one isn't keeps its own row.
			if (array_all($asked["ext-{$extension}"] ?? [], static fn (Requirement $requirement): bool => $requirement->met)) {
				unset($asked["ext-{$extension}"]);
			}

			unset($suggested["ext-{$extension}"]);
		}

		foreach (self::OPTIONAL as $extension => $recommended) {
			$loaded  = $extension === 'opcache' ? self::opcache() : extension_loaded($extension);
			$asking  = $asked["ext-{$extension}"] ?? [];
			$cache   = $extension === 'apcu' && in_array(CacheDriver::Apcu->value, [$this->cache->driver, ...array_values($this->cache->stores)], true);
			$needed  = $cache || $asking !== [];
			$met     = $loaded && array_all($asking, static fn (Requirement $requirement): bool => $requirement->met);
			$why     = $cache ? $this->messages->t('admin.site_health.requirements.why.apcu_driver', 'Keeps the cache in memory: this site\'s cache driver is set to apcu') : $this->optionalWhy($extension);
			$rows[]  = [
				'group'     => $extensions,
				'name'      => $extension,
				'why'       => $asking === [] ? $why : $this->messages->t('admin.site_health.requirements.why_and_required_by', '{why}; required by {extensions}', why: $why, extensions: self::labels($asking)),
				'needs'     => $needed ? $this->needs($asking) : ($recommended ? $this->messages->t('admin.site_health.requirements.recommended', 'recommended') : $this->messages->t('admin.site_health.requirements.optional', 'optional')),
				'installed' => $loaded ? ($extension === 'opcache' ? $this->messages->t('admin.site_health.requirements.on', 'on') : $this->loaded($extension, $asking)) : ($extension === 'opcache' && extension_loaded('Zend OPcache') ? $this->messages->t('admin.site_health.requirements.off', 'off') : $missing),
				'status'    => $met || (! $needed && $loaded) ? 'pass' : ($needed ? 'failure' : ($recommended ? 'warning' : 'optional'))
			];

			unset($asked["ext-{$extension}"], $suggested["ext-{$extension}"]);
		}

		foreach ($asked as $name => $asking) {
			$extension = substr($name, 4);
			$php       = $name === 'php';
			$loaded    = $php || extension_loaded($extension);
			$rows[]    = [
				'group'     => $this->messages->t('admin.site_health.requirements.groups.extensions_asked', 'Plugins and Themes'),
				'name'      => $php ? 'PHP' : $extension,
				'why'       => $this->messages->t('admin.site_health.requirements.required_by', 'Required by {extensions}', extensions: self::labels($asking)),
				'needs'     => $this->needs($asking),
				'installed' => $php ? PHP_VERSION : ($loaded ? $this->loaded($extension, $asking) : $missing),
				'status'    => array_all($asking, static fn (Requirement $requirement): bool => $requirement->met) ? 'pass' : 'failure'
			];

			unset($suggested[$name]);
		}

		foreach ($suggested as $name => $reasons) {
			$extension = substr($name, 4);
			$loaded    = extension_loaded($extension);
			$rows[]    = [
				'group'     => $this->messages->t('admin.site_health.requirements.groups.extensions_asked', 'Plugins and Themes'),
				'name'      => $extension,
				'why'       => implode('; ', array_map(fn (string $label, string $reason): string => $reason === '' ? (string) $this->messages->t('admin.site_health.requirements.suggested_by', 'Suggested by {extension}', extension: $label) : "{$label}: {$reason}", array_keys($reasons), $reasons)),
				'needs'     => $this->messages->t('admin.site_health.requirements.optional', 'optional'),
				'installed' => $loaded ? $this->loadedWord() : $missing,
				'status'    => $loaded ? 'pass' : 'optional'
			];
		}

		$largest = $this->largestUpload();

		foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
			$value  = (string) ini_get($setting);
			$bytes  = self::bytes($value);
			$rows[] = [
				'group'     => $this->messages->t('admin.site_health.requirements.groups.uploads', 'Uploads'),
				'name'      => $setting,
				'why'       => $largest === null ? $this->messages->t('admin.site_health.requirements.why.upload_php', 'The Media settings take PHP\'s limit') : $this->messages->t('admin.site_health.requirements.why.upload_media', 'The Media settings allow files this large'),
				'needs'     => $largest === null ? $this->messages->t('admin.site_health.requirements.any', 'any') : $this->messages->t('admin.site_health.requirements.megabytes_or_more', '{size}M or more', size: (string) $largest),
				'installed' => $value,
				'status'    => $largest === null || $bytes === 0 || $bytes >= $largest * 1024 ** 2 ? 'pass' : 'warning'
			];
		}

		foreach ($this->setup->storage() as $result) {
			$rows[] = [
				'group'     => $this->messages->t('admin.site_health.requirements.groups.writable', 'Writable'),
				'name'      => (string) $result->label,
				'why'       => '',
				'needs'     => $this->messages->t('admin.site_health.requirements.writable', 'writable'),
				'installed' => lcfirst(rtrim((string) $result->message, '.')),
				'status'    => $result->status === CheckStatus::Pass ? 'pass' : 'failure'
			];
		}

		return $rows;
	}

	/**
	 * Says what uses an optional extension.
	 */
	private function optionalWhy(string $extension): Text
	{
		return match ($extension) {
			'opcache'  => $this->messages->t('admin.site_health.requirements.why.opcache', 'Keeps compiled PHP in memory, so pages are faster'),
			'fileinfo' => $this->messages->t('admin.site_health.requirements.why.fileinfo', 'Tells uploaded and served files\' types apart'),
			'zip'      => $this->messages->t('admin.site_health.requirements.why.zip', 'Installs plugins and themes from a .zip'),
			'exif'     => $this->messages->t('admin.site_health.requirements.why.exif', 'Reads photos\' embedded details'),
			'zlib'     => $this->messages->t('admin.site_health.requirements.why.zlib', 'Reads compressed PDFs\' details'),
			default    => $this->messages->t('admin.site_health.requirements.why.apcu', 'Keeps the cache in memory instead of files, when the cache driver is set to apcu')
		};
	}

	/**
	 * What the plugins, themes, and icon packs that are on ask of PHP
	 * (D-641): each `ext-{name}` they require, checked as boot checks it,
	 * and each `php` stricter than Blush's own (one Blush's oldest PHP
	 * doesn't meet, or this server's doesn't); and each `ext-{name}` they
	 * suggest, with its reason. Each is by name, then by the label of the
	 * extension asking.
	 *
	 * @return array{array<string, array<string, Requirement>>, array<string, array<string, string>>}
	 */
	private function asked(): array
	{
		$required  = [];
		$suggested = [];

		foreach ($this->extensions->on() as $extension) {
			foreach ($this->extensions->check($extension) as $requirement) {
				$php = $requirement->kind === RequirementKind::Php
					&& ! (VersionConstraint::satisfies(SetupChecks::MINIMUM_PHP, $requirement->constraint) && $requirement->met);

				if (! $requirement->conflict && ($php || $requirement->kind === RequirementKind::Extension)) {
					$required[$php ? 'php' : strtolower($requirement->name)][(string) $this->text->label($extension)] = $requirement;
				}
			}

			foreach ($extension->suggest as $name => $reason) {
				if (str_starts_with($name, 'ext-')) {
					$suggested[strtolower($name)][(string) $this->text->label($extension)] = $reason;
				}
			}
		}

		return [$required, $suggested];
	}

	/**
	 * Names the extensions asking for something: "Gallery and Shop".
	 *
	 * @param array<string, Requirement> $asking By the label of the extension asking.
	 */
	private static function labels(array $asking): string
	{
		$labels = array_map(strval(...), array_keys($asking));
		$last   = array_pop($labels);

		return $labels === [] ? (string) $last : implode(', ', $labels) . (count($labels) > 1 ? ',' : '') . " and {$last}";
	}

	/**
	 * What's needed: `required`, or the versions when one asks for them.
	 *
	 * @param array<string, Requirement> $asking
	 */
	private function needs(array $asking): string|Text
	{
		$constraints = self::constraints($asking);

		return $constraints === [] ? $this->messages->t('admin.site_health.requirements.required', 'required') : implode(', ', $constraints);
	}

	/**
	 * The versions the extensions asking ask for, if any.
	 *
	 * @param  array<string, Requirement> $asking
	 * @return list<string>
	 */
	private static function constraints(array $asking): array
	{
		return array_values(array_unique(array_filter(array_map(static fn (Requirement $requirement): string => $requirement->constraint, $asking), static fn (string $constraint): bool => $constraint !== '*')));
	}

	/**
	 * A loaded extension as installed: its version when one asks for
	 * versions, or `loaded`.
	 *
	 * @param array<string, Requirement> $asking
	 */
	private function loaded(string $extension, array $asking): string|Text
	{
		return self::constraints($asking) === [] ? $this->loadedWord() : (phpversion($extension) ?: $this->loadedWord());
	}

	/**
	 * Says an extension is loaded.
	 */
	private function loadedWord(): Text
	{
		return $this->messages->t('admin.site_health.requirements.loaded', 'loaded');
	}

	/**
	 * Returns facts about the site, each in words for reading and as it's
	 * kept (`key`, `raw`) for Copy Report.
	 *
	 * @return list<array{key: string, label: string|Text, value: string|Text, raw: string, note: ?string, mono: bool}>
	 */
	private function siteFacts(): array
	{
		$languages = $this->app->languages->all();
		$theme     = $this->extensions->themes->find($this->extensions->theme);
		$label     = $theme === null ? $this->extensions->theme : (string) $this->text->label($theme);
		$driver    = CacheDriver::tryFrom($this->cache->driver);
		$zone      = $this->app->timezone();
		$entries   = number_format($this->content->query()->any()->count());
		$media     = number_format($this->library->query(new MediaQuery(per: 1))->total);

		return [
			self::fact('version', $this->messages->t('admin.site_health.facts.version', 'Version'), Framework::NAME . ' ' . Framework::VERSION, Framework::VERSION),
			self::fact('environment', $this->messages->t('admin.site_health.facts.environment', 'Environment'), ucfirst($this->app->environment->value), $this->app->environment->value, mono: false),
			self::fact('debug', $this->messages->t('admin.site_health.facts.debug', 'Debugging'), $this->app->debug ? $this->messages->t('admin.site_health.facts.on', 'On') : $this->messages->t('admin.site_health.facts.off', 'Off'), $this->app->debug ? 'true' : 'false', mono: false),
			self::fact('url', $this->messages->t('admin.site_health.facts.url', 'Site URL'), $this->app->url),
			self::fact('admin', $this->messages->t('admin.site_health.facts.admin', 'Admin URL'), rtrim($this->app->url, '/') . $this->admin->path, $this->admin->path),
			$this->contentFiles->kept()
				? self::fact('content', $this->messages->t('admin.site_health.facts.content_folder', 'Content folder'), $this->contentFiles->source()->location('') . '/')
				: self::fact('content', $this->messages->t('admin.site_health.facts.content', 'Content'), $this->messages->t('admin.site_health.facts.in_database', 'In the database ({file})', file: $this->database())),
			self::fact('storage', $this->messages->t('admin.site_health.facts.storage', 'Storage'), $this->drivers(), mono: false),
			self::fact('media', $this->messages->t('admin.site_health.facts.media', 'Media folder'), $this->paths->relative($this->paths->media) . '/'),
			self::fact('timezone', $this->messages->t('admin.site_health.facts.timezone', 'Time zone'), sprintf('%s (%s)', $zone->getName(), $this->offset($zone)), $zone->getName()),
			self::fact(
				'languages',
				$this->messages->t('admin.site_health.facts.languages', 'Languages'),
				implode(', ', array_map(static fn (Language $language): string => $language->name(), $languages)),
				implode(', ', array_map(static fn (Language $language): string => $language->code, $languages)),
				mono: false
			),
			self::fact('theme', $this->messages->t('admin.site_health.facts.theme', 'Theme'), $label, $this->extensions->theme, $label !== $this->extensions->theme ? $this->extensions->theme : null, false),
			self::fact('cache', $this->messages->t('admin.site_health.facts.cache', 'Cache'), $driver?->label($this->messages) ?? $this->cache->driver, $this->cache->driver, mono: $driver === null),
			self::fact('entries', $this->messages->t('admin.site_health.facts.entries', 'Entries'), $entries, mono: false),
			self::fact('media_files', $this->messages->t('admin.site_health.facts.media_files', 'Media files'), $media, mono: false)
		];
	}

	/**
	 * Returns facts about the server, as PHP sees it from here, each in
	 * words for reading and as PHP has it (`key`, `raw`) for Copy Report.
	 *
	 * @param  array<array-key, mixed> $server
	 * @return list<array{key: string, label: string|Text, value: string|Text, raw: string, note: ?string, mono: bool}>
	 */
	private function serverFacts(array $server): array
	{
		$software = $server['SERVER_SOFTWARE'] ?? null;
		$free     = @disk_free_space($this->paths->root);
		$total    = @disk_total_space($this->paths->root);
		$memory   = (string) ini_get('memory_limit');
		$time     = (int) ini_get('max_execution_time');
		$os       = sprintf('%s %s (%s)', PHP_OS_FAMILY, php_uname('r'), php_uname('m'));
		$phpZone  = date_default_timezone_get();
		$siteZone = $this->app->timezone()->getName();

		return array_values(array_filter([
			is_string($software) && $software !== '' ? self::fact('software', $this->messages->t('admin.site_health.facts.software', 'Web server'), $software) : null,
			self::fact(
				'os',
				$this->messages->t('admin.site_health.facts.os', 'Operating system'),
				PHP_OS_FAMILY === 'Darwin' ? sprintf('macOS (Darwin %s, %s)', php_uname('r'), php_uname('m')) : $os,
				$os
			),
			self::fact('php', 'PHP', sprintf('%s, %s', PHP_VERSION, $this->sapi(PHP_SAPI)), sprintf('%s (%s)', PHP_VERSION, PHP_SAPI)),
			self::fact(
				'memory_limit',
				$this->messages->t('admin.site_health.facts.memory_limit', 'Memory limit'),
				match (true) {
					$memory === '-1'          => $this->messages->t('admin.site_health.facts.no_limit', 'No limit'),
					self::bytes($memory) > 0  => self::size(self::bytes($memory)),
					default                   => $memory
				},
				$memory,
				'memory_limit',
				false
			),
			self::fact(
				'max_execution_time',
				$this->messages->t('admin.site_health.facts.time_limit', 'Time limit'),
				$time === 0 ? $this->messages->t('admin.site_health.facts.no_limit', 'No limit') : $this->messages->t('admin.site_health.facts.seconds', '{count, plural, one {# second} other {# seconds}}', count: $time),
				(string) $time,
				'max_execution_time',
				false
			),
			self::fact(
				'date.timezone',
				$this->messages->t('admin.site_health.facts.php_timezone', 'PHP time zone'),
				$phpZone === $siteZone ? $phpZone : $this->messages->t('admin.site_health.facts.not_site_timezone', '{zone}, not the site\'s ({site})', zone: $phpZone, site: $siteZone),
				$phpZone,
				'date.timezone'
			),
			$free !== false && $total !== false
				? self::fact('disk', $this->messages->t('admin.site_health.facts.disk', 'Disk space'), $this->messages->t('admin.site_health.facts.disk_free', '{free} free of {total}', free: self::size($free), total: self::size($total)), mono: false)
				: null
		]));
	}

	/**
	 * Describes a fact: `$value` to read, and `$raw` (the value when not
	 * given) as it's kept, for a bug report. `$note` names the setting.
	 *
	 * @return array{key: string, label: string|Text, value: string|Text, raw: string, note: ?string, mono: bool}
	 */
	private static function fact(string $key, string|Text $label, string|Text $value, ?string $raw = null, ?string $note = null, bool $mono = true): array
	{
		return ['key' => $key, 'label' => $label, 'value' => $value, 'raw' => $raw ?? (string) $value, 'note' => $note, 'mono' => $mono];
	}

	/**
	 * Names how PHP is run (its SAPI): "PHP-FPM", "Apache module".
	 */
	private function sapi(string $sapi): string
	{
		return match ($sapi) {
			'fpm-fcgi'       => 'PHP-FPM',
			'apache2handler' => (string) $this->messages->t('admin.site_health.facts.sapi.apache', 'Apache module'),
			'cgi-fcgi'       => 'FastCGI',
			'cli-server'     => (string) $this->messages->t('admin.site_health.facts.sapi.built_in', 'PHP\'s built-in server'),
			'cli'            => (string) $this->messages->t('admin.site_health.facts.sapi.cli', 'command line'),
			'litespeed'      => 'LiteSpeed',
			'frankenphp'     => 'FrankenPHP',
			default          => $sapi
		};
	}

	/**
	 * A time zone's offset from UTC now: "UTC−5", "UTC+5:30", "UTC".
	 */
	private function offset(DateTimeZone $zone): string
	{
		$seconds = $zone->getOffset($this->clock->now());

		if ($seconds === 0) {
			return 'UTC';
		}

		$minutes = intdiv(abs($seconds), 60);

		return sprintf('UTC%s%d%s', $seconds < 0 ? '−' : '+', intdiv($minutes, 60), $minutes % 60 === 0 ? '' : sprintf(':%02d', $minutes % 60));
	}

	/**
	 * The largest upload the Media settings allow, in megabytes, or `null`
	 * when they take PHP's limit.
	 */
	private function largestUpload(): ?int
	{
		$uploads = $this->media->uploads;
		$sizes   = [$uploads->maxSize];

		foreach ($uploads->kinds as $rule) {
			$sizes[] = $rule->enabled ? $rule->maxSize : null;
		}

		$sizes = array_filter($sizes, static fn (?int $size): bool => $size !== null);

		return $uploads->enabled && $sizes !== [] ? max($sizes) : null;
	}

	/**
	 * Whether opcache is on for the web server.
	 */
	private static function opcache(): bool
	{
		if (! function_exists('opcache_get_status')) {
			return false;
		}

		$status = @opcache_get_status(false);

		return is_array($status) && ($status['opcache_enabled'] ?? false) === true;
	}

	/**
	 * Describes a check to keep (D-836): its words as messages
	 * (`Text::toArray()`), turned into text in the reader's language when
	 * the report is read (`readable()`); a name (a path, a setting) as
	 * it is.
	 *
	 * @return array{area: string, key: string, status: string, label: string|array<string, mixed>, message: string|array<string, mixed>, hint: string|array<string, mixed>, link: ?string}
	 */
	private static function check(string $area, string $key, CheckResult $result, ?string $link = null): array
	{
		return [
			'area'    => $area,
			'key'     => $key,
			'status'  => $result->status === CheckStatus::Pass ? 'pass' : $result->status->value,
			'label'   => self::kept($result->label),
			'message' => self::kept($result->message),
			'hint'    => self::kept($result->hint),
			'link'    => $result->status === CheckStatus::Pass ? null : $link
		];
	}

	/**
	 * Returns text as it's kept: a message as its key, text, and values;
	 * a string as it is.
	 *
	 * @return string|array<string, mixed>
	 */
	public static function kept(string|Text $text): string|array
	{
		return $text instanceof Text ? $text->toArray() : $text;
	}

	/**
	 * Returns a kept report with its words in the current language (the
	 * reader's, D-836): each check's label, message, and hint, and the
	 * files' problems. A report kept before its words were messages
	 * shows them as they were kept.
	 *
	 * @param  array<string, mixed> $report
	 * @return array<string, mixed>
	 */
	private function readable(array $report): array
	{
		if (is_array($report['checks'] ?? null)) {
			$report['checks'] = array_map(fn (mixed $check): mixed => is_array($check) ? [
				...$check,
				'label'   => $this->shown($check['label'] ?? ''),
				'message' => $this->shown($check['message'] ?? ''),
				'hint'    => $this->shown($check['hint'] ?? '')
			] : $check, $report['checks']);
		}

		if (is_array($report['files'] ?? null)) {
			$report['files'] = $this->readableFiles(array_filter($report['files'], is_string(...), ARRAY_FILTER_USE_KEY));
		}

		return $report;
	}

	/**
	 * Returns a kept files report with each problem's message in the
	 * current language, and its key (`problem`, D-613), which is made
	 * from its English, so it's the same in every language.
	 *
	 * @param  array<string, mixed> $files
	 * @return array<string, mixed>
	 */
	private function readableFiles(array $files): array
	{
		if (! is_array($files['files'] ?? null)) {
			return $files;
		}

		$list = $files['files'];

		foreach ($list as $index => $file) {
			if (! is_array($file) || ! is_array($file['violations'] ?? null)) {
				continue;
			}

			$violations = $file['violations'];

			foreach ($violations as $number => $violation) {
				if (is_array($violation)) {
					$violations[$number] = [
						...$violation,
						'message' => $this->shown($violation['message'] ?? ''),
						'problem' => ProblemKeys::file(ProblemKeys::message($file['area'] ?? ''), ProblemKeys::message($file['path'] ?? ''), ProblemKeys::message($violation['field'] ?? ''), ProblemKeys::message($violation['message'] ?? ''))
					];
				}
			}

			$list[$index] = [...$file, 'violations' => $violations];
		}

		return [...$files, 'files' => $list];
	}

	/**
	 * Returns kept text to show: a message in the current language, or
	 * text kept before it was one, as it was kept.
	 */
	private function shown(mixed $kept): string|Text
	{
		return $this->messages->restore($kept) ?? (is_string($kept) ? $kept : '');
	}

	/**
	 * Reads an ini size: `64M`, `2G`, `512K`, or bytes.
	 */
	private static function bytes(string $value): int
	{
		if (preg_match('/^(\d+)\s*([KMG]?)$/i', trim($value), $match) !== 1) {
			return 0;
		}

		return (int) $match[1] * match (strtoupper($match[2])) {
			'G'     => 1024 ** 3,
			'M'     => 1024 ** 2,
			'K'     => 1024,
			default => 1
		};
	}

	/**
	 * A byte count for reading: "4.8 GB".
	 */
	private static function size(float $bytes): string
	{
		$units = ['bytes', 'KB', 'MB', 'GB', 'TB'];
		$unit  = 0;

		while ($bytes >= 1024 && $unit < count($units) - 1) {
			$bytes /= 1024;
			$unit++;
		}

		$number = sprintf($unit === 0 ? '%.0f' : '%.1f', $bytes);

		return (str_ends_with($number, '.0') ? substr($number, 0, -2) : $number) . ' ' . $units[$unit];
	}

	/**
	 * Returns which driver keeps each area, as Site Health shows it:
	 * `SQLite`, or each area's when they differ.
	 */
	private function drivers(): string
	{
		$names   = [StorageConfig::FILESYSTEM => (string) $this->messages->t('admin.site_health.facts.files_driver', 'Files'), StorageConfig::SQLITE => 'SQLite'];
		$drivers = [];

		foreach (StorageArea::cases() as $area) {
			$driver                      = $this->storage->driverFor($area);
			$drivers[$names[$driver] ?? $driver][] = $area->value;
		}

		if (count($drivers) === 1) {
			return (string) array_key_first($drivers);
		}

		return implode('; ', array_map(static fn (string $driver, array $areas): string => sprintf('%s (%s)', $driver, implode(', ', $areas)), array_keys($drivers), $drivers));
	}

	/**
	 * Returns the SQLite database's file, from the site's root.
	 */
	private function database(): string
	{
		return str_starts_with($this->storage->sqlite, '/') ? $this->paths->relative($this->storage->sqlite) : $this->storage->sqlite;
	}
}
