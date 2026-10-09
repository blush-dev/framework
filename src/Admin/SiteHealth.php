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

namespace Blush\Admin;

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
 */
final readonly class SiteHealth
{
	/**
	 * Optional extensions, by what uses them; recommended ones warn when
	 * they're missing.
	 *
	 * @var array<string, array{why: string, recommended: bool}>
	 */
	private const array OPTIONAL = [
		'opcache'  => ['why' => 'Keeps compiled PHP in memory, so pages are faster', 'recommended' => true],
		'fileinfo' => ['why' => 'Tells uploaded and served files\' types apart', 'recommended' => true],
		'zip'      => ['why' => 'Installs plugins and themes from a .zip', 'recommended' => false],
		'exif'     => ['why' => 'Reads photos\' embedded details', 'recommended' => false],
		'zlib'     => ['why' => 'Reads compressed PDFs\' details', 'recommended' => false],
		'apcu'     => ['why' => 'Keeps the cache in memory instead of files, when the cache driver is set to apcu', 'recommended' => false]
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
		private IgnoredProblems $ignored
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
			'areas'        => [
				['key' => 'content', 'label' => 'Content', 'description' => 'Entries\' files: their fields, ids, terms, parents, folders, and names'],
				['key' => 'media', 'label' => 'Media', 'description' => 'Library files\' ids, details, and image sizes'],
				['key' => 'extensions', 'label' => 'Extensions', 'description' => 'The theme, plugins, and icon packs that are on'],
				['key' => 'system', 'label' => 'System', 'description' => 'PHP, settings, the public folder, and storage'],
				['key' => 'accounts', 'label' => 'Accounts', 'description' => 'Who can always get back in']
			],
			'checks'       => [...$this->summarizeFiles($files = $this->fileReport()), ...$this->site()],
			'files'        => $files,
			'requirements' => $this->requirements(),
			'site'         => $this->siteFacts(),
			'server'       => $this->serverFacts($server)
		];

		$this->store->save($report);

		return $report;
	}

	/**
	 * Returns the last report, or `null` before the first.
	 *
	 * @return ?array<string, mixed>
	 */
	public function saved(): ?array
	{
		return $this->store->load();
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
		$report = $this->saved();

		if ($report === null) {
			return $this->run($server);
		}

		return [
			...$report,
			'requirements' => $this->requirements(),
			'site'         => $this->siteFacts(),
			'server'       => $this->serverFacts($server)
		];
	}

	/**
	 * Whether the last report has its content and media files, in their
	 * current shape, so checking them again can be left to a job.
	 */
	public function hasFiles(): bool
	{
		$report = $this->saved();
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
		$report = $this->saved();

		if ($report === null || ! $this->hasFiles() || ! is_array($report['checks'] ?? null)) {
			return $this->run($server);
		}

		$kept = array_values(array_filter($report['checks'], static fn (mixed $check): bool => is_array($check) && in_array($check['area'] ?? null, ['content', 'media'], true)));

		$report = [
			...$report,
			'checked'      => $this->clock->now()->format(DateTimeInterface::ATOM),
			'checks'       => [...$kept, ...$this->site()],
			'requirements' => $this->requirements(),
			'site'         => $this->siteFacts(),
			'server'       => $this->serverFacts($server)
		];

		$this->store->save($report);

		return $report;
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
		$files = $this->saved()['files'] ?? null;

		return is_array($files) && ($files['version'] ?? null) === ContentHealth::VERSION ? array_filter($files, is_string(...), ARRAY_FILTER_USE_KEY) : $this->checkFiles($server);
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
		$report = $this->saved();

		if ($report === null || ! is_array($report['checks'] ?? null)) {
			$files = $this->run($server)['files'];

			return is_array($files) ? array_filter($files, is_string(...), ARRAY_FILTER_USE_KEY) : [];
		}

		$files  = $this->fileReport($lint);
		$others = array_values(array_filter($report['checks'], static fn (mixed $check): bool => ! is_array($check) || ! in_array($check['area'] ?? null, ['content', 'media'], true)));

		$report['checks'] = [...$this->summarizeFiles($files), ...$others];
		$report['files']  = $files;
		$this->store->save($report);

		return $files;
	}

	/**
	 * Sums up the last report's content and media files again, without
	 * checking them, as after a problem is ignored or no longer is
	 * (D-613), and keeps it.
	 */
	public function resummarize(): void
	{
		$report = $this->saved();
		$files  = $report['files'] ?? null;

		if ($report === null || ! is_array($report['checks'] ?? null) || ! is_array($files) || ($files['version'] ?? null) !== ContentHealth::VERSION) {
			return;
		}

		/** @var array{files: list<array{path: string, area: string, violations: list<array{field: string, message: string, severity: string, kind: ?string}>}>, checked: int, metadata: int, ids: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, mediaIds: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, fileNames: list<array{count: int, items: list<array{path: string, to: string}>}>, folders: array{count: int, items: list<array{path: string, to: string}>}, terms: array{count: int, items: list<array{type: string, slug: string}>}, parents: array{count: int, items: list<array{type: string, key: string}>}, refs: array{count: int, items: list<array{path: string}>}, taxonomies: list<string>, typeFolders: list<array{name: string, from: string, to: string}>, mediaSizes: array{sizes: int, images: int, stale: int, items: list<array{key: string, unrecorded: int, stale: int}>}} $files */
		$others = array_values(array_filter($report['checks'], static fn (mixed $check): bool => ! is_array($check) || ! in_array($check['area'] ?? null, ['content', 'media'], true)));

		$report['checks'] = [...$this->summarize($this->withoutIgnored($files)), ...$others];
		$this->store->save($report);
	}

	/**
	 * Sums up a new files report, first forgetting the ignored problems
	 * it no longer finds (D-613).
	 *
	 * @param  array{files: list<array{path: string, area: string, violations: list<array{field: string, message: string, severity: string, kind: ?string}>}>, checked: int, metadata: int, ids: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, mediaIds: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, fileNames: list<array{count: int, items: list<array{path: string, to: string}>}>, folders: array{count: int, items: list<array{path: string, to: string}>}, terms: array{count: int, items: list<array{type: string, slug: string}>}, parents: array{count: int, items: list<array{type: string, key: string}>}, refs: array{count: int, items: list<array{path: string}>}, taxonomies: list<string>, typeFolders: list<array{name: string, from: string, to: string}>, mediaSizes: array{sizes: int, images: int, stale: int, items: list<array{key: string, unrecorded: int, stale: int}>}} $files
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
	 * @param  array{files: list<array{path: string, area: string, violations: list<array{field: string, message: string, severity: string, kind: ?string}>}>, checked: int, metadata: int, ids: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, mediaIds: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, fileNames: list<array{count: int, items: list<array{path: string, to: string}>}>, folders: array{count: int, items: list<array{path: string, to: string}>}, terms: array{count: int, items: list<array{type: string, slug: string}>}, parents: array{count: int, items: list<array{type: string, key: string}>}, refs: array{count: int, items: list<array{path: string}>}, taxonomies: list<string>, typeFolders: list<array{name: string, from: string, to: string}>, mediaSizes: array{sizes: int, images: int, stale: int, items: list<array{key: string, unrecorded: int, stale: int}>}} $files
	 * @return array{files: list<array{path: string, area: string, violations: list<array{field: string, message: string, severity: string, kind: ?string}>}>, checked: int, metadata: int, ids: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, mediaIds: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, fileNames: list<array{count: int, items: list<array{path: string, to: string}>}>, folders: array{count: int, items: list<array{path: string, to: string}>}, terms: array{count: int, items: list<array{type: string, slug: string}>}, parents: array{count: int, items: list<array{type: string, key: string}>}, refs: array{count: int, items: list<array{path: string}>}, taxonomies: list<string>, typeFolders: list<array{name: string, from: string, to: string}>, mediaSizes: array{sizes: int, images: int, stale: int, items: list<array{key: string, unrecorded: int, stale: int}>}}
	 */
	private function withoutIgnored(array $files): array
	{
		$ignored = $this->ignored->all();

		if ($ignored === []) {
			return $files;
		}

		$out = static fn (string $key): bool => ! isset($ignored[$key]);

		foreach ($files['files'] as $index => $file) {
			$files['files'][$index]['violations'] = array_values(array_filter($file['violations'], static fn (array $violation): bool => $out(ProblemKeys::file($file['area'], $file['path'], $violation['field'], $violation['message']))));
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
	 * @return array{at: string, version: int, checked: int, metadata: int, strict: bool, counts: array{error: int, warning: int, notice: ?int}, files: list<array{path: string, area: string, violations: list<array{field: string, message: string, severity: string, kind: ?string}>}>, entries: array<string, array{title: string, type: string, id: ?string}>, ids: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, mediaIds: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, fileNames: list<array{type: string, label: string, pattern: string, count: int, items: list<array{path: string, to: string}>, skipped: int}>, folders: array{count: int, items: list<array{path: string, to: string}>}, terms: array{count: int, items: list<array{type: string, label: string, slug: string, title: string, entries: int}>}, parents: array{count: int, items: list<array{type: string, label: string, key: string, title: string, pages: int}>}, refs: array{count: int, items: list<array{path: string, relations: list<string>}>}, taxonomies: list<string>, typeFolders: list<array{name: string, from: string, to: string}>, mediaSizes: array{sizes: int, images: int, stale: int, items: list<array{key: string, unrecorded: int, stale: int}>}}
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
	 * @param  array{files: list<array{path: string, area: string, violations: list<array{field: string, message: string, severity: string, kind: ?string}>}>, checked: int, metadata: int, ids: array{missing: list<string>, duplicates: list<mixed>}, mediaIds: array{missing: list<string>, duplicates: list<mixed>}, fileNames: list<array{count: int}>, folders: array{count: int}, terms: array{count: int}, parents: array{count: int}, refs: array{count: int}, taxonomies: list<string>, typeFolders: list<array{name: string, from: string, to: string}>, mediaSizes: array{sizes: int, images: int, stale: int}} $report
	 * @return list<array<string, mixed>>
	 */
	private function summarize(array $report): array
	{
		$checks = [];

		foreach (['content' => 'Content files', 'media' => 'Media details'] as $area => $label) {
			$files    = array_filter($report['files'], static fn (array $file): bool => $file['area'] === $area);
			$worst    = array_map(static fn (array $file): string => in_array('error', array_column($file['violations'], 'severity'), true) ? 'error' : (in_array('warning', array_column($file['violations'], 'severity'), true) ? 'warning' : 'notice'), $files);
			$errors   = count(array_keys($worst, 'error', true));
			$warnings = count(array_keys($worst, 'warning', true));
			$checked  = $area === 'content' ? $report['checked'] : $report['metadata'];

			$checks[] = self::check($area, 'files', match (true) {
				$errors > 0   => CheckResult::failure($label, self::sentence([[$errors, 'file has errors', 'files have errors'], [$warnings, 'has warnings', 'have warnings']])),
				$warnings > 0 => CheckResult::warning($label, self::sentence([[$warnings, 'file has warnings', 'files have warnings']])),
				default       => CheckResult::pass($label, sprintf('%s checked, and nothing is wrong.', self::count($checked, 'file', 'files')))
			}, $area);
		}

		foreach (['content' => ['ids', 'Entry ids', 'entry'], 'media' => ['mediaIds', 'Media ids', 'media file']] as $area => [$key, $label, $noun]) {
			$missing = count($report[$key]['missing']);
			$shared  = count($report[$key]['duplicates']);

			$checks[] = self::check($area, 'ids', $missing + $shared > 0
				? CheckResult::warning($label, self::sentence([[$missing, 'file has no id', 'files have no id'], [$shared, 'id is shared', 'ids are shared']]), 'A file without an id can\'t be opened in the admin.')
				: CheckResult::pass($label, sprintf('Every %s has an id of its own.', $noun)), $area);
		}

		$folders = $report['folders']['count'];

		$checks[] = self::check('content', 'folders', $folders > 0
			? CheckResult::warning('Collection folders', sprintf('%s not in the folder %s type keeps it in.', self::count($folders, 'entry is', 'entries are'), $folders === 1 ? 'its' : 'their'))
			: CheckResult::pass('Collection folders', 'Every collection\'s entries are files in the folders it keeps them in.'), 'content');

		$terms = $report['terms']['count'];

		$checks[] = self::check('content', 'terms', $terms > 0
			? CheckResult::warning('Terms and profiles', sprintf('%s no file, so the site leaves %s out.', self::count($terms, 'term or profile entries name has', 'terms and profiles entries name have'), $terms === 1 ? 'it' : 'them'))
			: CheckResult::pass('Terms and profiles', 'Every term and profile entries name has a file.'), 'content');

		$parents = $report['parents']['count'];

		$checks[] = self::check('content', 'parents', $parents > 0
			? CheckResult::warning('Parent pages', sprintf('%s no page, so the pages under %s are at the top of their tree.', self::count($parents, 'folder pages are kept in has', 'folders pages are kept in have'), $parents === 1 ? 'it' : 'them'))
			: CheckResult::pass('Parent pages', 'Every page is under a parent page that exists.'), 'content');

		$refs = $report['refs']['count'];

		$checks[] = self::check('content', 'refs', $refs > 0
			? CheckResult::warning('Links between entries', sprintf('%s links not filed with their ids, so a rename or move of what %s link to can break them.', self::count($refs, 'file has', 'files have'), $refs === 1 ? 'it' : 'they'))
			: CheckResult::pass('Links between entries', 'Every link between entries is filed with its id.'), 'content');

		$taxonomies = count($report['taxonomies']);

		$checks[] = self::check('content', 'taxonomies', $taxonomies > 0
			? CheckResult::warning('Taxonomies', sprintf('%s still written as %s, which Blush reads as %s until %s migrated.', self::count($taxonomies, 'content type is', 'content types are'), $taxonomies === 1 ? 'a taxonomy' : 'taxonomies', $taxonomies === 1 ? 'a collection and its relation' : 'collections and their relations', $taxonomies === 1 ? 'it\'s' : 'they\'re'))
			: CheckResult::pass('Taxonomies', 'Every content type is written as a collection or a tree.'), 'content');

		$named = count($report['typeFolders']);

		$checks[] = self::check('content', 'types', $named > 0
			? CheckResult::warning('Type folders', sprintf('%s own folder, so %s entries aren\'t found until %s moved into _ and %s name.', self::count($named, 'content type names its', 'content types name their'), $named === 1 ? 'its' : 'their', $named === 1 ? 'it\'s' : 'they\'re', $named === 1 ? 'its' : 'their'))
			: CheckResult::pass('Type folders', 'Every content type is kept in _ and its name.'), 'content');

		$renames = array_sum(array_column($report['fileNames'], 'count'));

		$checks[] = self::check('content', 'names', $renames > 0
			? CheckResult::warning('File names', sprintf('%s named by an older pattern than its type\'s.', self::count($renames, 'entry is', 'entries are')), 'Older names keep working; renaming them changes no address.')
			: CheckResult::pass('File names', 'Every entry is named by its type\'s pattern.'), 'content');

		$sizes = $report['mediaSizes'];

		$checks[] = self::check('media', 'sizes', $sizes['images'] + $sizes['stale'] > 0
			? CheckResult::warning('Image sizes', self::sentence([[$sizes['images'], 'image doesn\'t list its sizes', 'images don\'t list their sizes'], [$sizes['stale'], 'lists files that aren\'t its sizes', 'list files that aren\'t their sizes']]))
			: CheckResult::pass('Image sizes', 'Every image lists its sizes.'), 'media');

		return $checks;
	}

	/**
	 * Returns `doctor`'s checks: extensions, the system, and accounts.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function site(): array
	{
		$links  = ['theme' => 'themes', 'plugins' => 'plugins', 'icon-packs' => 'icon-packs', 'owner' => 'account'];
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
	 * @return list<array{group: string, name: string, why: string, needs: string, installed: string, status: string}>
	 */
	private function requirements(): array
	{
		$rows = [[
			'group'     => 'PHP',
			'name'      => 'PHP',
			'why'       => '',
			'needs'     => SetupChecks::MINIMUM_PHP . ' or newer',
			'installed' => PHP_VERSION,
			'status'    => version_compare(PHP_VERSION, SetupChecks::MINIMUM_PHP, '>=') ? 'pass' : 'failure'
		]];

		[$required, $suggested] = $this->asked();

		foreach (SetupChecks::EXTENSIONS as $extension => $why) {
			$loaded = extension_loaded($extension);
			$rows[] = ['group' => 'PHP Extensions', 'name' => $extension, 'why' => $why, 'needs' => 'required', 'installed' => $loaded ? 'loaded' : 'missing', 'status' => $loaded ? 'pass' : 'failure'];

			// A plugin asking for versions this one isn't keeps its own row.
			if (array_all($required["ext-{$extension}"] ?? [], static fn (Requirement $requirement): bool => $requirement->met)) {
				unset($required["ext-{$extension}"]);
			}

			unset($suggested["ext-{$extension}"]);
		}

		foreach (self::OPTIONAL as $extension => ['why' => $why, 'recommended' => $recommended]) {
			$loaded  = $extension === 'opcache' ? self::opcache() : extension_loaded($extension);
			$asking  = $required["ext-{$extension}"] ?? [];
			$cache   = $extension === 'apcu' && in_array(CacheDriver::Apcu->value, [$this->cache->driver, ...array_values($this->cache->stores)], true);
			$needed  = $cache || $asking !== [];
			$met     = $loaded && array_all($asking, static fn (Requirement $requirement): bool => $requirement->met);
			$why     = $cache ? 'Keeps the cache in memory: this site\'s cache driver is set to apcu' : $why;
			$rows[]  = [
				'group'     => 'PHP Extensions',
				'name'      => $extension,
				'why'       => $asking === [] ? $why : $why . '; ' . lcfirst(self::askedBy($asking)),
				'needs'     => $needed ? self::needs($asking) : ($recommended ? 'recommended' : 'optional'),
				'installed' => $loaded ? ($extension === 'opcache' ? 'on' : self::loaded($extension, $asking)) : ($extension === 'opcache' && extension_loaded('Zend OPcache') ? 'off' : 'missing'),
				'status'    => $met || (! $needed && $loaded) ? 'pass' : ($needed ? 'failure' : ($recommended ? 'warning' : 'optional'))
			];

			unset($required["ext-{$extension}"], $suggested["ext-{$extension}"]);
		}

		foreach ($required as $name => $asking) {
			$extension = substr($name, 4);
			$php       = $name === 'php';
			$loaded    = $php || extension_loaded($extension);
			$rows[]    = [
				'group'     => 'Plugins and Themes',
				'name'      => $php ? 'PHP' : $extension,
				'why'       => self::askedBy($asking),
				'needs'     => self::needs($asking),
				'installed' => $php ? PHP_VERSION : ($loaded ? self::loaded($extension, $asking) : 'missing'),
				'status'    => array_all($asking, static fn (Requirement $requirement): bool => $requirement->met) ? 'pass' : 'failure'
			];

			unset($suggested[$name]);
		}

		foreach ($suggested as $name => $reasons) {
			$extension = substr($name, 4);
			$loaded    = extension_loaded($extension);
			$rows[]    = [
				'group'     => 'Plugins and Themes',
				'name'      => $extension,
				'why'       => implode('; ', array_map(static fn (string $label, string $reason): string => $reason === '' ? "Suggested by {$label}" : "{$label}: {$reason}", array_keys($reasons), $reasons)),
				'needs'     => 'optional',
				'installed' => $loaded ? 'loaded' : 'missing',
				'status'    => $loaded ? 'pass' : 'optional'
			];
		}

		$largest = $this->largestUpload();

		foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
			$value  = (string) ini_get($setting);
			$bytes  = self::bytes($value);
			$rows[] = [
				'group'     => 'Uploads',
				'name'      => $setting,
				'why'       => $largest === null ? 'The Media settings take PHP\'s limit' : 'The Media settings allow files this large',
				'needs'     => $largest === null ? 'any' : sprintf('%dM or more', $largest),
				'installed' => $value,
				'status'    => $largest === null || $bytes === 0 || $bytes >= $largest * 1024 ** 2 ? 'pass' : 'warning'
			];
		}

		foreach ($this->setup->storage() as $result) {
			$rows[] = [
				'group'     => 'Writable',
				'name'      => $result->label,
				'why'       => '',
				'needs'     => 'writable',
				'installed' => lcfirst(rtrim($result->message, '.')),
				'status'    => $result->status === CheckStatus::Pass ? 'pass' : 'failure'
			];
		}

		return $rows;
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
					$required[$php ? 'php' : strtolower($requirement->name)][$extension->label] = $requirement;
				}
			}

			foreach ($extension->suggest as $name => $reason) {
				if (str_starts_with($name, 'ext-')) {
					$suggested[strtolower($name)][$extension->label] = $reason;
				}
			}
		}

		return [$required, $suggested];
	}

	/**
	 * Says which extensions require something: "Required by Gallery and
	 * Shop".
	 *
	 * @param array<string, Requirement> $asking By the label of the extension asking.
	 */
	private static function askedBy(array $asking): string
	{
		$labels = array_map(strval(...), array_keys($asking));
		$last   = array_pop($labels);

		return 'Required by ' . ($labels === [] ? $last : implode(', ', $labels) . (count($labels) > 1 ? ',' : '') . " and {$last}");
	}

	/**
	 * What's needed: `required`, or the versions when one asks for them.
	 *
	 * @param array<string, Requirement> $asking
	 */
	private static function needs(array $asking): string
	{
		$constraints = array_values(array_unique(array_filter(array_map(static fn (Requirement $requirement): string => $requirement->constraint, $asking), static fn (string $constraint): bool => $constraint !== '*')));

		return $constraints === [] ? 'required' : implode(', ', $constraints);
	}

	/**
	 * A loaded extension as installed: its version when one asks for
	 * versions, or `loaded`.
	 *
	 * @param array<string, Requirement> $asking
	 */
	private static function loaded(string $extension, array $asking): string
	{
		return self::needs($asking) === 'required' ? 'loaded' : (phpversion($extension) ?: 'loaded');
	}

	/**
	 * Returns facts about the site, each in words for reading and as it's
	 * kept (`key`, `raw`) for Copy Report.
	 *
	 * @return list<array{key: string, label: string, value: string, raw: string, note: ?string, mono: bool}>
	 */
	private function siteFacts(): array
	{
		$languages = $this->app->languages->all();
		$theme     = $this->extensions->themes->find($this->extensions->theme);
		$label     = $theme->label ?? $this->extensions->theme;
		$driver    = CacheDriver::tryFrom($this->cache->driver);
		$zone      = $this->app->timezone();
		$entries   = number_format($this->content->query()->any()->count());
		$media     = number_format($this->library->query(new MediaQuery(per: 1))->total);

		return [
			self::fact('version', 'Version', Framework::NAME . ' ' . Framework::VERSION, Framework::VERSION),
			self::fact('environment', 'Environment', ucfirst($this->app->environment->value), $this->app->environment->value, mono: false),
			self::fact('debug', 'Debugging', $this->app->debug ? 'On' : 'Off', $this->app->debug ? 'true' : 'false', mono: false),
			self::fact('url', 'Site URL', $this->app->url),
			self::fact('admin', 'Admin URL', rtrim($this->app->url, '/') . $this->admin->path, $this->admin->path),
			$this->contentFiles->kept()
				? self::fact('content', 'Content folder', $this->contentFiles->source()->location('') . '/')
				: self::fact('content', 'Content', sprintf('In the database (%s)', $this->database())),
			self::fact('storage', 'Storage', $this->drivers(), mono: false),
			self::fact('media', 'Media folder', $this->paths->relative($this->paths->media) . '/'),
			self::fact('timezone', 'Time zone', sprintf('%s (%s)', $zone->getName(), $this->offset($zone)), $zone->getName()),
			self::fact(
				'languages',
				'Languages',
				implode(', ', array_map(static fn (Language $language): string => $language->name(), $languages)),
				implode(', ', array_map(static fn (Language $language): string => $language->code, $languages)),
				mono: false
			),
			self::fact('theme', 'Theme', $label, $this->extensions->theme, $label !== $this->extensions->theme ? $this->extensions->theme : null, false),
			self::fact('cache', 'Cache', $driver?->label() ?? $this->cache->driver, $this->cache->driver, mono: $driver === null),
			self::fact('entries', 'Entries', $entries, mono: false),
			self::fact('media_files', 'Media files', $media, mono: false)
		];
	}

	/**
	 * Returns facts about the server, as PHP sees it from here, each in
	 * words for reading and as PHP has it (`key`, `raw`) for Copy Report.
	 *
	 * @param  array<array-key, mixed> $server
	 * @return list<array{key: string, label: string, value: string, raw: string, note: ?string, mono: bool}>
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
			is_string($software) && $software !== '' ? self::fact('software', 'Web server', $software) : null,
			self::fact(
				'os',
				'Operating system',
				PHP_OS_FAMILY === 'Darwin' ? sprintf('macOS (Darwin %s, %s)', php_uname('r'), php_uname('m')) : $os,
				$os
			),
			self::fact('php', 'PHP', sprintf('%s, %s', PHP_VERSION, self::sapi(PHP_SAPI)), sprintf('%s (%s)', PHP_VERSION, PHP_SAPI)),
			self::fact(
				'memory_limit',
				'Memory limit',
				match (true) {
					$memory === '-1'          => 'No limit',
					self::bytes($memory) > 0  => self::size(self::bytes($memory)),
					default                   => $memory
				},
				$memory,
				'memory_limit',
				false
			),
			self::fact(
				'max_execution_time',
				'Time limit',
				$time === 0 ? 'No limit' : self::count($time, 'second', 'seconds'),
				(string) $time,
				'max_execution_time',
				false
			),
			self::fact(
				'date.timezone',
				'PHP time zone',
				$phpZone === $siteZone ? $phpZone : sprintf('%s, not the site\'s (%s)', $phpZone, $siteZone),
				$phpZone,
				'date.timezone'
			),
			$free !== false && $total !== false
				? self::fact('disk', 'Disk space', sprintf('%s free of %s', self::size($free), self::size($total)), mono: false)
				: null
		]));
	}

	/**
	 * Describes a fact: `$value` to read, and `$raw` (the value when not
	 * given) as it's kept, for a bug report. `$note` names the setting.
	 *
	 * @return array{key: string, label: string, value: string, raw: string, note: ?string, mono: bool}
	 */
	private static function fact(string $key, string $label, string $value, ?string $raw = null, ?string $note = null, bool $mono = true): array
	{
		return ['key' => $key, 'label' => $label, 'value' => $value, 'raw' => $raw ?? $value, 'note' => $note, 'mono' => $mono];
	}

	/**
	 * Names how PHP is run (its SAPI): "PHP-FPM", "Apache module".
	 */
	private static function sapi(string $sapi): string
	{
		return match ($sapi) {
			'fpm-fcgi'       => 'PHP-FPM',
			'apache2handler' => 'Apache module',
			'cgi-fcgi'       => 'FastCGI',
			'cli-server'     => 'PHP\'s built-in server',
			'cli'            => 'command line',
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
	 * Describes a check for the admin.
	 *
	 * @return array{area: string, key: string, status: string, label: string, message: string, hint: string, link: ?string}
	 */
	private static function check(string $area, string $key, CheckResult $result, ?string $link = null): array
	{
		return [
			'area'    => $area,
			'key'     => $key,
			'status'  => $result->status === CheckStatus::Pass ? 'pass' : $result->status->value,
			'label'   => $result->label,
			'message' => $result->message,
			'hint'    => $result->hint,
			'link'    => $result->status === CheckStatus::Pass ? null : $link
		];
	}

	/**
	 * Joins counts with their phrases into a sentence, leaving out the
	 * ones at zero: "2 files have no id, and 1 id is shared."
	 *
	 * @param list<array{int, string, string}> $parts Each count, its singular phrase, and its plural one.
	 */
	private static function sentence(array $parts): string
	{
		$phrases = [];

		foreach ($parts as [$count, $one, $many]) {
			if ($count > 0) {
				$phrases[] = self::count($count, $one, $many);
			}
		}

		return ucfirst(implode(', and ', $phrases)) . '.';
	}

	/**
	 * A count and its phrase.
	 */
	private static function count(int $count, string $one, string $many): string
	{
		return number_format($count) . ' ' . ($count === 1 ? $one : $many);
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
		$names   = [StorageConfig::FILESYSTEM => 'Files', StorageConfig::SQLITE => 'SQLite'];
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
