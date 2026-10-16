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
use Blush\Admin\AdminConfig;
use Blush\Auth\Account;
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
use Blush\Env\Env;
use Blush\Env\EnvException;
use Blush\Env\EnvFile;
use Blush\Secret\SecretKeys;
use Blush\Setup\SetupChecks;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;

/**
 * Sets up a site after it's installed (D-218), and is safe to run again:
 *
 * 1. Creates `.env` from `.env.example` when there's none. In a terminal,
 *    it asks for the site's name, URL (DDEV's when it runs there, else
 *    `serve`'s), timezone
 *    (this computer's to start), and environment first (D-876). An
 *    existing `.env` is never changed, except to add secrets it lacks.
 * 2. Adds a random `APP_SECRET` when there's none, which signs preview
 *    links (D-226), and a `SECRETS_KEY`, which encrypts stored secrets
 *    (D-715). With `--webhook`, also adds a random `PUBLISH_SECRET`,
 *    which turns on the publish webhook; it isn't asked, and stays off
 *    otherwise, since it runs publishing on request.
 * 3. Creates the storage folders and reports any Blush can't write to.
 * 4. In a terminal, while there are no accounts, offers to create an
 *    owner (D-217, D-500): an email address, a name, and a username
 *    suggested from the email. It writes the email address to `.env` as
 *    `APP_EMAIL`, the site's (D-723), when that isn't set.
 * 5. Ends with what the site is set up as, and where to go next.
 *
 * The skeleton runs it after `composer create-project`.
 */
