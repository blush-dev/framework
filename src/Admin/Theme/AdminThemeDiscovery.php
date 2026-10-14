<?php

/**
 * Admin theme discovery.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Theme;

use Throwable;
use Blush\Core\Framework;
use Blush\Core\Paths;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKind;
use Blush\Extension\LocalExtension;
use Blush\Extension\LocalExtensions;
use Blush\Extension\ManifestFile;
use Blush\Support\ComposerPackages;
use Blush\Translation\Messages;

/**
 * Finds every installed admin theme (D-827): the framework's own, in
 * `resources/admin-themes` (Neutral and Editorial), Composer packages of
 * type `blush-admin-theme`, and folders in `extensions/{vendor}/{name}`
 * holding an `admin-theme.json` or a `composer.json` of that type, whose
 * name must be the folder's (D-418, D-432). What a manifest leaves out it
 * takes from its `composer.json`. A folder theme replaces a Composer one
 * of the same name, and neither may take a framework theme's name. A
 * broken theme is recorded, by where it was found, instead of failing
 * discovery, since a skin is never worth taking the site down for.
 */
final readonly class AdminThemeDiscovery
{
	/**
	 * The framework's admin themes' folder.
	 */
	public const string FOLDER = 'resources/admin-themes';

	/**
	 * @param Messages $messages What writes why a theme is broken: one
	 *                           without a translator during boot, since
	 *                           the reasons are kept as messages and
	 *                           translated where they're shown (D-836).
	 */
	public function __construct(
		private Paths $paths,
		private Messages $messages = new Messages()
	) {}

	/**
	 * Finds every theme.
	 */
	public function discover(): AdminThemes
	{
		$invalid = [];
		$found   = [];

		foreach (glob(Framework::path(self::FOLDER) . '/*', GLOB_ONLYDIR) ?: [] as $path) {
			$found[] = [self::FOLDER . '/' . basename($path), $path, AdminThemeSource::Framework];
		}

		try {
			foreach (new ComposerPackages($this->paths->vendor)->ofType(ExtensionKind::AdminTheme->packageType()) as $package) {
				if (is_string($package['name'] ?? null) && is_string($package['path'] ?? null)) {
					$found[] = [$package['name'], $package['path'], AdminThemeSource::Composer];
				}
			}
		} catch (Throwable $error) {
			$invalid['composer'] = $error->getMessage();
		}

		foreach (LocalExtensions::forPaths($this->paths)->of(ExtensionKind::AdminTheme) as $extension) {
			$found[] = [$extension->where, $extension, AdminThemeSource::Local];
		}

		$themes = [];

		foreach ($found as [$where, $at, $source]) {
			try {
				$theme = $this->read($where, $at, $source);
			} catch (ExtensionException $error) {
				$invalid[$where] = $error->reason();

				continue;
			}

			$framework = $themes[$theme->name] ?? null;

			if ($framework !== null && $framework->source === AdminThemeSource::Framework) {
				$invalid[$where] = $this->messages->t('admin.admin_theme_discovery.framework_name', '"{name}" is a framework admin theme\'s name.', name: $theme->name);

				continue;
			}

			$themes[$theme->name] = $theme;
		}

		ksort($themes);

		return new AdminThemes($themes, $invalid);
	}

	/**
	 * Returns where a theme was found, as `AdminThemes::invalid()` keys it.
	 */
	public static function where(AdminTheme $theme, Paths $paths): string
	{
		return match ($theme->source) {
			AdminThemeSource::Composer  => $theme->name,
			AdminThemeSource::Framework => self::FOLDER . '/' . basename($theme->path),
			AdminThemeSource::Local     => $paths->relative($theme->path)
		};
	}

	/**
	 * Reads a theme found by discovery.
	 *
	 * @throws ExtensionException
	 */
	private function read(string $where, string|LocalExtension $at, AdminThemeSource $source): AdminTheme
	{
		if ($at instanceof LocalExtension) {
			return AdminTheme::fromArray($at->path, $at->read(), $source, messages: $this->messages);
		}

		$data = ManifestFile::load($at, ExtensionKind::AdminTheme);

		if ($source === AdminThemeSource::Composer) {
			$data['name'] ??= $where;

			if ($data['name'] !== $where) {
				throw new ExtensionException($this->messages->t('admin.admin_theme_discovery.composer_name', 'The admin theme in Composer package "{package}" is named "{name}"; a Composer admin theme\'s name is its package\'s.', package: $where, name: is_string($data['name']) ? $data['name'] : ''));
			}
		}

		return AdminTheme::fromArray($at, $data, $source, messages: $this->messages);
	}
}
