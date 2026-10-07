<?php

/**
 * Page rendering event.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Events;

use Psr\Http\Message\ServerRequestInterface;
use Blush\Content\Entry\Entry;
use Blush\Content\Http\ContentPage;
use Blush\Http\Status;
use Blush\Theme\ThemeChain;
use Blush\View\Foot;
use Blush\View\Head;
use Blush\View\ViewContext;

/**
 * Dispatched once per themed page, content or error, just before its
 * first template renders (D-571), with its head already filled in by
 * Blush (title, canonical URL, OpenGraph, feeds, the theme's files) and
 * its `<body>` classes. Listeners can load assets on the pages that need
 * them, add to the head or the foot (D-578), or add classes:
 *
 * ```php
 * $listeners->listen(PageRendering::class, function (PageRendering $event): void {
 *     if ($event->page?->type?->name === 'gallery') {
 *         $event->enqueue('acme/gallery');
 *     }
 * });
 * ```
 *
 * Templates render after it, so a template's own value for the same
 * head tag wins.
 */
final readonly class PageRendering
{
	/**
	 * The page's head.
	 */
	public Head $head;

	/**
	 * The end of the page's `<body>`.
	 */
	public Foot $foot;

	/**
	 * @param ?ContentPage $page   The content page, or `null` on an error page.
	 * @param ?Entry       $entry  The page's entry (an error page's, when the site has one), or `null`.
	 * @param ?Status      $status An error page's status, or `null` on a content page.
	 */
	public function __construct(
		private ViewContext $context,
		public ServerRequestInterface $request,
		public ThemeChain $chain,
		public ?ContentPage $page = null,
		public ?Entry $entry = null,
		public ?Status $status = null
	) {
		$this->head = $context->markup->head;
		$this->foot = $context->markup->foot;
	}

	/**
	 * Asks for registered assets by handle, such as `acme/gallery`.
	 */
	public function enqueue(string ...$handles): void
	{
		$this->context->markup->enqueue(...$handles);
	}

	/**
	 * Takes back assets asked for.
	 */
	public function dequeue(string ...$handles): void
	{
		$this->context->markup->dequeue(...$handles);
	}

	/**
	 * Adds `<body>` classes.
	 */
	public function addClass(string ...$classes): void
	{
		$this->context->addClass(...$classes);
	}

	/**
	 * Returns whether it's an error page.
	 */
	public function isError(): bool
	{
		return $this->status !== null;
	}

	/**
	 * Returns the page's URL path.
	 */
	public function path(): string
	{
		return $this->context->path;
	}

	/**
	 * Returns the page's locale.
	 */
	public function locale(): string
	{
		return $this->context->locale;
	}
}
