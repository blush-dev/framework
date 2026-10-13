<?php

/**
 * Block base.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Block;

use Override;
use Blush\Translation\Messages;
use Blush\View\Renderable;
use Blush\View\ViewContext;

/**
 * A block (D-026, D-532): what content says, such as
 * `:::callout{variant=warning}`, with typed props through constructor
 * promotion. Core, plugins, and the site register blocks; themes
 * can't, they give them a look. Its template (`blocks/{key}`) gets it
 * as `$block`, so its props and computed values are
 * `$block->label` and `$block->heading()`.
 *
 * Every block has a class (D-534), which renders it by default
 * (D-382): `render()` returns its HTML, or the template file it ships
 * with (`view()`), or `null` when it has no markup of its own and needs a
 * template in the theme chain or the site's views. A template there wins over it, so
 * themes restyle a block by giving it a template.
 *
 * ```php
 * final class Tabs extends Block
 * {
 *     public const BlockContent CONTENT = BlockContent::Blocks;
 *
 *     public const ?BlockKind KIND = BlockKind::Container;
 *
 *     public function __construct(
 *         public readonly string $title,
 *         public readonly int $columns = 2
 *     ) {}
 *
 *     public function render(): BlockView
 *     {
 *         return $this->view(__DIR__ . '/views/tabs.php');
 *     }
 * }
 * ```
 *
 * The block is built through the container, so its constructor can
 * also ask for services. Props given as strings (from Markdown) are cast
 * to a parameter's `int`, `float`, `bool`, or backed enum type.
 *
 * Every block has the `variant` prop too (D-266): the variants it
 * declares are its `VARIANTS`, `$block->variant` names the one it
 * renders (`'default'` for none), and a variant adds its modifier
 * (`block-callout--warning`). So no block takes a `variant`
 * parameter of its own. Its main content is `content()`; a block can
 * name it for its role, such as a figure's `caption()`.
 */
abstract class Block extends Renderable
{
	/**
	 * What the block wraps, which decides how the admin's inserter
	 * writes it in Markdown (D-172).
	 */
	public const BlockContent CONTENT = BlockContent::None;

	/**
	 * How it's written in Markdown (D-531), the only form it works in: a
	 * container (`:::name`), a leaf on a line of its own (`::name`), or
	 * inline, inside a sentence (`:name[…]`). Every block declares it
	 * (D-534): one left `null` can't be registered. Only a block that
	 * wraps blocks is a container.
	 */
	public const ?BlockKind KIND = null;

	/**
	 * The variants it declares, by name, besides Default (D-266). Their
	 * text is in the block's namespace's catalog.
	 *
	 * @var list<string>
	 */
	public const array VARIANTS = [];

	/**
	 * What a container holds, when it holds only some things (D-314): the
	 * keys the admin's inserter uses, `image` for a Markdown image or a
	 * block's full name. Empty for anything. The admin offers only
	 * these inside it, and the site renders only these (D-529).
	 *
	 * @var list<string>
	 */
	public const array HOLDS = [];

	/**
	 * The BEM block's prefix.
	 */
	protected const string BLOCK = 'block';

	/**
	 * The variant it renders: a variant's name, or `'default'`.
	 */
	public private(set) string $variant = Variant::DEFAULT;

	/**
	 * The container block it's directly in, in Markdown (D-708), or
	 * `null`: a playlist, for its tracks.
	 */
	public private(set) ?BlockName $container = null;

	/**
	 * The variant it renders, or `null` for Default.
	 */
	private ?Variant $variantInfo = null;

	/**
	 * Gives the block its name, its props, its content, the theme's
	 * translator, the render it's part of, the variant it renders
	 * (`null` for Default), and the container block it's in.
	 * Called by `Views` when it renders one.
	 *
	 * @internal
	 * @param array<string, mixed> $props
	 */
	final public function attach(
		BlockName $name,
		array $props,
		string $content = '',
		?Messages $messages = null,
		?ViewContext $context = null,
		?Variant $variant = null,
		?BlockName $container = null
	): void {
		$this->attachRenderable($name->name, $props, $content, $messages, $context);

		$this->variantInfo = $variant;
		$this->variant     = $variant->name ?? Variant::DEFAULT;
		$this->container   = $container;
	}

	/**
	 * Returns whether it renders a variant: `isVariant('warning')`.
	 */
	public function isVariant(string $name): bool
	{
		return $this->variant === $name;
	}

	/**
	 * Returns whether it's directly in a container block (D-708),
	 * named in full or, for a core one, by its short name: `isIn('playlist')`.
	 */
	public function isIn(string $name): bool
	{
		return $this->container !== null && (string) $this->container === (string) BlockName::parse($name);
	}

	/**
	 * Returns the block's own markup, used when the theme chain has
	 * no template for it: its HTML, the template file it ships with
	 * (`view()`), or `null` when it has none and needs a theme's
	 * template. A string is printed as it is, so escape what goes in it
	 * (`attributes()` and `html()` do).
	 */
	abstract public function render(): string|BlockView|null;

	/**
	 * Returns its variant's modifier first, then its own.
	 *
	 * @inheritDoc
	 */
	#[Override]
	protected function classModifiers(): array
	{
		return $this->variantInfo === null ? $this->modifiers() : [$this->variantInfo->modifier(), ...$this->modifiers()];
	}

	/**
	 * Returns a template file the block ships with, for `render()`.
	 *
	 * @param string $file The template's absolute path.
	 */
	protected function view(string $file, mixed ...$data): BlockView
	{
		/** @var array<string, mixed> $data */
		return new BlockView($file, $data);
	}
}
