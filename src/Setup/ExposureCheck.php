<?php

/**
 * Exposure check.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Setup;

use Blush\Core\AppConfig;
use Blush\Core\Paths;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Asks the site's own address for files that must never be served
 * (D-716): `.env`, `config/app.php`, and a file it writes in `storage/`
 * for the check and removes after, which stands for everything there
 * (accounts, sessions, stored secrets, the SQLite database). It catches
 * what a folder's rules can't: a web server pointed at the site's root
 * instead of `public/`, `AllowOverride None`, or a replaced `.htaccess`.
 *
 * A file is served when the answer is a 200 whose body starts as the
 * file does, so a page the site answers every address with isn't
 * mistaken for one. An address that can't be reached is a warning,
 * never a failure; a file served is a failure.
 *
 * It runs only where it means something (`applies()`): in production,
 * at an address that isn't local. Under PHP's built-in server it
 * isn't checked, since a request to itself would wait on itself.
 */
final readonly class ExposureCheck
{
	/**
	 * How many of a file's first bytes are compared.
	 */
	public const int PREFIX = 512;

	/**
	 * The file written in `storage/` for the check.
	 */
	public const string SENTINEL = 'exposure-check.txt';

	public function __construct(
		private Paths $paths,
		private AppConfig $app,
		private UrlProbe $probe,
		private Filesystem $filesystem,
		private Messages $messages
	) {}

	/**
	 * The label its result goes by.
	 */
	public function label(): Text
	{
		return $this->messages->t('setup.exposure_check.label', 'Private files');
	}

	/**
	 * Whether the check runs: in production, at an address others can
	 * reach.
	 */
	public function applies(): bool
	{
		$host = parse_url($this->app->url, PHP_URL_HOST);

		return $this->app->environment->isProduction()
			&& is_string($host)
			&& ! in_array(strtolower($host), ['localhost', '127.0.0.1', '[::1]'], true)
			&& ! str_ends_with(strtolower($host), '.localhost');
	}

	/**
	 * Checks, and says what it found.
	 */
	public function run(): CheckResult
	{
		$url = rtrim($this->app->url, '/');

		if (PHP_SAPI === 'cli-server') {
			return CheckResult::warning($this->label(), $this->messages->t('setup.exposure_check.built_in_server', 'Not checked under PHP\'s built-in server, which can\'t answer a request to itself.'), $this->messages->t('setup.exposure_check.built_in_server_hint', 'Run "{command}" from the command line to check.', command: 'doctor'));
		}

		$sentinel = "{$this->paths->storage}/" . self::SENTINEL;

		try {
			$this->filesystem->writeAtomic($sentinel, bin2hex(random_bytes(32)) . "\n");
		} catch (FilesystemException) {
			$sentinel = null;
		}

		try {
			$found = $this->probe($url, array_filter([
				'.env'            => "{$this->paths->root}/.env",
				'config/app.php'  => "{$this->paths->config}/app.php",
				'storage/'        => $sentinel
			]));
		} finally {
			if ($sentinel !== null) {
				@unlink($sentinel);
			}
		}

		if ($found['served'] !== []) {
			return CheckResult::failure(
				$this->label(),
				$this->messages->t('setup.exposure_check.served', 'Anyone can download {files} from {url}.', files: self::list($found['served']), url: $url),
				$this->messages->t('setup.exposure_check.served_hint', 'Point the web server at the public/ folder, not the site\'s folder (or move public/\'s files into the host\'s web folder), so nothing else is served.')
			);
		}

		if ($found['unreached'] !== []) {
			return CheckResult::warning(
				$this->label(),
				$this->messages->t('setup.exposure_check.unreached', 'Couldn\'t reach {url} to check that {files} {count, plural, one {isn\'t} other {aren\'t}} served.', url: $url, files: self::list($found['unreached']), count: count($found['unreached'])),
				$this->messages->t('setup.exposure_check.unreached_hint', 'Check that {setting} is the site\'s address and that this server can reach it.', setting: 'APP_URL')
			);
		}

		return $found['checked'] === []
			? CheckResult::pass($this->label(), $this->messages->t('setup.exposure_check.nothing', 'Nothing to check.'))
			: CheckResult::pass($this->label(), $this->messages->t('setup.exposure_check.checked', 'Not served at {url}: {files}.', url: $url, files: self::list($found['checked'])));
	}

	/**
	 * Asks for each file inside the site's folder, by its name.
	 *
	 * @param  array<string, string> $files Absolute paths, by name.
	 * @return array{checked: list<string>, served: list<string>, unreached: list<string>}
	 */
	private function probe(string $url, array $files): array
	{
		$found = ['checked' => [], 'served' => [], 'unreached' => []];

		foreach ($files as $name => $file) {
			$path  = $this->paths->relative($file);
			$start = is_file($file) ? (string) @file_get_contents($file, false, null, 0, self::PREFIX) : '';

			// Outside the site's folder, a file can't be reached by its
			// path; empty, it can't be told from a blank page.
			if ($path === $file || $start === '') {
				continue;
			}

			$response = $this->probe->get($url . '/' . implode('/', array_map(rawurlencode(...), explode('/', $path))), self::PREFIX);

			$found[match (true) {
				$response === null                                                   => 'unreached',
				$response->status === 200 && str_starts_with($response->body, $start) => 'served',
				default                                                              => 'checked'
			}][] = $name;
		}

		return $found;
	}

	/**
	 * Lists names in words.
	 *
	 * @param list<string> $names
	 */
	private static function list(array $names): string
	{
		return match (count($names)) {
			0, 1    => implode('', $names),
			2       => implode(' and ', $names),
			default => implode(', ', array_slice($names, 0, -1)) . ', and ' . array_last($names)
		};
	}
}
