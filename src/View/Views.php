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
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;
use Blush\Theme\ThemeAssets;
use Blush\Theme\ThemeChain;
use Blush\Theme\ThemeSettings;
use Blush\Translation\Translator;
use Blush\View\Component\ComponentListing;
use Blush\View\Component\ComponentType;
use Blush\View\Component\Slots;

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
 * Components (D-025) render `components/{key}` with their props, `$slot`,
 * and `$slots`; a class registered for the key builds the props first.
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
	 * Returns whether a component exists: a class is registered for its
	 * key, or the chain has its template.
	 */
	public function hasComponent(string $key): bool
	{
		return ViewFinder::isValidName($key)
			&& ($this->services->components->isRegistered($key) || $this->finder->find("components/{$key}") !== null);
	}

	/**
	 * Returns every component the chain can render, by key: the core
	 * components, those with a registered class, and every
	 * `components/{key}.php` in the view directories.
	 *
	 * @return list<ComponentListing>
	 */
	public function components(): array
	{
		$keys = [
			...array_map(static fn (ComponentType $type): string => $type->value, ComponentType::cases()),
			...array_keys($this->services->components->all())
		];

		foreach ($this->finder->directories() as $directory) {
			$keys = [...$keys, ...self::templateKeys("{$directory}/components")];
		}

		$keys = array_unique($keys);
		sort($keys, SORT_STRING);

		return array_map(fn (string $key): ComponentListing => new ComponentListing(
			$key,
			$this->services->components->get($key),
			$this->finder->all("components/{$key}"),
			ComponentType::tryFrom($key) !== null
		), $keys);
	}

	/**
	 * Renders a component with its props and slots.
	 *
	 * @param  array<string, mixed> $props
	 * @throws ViewException
	 */
	public function component(string $key, array $props, string $slot, Slots $slots, ViewContext $context): string
	{
		if (! ViewFinder::isValidName($key)) {
			throw new ViewException(sprintf('"%s" is not a valid component key.', $key));
		}

		$class = $this->services->components->get($key);
		$data  = [...$props, 'props' => $props, 'slot' => $slot, 'slots' => $slots];
		$view  = "components/{$key}";

		if ($class !== null) {
			$component = $this->services->factory->make($class, $props);

			if (! $component->shouldRender()) {
				return '';
			}

			$view = $component->template() ?? $view;
			$data = [...$props, ...$component->data(), 'component' => $component, 'props' => $props, 'slot' => $slot, 'slots' => $slots];
		}

		$file = $this->finder->find($view) ?? throw ViewNotFound::forNames([$view]);

		return $this->renderFile($view, $file, $data, $context);
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

	/**
	 * Returns the component keys of the templates in a folder, including
	 * subfolders (`cards/post`).
	 *
	 * @return list<string>
	 */
	private static function templateKeys(string $folder): array
	{
		if (! is_dir($folder)) {
			return [];
		}

		$keys = [];

		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)) as $file) {
			if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
				$key = str_replace('\\', '/', substr($file->getPathname(), strlen($folder) + 1, -4));

				if (ViewFinder::isValidName($key)) {
					$keys[] = $key;
				}
			}
		}

		return $keys;
	}
}
