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

use Blush\Auth\AccountStore;
use Blush\Auth\Accounts;
use Blush\Auth\AuthException;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
use Blush\Core\Framework;
use Blush\Extension\DefinitionClash;
use Blush\Extension\ExtensionState;
use Blush\Extension\Requirements;

/**
 * The checks `doctor` runs and Site Health shows (D-543), by area:
 *
 * - `system`: the setup checks (`SetupChecks`): PHP and its extensions,
 *   `.env`, risky production settings, the public folder, and writable
 *   storage.
 * - `extensions`: extensions that are on but can't run (D-431): an active
 *   theme whose chain falls back to the default theme (`theme`), and
 *   plugins (`plugins`) and icon packs (`icon-packs`) that are on but
 *   don't run; content types and relations two plugins define by one
 *   name (`clashes`, D-597), the first kept; or one pass when everything
 *   that's on runs.
 * - `accounts`: a site with accounts but no owner (D-500), as `owner`.
 *
 * Within an area, a check's key says what it's about where that's one
 * thing the admin can open; the rest are listed in order.
 */
final readonly class SiteChecks
{
	public function __construct(
		private SetupChecks $setup,
		private AppConfig $app,
		private ExtensionState $extensions,
		private AccountStore $store,
		private Accounts $accounts,
		private ContentTypes $types
	) {}

	/**
	 * Runs every check, by area.
	 *
	 * @return array{system: array<array-key, CheckResult>, extensions: array<array-key, CheckResult>, accounts: array<array-key, CheckResult>}
	 */
	public function byArea(): array
	{
		return [
			'system'     => $this->setup->all($this->app),
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
	 * Warns when the site has accounts but no owner that isn't suspended
	 * (D-500), since only an owner is sure to keep the site; fails when
	 * the accounts can't be read. Says nothing without accounts.
	 *
	 * @return array<string, CheckResult>
	 */
	public function owner(): array
	{
		try {
			if ($this->store->isEmpty()) {
				return [];
			}

			return ['owner' => $this->accounts->hasOwner()
				? CheckResult::pass('Owner', 'The site has an owner.')
				: CheckResult::warning('Owner', 'No account is the site\'s owner, so administrators can lock each other out.', sprintf('Run "%s account:roles {username} --role owner", or make yourself the owner on Your Account in the admin.', Framework::BINARY))];
		} catch (AuthException $e) {
			return ['accounts' => CheckResult::failure('Accounts', $e->getMessage())];
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
				'Theme',
				sprintf('The "%s" theme can\'t run, so the default theme runs in its place. %s', $this->extensions->theme, Requirements::reason(array_merge(...array_values($themes)))),
				sprintf('Run "%s theme:check" for more.', Framework::BINARY)
			);
		}

		$kinds = [
			['plugins', 'Plugins', 'plugin', array_keys($this->extensions->plugins->unmet()), 'plugin:check'],
			['icon-packs', 'Icon packs', 'icon pack', array_keys($this->extensions->packs->unmet()), 'icon-pack:check']
		];

		foreach ($kinds as [$key, $label, $kind, $names, $command]) {
			if ($names !== []) {
				$results[$key] = CheckResult::warning(
					$label,
					sprintf('%s %s %s turned on but can\'t run: %s.', count($names), count($names) === 1 ? $kind : "{$kind}s", count($names) === 1 ? 'is' : 'are', implode(', ', $names)),
					sprintf('Run "%s %s" to see why.', Framework::BINARY, $command)
				);
			}
		}

		if ($this->types->clashes !== []) {
			$results['clashes'] = CheckResult::warning(
				'Names defined twice',
				implode(' ', array_map(fn (DefinitionClash $clash): string => sprintf(
					'Two plugins define the "%s" %s: %s\'s is used, and %s\'s is left out.',
					$clash->name,
					$clash->kind === 'type' ? 'content type' : $clash->kind,
					$this->pluginLabel($clash->kept),
					$this->pluginLabel($clash->dropped)
				), $this->types->clashes)),
				'Turn one of them off, or ask its author to rename what it defines.'
			);
		}

		return $results === [] ? ['extensions' => CheckResult::pass('Extensions', 'Everything that\'s on runs.')] : $results;
	}

	/**
	 * Returns the label of the plugin a class belongs to, else the class.
	 */
	private function pluginLabel(string $class): string
	{
		return $this->extensions->plugins->owning($class)->label ?? $class;
	}
}
