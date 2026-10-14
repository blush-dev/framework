<?php

/**
 * Site checks.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Setup;

use Psr\Clock\ClockInterface;
use Blush\Auth\Accounts;
use Blush\Auth\AuthException;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
use Blush\Core\Environment;
use Blush\Core\Framework;
use Blush\Extension\DefinitionClash;
use Blush\Extension\ExtensionState;
use Blush\Extension\Requirements;
use Blush\Field\FieldTargets;
use Blush\Field\InvalidSchema;
use Blush\Job\JobConfig;
use Blush\Job\JobRunner;
use Blush\Job\RunnerMode;
use Blush\Job\WebRunner;
use Blush\Mail\MailTransport;
use Blush\Secret\SecretException;
use Blush\Secret\SecretKeys;
use Blush\Secret\Secrets;
use Blush\Secret\SecretStatus;
use Blush\Secret\SecretStore;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * The checks `doctor` runs and Site Health shows (D-543), by area:
 *
 * - `system`: the setup checks (`SetupChecks`): PHP and its extensions,
 *   `.env`, risky production settings, the public folder, and writable
 *   storage; in production, whether the site's address serves `.env`,
 *   `config/`, or `storage/` (`exposure`, D-716); whether cron or a worker
 *   runs background jobs (`jobs`,
 *   D-621); how email is sent (`mail`, D-848); and the secrets (D-715): the key that encrypts them, required
 *   ones that aren't set, stored ones that won't decrypt, and stored ones
 *   nothing declares any more.
 * - `extensions`: extensions that are on but can't run (D-431): an active
 *   theme whose chain falls back to the default theme (`theme`), and
 *   plugins (`plugins`) and icon packs (`icon-packs`) that are on but
 *   don't run; content types and relations two plugins define by one
 *   name (`clashes`, D-597), the first kept; field sets left off a type
 *   because a key they declare is declared already (`field-sets`,
 *   D-786); or one pass when everything that's on runs.
 * - `accounts`: a site with accounts but no owner (D-500), as `owner`.
 *
 * Within an area, a check's key says what it's about where that's one
 * thing the admin can open; the rest are listed in order.
 */
