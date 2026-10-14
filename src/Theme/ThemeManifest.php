<?php

/**
 * Theme manifest.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use Blush\Asset\Asset;
use Blush\Asset\AssetException;
use Blush\Extension\Autoload;
use Blush\Extension\ExtensionAbandoned;
use Blush\Extension\ExtensionAuthor;
use Blush\Extension\ExtensionCatalogs;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKind;
use Blush\Extension\ExtensionLicense;
use Blush\Extension\ExtensionLinks;
use Blush\Extension\ExtensionManifest;
use Blush\Extension\ExtensionName;
use Blush\Extension\ExtensionNamespace;
use Blush\Extension\ExtensionRequire;
use Blush\Extension\ExtensionKeywords;
use Blush\Extension\ExtensionSuggest;
use Blush\Translation\Messages;

/**
 * A theme, as its `theme.json` describes it: its
 * name (`vendor/name`, the key it's known by), label, and namespace
 * (D-378), a version, an optional parent (by name), and the stylesheets
 * and scripts every page loads. Only `name` is required (its `label` is
 * its name without one, D-423, and its `namespace` its name, hyphenated,
 * D-424); `styles` defaults to `["style.css"]`, so the smallest theme
 * is a manifest and a stylesheet (D-021).
 *
 * A theme with PHP names a `provider` (a service provider, registered
 * before the site's, D-054) and, for a local theme, the `autoload.psr-4`
 * map Blush registers for it. Its `settings` are field definitions
 * (D-022), none named `menus`, which keeps the site's menu assignments
 * in the theme's group of settings (D-676); its `menus` declare the
 * locations the site fills (D-199), each a label or an object, with the
 * theme's default items if it has them. Its `variants` list
 * block variants by block (D-266). Its `preview` is what the
 * admin sketches it from (D-381). Keys this version doesn't read (image
 * sizes) are kept in `$data`. Its `authors` are in `composer.json`'s
 * shape (D-384), its `license` a string or a list (D-427, D-428), and its
 * `homepage`, `support`, and `funding` as Composer has them (D-428);
 * discovery fills each in from the `composer.json` in its folder when the manifest has none.
 * Its `abandoned` (`true`, or the package to use instead) only warns, as
 * Composer's does (D-433), and its `suggest` is only shown (D-434); its `keywords` are what
 * the admin's filter searches (D-565). Its `assets` register named
 * styles and scripts by handle (D-574), for a theme without a provider;
 * its files are its own unless they're full URLs.
 * Its `require`, `conflict` (D-435), `replace` (D-436), and `provide` (D-439) are checked as a plugin's are (D-431): an active theme
 * whose chain needs what the site doesn't have falls back to the
 * default theme.
 */
