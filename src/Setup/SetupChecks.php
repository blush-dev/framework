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

/**
 * Checks that a site is set up to run: PHP and its extensions, `.env`,
 * settings that are risky in production, the public folder, and writable
 * storage. `doctor` runs them all. The web runner runs only `storage()`
 * before every request, since an unwritable `storage/` is the setup
 * problem that breaks a site outright; it needs nothing but `Paths`, so it
 * works before the application loads.
 */
final readonly class SetupChecks
{
	/**
	 * The oldest PHP version Blush runs on.
	 */
	public const string MINIMUM_PHP = '8.5.0';

	/**
	 * The PHP extensions Blush needs.
	 */
	public const array EXTENSIONS = ['dom', 'intl', 'mbstring'];

	/**
	 * The storage paths Blush writes to, by name.
	 */
	public const array STORAGE = ['storage', 'cache', 'index', 'logs', 'sessions', 'accounts', 'export'];

	public function __construct(private Paths $paths)
	{
	}

	/**
	 * Runs every check.
	 *
	 * @return list<CheckResult>
	 */
	public function all(AppConfig $app): array
	{
		return [...$this->php(), ...$this->site($app), ...$this->storage()];
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
				: CheckResult::failure('PHP', sprintf('%s is too old.', PHP_VERSION), sprintf('Blush needs PHP %s or newer.', self::MINIMUM_PHP))
		];

		foreach (self::EXTENSIONS as $extension) {
			$results[] = extension_loaded($extension)
				? CheckResult::pass("ext-{$extension}", 'Loaded.')
				: CheckResult::failure("ext-{$extension}", 'Not loaded.', sprintf('Install or enable PHP\'s "%s" extension.', $extension));
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
		$init    = sprintf('Run "%s init" to create it.', Framework::BINARY);
		$results = [
			is_file("{$this->paths->root}/.env")
				? CheckResult::pass('.env', 'Found.')
				: CheckResult::warning('.env', 'Not found, so settings come only from the server\'s environment.', $init),
			CheckResult::pass('Environment', $app->environment->value . ($app->debug ? ', with debugging on' : ''))
		];

		if ($app->environment->isProduction()) {
			if ($app->debug) {
				$results[] = CheckResult::failure('APP_DEBUG', 'Debugging is on in production, so error pages show code and paths.', 'Set APP_DEBUG=false.');
			}

			if (in_array(Uri::parse($app->url)?->getHost(), ['localhost', '127.0.0.1'], true)) {
				$results[] = CheckResult::warning('APP_URL', sprintf('"%s" is a local address, so feeds, sitemaps, and full URLs point there.', $app->url), 'Set APP_URL to the site\'s real address.');
			}
		}

		$public = $this->paths->relative($this->paths->public);

		$results[] = is_file("{$this->paths->public}/index.php")
			? CheckResult::pass("{$public}/index.php", 'Found.')
			: CheckResult::failure("{$public}/index.php", 'Not found, so the web server has nothing to run.', 'Copy index.php from the Blush skeleton.');

		if (! is_file("{$this->paths->public}/.htaccess")) {
			$results[] = CheckResult::warning("{$public}/.htaccess", 'Not found. Apache needs it to send requests to index.php (nginx doesn\'t use it).');
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
		$hint  = sprintf('Run "%s init", or let the web server\'s user write to it.', Framework::BINARY);

		if (is_dir($path)) {
			return is_writable($path)
				? CheckResult::pass($label, 'Writable.')
				: CheckResult::failure($label, 'Not writable.', $hint);
		}

		if (file_exists($path)) {
			return CheckResult::failure($label, 'This is a file, not a folder.', 'Remove the file.');
		}

		$parent = dirname($path);

		while (! file_exists($parent) && dirname($parent) !== $parent) {
			$parent = dirname($parent);
		}

		return is_dir($parent) && is_writable($parent)
			? CheckResult::pass($label, 'Created when it\'s needed.')
			: CheckResult::failure($label, sprintf('Missing, and %s/ isn\'t writable.', $this->paths->relative($parent)), $hint);
	}
}
