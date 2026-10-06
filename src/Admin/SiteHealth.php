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
use Psr\Clock\ClockInterface;
use Blush\Cache\CacheConfig;
use Blush\Cache\CacheDriver;
use Blush\Content\ContentRepository;
use Blush\Core\AppConfig;
use Blush\Core\Framework;
use Blush\Core\Paths;
use Blush\Extension\ExtensionState;
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
 * once and checks again only when asked; `saved()` is that report. It
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
 *   settings, and writable storage.
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
		'apcu'     => ['why' => 'The APCu cache driver', 'recommended' => false]
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
		private ContentRepository $content,
		private MediaLibrary $library,
		private ClockInterface $clock,
		private HealthReportStore $store
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
				['key' => 'content', 'label' => 'Content', 'description' => 'Entries\' files: their fields, ids, folders, and names'],
				['key' => 'media', 'label' => 'Media', 'description' => 'Library files\' ids, details, and image sizes'],
				['key' => 'extensions', 'label' => 'Extensions', 'description' => 'The theme, plugins, and icon packs that are on'],
				['key' => 'system', 'label' => 'System', 'description' => 'PHP, settings, the public folder, and storage'],
				['key' => 'accounts', 'label' => 'Accounts', 'description' => 'Who can always get back in']
			],
			'checks'       => [...$this->summarize($files = $this->fileReport()), ...$this->site()],
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
	 * Returns the last report's content and media files (`ContentHealth`,
	 * notices included, with `at`, when), checking first when there's no
	 * report.
	 *
	 * @param  array<array-key, mixed> $server
	 * @return array<string, mixed>
	 */
	public function files(array $server = []): array
	{
		$files = $this->saved()['files'] ?? null;

		return is_array($files) && $files !== [] ? array_filter($files, is_string(...), ARRAY_FILTER_USE_KEY) : $this->checkFiles($server);
	}

	/**
	 * Checks content and media files again, keeping them and their
	 * summary in the last report (the rest of it as it was), and returns
	 * them. Without a report, it checks everything.
	 *
	 * @param  array<array-key, mixed> $server
	 * @return array<string, mixed>
	 */
	public function checkFiles(array $server = []): array
	{
		$report = $this->saved();

		if ($report === null || ! is_array($report['checks'] ?? null)) {
			$files = $this->run($server)['files'];

			return is_array($files) ? array_filter($files, is_string(...), ARRAY_FILTER_USE_KEY) : [];
		}

		$files  = $this->fileReport();
		$others = array_values(array_filter($report['checks'], static fn (mixed $check): bool => ! is_array($check) || ! in_array($check['area'] ?? null, ['content', 'media'], true)));

		$report['checks'] = [...$this->summarize($files), ...$others];
		$report['files']  = $files;
		$this->store->save($report);

		return $files;
	}

	/**
	 * Returns a new `ContentHealth` report, notices included (the admin
	 * hides them until asked), and when it was made (`at`).
	 *
	 * @return array{at: string, checked: int, metadata: int, strict: bool, counts: array{error: int, warning: int, notice: ?int}, files: list<array{path: string, area: string, violations: list<array{field: string, message: string, severity: string}>}>, ids: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, mediaIds: array{missing: list<string>, duplicates: list<array{id: string, paths: list<string>}>}, fileNames: list<array{type: string, label: string, pattern: string, count: int, examples: list<array{path: string, to: string}>, skipped: int}>, flat: array{count: int, examples: list<array{path: string, to: string}>}, mediaSizes: array{sizes: int, images: int, stale: int}}
	 */
	private function fileReport(): array
	{
		return ['at' => $this->clock->now()->format(DateTimeInterface::ATOM), ...$this->health->report(true)];
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
	 * @param  array{files: list<array{path: string, area: string, violations: list<array{field: string, message: string, severity: string}>}>, checked: int, metadata: int, ids: array{missing: list<string>, duplicates: list<mixed>}, mediaIds: array{missing: list<string>, duplicates: list<mixed>}, fileNames: list<array{count: int}>, flat: array{count: int}, mediaSizes: array{sizes: int, images: int, stale: int}} $report
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

		$flat = $report['flat']['count'];

		$checks[] = self::check('content', 'folders', $flat > 0
			? CheckResult::warning('Collection folders', sprintf('%s kept in a folder, not directly in its collection\'s.', self::count($flat, 'entry is', 'entries are')))
			: CheckResult::pass('Collection folders', 'Every collection\'s entries are files in its folder.'), 'content');

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

		foreach (SetupChecks::EXTENSIONS as $extension) {
			$loaded = extension_loaded($extension);
			$rows[] = ['group' => 'Extensions', 'name' => $extension, 'why' => '', 'needs' => 'required', 'installed' => $loaded ? 'loaded' : 'missing', 'status' => $loaded ? 'pass' : 'failure'];
		}

		foreach (self::OPTIONAL as $extension => ['why' => $why, 'recommended' => $recommended]) {
			$loaded   = $extension === 'opcache' ? self::opcache() : extension_loaded($extension);
			$required = $extension === 'apcu' && $this->cache->driver === CacheDriver::Apcu->value;
			$rows[]   = [
				'group'     => 'Extensions',
				'name'      => $extension,
				'why'       => $required ? "{$why}, which the cache uses" : $why,
				'needs'     => $required ? 'required' : ($recommended ? 'recommended' : 'optional'),
				'installed' => $loaded ? ($extension === 'opcache' ? 'on' : 'loaded') : ($extension === 'opcache' && extension_loaded('Zend OPcache') ? 'off' : 'missing'),
				'status'    => $loaded ? 'pass' : ($required ? 'failure' : ($recommended ? 'warning' : 'optional'))
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
	 * Returns facts about the site.
	 *
	 * @return list<array{label: string, value: string, mono: bool}>
	 */
	private function siteFacts(): array
	{
		$languages = array_map(static fn (object $language): string => $language->code, $this->app->languages->all());

		return [
			['label' => 'Blush', 'value' => Framework::VERSION, 'mono' => true],
			['label' => 'Environment', 'value' => $this->app->environment->value . ($this->app->debug ? ', debugging on' : ''), 'mono' => true],
			['label' => 'Site URL', 'value' => $this->app->url, 'mono' => true],
			['label' => 'Admin path', 'value' => $this->admin->path, 'mono' => true],
			['label' => 'Content', 'value' => $this->paths->relative($this->paths->content) . '/', 'mono' => true],
			['label' => 'Media', 'value' => $this->paths->relative($this->paths->media) . '/', 'mono' => true],
			['label' => 'Time zone', 'value' => $this->app->timezone()->getName(), 'mono' => true],
			['label' => 'Languages', 'value' => implode(', ', $languages), 'mono' => true],
			['label' => 'Theme', 'value' => $this->extensions->theme, 'mono' => true],
			['label' => 'Cache', 'value' => $this->cache->driver, 'mono' => true],
			['label' => 'Entries', 'value' => number_format($this->content->query()->any()->count()), 'mono' => false],
			['label' => 'Media files', 'value' => number_format($this->library->query(new MediaQuery(per: 1))->total), 'mono' => false]
		];
	}

	/**
	 * Returns facts about the server, as PHP sees it from here.
	 *
	 * @param  array<array-key, mixed> $server
	 * @return list<array{label: string, value: string, mono: bool}>
	 */
	private function serverFacts(array $server): array
	{
		$software = $server['SERVER_SOFTWARE'] ?? null;
		$free     = @disk_free_space($this->paths->root);
		$total    = @disk_total_space($this->paths->root);

		return array_values(array_filter([
			is_string($software) && $software !== '' ? ['label' => 'Software', 'value' => $software, 'mono' => true] : null,
			['label' => 'Operating system', 'value' => sprintf('%s %s (%s)', PHP_OS_FAMILY, php_uname('r'), php_uname('m')), 'mono' => true],
			['label' => 'PHP', 'value' => sprintf('%s (%s)', PHP_VERSION, PHP_SAPI), 'mono' => true],
			['label' => 'memory_limit', 'value' => (string) ini_get('memory_limit'), 'mono' => true],
			['label' => 'max_execution_time', 'value' => ((string) ini_get('max_execution_time')) . 's', 'mono' => true],
			['label' => 'PHP time zone', 'value' => date_default_timezone_get(), 'mono' => true],
			$free !== false && $total !== false ? ['label' => 'Disk', 'value' => sprintf('%s free of %s', self::size($free), self::size($total)), 'mono' => false] : null
		]));
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

		return sprintf($unit === 0 ? '%.0f %s' : '%.1f %s', $bytes, $units[$unit]);
	}
}
