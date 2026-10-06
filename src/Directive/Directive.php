<?php

/**
 * Directive base.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive;

use Override;
use Blush\Translation\DomainTranslator;
use Blush\View\Renderable;
use Blush\View\ViewContext;

/**
 * A directive (D-026, D-532): what content says, such as
 * `:::callout{variant=warning}`, with typed props through constructor
 * promotion. Core, plugins, and the site register directives; themes
 * can't, they give them a look. Its template (`directives/{key}`) gets it
 * as `$directive`, so its props and computed values are
 * `$directive->label` and `$directive->heading()`.
 *
 * Every directive has a class (D-534), which renders it by default
 * (D-382): `render()` returns its HTML, or the template file it ships
 * with (`view()`), or `null` when it has no markup of its own and needs a
 * template in the theme chain or the site's views. A template there wins over it, so
 * themes restyle a directive by giving it a template.
 *
 * ```php
 * final class Tabs extends Directive
 * {
 *     public const DirectiveContent CONTENT = DirectiveContent::Blocks;
 *
 *     public const ?DirectiveKind KIND = DirectiveKind::Container;
 *
 *     public function __construct(
 *         public readonly string $title,
 *         public readonly int $columns = 2
 *     ) {}
 *
 *     public function render(): DirectiveView
 *     {
 *         return $this->view(__DIR__ . '/views/tabs.php');
 *     }
 * }
 * ```
 *
 * The directive is built through the container, so its constructor can
 * also ask for services. Props given as strings (from Markdown) are cast
 * to a parameter's `int`, `float`, `bool`, or backed enum type.
 *
 * Every directive has the `variant` prop too (D-266): the variants it
 * declares are its `VARIANTS`, `$directive->variant` names the one it
 * renders (`'default'` for none), and a variant adds its modifier
 * (`directive-callout--warning`). So no directive takes a `variant`
 * parameter of its own. Its main content is `content()`; a directive can
 * name it for its role, such as a figure's `caption()`.
 */
abstract class Directive extends Renderable
{
	/**
	 * What the directive wraps, which decides how the admin's inserter
	 * writes it in Markdown (D-172).
	 */
	public const DirectiveContent CONTENT = DirectiveContent::None;

	/**
	 * How it's written in Markdown (D-531), the only form it works in: a
	 * container (`:::name`), a leaf on a line of its own (`::name`), or
	 * inline, inside a sentence (`:name[…]`). Every directive declares it
	 * (D-534): one left `null` can't be registered. Only a directive that
	 * wraps blocks is a container.
	 */
	public const ?DirectiveKind KIND = null;

	/**
	 * The variants it declares, by name, besides Default (D-266). Their
	 * text is in the directive's namespace's catalog.
	 *
	 * @var list<string>
	 */
	public const array VARIANTS = [];

	/**
	 * What a container holds, when it holds only some things (D-314): the
	 * keys the admin's inserter uses, `image` for a Markdown image or a
	 * directive's full name. Empty for anything. The admin offers only
	 * these inside it, and the site renders only these (D-529).
	 *
	 * @var list<string>
	 */
	public const array HOLDS = [];

	/**
	 * The BEM block's prefix.
	 */
	protected const string BLOCK = 'directive';

	/**
	 * The variant it renders: a variant's name, or `'default'`.
	 */
	public private(set) string $variant = Variant::DEFAULT;

	/**
	 * The variant it renders, or `null` for Default.
	 */
	private ?Variant $variantInfo = null;

	/**
	 * Gives the directive its name, its props, its content, the theme's
	 * translator, the render it's part of, and the variant it renders
	 * (`null` for Default). Called by `Views` when it renders one.
	 *
	 * @internal
	 * @param array<string, mixed> $props
	 */
	final public function attach(
		DirectiveName $name,
		array $props,
		string $content = '',
		?DomainTranslator $translator = null,
		?ViewContext $context = null,
		?Variant $variant = null
	): void {
		$this->attachRenderable($name->name, $props, $content, $translator, $context);

		$this->variantInfo = $variant;
		$this->variant     = $variant->name ?? Variant::DEFAULT;
	}

	/**
	 * Returns whether it renders a variant: `isVariant('warning')`.
	 */
	public function isVariant(string $name): bool
	{
		return $this->variant === $name;
	}

	/**
	 * Returns the directive's own markup, used when the theme chain has
	 * no template for it: its HTML, the template file it ships with
	 * (`view()`), or `null` when it has none and needs a theme's
	 * template. A string is printed as it is, so escape what goes in it
	 * (`attributes()` and `html()` do).
	 */
	abstract public function render(): string|DirectiveView|null;

	/**
	 * Returns its variant's modifier first, then its own.
	 *
	 * @inheritDoc
	 */
	#[Override]
	protected function blockModifiers(): array
	{
		return $this->variantInfo === null ? $this->modifiers() : [$this->variantInfo->modifier(), ...$this->modifiers()];
	}

	/**
	 * Returns a template file the directive ships with, for `render()`.
	 *
	 * @param string $file The template's absolute path.
	 */
	protected function view(string $file, mixed ...$data): DirectiveView
	{
		/** @var array<string, mixed> $data */
		return new DirectiveView($file, $data);
	}
}
