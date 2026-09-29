<?php

/**
 * Site setup command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use DateTimeZone;
use Uri\Rfc3986\Uri;
use Blush\Auth\Account;
use Blush\Auth\AccountStore;
use Blush\Auth\Accounts;
use Blush\Auth\AuthException;
use Blush\Auth\BuiltInRole;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Console\Prompt;
use Blush\Core\Environment;
use Blush\Core\Framework;
use Blush\Core\Paths;
use Blush\Env\EnvException;
use Blush\Env\EnvFile;
use Blush\Setup\SetupChecks;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;

/**
 * Sets up a site after it's installed (D-218), and is safe to run again:
 *
 * 1. Creates `.env` from `.env.example` when there's none. In a terminal,
 *    it asks for the site's name, URL, timezone, and environment first.
 *    An existing `.env` is never changed, except to add secrets it lacks.
 * 2. Adds a random `APP_SECRET` when there's none, which signs preview
 *    links (D-226). With `--webhook` (or a yes when asked), also adds a
 *    random `PUBLISH_SECRET`, which turns on the publish webhook. The
 *    webhook stays off otherwise, since it runs publishing on request.
 * 3. Creates the storage folders and reports any Blush can't write to.
 * 4. In a terminal, while there are no accounts, offers to create an
 *    administrator (D-217).
 *
 * The skeleton runs it after `composer create-project`.
 */
#[Command('init', 'Set up the site: .env, secrets, and storage folders.')]
final readonly class SetUpSite
{
	/**
	 * `.env`'s contents when the site has no `.env.example`.
	 */
	private const string DEFAULT_ENV = "APP_ENV=production\nAPP_DEBUG=false\nAPP_NAME=\"Blush\"\nAPP_URL=\"http://localhost\"\nAPP_TIMEZONE=\"UTC\"\nAPP_LOCALE=\"en_US\"\n";

	public function __construct(
		private Paths $paths,
		private Filesystem $filesystem,
		private AccountStore $accounts,
		private Accounts $manager
	) {}

	/**
	 * @throws EnvException       When `.env` can't be read or changed.
	 * @throws FilesystemException When `.env` can't be written.
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		Prompt $prompt,
		#[Option('Turn on the publish webhook by adding a PUBLISH_SECRET.')] bool $webhook = false
	): ExitCode {
		$file    = EnvFile::load("{$this->paths->root}/.env");
		$created = ! $file->exists();

		if ($created) {
			$file = new EnvFile($file->path, $this->example());

			if ($prompt->interactive) {
				$file = $this->ask($file, $prompt);
			}
		}

		$changed = $created;

		if (! $file->filled('APP_SECRET')) {
			$file    = $file->with('APP_SECRET', bin2hex(random_bytes(32)));
			$changed = true;

			$output->info('Added an APP_SECRET, which signs preview links.');
		}

		if (! $file->filled('PUBLISH_SECRET')) {
			if ($webhook || ($prompt->interactive && $prompt->confirm('Turn on the publish webhook?'))) {
				$file    = $file->with('PUBLISH_SECRET', bin2hex(random_bytes(32)));
				$changed = true;

				$output->info('Added a PUBLISH_SECRET; the publish webhook is on.');
			}
		} elseif ($webhook) {
			$output->comment('PUBLISH_SECRET is already set; left as it is.');
		}

		if ($changed) {
			$file->save($this->filesystem);
		}

		$output->info($created ? 'Created .env.' : ($changed ? 'Updated .env.' : '.env already exists; left as it is.'));

		$code = $this->createStorage($output);

		if ($code === ExitCode::Success && $prompt->interactive && $this->accounts->isEmpty()) {
			$this->offerAccount($output, $prompt);
		}

		if ($code === ExitCode::Success) {
			$output->success(sprintf(
				'The site is set up. Next: "%1$s doctor" checks it, and "%1$s serve" shows it in a browser.',
				Framework::BINARY
			));
		}

		return $code;
	}

	/**
	 * Offers to create the first account, an administrator.
	 *
	 * @throws InvalidInput
	 */
	private function offerAccount(Output $output, Prompt $prompt): void
	{
		if (! $prompt->confirm('Create an administrator account for the admin?', true)) {
			return;
		}

		$username = $prompt->ask('Username', 'admin', static function (string $username): ?string {
			return Account::isValidUsername($username) ? null : 'Use lowercase letters, digits, ".", "_", and "-".';
		});

		try {
			$password = $prompt->newSecret('Password:', 'Password again:', $this->manager->passwordProblem(...));
			$this->manager->create($username, $password, [BuiltInRole::Administrator->value]);
		} catch (AuthException $e) {
			$output->error($e->getMessage());

			return;
		}

		$output->info(sprintf('Created the "%s" account.', $username));
	}

	/**
	 * Returns `.env.example`'s contents, or the defaults without one.
	 *
	 * @throws EnvException
	 */
	private function example(): string
	{
		$example = EnvFile::load("{$this->paths->root}/.env.example");

		return $example->exists() ? $example->contents : self::DEFAULT_ENV;
	}

	/**
	 * Asks for the site's basic settings, offering the file's values as
	 * defaults.
	 *
	 * @throws EnvException
	 * @throws InvalidInput
	 */
	private function ask(EnvFile $file, Prompt $prompt): EnvFile
	{
		$name = $prompt->ask('Site name', $file->get('APP_NAME') ?: 'Blush');

		$url = $prompt->ask('Site URL', $file->get('APP_URL') ?: 'http://localhost', static function (string $url): ?string {
			return Uri::parse($url) !== null && preg_match('#^https?://#i', $url) === 1
				? null
				: 'Enter a full http:// or https:// address.';
		});

		$timezone = $prompt->ask('Timezone', $file->get('APP_TIMEZONE') ?: 'UTC', static function (string $timezone): ?string {
			return in_array($timezone, DateTimeZone::listIdentifiers(), true)
				? null
				: 'Enter a timezone such as "America/Chicago" or "UTC".';
		});

		$environment = Environment::from($prompt->choice(
			'Environment',
			[Environment::Development->value, Environment::Production->value],
			Environment::Development->value
		));

		return $file
			->with('APP_NAME', $name)
			->with('APP_URL', $url)
			->with('APP_TIMEZONE', $timezone)
			->with('APP_ENV', $environment->value)
			->with('APP_DEBUG', $environment->isDevelopment() ? 'true' : 'false');
	}

	/**
	 * Creates the storage folders and reports any that can't be written.
	 */
	private function createStorage(Output $output): ExitCode
	{
		$paths = $this->paths->toArray();

		foreach (SetupChecks::STORAGE as $name) {
			if (! is_dir($paths[$name])) {
				@mkdir($paths[$name], 0775, true);
			}
		}

		$failures = SetupChecks::failures(new SetupChecks($this->paths)->storage());

		foreach ($failures as $failure) {
			$output->error(sprintf('%s %s', $failure->label, $failure->message));
		}

		if ($failures !== []) {
			$output->line('The web server\'s user needs to be able to write to storage/.');

			return ExitCode::Failure;
		}

		return ExitCode::Success;
	}
}
