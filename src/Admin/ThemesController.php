<?php

/**
 * Admin themes controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\ExtensionAction;
use Blush\Auth\Permissions;
use Blush\Core\AppConfig;
use Blush\Core\Paths;
use Blush\Extension\ExtensionAuthor;
use Blush\Extension\ExtensionKind;
use Blush\Extension\ExtensionLicense;
use Blush\Extension\ExtensionState;
use Blush\Extension\Requirements;
use Blush\Extension\LocalExtensions;
use Blush\Extension\Install\ExtensionInstaller;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Settings\InvalidSetting;
use Blush\Settings\Setting;
use Blush\Settings\SettingsFile;
use Blush\Theme\ThemeConfig;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeManifest;
use Blush\Theme\Themes;
use Blush\Theme\ThemeSource;

/**
 * Answers `GET {path}/api/themes` (D-306, D-381, D-422), for accounts with
 * `extensions.themes.view` (D-389): the `active` theme's name, its `chain` (the theme,
 * then its ancestors, by name, D-632; empty, with the
 * `problem`, when it can't be built), whether `config/theme.php` exists
 * (`config`), whether the active theme is `saved` in
 * `user/data/settings.json` (over `config/theme.php`), whether
 * `?theme=` previews work (`preview`, in development only), every
 * installed theme, and the `invalid` ones.
 *
 * Each theme has its `name`, `label`, `namespace`, `version`,
 * `description`, `parent`, `source`, and whether it's `active` (D-378);
 * its `folder` (where it's installed, `null` for the default theme);
 * its `authors` (D-384, `composer.json`'s shape); its `license` and
 * `licenses` (as `GET plugins` has them, D-426, D-427); its `links` and
 * `funding` (as `GET plugins` has them, D-428); `keywords` (D-565);
 * its `preview` (what the admin sketches it from, or `null`); whether
 * it's `running` (in the chain that runs); its `requirements`, checked as
 * if it were active, and `requiredBy` (as `GET plugins` has them,
 * D-431); why it's `blocked` from being activated (a theme it falls
 * back to is missing, or a requirement in its chain isn't met, or
 * `null`); and whether it's `deletable` (a folder in `extensions/` the
 * active theme doesn't use).
 *
 * `fallback` says why the active theme's chain doesn't run, when its
 * requirements aren't met and the default theme runs in its place
 * (D-431), or is `null`. Each invalid theme has `where` it was
 * found, the `reason`, and whether it's `deletable`.
 *
 * The Themes screen activates a theme with `PATCH settings`
 * (`theme.active`), and deletes one with `DELETE themes/{folder}`.
 */
final readonly class ThemesController
{
	public function __construct(
		private Themes $themes,
		private ThemeConfig $config,
		private ExtensionState $extensions,
		private SettingsFile $settings,
		private AppConfig $app,
		private Paths $paths,
		private Permissions $permissions,
		private ExtensionInstaller $installer
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, ExtensionAction::View->on(ExtensionKind::Theme))) {
			return self::error('You aren\'t allowed to see the site\'s themes.', Status::Forbidden);
		}

		$active  = $this->config->active;
		$chain   = [];
		$problem = null;

		try {
			$chain = $this->themes->chain($active)->names();
		} catch (ThemeException $error) {
			$problem = $error->getMessage();
		}

		try {
			$saved = $this->settings->read()->has(Setting::Theme);
		} catch (InvalidSetting) {
			$saved = false;
		}

		$themes = array_values(array_map(fn (ThemeManifest $theme): array => [
			'name'        => $theme->name,
			'label'       => $theme->label,
			'namespace'   => $theme->namespace,
			'version'     => $theme->version,
			'description' => $theme->description,
			'parent'      => $theme->parent,
			'source'      => $theme->source->value,
			'active'      => $theme->name === $active,
			'folder'      => $theme->source === ThemeSource::Framework ? null : $this->paths->relative($theme->path),
			'preview'     => $theme->preview?->toArray(),
			'authors'     => array_map(static fn (ExtensionAuthor $author): array => $author->toArray(), $theme->authors),
			'license'     => $theme->license,
			'licenses'    => ExtensionLicense::parts($theme->license),
			'links'       => $theme->links->links(),
			'funding'     => $theme->links->funding,
			'keywords'    => $theme->keywords,
			'running'     => $this->extensions->runs($theme->name),
			...$this->requirements($theme, $active),
			'deletable'   => $theme->source === ThemeSource::Local && $theme->name !== $active && ! in_array($theme->name, $chain, true),
			'backup'      => ExtensionInstallController::backup($this->installer, ExtensionKind::Theme, $theme->source === ThemeSource::Local ? $theme->path : null, $theme->name)
		], $this->themes->all()));

		// The active theme first, then by label.
		usort($themes, static fn (array $a, array $b): int => [! $a['active'], $a['label']] <=> [! $b['active'], $b['label']]);

		$invalid = [];

		foreach ($this->themes->invalid() as $where => $reason) {
			$name      = LocalExtensions::nameAt($this->paths, $where);
			$invalid[] = [
				'where'     => $where,
				'reason'    => $reason,
				'deletable' => $name !== null && is_dir(LocalExtensions::path($this->paths, $name))
			];
		}

		return Response::json([
			'active'  => $active,
			'chain'   => $chain,
			'problem' => $problem,
			'fallback' => self::fallback($this->extensions),
			'config'  => is_file("{$this->paths->config}/theme.php"),
			'saved'   => $saved,
			'preview' => $this->app->environment->isDevelopment(),
			'themes'  => $themes,
			'invalid' => $invalid,
			'upload'  => ExtensionInstallController::upload($this->installer, ExtensionKind::Theme)
		], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * A theme's requirements, checked with it active (D-431), and why it
	 * can't be activated: a theme it falls back to is missing or broken,
	 * its parents loop, or a requirement in its chain isn't met; with the
	 * other side of its package links and what activating it stops,
	 * judged against what runs now (D-440).
	 *
	 * @return array<string, mixed>
	 */
	private function requirements(ThemeManifest $theme, string $active): array
	{
		$state  = $theme->name === $active ? $this->extensions : $this->extensions->with(theme: $theme->name);
		$report = [...$state->report($theme), ...$this->extensions->opposite($theme), 'stops' => $this->extensions->stops($theme)];

		try {
			$this->themes->chain($theme->name);
		} catch (ThemeException $error) {
			return [...$report, 'blocked' => $error->getMessage()];
		}

		return [...$report, 'blocked' => self::fallback($state)];
	}

	/**
	 * Why the active chain doesn't run, or `null` when it does.
	 */
	private static function fallback(ExtensionState $state): ?string
	{
		$unmet = array_merge(...array_values($state->themes->unmet()));

		return $state->themes->unmet() === [] ? null : Requirements::reason($unmet);
	}

	private static function error(string $message, Status $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
