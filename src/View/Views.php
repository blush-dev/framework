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

use Throwable;
use Blush\Theme\ThemeAssets;
use Blush\Theme\ThemeChain;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeSettings;
use Blush\Icon\IconName;
use Blush\Translation\DomainTranslator;
use Blush\Translation\Translator;
use Blush\Component\ComponentListing;
use Blush\Component\ComponentName;
use Blush\Component\Slots;
use Blush\Component\TemplateComponent;
use Blush\Directive\DirectiveDefinition;
use Blush\Directive\DirectiveListing;
use Blush\Directive\DirectiveName;
use Blush\Directive\Variant;

/**
 * Renders templates for one theme chain, each through the view engine
 * its extension names (D-502): plain PHP templates (D-009) built in.
 *
 * A template gets its data and a `Template`, which exposes only its
 * public API (D-158). When a
 * template calls `layout()`, its output becomes the `content` section
 * and the layout renders next, with the same data plus the layout's own
 * (layouts may have layouts). Partials see the shared data (the site,
 * and on content and error pages the page's data, D-146) plus what
 * they're given, not their caller's variables. Context providers attached
 * to a view add their data under what the view is given.
 *
 * Directives (what content says) and components (a template's reusable
 * pieces) are two things (D-532): a directive renders its template
 * (`directives/{namespace}-{name}`) with `$directive`, and a component its
 * own (`components/{namespace}-{name}`, D-171) with `$component` and its
 * slots; a registered class builds the props first.
 *
 * `ViewFactory` builds one `Views` per theme chain, with the chain's
 * assets and settings; the services templates reach through
 * `Template` hang off it.
 */