#[Command('init', 'Set up the site: .env, secrets, and storage folders.')]
final readonly class SetUpSite
{
	/**
	 * `.env`'s contents when the site has no `.env.example`.
	 */
	private const string DEFAULT_ENV = "APP_ENV=production\nAPP_DEBUG=false\nAPP_NAME=\"Blush\"\nAPP_URL=\"" . Serve::ADDRESS . "\"\nAPP_TIMEZONE=\"UTC\"\nAPP_LOCALE=\"en_US\"\n";

	public function __construct(
		private Paths $paths,
		private Filesystem $filesystem,
		private Accounts $accounts,
		private Accounts $manager,
		private Env $env,
		private AdminConfig $admin
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

			$output->success('Added an APP_SECRET, which signs preview links.');
		}

		if (! $file->filled(SecretKeys::CURRENT)) {
			$file    = $file->with(SecretKeys::CURRENT, SecretKeys::generate());
			$changed = true;

			$output->success(sprintf('Added a %s, which encrypts stored secrets.', SecretKeys::CURRENT));
		}

		if (! $file->filled('PUBLISH_SECRET')) {
			if ($webhook) {
				$file    = $file->with('PUBLISH_SECRET', bin2hex(random_bytes(32)));
				$changed = true;

				$output->success('Added a PUBLISH_SECRET; the publish webhook is on.');
			}
		} elseif ($webhook) {
			$output->comment('PUBLISH_SECRET is already set; left as it is.');
		}

		if ($changed) {
			$file->save($this->filesystem);
		}

		if ($created || $changed) {
			$output->success($created ? 'Created .env.' : 'Updated .env.');
		} else {
			$output->comment('.env already exists; left as it is.');
		}

		$code  = $this->createStorage($output);
		$owner = null;

		if ($code === ExitCode::Success && $prompt->interactive && $this->accounts->isEmpty()) {
			$owner = $this->offerAccount($output, $prompt);

			if ($owner !== null && $owner->email !== null) {
				$this->addSiteEmail($output, $owner->email);
			}
		}

		if ($code === ExitCode::Success) {
			$this->report($output, $owner);
		}

		return $code;
	}

	/**
	 * Says the site is set up, what it's set up as from `.env`, and where
	 * to go next: the admin's sign-in when it's on.
	 *
	 * @throws EnvException
	 */
	private function report(Output $output, ?Account $owner): void
	{
		$file = EnvFile::load("{$this->paths->root}/.env");
		$url  = (string) $file->get('APP_URL');

		$output->success('The site is set up.');
		$output->definitions(array_filter([
			'Name'        => (string) $file->get('APP_NAME'),
			'URL'         => $url,
			'Timezone'    => (string) $file->get('APP_TIMEZONE'),
			'Environment' => (string) $file->get('APP_ENV'),
			'Owner'       => $owner === null ? '' : $owner->username
		], static fn (string $value): bool => $value !== ''));

		$output->next($this->admin->enabled && $url !== ''
			? sprintf('Next: sign in at %2$s, `%1$s doctor` checks the site, and `%1$s serve` shows it in a browser.', Framework::BINARY, rtrim($url, '/') . $this->admin->path)
			: sprintf('Next: `%1$s doctor` checks the site, and `%1$s serve` shows it in a browser.', Framework::BINARY));
	}

	/**
	 * Offers to create the first account, the owner (D-500), asking for
	 * its email address first, then a name, then a username suggested
	 * from the email (never `admin`, the first name anyone tries; D-876).
	 * Returns the account, or `null` when none was made.
	 *
	 * @throws InvalidInput
	 */
	private function offerAccount(Output $output, Prompt $prompt): ?Account
	{
		if (! $prompt->confirm('Create an owner account for the admin?', true)) {
			return null;
		}

		$email = trim($prompt->ask('Email address', null, static function (string $email): ?string {
			return Account::isValidEmail(trim($email)) ? null : 'That isn\'t an email address.';
		}, 'you@example.com'));

		$name = trim($prompt->ask('Name', '', placeholder: 'What the admin calls you (optional)'));

		$username = $prompt->ask('Username', self::suggestUsername($email), fn (string $username): ?string => $this->manager->usernameProblem($username)?->__toString(), 'jane');

		try {
			$password = $prompt->newSecret('Password:', 'Password again:', fn (string $password): ?string => $this->manager->passwordProblem($password)?->__toString());
			$account = $this->manager->create($username, $password, [BuiltInRole::Owner->value], name: $name === '' ? null : $name, email: $email);
		} catch (AuthException $e) {
			$output->error($e->getMessage());

			return null;
		}

		$output->success(sprintf('Created the "%s" account.', $username));

		return $account;
	}

	/**
	 * Returns a username made from an email address's name ("Jane.Doe+x"
	 * is `jane.doe`), or `null` when it doesn't make one.
	 */
	private static function suggestUsername(string $email): ?string
	{
		$local    = strtolower(strstr($email, '@', true) ?: $email);
		$username = (string) preg_replace('/[^a-z0-9._-]/', '', explode('+', $local)[0]);

		return Account::isValidUsername($username) ? $username : null;
	}

	/**
	 * Makes the owner's email address the site's (D-723), unless `.env`
	 * already names one.
	 *
	 * @throws EnvException
	 * @throws FilesystemException
	 */
	private function addSiteEmail(Output $output, string $email): void
	{
		$file = EnvFile::load("{$this->paths->root}/.env");

		if ($file->filled('APP_EMAIL')) {
			return;
		}

		$file->with('APP_EMAIL', $email)->save($this->filesystem);

		$output->success(sprintf('Added APP_EMAIL, the site\'s email address: %s.', $email));
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

		$url = $prompt->ask('Site URL', $this->env->get('DDEV_PRIMARY_URL') ?: Serve::ADDRESS, static function (string $url): ?string {
			return Uri::parse($url) !== null && preg_match('#^https?://#i', $url) === 1
				? null
				: 'Enter a full http:// or https:// address.';
		});

		$timezone = $prompt->find(
			'Timezone',
			array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers()),
			$this->localTimezone() ?? ($file->get('APP_TIMEZONE') ?: 'UTC'),
			'Enter a timezone such as "America/Chicago" or "UTC".'
		);

		$environment = Environment::from($prompt->select('Environment', [
			Environment::Development->value => 'Development: shows errors, for working on this computer',
			Environment::Production->value  => 'Production: a live site'
		], Environment::Development->value));

		return $file
			->with('APP_NAME', $name)
			->with('APP_URL', $url)
			->with('APP_TIMEZONE', $timezone)
			->with('APP_ENV', $environment->value)
			->with('APP_DEBUG', $environment->isDevelopment() ? 'true' : 'false');
	}

	/**
	 * Returns this computer's timezone, from `TZ` or the zone
	 * `/etc/localtime` links to, or `null` when neither names one.
	 */
	private function localTimezone(): ?string
	{
		$link  = @readlink('/etc/localtime');
		$zones = DateTimeZone::listIdentifiers();

		foreach ([$this->env->get('TZ'), $link === false ? null : preg_replace('#^.*zoneinfo/#', '', $link)] as $zone) {
			if (is_string($zone) && in_array($zone, $zones, true)) {
				return $zone;
			}
		}

		return null;
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

		foreach ($failures as $index => $failure) {
			$output->error(sprintf('%s %s', $failure->label, $failure->message), $index === array_key_last($failures) ? 'The web server\'s user needs to be able to write to `storage/`.' : '');
		}

		if ($failures !== []) {
			return ExitCode::Failure;
		}

		return ExitCode::Success;
	}
}
