<?php

/**
 * Registered blocks, rendered from Markdown.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Block;

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
 * Renders the blocks found in Markdown as the registered blocks of the same
 * name (D-026, D-532), with the theme chain of the request being
 * rendered: `:::callout{variant=info}` is the `core/callout` block
 * with `variant` and the block's HTML as its content, and `::acme/tabs`
 * is `acme/tabs`. Only core blocks have
 * short names (D-171). A block's `[label]` is also given as the
 * `label` prop. A registered block's `media` props are resolved like
 * an image's (D-179): `src=/media/clip.mp4` becomes that file's URL.
 * Those and its link props
 * (`#[LinkProp]`) become full URLs when they start with `/`, as
 * Markdown's links do (D-190). A table of contents gets the
 * document's outline as `headings` (D-183). One directly in a
 * registered container block is told which (D-708). An inline block's HTML is
 * trimmed, so a template's line breaks don't add spaces to the sentence.
 * A name no one registered returns `null`, so the block renders as
 * plain content.
 *
 * A container whose block holds only some things (`HOLDS`) renders
 * only those (D-529): the parser leaves out the rest. A registered
 * block works only in the form it's registered as (D-530, D-531):
 * its `kind()`.
 *
 * Blocks rendered this way get a bare context: what they add to the
 * head or the foot themselves doesn't reach the page, but the assets they ask for
 * (`assets()`, or `$template->enqueue()` in their templates) do, and
 * are kept with the body (D-572).
 *
 * The view factory is resolved on first use, since it depends (through
 * the content repository) on the Markdown parser that depends on this.
 */
final readonly class RegisteredBlocks implements BlockRenderer, BlockRules
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
	public function render(ParsedBlock $block): ?string
	{
		$factory = ($this->views)();
		$chain   = $this->themes->current();
		$views   = $factory->forChain($chain);

		$definition = $views->services->blocks->get($block->name);

		if ($definition === null) {
			return null;
		}

		$props = $block->attributes;

		if ($block->label !== '') {
			$props['label'] ??= $block->label;
		}

		if ($block->outline !== []) {
			$props['headings'] = $block->outline;
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

		$container = $block->container === '' ? null : $views->services->blocks->get($block->container)?->name;
		$html      = $views->block($block->name, $props, $block->content, $factory->fragment($block->language), $container);

		// Inside a sentence, a template's surrounding line breaks would
		// show as spaces.
		return $block->kind === BlockKind::Inline ? trim($html) : $html;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function holds(string $container): array
	{
		$definition = ($this->views)()->forChain($this->themes->current())->services->blocks->get($container);

		return array_map(
			fn (string $held): string => $held === 'image' ? $held : $this->fullName($held),
			$definition?->holds() ?? []
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function forms(string $block): ?array
	{
		$definition = ($this->views)()->forChain($this->themes->current())->services->blocks->get($block);

		if ($definition === null) {
			return null;
		}

		return [$definition->kind()];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fullName(string $block): string
	{
		$name = BlockName::parse($block);

		return $name === null ? $block : (string) $name;
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
