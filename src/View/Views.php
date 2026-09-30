<?php

/**
 * Views.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use Closure;
use Error;
use Throwable;
use Blush\Theme\ThemeAssets;
use Blush\Theme\ThemeChain;
use Blush\Theme\ThemeSettings;
use Blush\Icon\IconName;
use Blush\Translation\Translator;
use Blush\Component\ComponentListing;
use Blush\Component\ComponentName;
use Blush\Component\ComponentType;
use Blush\Component\Variant;
use Blush\Component\Slots;
use Blush\Component\TemplateComponent;

/**
 * Renders plain PHP templates (D-009) for one theme chain.
 *
 * A template runs in an isolated scope: its data become variables, and
 * `$template` is a `Template`, which exposes only its public API (D-158);
 * `$this` isn't available. When a
 * template calls `layout()`, its output becomes the `content` section
 * and the layout renders next, with the same data plus the layout's own
 * (layouts may have layouts). Partials see the shared data (the site,
 * and on content and error pages the page's data, D-146) plus what
 * they're given, not their caller's variables. Context providers attached
 * to a view add their data under what the view is given.
 *
 * Components (D-025) render their template (`components/{namespace}-{name}`,
 * D-171) with their props, `$slot`, and `$slots`; a registered class builds
 * the props first.
 *
 * `ViewFactory` builds one `Views` per theme chain, with the chain's
 * assets and settings; the services templates reach through
 * `Template` hang off it.
 */
