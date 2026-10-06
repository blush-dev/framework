<?php

/**
 * Pending directive.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive;

use Override;
use Stringable;
use Blush\View\SafeHtml;
use Blush\View\ViewContext;
use Blush\View\ViewException;
use Blush\View\Views;

/**
 * What `$template->directive()` returns: a directive drawn by a template
 * rather than written in content (D-532). It renders when printed, so its
 * content chains on first:
 *
 * ```php
 * <?= $template->directive('callout', variant: 'tip')->content('<p>Body</p>') ?>
 * ```
 */
final class PendingDirective implements SafeHtml
{
	/**
	 * The content's HTML.
	 */
	private string $content = '';

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
	 * Gives the directive its content, as HTML: what a container holds in
	 * Markdown.
	 */
	public function content(Stringable|string $html): self
	{
		$this->content = (string) $html;

		return $this;
	}

	/**
	 * Renders the directive.
	 *
	 * @throws ViewException
	 */
	public function render(): string
	{
		return $this->views->directive($this->name, $this->props, $this->content, $this->context);
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
