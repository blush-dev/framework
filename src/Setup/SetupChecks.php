<?php

/**
 * Setup checks.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Setup;

use Uri\Rfc3986\Uri;
use Blush\Core\AppConfig;
use Blush\Core\Framework;
use Blush\Core\Paths;
use Blush\Storage\Sql\SqliteConnection;
use Blush\Storage\StorageConfig;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Checks that a site is set up to run: PHP and its extensions, `.env`,
 * settings that are risky in production, the public folder, and writable
 * storage. `doctor` runs them all. The web runner runs only `storage()`
 * before every request, since an unwritable `storage/` is the setup
 * problem that breaks a site outright; it needs nothing but `Paths`, so it
 * works before the application loads, in English (the `Messages` it's
 * given then has no translator); the container gives it core's.
 */
final readonly class SetupChecks
{
	/**
	 * The oldest PHP version Blush runs on.
	 */
	public const string MINIMUM_PHP = '8.5.0';

	/**
	 * The PHP extensions Blush needs; what uses each is `why()`. Those
	 * every PHP 8.5 has (`json`, `uri`, and so on) are left out: they
	 * can't be missing.
	 *
	 * @var list<string>
	 */
	public const array EXTENSIONS = ['dom', 'intl', 'mbstring'];

	/**
	 * The storage paths Blush writes to, by name.
	 */
	public const array STORAGE = ['storage', 'cache', 'index', 'logs', 'sessions', 'jobs', 'accounts'];

	public function __construct(
		private Paths $paths,
		private ?StorageConfig $storage = null,
		private Messages $messages = new Messages()
	) {}

	/**
	 * Says what uses a PHP extension Blush needs (`EXTENSIONS`).
	 */
	public function why(string $extension): Text
	{
		return match ($extension) {
			'dom'   => $this->messages->t('setup.setup_checks.why.dom', 'Reads and rewrites HTML (pages, embeds, icons, theme checks) and photos\' XMP details'),
			'intl'  => $this->messages->t('setup.setup_checks.why.intl', 'Dates, numbers, and sorting in the site\'s language, and translations'),
			default => $this->messages->t('setup.setup_checks.why.mbstring', 'Handles text in every language (lengths, excerpts, slugs), and Markdown needs it')
		};
	}

	/**
	 * Runs every check.
	 *
	 * @return list<CheckResult>
	 */
	public function all(AppConfig $app): array
	{
		return [...$this->php(), ...$this->site($app), ...$this->storage(), ...$this->driver()];
	}

	/**
	 * Checks the storage driver (D-662): files need nothing more; SQLite
	 * needs PHP's `pdo_sqlite` with SQLite's JSON functions, and a
	 * writable folder for its database.
	 *
	 * @return list<CheckResult>
	 */
	public function driver(): array
	{
		if ($this->storage === null || ! $this->storage->uses(StorageConfig::SQLITE)) {
			return [CheckResult::pass($this->storageLabel(), $this->messages->t('setup.setup_checks.storage_files', 'Files.'))];
		}

		$file = str_starts_with($this->storage->sqlite, '/') ? $this->storage->sqlite : "{$this->paths->root}/{$this->storage->sqlite}";

		if (! SqliteConnection::available()) {
			return [CheckResult::failure($this->storageLabel(), $this->messages->t('setup.setup_checks.sqlite_unavailable', 'SQLite is named, but PHP can\'t open SQLite databases with their JSON functions.'), $this->messages->t('setup.setup_checks.sqlite_unavailable_hint', 'Turn on PHP\'s pdo_sqlite extension, or set the storage driver back to "filesystem".'))];
		}

		return [
			CheckResult::pass($this->storageLabel(), $this->messages->t('setup.setup_checks.storage_sqlite', 'SQLite, in {file}.', file: $this->paths->relative($file))),
			$this->writable(dirname($file))
		];
	}

	/**
	 * Checks the PHP version and extensions.
	 *
	 * @return list<CheckResult>
	 */
	public function php(): array
	{
		$results = [
			version_compare(PHP_VERSION, self::MINIMUM_PHP, '>=')
				? CheckResult::pass('PHP', PHP_VERSION)
				: CheckResult::failure('PHP', $this->messages->t('setup.setup_checks.php_too_old', '{version} is too old.', version: PHP_VERSION), $this->messages->t('setup.setup_checks.php_too_old_hint', '{name} needs PHP {version} or newer.', name: Framework::NAME, version: self::MINIMUM_PHP))
		];

		foreach (self::EXTENSIONS as $extension) {
			$results[] = extension_loaded($extension)
				? CheckResult::pass("ext-{$extension}", $this->messages->t('setup.setup_checks.loaded', 'Loaded.'))
				: CheckResult::failure("ext-{$extension}", $this->messages->t('setup.setup_checks.not_loaded', 'Not loaded.'), $this->messages->t('setup.setup_checks.not_loaded_hint', 'Install or enable PHP\'s "{extension}" extension.', extension: $extension));
		}

		return $results;
	}

	/**
	 * Checks `.env`, the environment's settings, and the public folder.
	 *
	 * @return list<CheckResult>
	 */
	public function site(AppConfig $app): array
	{
		$results = [
			is_file("{$this->paths->root}/.env")
				? CheckResult::pass('.env', $this->found())
				: CheckResult::warning('.env', $this->messages->t('setup.setup_checks.env_missing', 'Not found, so settings come only from the server\'s environment.'), $this->messages->t('setup.setup_checks.env_missing_hint', 'Run "{command}" to create it.', command: Framework::BINARY . ' init')),
			CheckResult::pass(
				$this->messages->t('setup.setup_checks.environment', 'Environment'),
				$app->debug ? $this->messages->t('setup.setup_checks.environment_debug', '{environment}, with debugging on', environment: $app->environment->value) : $app->environment->value
			)
		];

		if ($app->environment->isProduction()) {
			if ($app->debug) {
				$results[] = CheckResult::failure('APP_DEBUG', $this->messages->t('setup.setup_checks.debug_in_production', 'Debugging is on in production, so error pages show code and paths.'), $this->messages->t('setup.setup_checks.debug_in_production_hint', 'Set {setting}.', setting: 'APP_DEBUG=false'));
			}

			if (in_array(Uri::parse($app->url)?->getHost(), ['localhost', '127.0.0.1'], true)) {
				$results[] = CheckResult::warning('APP_URL', $this->messages->t('setup.setup_checks.local_url', '"{url}" is a local address, so feeds, sitemaps, and full URLs point there.', url: $app->url), $this->messages->t('setup.setup_checks.local_url_hint', 'Set {setting} to the site\'s real address.', setting: 'APP_URL'));
			}
		}

		$public = $this->paths->relative($this->paths->public);

		$results[] = is_file("{$this->paths->public}/index.php")
			? CheckResult::pass("{$public}/index.php", $this->found())
			: CheckResult::failure("{$public}/index.php", $this->messages->t('setup.setup_checks.index_missing', 'Not found, so the web server has nothing to run.'), $this->messages->t('setup.setup_checks.index_missing_hint', 'Copy index.php from the {name} skeleton.', name: Framework::NAME));

		if (! is_file("{$this->paths->public}/.htaccess")) {
			$results[] = CheckResult::warning("{$public}/.htaccess", $this->messages->t('setup.setup_checks.htaccess_missing', 'Not found. Apache needs it to send requests to index.php (nginx doesn\'t use it).'));
		}

		return $results;
	}

	/**
	 * Checks that Blush can write to each storage folder. A folder that
	 * doesn't exist yet passes when the nearest folder above it is
	 * writable, since Blush creates folders as it needs them.
	 *
	 * @return list<CheckResult>
	 */
	public function storage(): array
	{
		$paths   = $this->paths->toArray();
		$results = [];

		foreach (self::STORAGE as $name) {
			$results[] = $this->writable($paths[$name]);
		}

		return $results;
	}

	/**
	 * Returns only the failures among some results.
	 *
	 * @param  list<CheckResult> $results
	 * @return list<CheckResult>
	 */
	public static function failures(array $results): array
	{
		return array_values(array_filter($results, static fn (CheckResult $result): bool => $result->isFailure()));
	}

	/**
	 * Checks one storage path.
	 */
	private function writable(string $path): CheckResult
	{
		$label = $this->paths->relative($path) . '/';
		$hint  = $this->messages->t('setup.setup_checks.unwritable_hint', 'Run "{command}", or let the web server\'s user write to it.', command: Framework::BINARY . ' init');

		if (is_dir($path)) {
			return is_writable($path)
				? CheckResult::pass($label, $this->messages->t('setup.setup_checks.writable', 'Writable.'))
				: CheckResult::failure($label, $this->messages->t('setup.setup_checks.unwritable', 'Not writable.'), $hint);
		}

		if (file_exists($path)) {
			return CheckResult::failure($label, $this->messages->t('setup.setup_checks.not_a_folder', 'This is a file, not a folder.'), $this->messages->t('setup.setup_checks.not_a_folder_hint', 'Remove the file.'));
		}

		$parent = dirname($path);

		while (! file_exists($parent) && dirname($parent) !== $parent) {
			$parent = dirname($parent);
		}

		return is_dir($parent) && is_writable($parent)
			? CheckResult::pass($label, $this->messages->t('setup.setup_checks.created_when_needed', 'Created when it\'s needed.'))
			: CheckResult::failure($label, $this->messages->t('setup.setup_checks.missing_parent_unwritable', 'Missing, and {folder} isn\'t writable.', folder: $this->paths->relative($parent) . '/'), $hint);
	}

	/**
	 * Says a file was found.
	 */
	private function found(): Text
	{
		return $this->messages->t('setup.setup_checks.found', 'Found.');
	}

	/**
	 * The storage driver check's label.
	 */
	private function storageLabel(): Text
	{
		return $this->messages->t('setup.setup_checks.storage', 'Storage');
	}
}