final readonly class Views
{
	public ThemeChain $chain;

	public function __construct(
		public ViewFinder $finder,
		public ThemeAssets $assets,
		public Translator $translator,
		public ViewServices $services,
		public ThemeSettings $settings = new ThemeSettings()
	) {
		$this->chain = $assets->chain;
	}

	/**
	 * Renders a page from the first view in a hierarchy that exists. The
	 * context's layout (from front matter) replaces the one the page's
	 * template asks for.
	 *
	 * @param  string|list<string>  $names
	 * @param  array<string, mixed> $data
	 * @throws ViewException
	 */
	public function render(string|array $names, array $data = [], ViewContext $context = new ViewContext()): string
	{
		$names = (array) $names;
		$found = $this->finder->first($names) ?? throw ViewNotFound::forNames($names);

		return $this->renderFile($found[0], $found[1], $data, $context, $context->layout);
	}

	/**
	 * Renders a partial, the first of `$names` that exists, with the
	 * shared data plus its own.
	 *
	 * @param  string|list<string>  $names
	 * @param  array<string, mixed> $data
	 * @throws ViewException
	 */
	public function partial(string|array $names, array $data, ViewContext $context): string
	{
		[$name, $file] = $this->findPartial($names) ?? throw ViewNotFound::forNames((array) $names);

		return $this->renderFile($name, $file, $data, $context);
	}

	/**
	 * Returns whether any of `$names` has a view.
	 *
	 * @param  string|list<string> $names
	 * @throws ViewException When a single name isn't valid.
	 */
	public function exists(string|array $names): bool
	{
		return $this->findPartial($names) !== null;
	}

	/**
	 * Returns the first of `$names` that has a view, with its file. A
	 * single invalid name is an error; lists skip invalid names, since
	 * they're often built from type names and slugs.
	 *
	 * @param  string|list<string> $names
	 * @return ?array{string, string}
	 * @throws ViewException
	 */
	private function findPartial(string|array $names): ?array
	{
		if (is_string($names)) {
			$file = $this->finder->find($names);

			return $file === null ? null : [$names, $file];
		}

		return $this->finder->first($names);
	}

	/**
	 * Returns whether a component exists: it's registered, or the chain
	 * has its template. `$name` is a full name or a core component's
	 * short name (D-171).
	 */
	public function hasComponent(string $name): bool
	{
		$parsed = ComponentName::parse($name);

		return $parsed !== null
			&& ($this->services->components->isRegistered($name) || $this->finder->nearest($parsed->views()) !== null);
	}

	/**
	 * Returns every component the chain can render, by name: the core
	 * components, the registered ones, and every template in a
	 * `components/` folder named for a component, other than a variant's
	 * template (see `strayComponentFiles()` for the rest), each with its
	 * variants.
	 *
	 * @return list<ComponentListing>
	 */
	public function components(): array
	{
		$names    = $this->knownComponents();
		$variants = $this->variantFiles();

		foreach ($this->componentFiles() as [$fileName]) {
			$name = isset($variants[$fileName]) ? null : ComponentName::fromFileName($fileName, $this->componentNamespaces());

			if ($name !== null) {
				$names[(string) $name] ??= $name;
			}
		}

		ksort($names, SORT_STRING);

		return array_values(array_map(fn (ComponentName $name): ComponentListing => new ComponentListing(
			$name,
			$this->services->components->get((string) $name),
			array_column($this->finder->allOf($name->views()), 1),
			$this->componentText($name, 'label'),
			$this->componentText($name, 'description'),
			$this->variants($name)
		), $names));
	}

	/**
	 * Returns a component's variants under the chain, Default not
	 * included (D-266).
	 *
	 * @return list<Variant>
	 */
	public function variants(ComponentName $name): array
	{
		return $this->services->variants->for($name, $this->chain);
	}

	/**
	 * Returns a variant's translated text (`label` or `description`), or
	 * `null` when no catalog has it: `components.{name}.variants.{variant}.{key}`
	 * in its registrant's domain.
	 *
	 * @param array<string, mixed> $params
	 */
	public function variantText(ComponentName $name, Variant $variant, string $key, array $params = []): ?string
	{
		return $this->namespaceText($variant->registrant, "components.{$name->name}.variants.{$variant->name}.{$key}", $params);
	}

	/**
	 * Returns the variant templates the chain could have for the core and
	 * registered components (`callout-bordered`), as file names, each
	 * with its component and variant.
	 *
	 * @return array<string, array{ComponentName, Variant}>
	 */
	public function variantFiles(): array
	{
		$files = [];

		foreach ($this->knownComponents() as $name) {
			foreach ($this->variants($name) as $variant) {
				foreach ($name->views() as $view) {
					$files[substr($view, strlen('components/')) . "-{$variant->name}"] = [$name, $variant];
				}
			}
		}

		return $files;
	}

	/**
	 * Returns the core and registered components, by full name.
	 *
	 * @return array<string, ComponentName>
	 */
	private function knownComponents(): array
	{
		$names = [];

		foreach (ComponentType::cases() as $type) {
			$names[(string) $type->componentName()] = $type->componentName();
		}

		foreach ($this->services->components->all() as $key => $definition) {
			$names[$key] = $definition->name;
		}

		return $names;
	}

	/**
	 * Returns the templates in the chain's `components/` folders that
	 * aren't named for any component or variant, such as a theme's
	 * `card.php` that should be `{slug}-card.php`. Nothing can render them.
	 *
	 * @return list<string>
	 */
	public function strayComponentFiles(): array
	{
		$namespaces = $this->componentNamespaces();
		$variants   = $this->variantFiles();

		return array_values(array_map(
			static fn (array $file): string => $file[1],
			array_filter($this->componentFiles(), static fn (array $file): bool => ! isset($variants[$file[0]]) && ComponentName::fromFileName($file[0], $namespaces) === null)
		));
	}

	/**
	 * Returns a component's translated text, such as its `label` or
	 * `description`, or `null` when no catalog has it (D-172). Text is
	 * keyed `components.{name}.{key}` in the namespace's domain: `blush`
	 * for core components, `theme` for the chain's themes, and otherwise
	 * the namespace itself (`app` for the site, a vendor for an
	 * extension). Prop text is `props.{prop}.label` and
	 * `props.{prop}.choices.{value}`.
	 *
	 * @param array<string, mixed> $params
	 */
	public function componentText(ComponentName $name, string $key, array $params = []): ?string
	{
		return $this->namespaceText($name->namespace, "components.{$name->name}.{$key}", $params);
	}

	/**
	 * Returns an icon's translated text, such as its `label`, or `null`
	 * when no catalog has it (D-187): `icons.{name}.{key}` in the
	 * namespace's domain, as for components.
	 *
	 * @param array<string, mixed> $params
	 */
	public function iconText(IconName $name, string $key, array $params = []): ?string
	{
		return $this->namespaceText($name->namespace, "icons.{$name->name}.{$key}", $params);
	}

	/**
	 * Returns a message from a namespace's catalog domain: `blush` for
	 * core, `theme` for the chain's themes, and otherwise the namespace
	 * itself (`app`, or an extension's vendor).
	 *
	 * @param array<string, mixed> $params
	 */
	private function namespaceText(string $namespace, string $message, array $params): ?string
	{
		$domain = match (true) {
			$namespace === ComponentName::CORE                => 'blush',
			in_array($namespace, $this->chain->slugs(), true) => 'theme',
			default                                           => $namespace
		};

		return $this->translator->has($message, $domain) ? $this->translator->translate($message, $params, $domain) : null;
	}

	/**
	 * Renders a component with its props and slots. `$name` is a full
	 * name or a core component's short name. Its template gets
	 * `$component` (its class, or a `TemplateComponent`), which holds its
	 * content and slots (D-195, D-196), and its variant (D-266): the
	 * `variant` prop, if the component has it under the chain. A
	 * variant's own template (`components/callout-bordered`) is used when
	 * the chain has one.
	 *
	 * @param  array<string, mixed> $props
	 * @throws ViewException
	 */
	public function component(string $name, array $props, string $slot, Slots $slots, ViewContext $context): string
	{
		$parsed    = ComponentName::parse($name) ?? throw $this->invalidComponent($name);
		$class     = $this->services->components->get($name)?->class;
		$component = $class === null ? new TemplateComponent() : $this->services->factory->make($class, $props);

		$variant   = $this->services->variants->resolve($parsed, $this->chain, $props['variant'] ?? null);

		$component->attach($parsed, $props, $slot, $slots, $this->translator, $context, $variant);

		if (! $component->shouldRender()) {
			return '';
		}

		// A variant's own template (`components/callout-bordered`) wins
		// over the component's (D-266).
		$view  = $component->template();
		$views = $view === null ? $parsed->views() : [$view];
		$views = $variant === null ? $views : [...array_map(static fn (string $name): string => "{$name}-{$variant->name}", $views), ...$views];

		[$view, $file] = $this->finder->nearest($views) ?? throw ViewNotFound::forNames($views);

		return $this->renderFile($view, $file, ['component' => $component], $context);
	}

	/**
	 * Returns the error for a string that isn't a component name.
	 */
	private function invalidComponent(string $name): ViewException
	{
		if (preg_match('#^' . ComponentName::SYNTAX . '$#', $name) === 1 && ! str_contains($name, '/')) {
			return new ViewException(sprintf(
				'"%s" isn\'t a core component, so it needs its namespace, such as "%s/%s" (D-171).',
				$name,
				$this->chain->active()->slug,
				$name
			));
		}

		return new ViewException(sprintf('"%s" is not a valid component name.', $name));
	}

	/**
	 * Returns the namespaces a component template's file name may start
	 * with: core, the site, the chain's themes, and every registered one.
	 *
	 * @return list<string>
	 */
	private function componentNamespaces(): array
	{
		return array_values(array_unique([
			ComponentName::CORE,
			ComponentName::SITE,
			...$this->chain->slugs(),
			...$this->services->components->namespaces()
		]));
	}

	/**
	 * Returns the templates directly in each view directory's
	 * `components/` folder, as file names (without `.php`) and paths.
	 * Subfolders aren't components.
	 *
	 * @return list<array{string, string}>
	 */
	private function componentFiles(): array
	{
		$files = [];

		foreach ($this->finder->directories() as $directory) {
			foreach (glob("{$directory}/components/*.php") ?: [] as $path) {
				$fileName = basename($path, '.php');

				if (is_file($path) && ViewFinder::isValidName($fileName)) {
					$files[] = [$fileName, $path];
				}
			}
		}

		return $files;
	}

	/**
	 * Renders a template file, then its layout, if it asked for one.
	 *
	 * @param  array<string, mixed> $data
	 * @throws ViewException
	 */
	private function renderFile(string $name, string $file, array $data, ViewContext $context, ?string $layoutOverride = null): string
	{
		$template = new Template($this, $context);
		$data     = [...$context->shared, ...$data];
		$output   = $this->evaluate($template, $file, $this->services->providers->apply($name, $data));
		$layout   = $template->requestedLayout();

		if ($layout === null) {
			return $output;
		}

		[$name, $layoutData] = $layout;

		if ($layoutOverride !== null && ViewFinder::isValidName($layoutOverride)) {
			$override = str_contains($layoutOverride, '/') ? $layoutOverride : "layouts/{$layoutOverride}";
			$name     = $this->finder->find($override) === null ? $name : $override;
		}

		$context->setSection('content', $output);

		$layoutFile = $this->finder->find($name) ?? throw ViewNotFound::forNames([$name]);

		return $this->renderFile($name, $layoutFile, [...$data, ...$layoutData], $context);
	}

	/**
	 * Runs a template file and returns its output. Output buffers it
	 * opens are closed whatever happens, and an exception is rethrown
	 * with the file named.
	 *
	 * @param  array<string, mixed> $data
	 * @throws ViewException
	 */
	private function evaluate(Template $template, string $file, array $data): string
	{
		$level = ob_get_level();

		ob_start();

		try {
			self::includer($template)($file, $data);
		} catch (Throwable $exception) {
			while (ob_get_level() > $level) {
				ob_end_clean();
			}

			if ($exception instanceof Error && str_contains($exception->getMessage(), 'Using $this')) {
				throw new ViewException(sprintf('Views use $template, not $this, in view %s', $file), 0, $exception);
			}

			throw $exception instanceof ViewException
				? $exception
				: new ViewException(sprintf('%s in view %s', $exception->getMessage(), $file), 0, $exception);
		}

		$open = $template->openSections();

		while (ob_get_level() > $level + 1) {
			ob_end_clean();
		}

		$output = (string) ob_get_clean();

		if ($open !== []) {
			throw new ViewException(sprintf('Section "%s" was never stopped in view %s.', array_last($open), $file));
		}

		return $output;
	}

	/**
	 * Returns a function that includes a template file with the template
	 * as `$template` and no object or class scope, so the file sees its
	 * data and `Template`'s public API only (D-158). A data key named
	 * `template` is ignored; so are `__data` and `__file`, and `$__file`
	 * stays in scope.
	 *
	 * @return Closure(string, array<string, mixed>): void
	 */
	private static function includer(Template $template): Closure
	{
		return static function (string $__file, array $__data) use ($template): void {
			extract($__data, EXTR_SKIP);
			unset($__data);

			include func_get_arg(0);
		};
	}
}