final readonly class ThemeManifest implements ExtensionManifest
{
	/**
	 * The key of the theme's menu locations in `theme.json`, and of the
	 * site's menu assignments in the theme's group of settings (D-676),
	 * so no setting has it.
	 */
	public const string MENUS = 'menus';

	/**
	 * @param string       $name      The theme's `vendor/name`.
	 * @param string       $path      The theme's absolute folder.
	 * @param string       $label     The theme's title.
	 * @param string       $namespace What its components, icons, and catalog keys go by.
	 * @param list<string> $styles    Stylesheet paths, relative to the theme (resolved through the chain).
	 * @param list<string> $scripts   Script paths, relative to the theme.
	 * @param list<string> $preload   Files every page preloads, relative to the theme, such as fonts (D-558).
	 * @param array<string, mixed>  $data     The whole manifest.
	 * @param ?string               $provider A service provider class.
	 * @param Autoload              $autoload Its `psr-4` map and `files`, relative to the theme (D-418).
	 * @param ?ThemePreview         $preview  What the admin draws its preview from, if it says.
	 * @param list<ExtensionAuthor> $authors  Who made it.
	 * @param string                $license  How it may be used, as Composer has it (`MIT`, D-427, D-428).
	 * @param ExtensionLinks        $links    Its homepage, support, and funding (D-428).
	 * @param array<string, string> $require  What it needs, each mapped to a version constraint (D-431).
	 * @param array<string, string> $conflict What it can't run with, each mapped to the versions it can't (D-435).
	 * @param array<string, string> $replace  What it replaces, each mapped to the versions it stands in for (D-436).
	 * @param array<string, string> $provide  What it provides, each mapped to the versions it provides (D-439).
	 * @param bool|string           $abandoned Whether it's abandoned, or the package to use instead (D-433).
	 * @param array<string, string> $suggest  Package => why it's suggested (D-434).
	 * @param list<string> $keywords What it's about, searched by the admin (D-565).
	 * @param list<Asset>  $assets   The assets it registers by handle (D-574).
	 * @param array<string, list<string>> $catalogs Its scripts' folders by named catalog, or `[]` for the defaults (D-831).
	 */
	public function __construct(
		public string $name,
		public string $path,
		public string $label,
		public string $namespace,
		public string $version = '',
		public ?string $parent = null,
		public string $description = '',
		public array $styles = ['style.css'],
		public array $scripts = [],
		public array $data = [],
		public ThemeSource $source = ThemeSource::Local,
		public ?string $provider = null,
		public Autoload $autoload = new Autoload(),
		public ?ThemePreview $preview = null,
		public array $authors = [],
		public string $license = '',
		public ExtensionLinks $links = new ExtensionLinks(),
		public array $require = [],
		public array $conflict = [],
		public array $replace = [],
		public array $provide = [],
		public bool|string $abandoned = false,
		public array $suggest = [],
		public array $preload = [],
		public array $keywords = [],
		public array $assets = [],
		public array $catalogs = []
	) {}

	public function kind(): ExtensionKind
	{
		return ExtensionKind::Theme;
	}

	/**
	 * Builds a manifest from parsed data, found in a folder.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws ThemeException When a value is missing or has the wrong type.
	 */
	public static function fromArray(string $path, array $data, ThemeSource $source = ThemeSource::Local, Messages $messages = new Messages()): self
	{
		$theme = $data['name'] ?? null;

		if (! is_string($theme) || ! ExtensionName::isValid($theme)) {
			throw new ThemeException((string) $messages->t('theme.theme_manifest.no_name', 'The theme in {path} needs a "name": vendor/name, such as "acme/nova".', path: $path));
		}

		$label = $data['label'] ?? null;

		if ($label !== null && ! is_string($label)) {
			throw new ThemeException((string) $messages->t('theme.theme_manifest.label', 'The "{theme}" theme\'s "label" must be a string.', theme: $theme));
		}

		$namespace = $data['namespace'] ?? ExtensionNamespace::fromName($theme);

		if (! is_string($namespace) || ! ExtensionNamespace::isValid($namespace) || (ExtensionNamespace::isReserved($namespace) && $source !== ThemeSource::Framework)) {
			throw new ThemeException((string) $messages->t(
				'theme.theme_manifest.namespace',
				'The "{theme}" theme\'s manifest needs a "namespace": lowercase letters, digits, hyphens, and underscores, and not {reserved}.',
				theme: $theme,
				reserved: implode(', ', ExtensionNamespace::RESERVED)
			));
		}

		$parent = $data['parent'] ?? null;

		if ($parent !== null && (! is_string($parent) || ! ExtensionName::isValid($parent))) {
			throw new ThemeException((string) $messages->t('theme.theme_manifest.parent', 'The "{theme}" theme\'s "parent" must be a theme\'s name (vendor/name).', theme: $theme));
		}

		$provider = $data['provider'] ?? null;

		if ($provider !== null && (! is_string($provider) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $provider) !== 1)) {
			throw new ThemeException((string) $messages->t('theme.theme_manifest.provider', 'The "{theme}" theme\'s "provider" must be a class name.', theme: $theme));
		}

		$settings = $data['settings'] ?? [];

		if (! is_array($settings) || ($settings !== [] && array_is_list($settings)) || ! array_all($settings, static fn (mixed $item): bool => is_array($item))) {
			throw new ThemeException((string) $messages->t('theme.theme_manifest.settings', 'The "{theme}" theme\'s "settings" must map names to field definitions.', theme: $theme));
		}

		if (array_key_exists(self::MENUS, $settings)) {
			throw new ThemeException((string) $messages->t('theme.theme_manifest.menus_setting', 'The "{theme}" theme can\'t have a setting named "{name}": it keeps which menus the site shows in the theme\'s locations.', theme: $theme, name: self::MENUS));
		}

		$locations = $data['menus'] ?? [];

		if (
			! is_array($locations)
			|| ($locations !== [] && array_is_list($locations))
			|| ! array_all($locations, static fn (mixed $value, int|string $name): bool => (is_string($value) || is_array($value)) && preg_match('/^[a-z0-9][a-z0-9_-]*$/', (string) $name) === 1)
		) {
			throw new ThemeException((string) $messages->t('theme.theme_manifest.menus', 'The "{theme}" theme\'s "menus" must map location names (lowercase letters, digits, hyphens, underscores) to labels or objects.', theme: $theme));
		}

		$variants = $data['variants'] ?? [];

		if (! is_array($variants) || ($variants !== [] && array_is_list($variants)) || ! array_all($variants, static fn (mixed $list): bool => is_array($list) && array_is_list($list))) {
			throw new ThemeException((string) $messages->t('theme.theme_manifest.variants', 'The "{theme}" theme\'s "variants" must map block names to lists of variants.', theme: $theme));
		}

		$bleed = $data['bleed'] ?? [];

		if (
			! is_array($bleed)
			|| ($bleed !== [] && array_is_list($bleed))
			|| ! array_all($bleed, static fn (mixed $class, int|string $width): bool => in_array($width, ['wide', 'full'], true) && is_string($class) && preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', $class) === 1)
		) {
			throw new ThemeException((string) $messages->t('theme.theme_manifest.bleed', 'The "{theme}" theme\'s "bleed" must map "wide" and "full" to class names.', theme: $theme));
		}

		$preview = $data['preview'] ?? null;

		if ($preview !== null && (! is_array($preview) || ($preview !== [] && array_is_list($preview)))) {
			throw new ThemeException((string) $messages->t('theme.theme_manifest.preview', 'The "{theme}" theme\'s "preview" must be an object with "layout", "type", and "palette".', theme: $theme));
		}

		try {
			$authors  = ExtensionAuthor::list($data['authors'] ?? [], $messages);
			$autoload = Autoload::fromArray($data['autoload'] ?? null, $messages);
			$license  = ExtensionLicense::fromManifest($data['license'] ?? '', $messages);
			$links    = ExtensionLinks::fromArray($data, $messages);
			$require  = ExtensionRequire::fromArray($data['require'] ?? null, 'require', $messages);
			$conflict  = ExtensionRequire::fromArray($data['conflict'] ?? null, 'conflict', $messages);
			$replace  = ExtensionRequire::fromArray($data['replace'] ?? null, 'replace', $messages);
			$provide  = ExtensionRequire::fromArray($data['provide'] ?? null, 'provide', $messages);
			$abandoned = ExtensionAbandoned::fromManifest($data['abandoned'] ?? false, $messages);
			$suggest   = ExtensionSuggest::fromManifest($data['suggest'] ?? null, $messages);
			$keywords  = ExtensionKeywords::fromManifest($data['keywords'] ?? null, $messages);
			$catalogs  = ExtensionCatalogs::fromManifest($data['catalogs'] ?? null, $messages);
		} catch (ExtensionException $error) {
			throw new ThemeException((string) $messages->t('theme.theme_manifest.invalid', 'The "{theme}" theme\'s manifest: {error}', theme: $theme, error: $error->getMessage()), 0, $error);
		}

		$assets = $data['assets'] ?? [];

		if (! is_array($assets) || ($assets !== [] && array_is_list($assets))) {
			throw new ThemeException((string) $messages->t('theme.theme_manifest.assets', 'The "{theme}" theme\'s "assets" must map handles (vendor/name) to the styles and scripts each loads.', theme: $theme));
		}

		try {
			$assets = array_map(
				static fn (mixed $asset, int|string $handle): Asset => is_array($asset)
					? Asset::fromArray((string) $handle, $asset, $theme, $messages)
					: throw new AssetException((string) $messages->t('theme.theme_manifest.asset_not_object', 'The "{handle}" asset must be an object with "styles", "scripts", or "requires".', handle: $handle)),
				$assets,
				array_keys($assets)
			);
		} catch (AssetException $error) {
			throw new ThemeException((string) $messages->t('theme.theme_manifest.invalid', 'The "{theme}" theme\'s manifest: {error}', theme: $theme, error: $error->getMessage()), 0, $error);
		}

		/** @var array<string, mixed> $data */
		return new self(
			name: $theme,
			path: $path,
			label: ExtensionName::label($label, $theme),
			namespace: $namespace,
			version: self::string($theme, $data, 'version', $messages),
			parent: $parent,
			description: self::string($theme, $data, 'description', $messages),
			styles: self::paths($theme, $data, 'styles', ['style.css'], $messages),
			scripts: self::paths($theme, $data, 'scripts', [], $messages),
			data: $data,
			source: $source,
			provider: $provider,
			autoload: $autoload,
			preview: $preview === null ? null : ThemePreview::fromArray($theme, $preview, $messages),
			authors: $authors,
			license: $license,
			links: $links,
			require: $require,
			conflict: $conflict,
			replace: $replace,
			provide: $provide,
			abandoned: $abandoned,
			suggest: $suggest,
			preload: self::paths($theme, $data, 'preload', [], $messages),
			keywords: $keywords,
			assets: $assets,
			catalogs: $catalogs
		);
	}

	/**
	 * Returns the manifest's setting definitions, by name.
	 *
	 * @return array<string, array<array-key, mixed>>
	 */
	public function settings(): array
	{
		/** @var array<string, array<array-key, mixed>> Checked by `fromArray()`. */
		return $this->data['settings'] ?? [];
	}

	/**
	 * Returns the manifest's menu location declarations, by name: labels
	 * or objects (see `Menu\MenuLocation`).
	 *
	 * @return array<string, string|array<array-key, mixed>>
	 */
	public function menus(): array
	{
		/** @var array<string, string|array<array-key, mixed>> Checked by `fromArray()`. */
		return $this->data['menus'] ?? [];
	}

	/**
	 * Returns the manifest's block variants, by block name: each
	 * a variant name or a `{"name", "modifier"}` object (see
	 * `Block\BlockVariants`).
	 *
	 * @return array<string, list<mixed>>
	 */
	public function variants(): array
	{
		/** @var array<string, list<mixed>> Checked by `fromArray()`. */
		return $this->data['variants'] ?? [];
	}

	/**
	 * Returns the classes the theme names for the editor's bleed widths
	 * (D-313), `wide` and `full`, as far as it names them.
	 *
	 * @return array{wide?: string, full?: string}
	 */
	public function bleed(): array
	{
		/** @var array{wide?: string, full?: string} Checked by `fromArray()`. */
		return $this->data['bleed'] ?? [];
	}

	/**
	 * Returns what the theme cache stores.
	 *
	 * @return array{path: string, source: string, data: array<string, mixed>}
	 */
	public function toArray(): array
	{
		return ['path' => $this->path, 'source' => $this->source->value, 'data' => $this->data];
	}

	/**
	 * Returns the theme's views folder.
	 */
	public function viewsPath(): string
	{
		return "{$this->path}/views";
	}

	/**
	 * Returns the theme's message catalog folder.
	 */
	public function langPath(): string
	{
		return "{$this->path}/lang";
	}

	/**
	 * Reads an optional string.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws ThemeException
	 */
	private static function string(string $theme, array $data, string $key, Messages $messages): string
	{
		$value = $data[$key] ?? '';

		return is_string($value) ? $value : throw new ThemeException((string) $messages->t('theme.theme_manifest.not_string', 'The "{theme}" theme\'s "{field}" must be a string.', theme: $theme, field: $key));
	}

	/**
	 * Reads an optional list of relative asset paths.
	 *
	 * @param  array<array-key, mixed> $data
	 * @param  list<string>            $default
	 * @return list<string>
	 * @throws ThemeException
	 */
	private static function paths(string $theme, array $data, string $key, array $default, Messages $messages): array
	{
		$value = $data[$key] ?? $default;

		if (! is_array($value) || ! array_is_list($value)) {
			throw new ThemeException((string) $messages->t('theme.theme_manifest.not_paths', 'The "{theme}" theme\'s "{field}" must be a list of paths.', theme: $theme, field: $key));
		}

		$paths = [];

		foreach ($value as $path) {
			if (! is_string($path) || ! ThemeChain::isValidAssetPath($path)) {
				throw new ThemeException((string) $messages->t('theme.theme_manifest.paths_outside', 'The "{theme}" theme\'s "{field}" must be a list of paths inside the theme.', theme: $theme, field: $key));
			}

			$paths[] = $path;
		}

		return $paths;
	}
}
