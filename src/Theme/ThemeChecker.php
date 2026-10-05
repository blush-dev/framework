<?php

/**
 * Theme checker.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use Throwable;
use Dom\Element;
use Dom\HTMLDocument;
use Blush\Component\ComponentListing;
use Blush\Component\ComponentName;
use Blush\Component\ComponentVariants;
use Blush\Content\Http\ContentPage;
use Blush\Content\Http\PageKind;
use Blush\Core\ServiceProvider;
use Blush\Extension\ExtensionAbandoned;
use Blush\Extension\ExtensionState;
use Blush\Extension\Requirements;
use Blush\Extension\VersionConstraint;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Menu\Menus;
use Blush\Region\Regions;
use Blush\View\ViewFactory;
use Blush\View\Views;
use Blush\Translation\CatalogCheck;

/**
 * Checks a theme for `theme:check` (D-020, D-030, D-032):
 *
 * - **Errors:** a chain that doesn't resolve; the active theme's chain
 *   with a requirement that isn't met, so the default theme runs in its
 *   place (D-431); a provider that isn't a service provider; invalid setting definitions; invalid menu or
 *   region location declarations; a base layout without `lang` on
 *   `<html>`, one `<main>`, or a skip link to it; a `lang/` catalog that
 *   can't be read.
 * - **Warnings:** another theme's chain with a requirement that isn't
 *   met, so it can't be activated; a `version` Composer can't read
 *   (D-430, D-431); an abandoned theme (D-433); shadowed manifests (JSON wins); site setting values
 *   that don't fit; other broken themes; a component with a class but no
 *   template to render; a component template not named for a component;
 *   site menu and region files or items that are invalid or don't
 *   resolve (D-199, D-201); a layout without `<header>` or `<footer>`,
 *   or with other than one `<h1>`, or one that prints a tag `head()`
 *   prints (`<meta charset>`, `viewport`, `generator`, `<title>`; D-472); a catalog whose `@@locale` or
 *   `@@domain` doesn't match its file or theme (D-452).
 * - **Notices:** the theme's registered components without a translated label; site
 *   menus and regions no location shows; a catalog without `@@locale`
 *   and `@@domain`; a base layout without `dir` on `<html>` (D-470).
 *
 * The layout is checked by rendering the `welcome` page.
 */
