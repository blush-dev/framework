<?php

/**
 * Pending component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

use Override;
use Stringable;
use Blush\View\SafeHtml;
use Blush\View\ViewContext;
use Blush\View\ViewException;
use Blush\View\Views;

/**
 * What `$template->component()` returns: a component waiting for its slots.
 * It renders when printed, so slots chain on first:
 *
 * ```php
 * <?= $template->component('acme/card', title: 'Hi')
 *     ->content('<p>Body</p>')
 *     ->slot('footer', $template->section('card-footer')) ?>
 * ```
 */
final class PendingComponent implements SafeHtml
{
	/**
	 * The default slot's HTML.
	 */
	private string $content = '';

	/**
	 * Named slots' HTML.
	 *
	 * @var array<string, string>
	 */
	private array $slots = [];

	/**
	 * @param array<string, mixed> $props
	 * @param bool                 $optional Whether it renders `''` when the component doesn't exist (`componentIf()`, D-826).
	 */
	public function __construct(
		private readonly Views $views,
		private readonly ViewContext $context,
		private readonly string $name,
		private readonly array $props,
		private readonly bool $optional = false
	) {}

	/**
	 * Fills the default slot (`$slot`) with HTML.
	 */
	public function content(Stringable|string $html): self
	{
		$this->content = (string) $html;

		return $this;
	}

	/**
	 * Fills a named slot (`$slots->{name}`) with HTML.
	 */
	public function slot(string $name, Stringable|string $html): self
	{
		$this->slots[$name] = (string) $html;

		return $this;
	}

	/**
	 * Renders the component, or returns `''` when it's optional and
	 * doesn't exist. An optional component's name must still be valid.
	 *
	 * @throws ViewException
	 */
	public function render(): string
	{
		if ($this->optional && ComponentName::parse($this->name) !== null && ! $this->views->hasComponent($this->name)) {
			return '';
		}

		return $this->views->component($this->name, $this->props, $this->content, new Slots($this->slots), $this->context);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function __toString(): string
	{
		return $this->render();
	}
}
