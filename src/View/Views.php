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
use Throwable;
use Blush\Theme\ThemeAssets;
use Blush\Theme\ThemeChain;
use Blush\Theme\ThemeSettings;
use Blush\Theme\Token\TokenSet;
use Blush\Translation\Translator;
use Blush\View\Component\Slots;

/**
 * Renders plain PHP templates (D-009) for one theme chain.
 *
 * A template runs in an isolated scope: its data become variables, and
 * `$this` is a `Template`, which exposes only its public API. When a
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
 * assets, settings, and tokens; the services templates reach through
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
		public ThemeSettings $settings = new ThemeSettings(),
		public TokenSet $tokens = new TokenSet()
	) {
		$this->chain = $assets->chain;
	}

	/**
	 * Returns whether a view exists.
	 *
	 * @throws ViewException When the name isn't valid.
	 */
	public function exists(string $name): bool
	{
		return $this->finder->find($name) !== null;
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
	 * Renders a partial with the shared data plus its own.
	 *
	 * @param  array<string, mixed> $data
	 * @throws ViewException
	 */
	public function partial(string $name, array $data, ViewContext $context): string
	{
		$file = $this->finder->find($name) ?? throw ViewNotFound::forNames([$name]);

		return $this->renderFile($name, $file, $data, $context);
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
	 * Returns a function that includes a template file with `$this` bound
	 * to the template and no class scope, so the file sees its data and
	 * `Template`'s public API only. (`$__file` stays in scope, and a data
	 * key named `__data` or `__file` is ignored.)
	 *
	 * @return Closure(string, array<string, mixed>): void
	 */
	private static function includer(Template $template): Closure
	{
		$include = Closure::bind(
			function (string $__file, array $__data): void {
				extract($__data, EXTR_SKIP);
				unset($__data);

				include func_get_arg(0);
			},
			$template,
			null
		);

		/** @var Closure(string, array<string, mixed>): void $include */
		return $include;
	}
}