final readonly class ThemeChecker
{
	public function __construct(
		private Themes $themes,
		private ExtensionState $extensions,
		private ThemeConfig $config,
		private SettingsResolver $settings,
		private ViewFactory $views,
		private Menus $menus,
		private Regions $regions,
		private CatalogCheck $catalogs
	) {}

	/**
	 * Checks a theme and its chain.
	 */
	public function check(string $name): ThemeReport
	{
		$problems = [];

		foreach ($this->themes->invalid() as $invalid => $message) {
			if ($invalid !== $name) {
				$problems[] = new Violation("theme {$invalid}", $message, Severity::Warning);
			}
		}

		try {
			$chain = $this->themes->chain($name);
		} catch (ThemeException $error) {
			return new ThemeReport($name, [new Violation('manifest', $error->getMessage()), ...$problems]);
		}

		foreach ($chain as $theme) {
			$problems = [...$problems, ...$this->manifest($theme), ...$this->catalogs($theme)];
		}

		$problems = [...$problems, ...$this->requirements($name)];

		$problems = [
			...$problems,
			...$this->settings($chain),
			...$this->components($chain),
			...$this->menus->check($chain),
			...$this->regions->check($chain),
			...$this->layout($chain)
		];

		return new ThemeReport($name, $problems);
	}

	/**
	 * Checks a theme's `lang/` catalogs against what they say they
	 * translate (D-452), named for the theme in the chain they're in.
	 *
	 * @return list<Violation>
	 */
	private function catalogs(ThemeManifest $theme): array
	{
		return array_map(
			static fn (Violation $violation): Violation => new Violation("{$violation->field} ({$theme->name})", $violation->message, $violation->severity),
			$this->catalogs->check($theme->langPath(), $theme->name)
		);
	}

	/**
	 * Checks a theme's manifest files and provider.
	 *
	 * @return list<Violation>
	 */
	private function manifest(ThemeManifest $theme): array
	{
		$problems = [];

		foreach (array_slice(ThemeDiscovery::manifestFiles($theme->path), 1) as $shadowed) {
			$problems[] = new Violation('manifest', sprintf('%s is ignored; the "%s" theme\'s %s wins (D-032).', basename($shadowed), $theme->name, basename(ThemeDiscovery::manifestFiles($theme->path)[0])), Severity::Warning);
		}

		if ($theme->provider !== null && ! is_subclass_of($theme->provider, ServiceProvider::class)) {
			$problems[] = new Violation('provider', sprintf('The "%s" theme\'s provider %s isn\'t a service provider class (check its "autoload").', $theme->name, $theme->provider));
		}

		$abandoned = ExtensionAbandoned::warning($theme->abandoned);

		// An abandoned theme still runs, as in Composer (D-433).
		if ($abandoned !== null) {
			$problems[] = new Violation('abandoned', sprintf('The "%s" theme: %s', $theme->name, $abandoned), Severity::Warning);
		}

		// A version Composer can't read meets only `*` (D-429, D-430).
		if ($theme->version !== '' && VersionConstraint::normalize($theme->version) === null) {
			$problems[] = new Violation('version', sprintf('The "%s" theme\'s version, "%s", isn\'t one Composer can read, so a requirement of it is met only by "*".', $theme->name, $theme->version), Severity::Warning);
		}

		return $problems;
	}

	/**
	 * Checks the chain's requirements (D-431), as if the theme were
	 * active: an error for the active theme, whose chain then falls back
	 * to the default theme, and a warning for another, which can't be
	 * activated.
	 *
	 * @return list<Violation>
	 */
	private function requirements(string $name): array
	{
		$active = $name === $this->config->active;
		$state  = $active ? $this->extensions : $this->extensions->with(theme: $name);
		$unmet  = $state->themes->unmet();

		if ($unmet === []) {
			return [];
		}

		$reason = Requirements::reason(array_merge(...array_values($unmet)));

		return [$active
			? new Violation('require', sprintf('%s The default theme runs in its place until that\'s fixed.', $reason))
			: new Violation('require', sprintf('%s It can\'t be activated until that\'s fixed.', $reason), Severity::Warning)];
	}

	/**
	 * Checks the chain's settings.
	 *
	 * @return list<Violation>
	 */
	private function settings(ThemeChain $chain): array
	{
		try {
			return array_map(
				static fn (Violation $violation): Violation => new Violation("setting {$violation->field}", $violation->message, Severity::Warning),
				$this->settings->for($chain)->violations
			);
		} catch (Throwable $error) {
			return [new Violation('settings', $error->getMessage())];
		}
	}

	/**
	 * Checks the theme's components, skipping another theme's (its
	 * provider registers them when it's the active theme). A component
	 * with a registered class but no template in the chain (and no other
	 * view of its own) fails whenever it's used; a template in the theme's `components/` that
	 * isn't named for a component (`{namespace}-{name}.php`, or a core
	 * component's name) is never rendered (D-171); and the theme's
	 * registered components should have a translated label for the
	 * admin's inserter (a notice, D-172). Its `theme.json` variants
	 * (D-266) must be for components that exist and have valid names, and
	 * should have labels; a variant's template mustn't also be a
	 * component's own.
	 *
	 * @return list<Violation>
	 */
	private function components(ThemeChain $chain): array
	{
		try {
			$views      = $this->views->forChain($chain);
			$components = $views->components();
			$stray      = $views->strayComponentFiles();
		} catch (Throwable) {
			// The layout check reports views that can't be built.
			return [];
		}

		$theme    = $chain->active();
		$problems = [];

		foreach ($components as $component) {
			$name = (string) $component->name;

			if ($this->themes->isOutside($component->name->namespace, $chain)) {
				continue;
			}

			if ($component->isMissingTemplate()) {
				$problems[] = new Violation("component {$name}", sprintf('The "%s" component (%s) has no %s.php template in the chain.', $name, $component->className() ?? 'no class', array_last($component->name->views())), Severity::Warning);
			}

			if ($component->isRegistered() && $component->label === null && $component->name->namespace === $theme->namespace) {
				$problems[] = new Violation("component {$name}", sprintf('The "%s" component has no label; add "components.%s.label" to the theme\'s lang/ catalog.', $name, $component->name->name), Severity::Notice);
			}
		}

		$problems = [...$problems, ...$this->variants($theme, $views, $components)];

		foreach ($stray as $file) {
			if (str_starts_with($file, $theme->viewsPath() . '/')) {
				$fileName   = basename($file, '.php');
				$problems[] = new Violation("component {$fileName}", sprintf('components/%s.php isn\'t named for a component, so it never renders; name it components/%s-%s.php.', $fileName, $theme->namespace, $fileName), Severity::Warning);
			}
		}

		return $problems;
	}

	/**
	 * Checks the variants the theme's manifest lists, and variant
	 * templates that are also a component's own template name.
	 *
	 * @param  list<ComponentListing> $components
	 * @return list<Violation>
	 */
	private function variants(ThemeManifest $theme, Views $views, array $components): array
	{
		$known    = array_map(static fn (ComponentListing $component): string => (string) $component->name, $components);
		$problems = [];

		foreach ($theme->variants() as $component => $list) {
			if ($component === ComponentVariants::IMAGE) {
				$problems = [...$problems, ...$this->imageVariants($theme, $views, $list)];

				continue;
			}

			$name = ComponentName::parse($component);

			if ($name === null || ! in_array((string) $name, $known, true)) {
				$problems[] = new Violation("variants {$component}", sprintf('theme.json lists variants for "%s", which isn\'t a component.', $component), Severity::Warning);

				continue;
			}

			foreach ($list as $item) {
				$variant = ComponentVariants::manifestItem($item, $theme->namespace);

				if ($variant === null) {
					$problems[] = new Violation("variants {$name}", sprintf('theme.json lists a variant of "%s" that isn\'t valid: a name is lowercase letters, digits, and hyphens, starting with a letter, and not "default".', $name), Severity::Warning);
				} elseif ($views->variantText($name, $variant, 'label') === null) {
					$problems[] = new Violation("variants {$name}", sprintf('The "%s" variant of "%s" has no label; add "components.%s.variants.%s.label" to the theme\'s lang/ catalog.', $variant->name, $name, $name->name, $variant->name), Severity::Notice);
				}
			}
		}

		$namespaces = array_values(array_unique(array_map(static fn (ComponentListing $component): string => $component->name->namespace, $components)));

		foreach ($views->variantFiles() as $fileName => [$name, $variant]) {
			$other = ComponentName::fromFileName($fileName, $namespaces);

			if ($other !== null && in_array((string) $other, $known, true) && (string) $other !== (string) $name) {
				$problems[] = new Violation("component {$other}", sprintf('components/%s.php is both the "%s" component\'s template and the "%s" variant\'s of "%s"; rename one.', $fileName, $other, $variant->name, $name), Severity::Warning);
			}
		}

		return $problems;
	}

	/**
	 * Checks the Markdown image variants the theme's manifest lists
	 * (D-268): each a valid name, with a label in the theme's catalog.
	 *
	 * @param  list<mixed> $list
	 * @return list<Violation>
	 */
	private function imageVariants(ThemeManifest $theme, Views $views, array $list): array
	{
		$problems = [];

		foreach ($list as $item) {
			$variant = ComponentVariants::manifestItem($item, $theme->namespace);

			if ($variant === null) {
				$problems[] = new Violation('variants image', 'theme.json lists an image variant that isn\'t valid: a name is lowercase letters, digits, and hyphens, starting with a letter, and not "default".', Severity::Warning);
			} elseif ($views->imageVariantText($variant, 'label') === null) {
				$problems[] = new Violation('variants image', sprintf('The "%s" image variant has no label; add "images.variants.%s.label" to the theme\'s lang/ catalog.', $variant->name, $variant->name), Severity::Notice);
			}
		}

		return $problems;
	}

	/**
	 * Renders the welcome page and checks its landmarks.
	 *
	 * @return list<Violation>
	 */
	private function layout(ThemeChain $chain): array
	{
		try {
			$views   = $this->views->forChain($chain);
			$context = $this->views->context($views);
			$context->share(['page' => new ContentPage(PageKind::Welcome, ''), 'entry' => null, 'entries' => null, 'type' => null, 'title' => '']);
			$html    = $views->render('welcome', [], $context);
		} catch (Throwable $error) {
			return [new Violation('layout', sprintf('The welcome page doesn\'t render: %s', $error->getMessage()))];
		}

		$document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
		$problems = [];
		$root     = $document->documentElement;
		$mains    = $document->querySelectorAll('main');

		if (! $root instanceof Element || trim((string) $root->getAttribute('lang')) === '') {
			$problems[] = new Violation('layout', 'The base layout\'s <html> has no lang attribute.');
		}

		if ($root instanceof Element && trim((string) $root->getAttribute('dir')) === '') {
			$problems[] = new Violation('layout', 'The base layout\'s <html> has no dir attribute; add dir="<?= attr($site->dir) ?>" so right-to-left languages read right to left.', Severity::Notice);
		}

		foreach (['meta[charset]' => '<meta charset>', 'meta[name="viewport"]' => '<meta name="viewport">', 'meta[name="generator"]' => '<meta name="generator">', 'title' => '<title>'] as $selector => $tag) {
			if ($document->querySelectorAll($selector)->length > 1) {
				$problems[] = new Violation('layout', sprintf('The base layout prints %s more than once; $template->head() prints it, so take it out of the layout.', $tag), Severity::Warning);
			}
		}

		if ($mains->length !== 1) {
			$problems[] = new Violation('layout', sprintf('The base layout needs one <main> landmark; it has %d.', $mains->length));
		}

		$main = $mains->item(0);
		$skip = $document->querySelector('body a[href^="#"]');
		$id   = $skip instanceof Element ? substr((string) $skip->getAttribute('href'), 1) : '';

		if ($id === '' || ! $main instanceof Element || ($main->getAttribute('id') !== $id && $main->querySelector('[id="' . addslashes($id) . '"]') === null)) {
			$problems[] = new Violation('layout', 'The base layout needs a skip link (the first in-page link) to the <main> content.');
		}

		foreach (['header', 'footer'] as $landmark) {
			if (! self::hasLandmark($document, $landmark)) {
				$problems[] = new Violation('layout', "The base layout has no <{$landmark}> landmark.", Severity::Warning);
			}
		}

		$headings = $document->querySelectorAll('h1')->length;

		if ($headings !== 1) {
			$problems[] = new Violation('layout', sprintf('The welcome page has %d <h1> headings; pages should have one.', $headings), Severity::Warning);
		}

		return $problems;
	}

	/**
	 * Returns whether a page has a `<header>` or `<footer>` that's a
	 * landmark: one not inside sectioning content (an article's header
	 * isn't the page's banner).
	 */
	private static function hasLandmark(HTMLDocument $document, string $element): bool
	{
		foreach ($document->querySelectorAll($element) as $candidate) {
			if ($candidate->closest('article, aside, main, nav, section') === null) {
				return true;
			}
		}

		return false;
	}
}
