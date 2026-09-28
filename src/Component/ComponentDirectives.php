<?php

/**
 * Component directives.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

use Closure;
use Override;
use Blush\Container\Attributes\Defer;
use Blush\Content\Schema\Fields\MediaField;
use Blush\Core\AppConfig;
use Blush\Markdown\Directive;
use Blush\Markdown\DirectiveKind;
use Blush\Markdown\DirectiveRenderer;
use Blush\Markdown\MarkdownConfig;
use Blush\Media\MediaResolver;
use Blush\Theme\ThemeResolver;
use Blush\View\ViewFactory;

/**
 * Renders Markdown directives as the components of the same name (D-026),
 * with the theme chain of the request being rendered: `:::callout{tone=info}`
 * is the `blush/callout` component with `tone` and the block's HTML as
 * `$slot`, and `::acme/tabs` is `acme/tabs`. Only core components have
 * short names (D-171). A directive's `[label]` is also given as the
 * `label` prop. A registered component's `media` props are resolved like
 * an image's, against the entry's folder (D-179): `src=clip.mp4` in a
 * page bundle becomes that file's URL. Those and its link props
 * (`#[LinkProp]`) become full URLs when they start with `/`, as
 * Markdown's links do (D-190). A table of contents gets the
 * document's outline as `headings` (D-183). An inline directive's HTML is
 * trimmed, so a template's line breaks don't add spaces to the sentence.
 * An unknown name returns `null`, so the directive renders as plain
 * content.
 *
 * Components rendered this way get a bare context: what they add to the
 * `Head` doesn't reach the page.
 *
 * The view factory is resolved on first use, since it depends (through
 * the content repository) on the Markdown parser that depends on this.
 */
final readonly class ComponentDirectives implements DirectiveRenderer
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
	public function render(Directive $directive): ?string
	{
		$factory = ($this->views)();
		$views   = $factory->forChain($this->themes->current());

		if (! $views->hasComponent($directive->name)) {
			return null;
		}

		$props = $directive->attributes;

		if ($directive->label !== '') {
			$props['label'] ??= $directive->label;
		}

		if ($directive->outline !== []) {
			$props['headings'] = $directive->outline;
		}

		$definition = $views->services->components->get($directive->name);

		foreach ($definition?->props() ?? [] as $field) {
			$value = $props[$field->name] ?? null;

			if ($field instanceof MediaField && is_string($value)) {
				$props[$field->name] = $this->absolute($this->media->resolve($value, $directive->base)->url ?? $value);
			}
		}

		foreach ($definition?->links() ?? [] as $name) {
			$value = $props[$name] ?? null;

			if (is_string($value)) {
				$props[$name] = $this->absolute(trim($value));
			}
		}

		$html = $views->component($directive->name, $props, $directive->content, new Slots(), $factory->fragment());

		// Inside a sentence, a template's surrounding line breaks would
		// show as spaces.
		return $directive->kind === DirectiveKind::Inline ? trim($html) : $html;
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
