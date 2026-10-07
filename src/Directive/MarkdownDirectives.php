<?php

/**
 * Directive directives.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive;

use Closure;
use Override;
use Blush\Container\Attributes\Defer;
use Blush\Core\AppConfig;
use Blush\Field\Fields\MediaField;
use Blush\Markdown\MarkdownConfig;
use Blush\Media\MediaResolver;
use Blush\Theme\ThemeResolver;
use Blush\View\ViewFactory;

/**
 * Renders Markdown directives as the registered directives of the same
 * name (D-026, D-532), with the theme chain of the request being
 * rendered: `:::callout{variant=info}` is the `blush/callout` directive
 * with `variant` and the block's HTML as its content, and `::acme/tabs`
 * is `acme/tabs`. Only core directives have
 * short names (D-171). A directive's `[label]` is also given as the
 * `label` prop. A registered directive's `media` props are resolved like
 * an image's (D-179): `src=/media/clip.mp4` becomes that file's URL.
 * Those and its link props
 * (`#[LinkProp]`) become full URLs when they start with `/`, as
 * Markdown's links do (D-190). A table of contents gets the
 * document's outline as `headings` (D-183). An inline directive's HTML is
 * trimmed, so a template's line breaks don't add spaces to the sentence.
 * A name no one registered returns `null`, so the directive renders as
 * plain content.
 *
 * A container whose directive holds only some things (`HOLDS`) renders
 * only those (D-529): the parser leaves out the rest. A registered
 * directive works only in the form it's registered as (D-530, D-531):
 * its `kind()`.
 *
 * Directives rendered this way get a bare context: what they add to the
 * head or the foot themselves doesn't reach the page, but the assets they ask for
 * (`assets()`, or `$template->enqueue()` in their templates) do, and
 * are kept with the body (D-572).
 *
 * The view factory is resolved on first use, since it depends (through
 * the content repository) on the Markdown parser that depends on this.
 */
final readonly class MarkdownDirectives implements DirectiveRenderer, DirectiveRules
{
	/**
	 * @param Closure(): ViewFactory $views
	 */
	public function __construct(
		#[Defer(ViewFactory::class)] private Closure $views,
		private ThemeResolver $themes,
		private MediaResolver $media,
		private MarkdownConfig $markdown,
		private AppConfig $app
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function render(ParsedDirective $directive): ?string
	{
		$factory = ($this->views)();
		$chain   = $this->themes->current();
		$views   = $factory->forChain($chain);

		$definition = $views->services->directives->get($directive->name);

		if ($definition === null) {
			return null;
		}

		$props = $directive->attributes;

		if ($directive->label !== '') {
			$props['label'] ??= $directive->label;
		}

		if ($directive->outline !== []) {
			$props['headings'] = $directive->outline;
		}

		foreach ($definition->props() as $field) {
			$value = $props[$field->name] ?? null;

			if ($field instanceof MediaField && is_string($value)) {
				$props[$field->name] = $this->absolute($this->media->resolve($value)->url ?? $value);
			}
		}

		foreach ($definition->links() as $name) {
			$value = $props[$name] ?? null;

			if (is_string($value)) {
				$props[$name] = $this->absolute(trim($value));
			}
		}

		$html = $views->directive($directive->name, $props, $directive->content, $factory->fragment($directive->language));

		// Inside a sentence, a template's surrounding line breaks would
		// show as spaces.
		return $directive->kind === DirectiveKind::Inline ? trim($html) : $html;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function holds(string $container): array
	{
		$definition = ($this->views)()->forChain($this->themes->current())->services->directives->get($container);

		return array_map(
			fn (string $held): string => $held === 'image' ? $held : $this->fullName($held),
			$definition?->holds() ?? []
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function forms(string $directive): ?array
	{
		$definition = ($this->views)()->forChain($this->themes->current())->services->directives->get($directive);

		if ($definition === null) {
			return null;
		}

		return [$definition->kind()];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fullName(string $directive): string
	{
		$name = DirectiveName::parse($directive);

		return $name === null ? $directive : (string) $name;
	}

	/**
	 * Returns a root-relative URL (`/media/a.mp3`) as a full URL on the
	 * site, as Markdown's links are (`MarkdownConfig::$absoluteLinks`), so
	 * it works in feeds; anything else as given.
	 */
	private function absolute(string $url): string
	{
		return $this->markdown->absoluteLinks && str_starts_with($url, '/') && ! str_starts_with($url, '//')
			? $this->app->absoluteUrl($url)
			: $url;
	}
}