final readonly class SiteChecks
{
	/**
	 * Seconds since cron or a worker last ran before Site Health warns.
	 */
	public const int STALE = 600;

	public function __construct(
		private SetupChecks $setup,
		private AppConfig $app,
		private ExtensionState $extensions,
		private Accounts $accounts,
		private ContentTypes $types,
		private JobRunner $jobs,
		private JobConfig $jobConfig,
		private ClockInterface $clock,
		private Secrets $secrets,
		private SecretStore $secretStore,
		private SecretKeys $secretKeys,
		private ExposureCheck $exposure,
		private FieldTargets $targets,
		private MailTransport $mail,
		private Messages $messages
	) {}

	/**
	 * Runs every check, by area.
	 *
	 * @return array{system: array<array-key, CheckResult>, extensions: array<array-key, CheckResult>, accounts: array<array-key, CheckResult>}
	 */
	public function byArea(): array
	{
		return [
			'system'     => [...$this->setup->all($this->app), ...$this->exposure(), ...$this->jobs(), ...$this->mail(), ...$this->secrets()],
			'extensions' => $this->extensions(),
			'accounts'   => $this->owner()
		];
	}

	/**
	 * Runs every check, in one list.
	 *
	 * @return list<CheckResult>
	 */
	public function all(): array
	{
		return array_merge(...array_map(array_values(...), array_values($this->byArea())));
	}

	/**
	 * Passes when cron or a worker has run background jobs in the last
	 * `STALE` seconds; otherwise warns, saying whether visits stand in
	 * (D-621).
	 *
	 * @return array<string, CheckResult>
	 */
	public function jobs(): array
	{
		if ($this->jobConfig->runner === RunnerMode::Sync) {
			return ['jobs' => CheckResult::pass($this->jobsLabel(), $this->messages->t('setup.site_checks.jobs_sync', 'Jobs run when they\'re queued (the "sync" runner).'))];
		}

		$last = $this->jobs->lastDependableRun();

		if ($last !== null && $last >= $this->clock->now()->getTimestamp() - self::STALE) {
			return ['jobs' => CheckResult::pass($this->jobsLabel(), $this->messages->t('setup.site_checks.jobs_run', 'Cron or a worker runs them.'))];
		}

		$hint = $this->messages->t(
			'setup.site_checks.jobs_hint',
			'Add "{cron}" to cron, or keep "{worker}" running.',
			cron: sprintf('* * * * * cd /path/to/site && php bin/%s schedule:run', Framework::BINARY),
			worker: Framework::BINARY . ' jobs:work'
		);

		$message = match (true) {
			$last !== null                                                      => $this->messages->t('setup.site_checks.jobs_stale', 'Cron hasn\'t run in the last {minutes} minutes, so scheduled tasks and background jobs may wait.', minutes: intdiv(self::STALE, 60)),
			$this->jobConfig->runner === RunnerMode::Auto && WebRunner::isAvailable() => $this->messages->t('setup.site_checks.jobs_visits', 'Cron isn\'t set up, so scheduled tasks and background jobs run only after page visits.'),
			default                                                             => $this->messages->t('setup.site_checks.jobs_waiting', 'Cron isn\'t set up, so scheduled tasks and background jobs run only while someone waits on them in the admin.')
		};

		return ['jobs' => CheckResult::warning($this->jobsLabel(), $message, $hint)];
	}

	/**
	 * Says how the site sends email (D-848): it warns when it can't, and
	 * on a live site, when it falls back to PHP's own mail (which often
	 * lands in spam) or uses a transport that delivers nothing (`log`,
	 * `null`). Mail turned off on purpose (an empty `MAIL_DSN`) passes.
	 *
	 * @return array<string, CheckResult>
	 */
	public function mail(): array
	{
		$label = $this->messages->t('setup.site_checks.mail', 'Email');
		$hint  = $this->messages->t('setup.site_checks.mail_hint', 'Set MAIL_DSN in .env to your host\'s SMTP server or a mail service\'s, such as smtp://user:password@smtp.example.com:587.');
		$live  = $this->app->environment === Environment::Production;

		return ['mail' => match (true) {
			$this->mail->isOff()                => CheckResult::pass($label, $this->messages->t('setup.site_checks.mail_off', 'Turned off: MAIL_DSN is empty, so the site sends none.')),
			! $this->mail->isSetUp()            => CheckResult::warning($label, $this->messages->t('setup.site_checks.mail_none', 'The site can\'t send email: no transport is set, and PHP on this server has no mail program.'), $hint),
			$this->mail->isFallback() && $live  => CheckResult::warning($label, $this->messages->t('setup.site_checks.mail_fallback', 'Email goes out through PHP\'s own mail, which often lands in spam or is refused.'), $hint),
			$this->mail->isFallback()           => CheckResult::pass($label, $this->messages->t('setup.site_checks.mail_fallback_dev', 'Email goes out through PHP\'s own mail; set a transport before the site goes live.')),
			$live && in_array($this->mail->scheme(), ['log', 'null'], true) => CheckResult::warning($label, $this->messages->t('setup.site_checks.mail_undelivered', 'Email isn\'t delivered: the transport is {transport}.', transport: (string) $this->mail->describe()), $hint),
			default                             => CheckResult::pass($label, $this->messages->t('setup.site_checks.mail_set', 'Sent through {transport}.', transport: (string) $this->mail->describe()))
		}];
	}

	/**
	 * Asks the site's address for files it must never serve (D-716), in
	 * production at an address others can reach; nothing otherwise.
	 *
	 * @return array<string, CheckResult>
	 */
	public function exposure(): array
	{
		return $this->exposure->applies() ? ['exposure' => $this->exposure->run()] : [];
	}

	/**
	 * Checks the secrets (D-715): fails for a key that isn't one and for
	 * stored secrets that won't decrypt; warns without a key, for
	 * required secrets set nowhere, and for stored secrets nothing
	 * declares any more. Never reads out a value.
	 *
	 * @return list<CheckResult>
	 */
	public function secrets(): array
	{
		$init    = Framework::BINARY . ' init';
		$results = array_map(fn (string|Text $problem): CheckResult => CheckResult::failure(SecretKeys::CURRENT, $problem, $this->messages->t('setup.site_checks.key_invalid_hint', 'Set it to 32 random bytes in base64, or remove it and run "{command}" to add one.', command: $init)), $this->secretKeys->problems());

		if ($results === []) {
			$results[] = $this->secretKeys->canEncrypt()
				? CheckResult::pass(SecretKeys::CURRENT, $this->messages->t('setup.site_checks.key_set', 'Set, so secrets can be stored.'))
				: CheckResult::warning(SecretKeys::CURRENT, $this->messages->t('setup.site_checks.key_missing', 'Not set, so secrets can be set only in the environment, not stored.'), $this->messages->t('setup.site_checks.key_missing_hint', 'Run "{command}" to add one.', command: $init));
		}

		try {
			$stored = $this->secretStore->all();

			foreach ($this->secrets->definitions() as $name => $definition) {
				$status = $this->secretStore->status($definition);

				if ($status === SecretStatus::Unreadable) {
					$results[] = CheckResult::failure(
						$this->secretLabel($name),
						$this->messages->t('setup.site_checks.secret_unreadable', 'Stored, but it won\'t decrypt: it was stored under a key this site doesn\'t have, or changed.'),
						$this->messages->t('setup.site_checks.secret_unreadable_hint', 'Store it again with "{command}", or put the key it was stored under in {previous}.', command: Framework::BINARY . " secrets:set {$name}", previous: SecretKeys::PREVIOUS)
					);
				} elseif ($definition->required && $status === SecretStatus::Missing) {
					$results[] = CheckResult::warning(
						$this->secretLabel($name),
						$definition->owner === $definition::CORE
							? $this->messages->t('setup.site_checks.secret_missing_core', 'Core needs it, but it isn\'t set.')
							: $this->messages->t('setup.site_checks.secret_missing', '{owner} needs it, but it isn\'t set.', owner: $definition->owner),
						$this->messages->t('setup.site_checks.secret_missing_hint', 'Set {env} in the environment, or run "{command}".', env: $definition->env, command: Framework::BINARY . " secrets:set {$name}")
					);
				}

				unset($stored[$name]);
			}

			foreach (array_keys($stored) as $name) {
				$results[] = CheckResult::warning(
					$this->secretLabel($name),
					$this->messages->t('setup.site_checks.secret_undeclared', 'Stored, but nothing declares it any more (an extension may have been removed).'),
					$this->messages->t('setup.site_checks.secret_undeclared_hint', 'Run "{command}" if it\'s no longer needed.', command: Framework::BINARY . " secrets:remove {$name}")
				);
			}
		} catch (SecretException $e) {
			$results[] = CheckResult::failure($this->messages->t('setup.site_checks.secrets', 'Secrets'), $e->getMessage());
		}

		return $results;
	}

	/**
	 * Warns when the site has accounts but no owner that isn't suspended
	 * (D-500), since only an owner is sure to keep the site; fails when
	 * the accounts can't be read. Says nothing without accounts.
	 *
	 * @return array<string, CheckResult>
	 */
	public function owner(): array
	{
		try {
			if ($this->accounts->isEmpty()) {
				return [];
			}

			$label = $this->messages->t('setup.site_checks.owner', 'Owner');

			return ['owner' => $this->accounts->hasOwner()
				? CheckResult::pass($label, $this->messages->t('setup.site_checks.owner_found', 'The site has an owner.'))
				: CheckResult::warning(
					$label,
					$this->messages->t('setup.site_checks.owner_missing', 'No account is the site\'s owner, so administrators can lock each other out.'),
					$this->messages->t('setup.site_checks.owner_missing_hint', 'Run "{command}", or make yourself the owner on Your Account in the admin.', command: Framework::BINARY . ' account:roles {username} --role owner')
				)];
		} catch (AuthException $e) {
			return ['accounts' => CheckResult::failure($this->messages->t('setup.site_checks.accounts', 'Accounts'), $e->getMessage())];
		}
	}

	/**
	 * Warns of the extensions that are on but can't run (D-431), or
	 * passes when everything that's on runs.
	 *
	 * @return array<string, CheckResult>
	 */
	public function extensions(): array
	{
		$results = [];
		$themes  = $this->extensions->themes->unmet();

		if ($themes !== []) {
			$results['theme'] = CheckResult::warning(
				$this->messages->t('setup.site_checks.theme', 'Theme'),
				$this->sentences([
					$this->messages->t('setup.site_checks.theme_unmet', 'The "{theme}" theme can\'t run, so the default theme runs in its place.', theme: $this->extensions->theme),
					Requirements::reason(array_merge(...array_values($themes)), $this->messages)
				]),
				$this->messages->t('setup.site_checks.theme_unmet_hint', 'Run "{command}" for more.', command: Framework::BINARY . ' theme:check')
			);
		}

		$kinds = [
			['plugins', $this->messages->t('setup.site_checks.plugins', 'Plugins'), 'plugin', array_keys($this->extensions->plugins->unmet()), 'plugin:check'],
			['icon-packs', $this->messages->t('setup.site_checks.icon_packs', 'Icon packs'), 'icon_pack', array_keys($this->extensions->packs->unmet()), 'icon-pack:check'],
			['admin-themes', $this->messages->t('setup.site_checks.admin_themes', 'Admin themes'), 'admin_theme', array_keys($this->extensions->adminThemes->unmet()), 'admin-theme:check']
		];

		foreach ($kinds as [$key, $label, $kind, $names, $command]) {
			if ($names !== []) {
				$results[$key] = CheckResult::warning(
					$label,
					$this->messages->t(
						'setup.site_checks.unmet',
						'{count, plural, one {# {kind, select, plugin {plugin} icon_pack {icon pack} other {admin theme}} is} other {# {kind, select, plugin {plugins} icon_pack {icon packs} other {admin themes}} are}} turned on but can\'t run: {names}.',
						count: count($names),
						kind: $kind,
						names: implode(', ', $names)
					),
					$this->messages->t('setup.site_checks.unmet_hint', 'Run "{command}" to see why.', command: Framework::BINARY . " {$command}")
				);
			}
		}

		if ($this->types->clashes !== []) {
			$results['clashes'] = CheckResult::warning(
				$this->messages->t('setup.site_checks.clashes', 'Names defined twice'),
				$this->sentences(array_map(fn (DefinitionClash $clash): Text => $this->messages->t(
					'setup.site_checks.clash',
					'Two plugins define the "{name}" {kind, select, type {content type} other {relation}}: {kept}\'s is used, and {dropped}\'s is left out.',
					name: $clash->name,
					kind: $clash->kind,
					kept: $this->pluginLabel($clash->kept),
					dropped: $this->pluginLabel($clash->dropped)
				), $this->types->clashes)),
				$this->messages->t('setup.site_checks.clashes_hint', 'Turn one of them off, or ask its author to rename what it defines.')
			);
		}

		try {
			$problems = $this->targets->problems($this->types->sets);
		} catch (InvalidSchema) {
			$problems = [];
		}

		if ($problems !== []) {
			$results['field-sets'] = CheckResult::warning(
				$this->messages->t('setup.site_checks.field_sets', 'Field sets left off'),
				$this->sentences(array_merge(...array_values($problems))),
				$this->messages->t('setup.site_checks.field_sets_hint', 'Rename the field in one of the sets, or turn off the plugin that adds one. Run "{command}" to see each.', command: Framework::BINARY . ' content:lint')
			);
		}

		return $results === [] ? ['extensions' => CheckResult::pass($this->messages->t('setup.site_checks.extensions', 'Extensions'), $this->messages->t('setup.site_checks.extensions_run', 'Everything that\'s on runs.'))] : $results;
	}

	/**
	 * Puts sentences one after another as one message, each kept as it
	 * was written (a message stays a message, D-836).
	 *
	 * @param list<string|Text> $sentences
	 */
	private function sentences(array $sentences): string|Text
	{
		$sentences = array_values(array_filter($sentences, static fn (string|Text $sentence): bool => $sentence instanceof Text || $sentence !== ''));
		$last      = array_pop($sentences) ?? '';

		foreach (array_reverse($sentences) as $sentence) {
			$last = $this->messages->t('setup.site_checks.sentences', '{sentence} {next}', sentence: $sentence, next: $last);
		}

		return $last;
	}

	/**
	 * The background jobs check's label.
	 */
	private function jobsLabel(): Text
	{
		return $this->messages->t('setup.site_checks.jobs', 'Background jobs');
	}

	/**
	 * A secret's check's label.
	 */
	private function secretLabel(string $name): Text
	{
		return $this->messages->t('setup.site_checks.secret', 'Secret {name}', name: $name);
	}

	/**
	 * Returns the label of the plugin a class belongs to, else the class.
	 */
	private function pluginLabel(string $class): string
	{
		return $this->extensions->plugins->owning($class)->label ?? $class;
	}
}
