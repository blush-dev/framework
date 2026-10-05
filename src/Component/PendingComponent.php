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
 * <?= $template->component('card', title: 'Hi')
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
	 */
	public function __construct(
		private readonly Views $views,
		private readonly ViewContext $context,
		private readonly string $name,
		private readonly array $props
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
	 * Renders the component.
	 *
	 * @throws ViewException
	 */
	public function render(): string
	{
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
