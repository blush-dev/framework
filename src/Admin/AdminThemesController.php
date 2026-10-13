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
use Blush\AdminTheme\AdminTheme;
use Blush\AdminTheme\AdminThemeContrast;
use Blush\AdminTheme\AdminThemes;
use Blush\AdminTheme\AdminThemeSource;
use Blush\AdminTheme\AdminToken;
use Blush\AdminTheme\FontFace;
use Blush\Auth\Account;
use Blush\Auth\ExtensionAction;
use Blush\Auth\Permissions;
use Blush\Core\Paths;
use Blush\Extension\ExtensionAuthor;
use Blush\Extension\ExtensionKind;
use Blush\Extension\ExtensionLicense;
use Blush\Extension\ExtensionState;
use Blush\Extension\ExtensionText;
use Blush\Extension\Install\ExtensionInstaller;
use Blush\Extension\LocalExtensions;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Settings\Setting;
use Blush\Settings\SettingsStore;

/**
 * Answers the Admin Themes screens (D-827), for accounts with
 * `extensions.admin-themes.view`:
 *
 * - `GET admin-themes`: every installed admin theme, Neutral first, then
 *   by label, each as `theme()` describes it; the broken ones (`invalid`,
 *   each `{where, reason, deletable}`); the site's `default` (the theme
 *   anyone who hasn't chosen one sees); whether a list of the themes
 *   that are on (`saved`) or a default (`defaultSaved`) was saved here
 *   over `config/admin-themes.php`, and whether that file exists
 *   (`config`); and what installing needs (`upload`).
 * - `GET admin-themes/{vendor}/{name}`: one theme.
 */
final readonly class AdminThemesController
{
	public function __construct(
		private AdminThemes $themes,
		private ExtensionState $extensions,
		private Paths $paths,
		private SettingsStore $settings,
		private Permissions $permissions,
		private ExtensionInstaller $installer,
		private ExtensionText $text
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::forbidden();
		}

		$themes = array_values($this->themes->all());

		usort($themes, fn (AdminTheme $a, AdminTheme $b): int => [$a->name !== AdminThemes::BASE, strtolower((string) $this->text->label($a))] <=> [$b->name !== AdminThemes::BASE, strtolower((string) $this->text->label($b))]);

		$invalid = [];

		foreach ($this->themes->invalid() as $where => $reason) {
			$name      = LocalExtensions::nameAt($this->paths, $where);
			$invalid[] = ['where' => $where, 'reason' => $reason, 'deletable' => $name !== null && is_dir(LocalExtensions::path($this->paths, $name))];
		}

		$saved = $this->settings->read();

		return Response::json([
			'themes'       => array_map($this->theme(...), $themes),
			'invalid'      => $invalid,
			'default'      => $this->themes->default()?->name,
			'saved'        => $saved->has(Setting::AdminThemes),
			'defaultSaved' => $saved->has(Setting::AdminThemeDefault),
			'config'       => is_file("{$this->paths->config}/admin-themes.php"),
			'upload'       => ExtensionInstallController::upload($this->installer, ExtensionKind::AdminTheme)
		], headers: ['Cache-Control' => 'no-store']);
	}

	public function show(ServerRequestInterface $request, string $vendor, string $name): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::forbidden();
		}

		$theme = $this->themes->find("{$vendor}/{$name}");

		if ($theme === null) {
			return Response::json(['error' => sprintf('No admin theme named %s/%s is installed.', $vendor, $name)], Status::NotFound, ['Cache-Control' => 'no-store']);
		}

		return Response::json(['theme' => $this->theme($theme)], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Describes a theme: what every extension's screens show, whether it's
	 * Neutral (`base`, always on and never deleted) or the site's
	 * `default`, whether it's `offered` to accounts, its `preview` colors,
	 * the tokens it `changes` (by path), the font families it ships
	 * (`fonts`), and its colors' `contrast` warnings.
	 *
	 * @return array<string, mixed>
	 */
	private function theme(AdminTheme $theme): array
	{
		$folder = $theme->source === AdminThemeSource::Local && LocalExtensions::contains($this->paths, $theme->path)
			? $this->paths->relative($theme->path)
			: null;

		return [
			'name'        => $theme->name,
			'label'       => (string) $this->text->label($theme),
			'version'     => $theme->version,
			'description' => (string) $this->text->description($theme),
			'authors'     => array_map(static fn (ExtensionAuthor $author): array => $author->toArray(), $theme->authors),
			'license'     => $theme->license,
			'licenses'    => ExtensionLicense::parts($theme->license),
			'links'       => $theme->links->links(),
			'funding'     => $theme->links->funding,
			'keywords'    => $theme->keywords,
			'source'      => $theme->source->value,
			'path'        => $this->paths->relative($theme->path),
			'folder'      => $folder,
			'enabled'     => $this->themes->isEnabled($theme->name),
			'running'     => $this->extensions->runs($theme->name),
			'offered'     => $this->themes->isOffered($theme->name),
			'base'        => $theme->name === AdminThemes::BASE,
			'default'     => $this->themes->default()?->name === $theme->name,
			...$this->extensions->report($theme, $this->text),
			...$this->extensions->opposite($theme, $this->text),
			'stops'       => $this->extensions->stops($theme, $this->text),
			'deletable'   => $folder !== null,
			'backup'      => ExtensionInstallController::backup($this->installer, ExtensionKind::AdminTheme, $folder === null ? null : $theme->path, $theme->name),
			'preview'     => $this->themes->preview($theme),
			'changes'     => array_values(array_map(static fn (AdminToken $token): string => $token->value, array_filter(AdminToken::cases(), $theme->tokens->has(...)))),
			'fonts'       => array_values(array_unique(array_map(static fn (FontFace $face): string => $face->family, $theme->tokens->faces))),
			'contrast'    => AdminThemeContrast::check($this->themes->resolved($theme))
		];
	}

	private function allowed(ServerRequestInterface $request): bool
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account && $this->permissions->can($account, ExtensionAction::View->on(ExtensionKind::AdminTheme));
	}

	private static function forbidden(): ResponseInterface
	{
		return Response::json(['error' => 'You aren\'t allowed to see the site\'s admin themes.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
	}
}