final readonly class Views
{
	public ThemeChain $chain;

	/**
	 * The translator bound to the chain's domains, child first (D-451):
	 * what `$template->t()` reads.
	 */
	public DomainTranslator $messages;

	public function __construct(
		public ViewFinder $finder,
		public ThemeAssets $assets,
		public Translator $translator,
		public ViewServices $services,
		public ThemeSettings $settings = new ThemeSettings()
	) {
		$this->chain    = $assets->chain;
		$this->messages = new DomainTranslator($translator, $this->chain->names());
	}

	/**
	 * Renders a page from the first view in a hierarchy that exists. The
	 * context's layout (from front matter) replaces the one the page's
	 * template asks for.
	 *
	 * The head is held while the page renders (D-570), and the assets
	 * anything in it asked for (D-572) are added before it's filled in,
	 * so what renders after the layout prints the head still reaches it.
	 *
	 * @param  string|list<string>  $names
	 * @param  array<string, mixed> $data
	 * @throws ViewException
	 * @throws ThemeException When an asset's theme has an invalid build manifest.
	 */
	public function render(string|array $names, array $data = [], ViewContext $context = new ViewContext()): string
	{
		$names = (array) $names;
		$found = $this->finder->first($names) ?? throw ViewNotFound::forNames($names);
		$holds = $context->head->hold();

		[$html, $handles] = $this->services->collector->collect(fn (): string => $this->renderFile($found[0], $found[1], $data, $context, $context->layout));

		$context->head->enqueue(...$handles);

		return $holds ? $context->head->fill($html) : $html;
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
	 * Returns whether a directive exists: it's registered (D-532).
	 * `$name` is a full name or a core directive's short name (D-171).
	 */
	public function hasDirective(string $name): bool
	{
		return $this->services->directives->isRegistered($name);
	}

	/**
	 * Returns every directive, by name, each with its template files in
	 * the chain and its variants under it. Templates in a `directives/`
	 * folder that aren't for a directive or variant are
	 * `strayDirectiveFiles()`.
	 *
	 * @return list<DirectiveListing>
	 */
	public function directives(): array
	{
		$definitions = $this->services->directives->all();

		ksort($definitions, SORT_STRING);

		return array_values(array_map(fn (DirectiveDefinition $definition): DirectiveListing => new DirectiveListing(
			$definition->name,
			$definition,
			array_column($this->finder->allOf($definition->name->views()), 1),
			$this->directiveText($definition->name, 'label'),
			$this->directiveText($definition->name, 'description'),
			$this->variants($definition->name)
		), $definitions));
	}

	/**
	 * Returns a directive's variants under the chain, Default not
	 * included (D-266).
	 *
	 * @return list<Variant>
	 */
	public function variants(DirectiveName $name): array
	{
		return $this->services->variants->for($name, $this->chain);
	}

	/**
	 * Returns Markdown images' variants under the chain (D-268), Default
	 * not included: classes its themes offer.
	 *
	 * @return list<Variant>
	 */
	public function imageVariants(): array
	{
		return $this->services->variants->forImages($this->chain);
	}

	/**
	 * Returns an image variant's translated text (`label` or
	 * `description`), or `null`: `images.variants.{variant}.{key}` in its
	 * theme's catalog.
	 *
	 * @param array<string, mixed> $params
	 */
	public function imageVariantText(Variant $variant, string $key, array $params = []): ?string
	{
		return $this->namespaceText($variant->registrant, "images.variants.{$variant->name}.{$key}", $params);
	}

	/**
	 * Returns a variant's translated text (`label` or `description`), or
	 * `null` when no catalog has it: `directives.{name}.variants.{variant}.{key}`
	 * in its registrant's domain.
	 *
	 * @param array<string, mixed> $params
	 */
	public function variantText(DirectiveName $name, Variant $variant, string $key, array $params = []): ?string
	{
		return $this->namespaceText($variant->registrant, "directives.{$name->name}.variants.{$variant->name}.{$key}", $params);
	}

	/**
	 * Returns the variant templates the chain could have for the
	 * directives (`callout-bordered`), as file names, each with its
	 * directive and variant.
	 *
	 * @return array<string, array{DirectiveName, Variant}>
	 */
	public function variantFiles(): array
	{
		$files = [];

		foreach ($this->services->directives->all() as $definition) {
			foreach ($this->variants($definition->name) as $variant) {
				foreach ($definition->name->views() as $view) {
					$files[substr($view, strlen('directives/')) . "-{$variant->name}"] = [$definition->name, $variant];
				}
			}
		}

		return $files;
	}

	/**
	 * Returns the templates in the chain's `directives/` folders that
	 * aren't for any directive or variant, such as a theme's `tabs.php`
	 * for a directive no plugin registers. Nothing can render them.
	 *
	 * @return list<string>
	 */
	public function strayDirectiveFiles(): array
	{
		$known = $this->variantFiles();

		foreach ($this->services->directives->all() as $definition) {
			foreach ($definition->name->views() as $view) {
				$known[substr($view, strlen('directives/'))] = true;
			}
		}

		return array_values(array_map(
			static fn (array $file): string => $file[1],
			array_filter($this->folderFiles('directives'), static fn (array $file): bool => ! isset($known[$file[0]]))
		));
	}

	/**
	 * Returns a directive's translated text, such as its `label` or
	 * `description`, or `null` when no catalog has it (D-172). Text is
	 * keyed `directives.{name}.{key}` in the namespace's domain: `blush`
	 * for core directives, and otherwise the namespace itself (`app` for
	 * the site, a vendor for an extension). Prop text is
	 * `props.{prop}.label` and `props.{prop}.choices.{value}`.
	 *
	 * @param array<string, mixed> $params
	 */
	public function directiveText(DirectiveName $name, string $key, array $params = []): ?string
	{
		return $this->namespaceText($name->namespace, "directives.{$name->name}.{$key}", $params);
	}

	/**
	 * Returns an icon's translated text, such as its `label`, or `null`
	 * when no catalog has it (D-187): `icons.{name}.{key}` in the
	 * namespace's domain, as for directives.
	 *
	 * @param array<string, mixed> $params
	 */
	public function iconText(IconName $name, string $key, array $params = []): ?string
	{
		return $this->namespaceText($name->namespace, "icons.{$name->name}.{$key}", $params);
	}

	/**
	 * Renders a directive with its props and content (D-532). `$name` is a
	 * full name or a core directive's short name. The assets it asks for
	 * (`assets()`, D-572) go to the page. Its template gets
	 * `$directive` (its registered class, D-534), which holds its
	 * content (D-195, D-196), and its variant (D-266): the `variant` prop,
	 * if the directive has it under the chain. A variant's own template
	 * (`directives/callout-bordered`) is used when the chain has one.
	 * Without a template in the chain, the directive renders itself
	 * (`render()`, D-382): its HTML, or the template file it ships with.
	 *
	 * @param  array<string, mixed> $props
	 * @throws ViewException
	 */
	public function directive(string $name, array $props, string $content, ViewContext $context): string
	{
		$definition = $this->services->directives->get($name) ?? throw $this->unknownDirective($name);
		$parsed     = $definition->name;
		$directive  = $this->services->factory->make($definition->class, $props, $context->language);
		$variant    = $this->services->variants->resolve($parsed, $this->chain, $props['variant'] ?? null);

		$directive->attach($parsed, $props, $content, $this->messages->with($this->ownDomain($parsed->namespace)), $context, $variant);

		if (! $directive->shouldRender()) {
			return '';
		}

		$this->services->collector->add(...$directive->assets());

		// A variant's own template (`directives/callout-bordered`) wins
		// over the directive's (D-266).
		$view  = $directive->template();
		$views = $view === null ? $parsed->views() : [$view];
		$views = $variant === null ? $views : [...array_map(static fn (string $name): string => "{$name}-{$variant->name}", $views), ...$views];
		$found = $this->finder->nearest($views);

		if ($found !== null) {
			return $this->renderFile($found[0], $found[1], ['directive' => $directive], $context);
		}

		$own = $directive->render();

		return $this->renderOwn('directive', (string) $parsed, $directive, $parsed->views()[0], $views, $own === null || is_string($own) ? $own : [$own->file, $own->data], $context);
	}

	/**
	 * Returns whether a component exists (D-532): a class is registered
	 * for it, or the chain has its template. `$name` is a full name.
	 */
	public function hasComponent(string $name): bool
	{
		$parsed = ComponentName::parse($name);

		return $parsed !== null
			&& ($this->services->components->isRegistered((string) $parsed) || $this->finder->nearest([$parsed->view()]) !== null);
	}

	/**
	 * Returns every component the chain can render, by name: those with
	 * a registered class and every template in a `components/` folder
	 * named for one (see `strayComponentFiles()` for the rest).
	 *
	 * @return list<ComponentListing>
	 */
	public function components(): array
	{
		$names = [];

		foreach (array_keys($this->services->components->all()) as $name) {
			$parsed = ComponentName::parse($name);

			if ($parsed !== null) {
				$names[$name] = $parsed;
			}
		}

		foreach ($this->folderFiles('components') as [$fileName]) {
			$name = ComponentName::fromFileName($fileName, $this->componentNamespaces());

			if ($name !== null) {
				$names[(string) $name] ??= $name;
			}
		}

		ksort($names, SORT_STRING);

		return array_values(array_map(fn (ComponentName $name): ComponentListing => new ComponentListing(
			$name,
			$this->services->components->get((string) $name),
			array_column($this->finder->allOf([$name->view()]), 1)
		), $names));
	}

	/**
	 * Returns the templates in the chain's `components/` folders that
	 * aren't named for a component, such as a theme's `card.php` that
	 * should be `{namespace}-card.php`. Nothing can render them.
	 *
	 * @return list<string>
	 */
	public function strayComponentFiles(): array
	{
		$namespaces = $this->componentNamespaces();

		return array_values(array_map(
			static fn (array $file): string => $file[1],
			array_filter($this->folderFiles('components'), static fn (array $file): bool => ComponentName::fromFileName($file[0], $namespaces) === null)
		));
	}

	/**
	 * Renders a component with its props and slots (D-532). `$name` is a
	 * full name. The assets it asks for (`assets()`, D-572) go to the
	 * page. Its template gets `$component` (its class, or a
	 * `TemplateComponent`), which holds its content and slots (D-195,
	 * D-196). Without a template in the chain, a component with a class
	 * renders itself (`render()`, D-382): its HTML, or the template file
	 * it ships with.
	 *
	 * @param  array<string, mixed> $props
	 * @throws ViewException
	 */
	public function component(string $name, array $props, string $slot, Slots $slots, ViewContext $context): string
	{
		$parsed    = ComponentName::parse($name) ?? throw new ViewException(sprintf('"%s" is not a valid component name; a component is always "{namespace}/{name}", such as "%s/%s" (D-532).', $name, $this->chain->active()->namespace, $name));
		$class     = $this->services->components->get((string) $parsed);
		$component = $class === null ? new TemplateComponent() : $this->services->factory->make($class, $props, $context->language);

		$component->attach($parsed, $props, $slot, $slots, $this->messages->with($this->ownDomain($parsed->namespace)), $context);

		if (! $component->shouldRender()) {
			return '';
		}

		$this->services->collector->add(...$component->assets());

		$view  = $component->template();
		$views = [$view ?? $parsed->view()];
		$found = $this->finder->nearest($views);

		if ($found !== null) {
			return $this->renderFile($found[0], $found[1], ['component' => $component], $context);
		}

		$own = $component->render();

		return $this->renderOwn('component', (string) $parsed, $component, $parsed->view(), $views, $own === null || is_string($own) ? $own : [$own->file, $own->data], $context);
	}

	/**
	 * Renders a directive's or component's own markup (D-382), when the
	 * chain has no template for it: `render()`'s HTML, or the file it
	 * ships with and its data, rendered under its view name. `null` (it
	 * has none) is the missing template's error.
	 *
	 * @param  list<string>                                    $views The templates looked for.
	 * @param  string|array{string, array<string, mixed>}|null $own
	 * @throws ViewException
	 */
	private function renderOwn(string $variable, string $name, Renderable $renderable, string $viewName, array $views, string|array|null $own, ViewContext $context): string
	{
		if (is_string($own)) {
			return $own;
		}

		if ($own === null) {
			throw ViewNotFound::forNames($views);
		}

		[$file, $data] = $own;

		if (! is_file($file)) {
			throw new ViewException(sprintf('The "%s" %s\'s template, %s, doesn\'t exist.', $name, $variable, $file));
		}

		return $this->renderFile($viewName, $file, [...$data, $variable => $renderable], $context);
	}

	/**
	 * Returns a message from a namespace's catalog domain (D-451):
	 * `blush` for core, the chain's themes for any of theirs (so a child
	 * theme can reword its parent's), and otherwise the namespace's
	 * extension's `vendor/name` (or `app`).
	 *
	 * @param array<string, mixed> $params
	 */
	private function namespaceText(string $namespace, string $message, array $params): ?string
	{
		$domain = match (true) {
			$namespace === DirectiveName::CORE                     => 'blush',
			in_array($namespace, $this->chain->namespaces(), true) => $this->chain->names(),
			default                                                => $this->translator->domainOf($namespace)
		};

		return $this->translator->has($message, $domain) ? $this->translator->translate($message, $params, $domain) : null;
	}

	/**
	 * Returns the domain of a directive's or component's namespace's own
	 * text: `blush` for core, or its extension's `vendor/name` (a theme's
	 * is in the chain).
	 */
	private function ownDomain(string $namespace): string
	{
		return $namespace === DirectiveName::CORE ? 'blush' : $this->translator->domainOf($namespace);
	}

	/**
	 * Returns the error for a directive that isn't registered.
	 */
	private function unknownDirective(string $name): ViewException
	{
		if (preg_match('#^' . DirectiveName::SYNTAX . '$#', $name) === 1 && ! str_contains($name, '/')) {
			return new ViewException(sprintf('"%s" isn\'t a core directive, so it needs its namespace, such as "acme/%s" (D-171).', $name, $name));
		}

		return new ViewException(sprintf('No directive "%s" is registered (D-532).', $name));
	}

	/**
	 * Returns the namespaces a component template's file name may start
	 * with: the site, the chain's themes, and every registered one.
	 *
	 * @return list<string>
	 */
	private function componentNamespaces(): array
	{
		return array_values(array_unique([
			ComponentName::SITE,
			...$this->chain->namespaces(),
			...$this->services->components->namespaces()
		]));
	}

	/**
	 * Returns the templates directly in each view directory's `$folder`
	 * (`directives` or `components`), in any engine's extension (D-502),
	 * as file names (without the extension) and paths. Subfolders aren't
	 * directives or components.
	 *
	 * @return list<array{string, string}>
	 */
	private function folderFiles(string $folder): array
	{
		$files = [];

		foreach ($this->finder->directories() as $directory) {
			foreach ($this->finder->extensions() as $extension) {
				foreach (glob("{$directory}/{$folder}/*.{$extension}") ?: [] as $path) {
					$fileName = basename($path, ".{$extension}");

					if (is_file($path) && ViewFinder::isValidName($fileName)) {
						$files[] = [$fileName, $path];
					}
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
	 * Runs a template file through its engine (D-502) and returns its
	 * output. An exception is rethrown with the file named, and so is a
	 * section left open.
	 *
	 * @param  array<string, mixed> $data
	 * @throws ViewException
	 */
	private function evaluate(Template $template, string $file, array $data): string
	{
		try {
			$output = $this->services->engines->forFile($file)->render($file, $data, $template);
		} catch (Throwable $exception) {
			throw $exception instanceof ViewException
				? $exception
				: new ViewException(sprintf('%s in view %s', $exception->getMessage(), $file), 0, $exception);
		}

		$open = $template->openSections();

		if ($open !== []) {
			throw new ViewException(sprintf('Section "%s" was never stopped in view %s.', array_last($open), $file));
		}

		return $output;
	}
}
