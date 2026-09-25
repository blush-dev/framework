<?php

/**
 * Template.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use BackedEnum;
use DateTimeInterface;
use IntlDateFormatter;
use Stringable;
use Blush\Content\Entry\Entry;
use Blush\Data\InvalidData;
use Blush\Routing\UrlGenerationException;

/**
 * `$this` inside a template: the small API templates use to build pages
 * (see `theming.md`). A template file runs in an isolated scope with its
 * data as variables, and can reach only this class's public methods.
 *
 * ```php
 * <?php $this->layout('base') ?>
 *
 * <article class="entry">
 *     <h1><?= e($entry->title) ?></h1>
 *     <?= raw($entry->body()) ?>
 * </article>
 * ```
 */
final class Template
{
	/**
	 * The layout this template asked for, and its data.
	 *
	 * @var ?array{string, array<string, mixed>}
	 */
	private ?array $layout = null;

	/**
	 * Sections being captured, innermost last.
	 *
	 * @var list<string>
	 */
	private array $capturing = [];

	public function __construct(
		private readonly Views $views,
		private readonly ViewContext $context
	) {}

	/**
	 * Wraps this template in a layout (`layouts/{name}`, unless the name
	 * has a folder), with extra data for it. The template's output
	 * becomes the layout's `content` section.
	 */
	public function layout(string $name, mixed ...$data): void
	{
		$this->layout = [str_contains($name, '/') ? $name : "layouts/{$name}", self::named($data)];
	}

	/**
	 * Returns the layout this template asked for, and its data. Used by
	 * `Views` after the template runs.
	 *
	 * @internal
	 * @return ?array{string, array<string, mixed>}
	 */
	public function requestedLayout(): ?array
	{
		return $this->layout;
	}

	/**
	 * Starts capturing a section.
	 */
	public function start(string $name): void
	{
		$this->capturing[] = $name;
		ob_start();
	}

	/**
	 * Stops capturing the current section and stores it.
	 *
	 * @throws ViewException When no section is being captured.
	 */
	public function stop(): void
	{
		$name = array_pop($this->capturing) ?? throw new ViewException('stop() was called without start().');

		$this->context->setSection($name, (string) ob_get_clean());
	}

	/**
	 * Returns the sections still being captured, innermost last. Used by
	 * `Views` to catch a missing `stop()`.
	 *
	 * @internal
	 * @return list<string>
	 */
	public function openSections(): array
	{
		return $this->capturing;
	}

	/**
	 * Returns a section's content, or a default. Sections hold rendered
	 * HTML, so print them as they are: `<?= $this->section('content') ?>`.
	 */
	public function section(string $name, string $default = ''): string
	{
		return $this->context->section($name) ?? $default;
	}

	/**
	 * Returns whether a section has been set.
	 */
	public function hasSection(string $name): bool
	{
		return $this->context->section($name) !== null;
	}

	/**
	 * Renders a partial (`parts/header`) with named data and returns it.
	 *
	 * @throws ViewException
	 */
	public function insert(string $name, mixed ...$data): string
	{
		return $this->views->partial($name, self::named($data), $this->context);
	}

	/**
	 * Translates a message from the theme's catalogs with named
	 * parameters: `$this->t('reading_time', minutes: 5)` (D-028).
	 *
	 * @throws InvalidData When a catalog can't be parsed.
	 */
	public function t(string $key, mixed ...$params): string
	{
		return $this->views->translator->translate($key, self::named($params), 'theme');
	}

	/**
	 * Returns the page's `Head`.
	 */
	public function head(): Head
	{
		return $this->context->head;
	}

	/**
	 * Returns a theme asset's URL (versioned), resolved through the
	 * theme chain, or `''` when no theme has it.
	 */
	public function asset(string $path): string
	{
		return $this->views->chain->assetUrl($path) ?? '';
	}

	/**
	 * Returns an entry's URL path, or `''` when it has none.
	 */
	public function permalink(Entry $entry): string
	{
		return $this->views->urls->entry($entry) ?? '';
	}

	/**
	 * Returns a named route's URL.
	 *
	 * @param  array<string, BackedEnum|Stringable|scalar|null> $params
	 * @throws UrlGenerationException
	 */
	public function route(string $name, array $params = [], bool $absolute = false): string
	{
		return $this->views->router->to($name, $params, $absolute);
	}

	/**
	 * Returns the term entries an entry has in a taxonomy, in the order
	 * front matter lists them. Terms that aren't published are left out.
	 *
	 * @return list<Entry>
	 */
	public function terms(Entry $entry, string $taxonomy): array
	{
		$terms = [];

		foreach ($entry->terms($taxonomy) as $slug) {
			$term = $this->views->content->term($taxonomy, $slug);

			if ($term !== null && $term->isPublished() && $term->isRoutable()) {
				$terms[] = $term;
			}
		}

		return $terms;
	}

	/**
	 * Formats a date in the site's locale and timezone: `full`, `long`,
	 * `medium`, or `short`, or else an ICU pattern (`'MMMM y'`).
	 */
	public function date(DateTimeInterface $date, string $format = 'long'): string
	{
		$style     = ['full' => IntlDateFormatter::FULL, 'long' => IntlDateFormatter::LONG, 'medium' => IntlDateFormatter::MEDIUM, 'short' => IntlDateFormatter::SHORT][$format] ?? null;
		$formatter = new IntlDateFormatter(
			$this->views->translator->locale(),
			$style ?? IntlDateFormatter::NONE,
			IntlDateFormatter::NONE,
			$this->views->app->timezone,
			null,
			$style === null ? $format : null
		);

		$formatted = $formatter->format($date);

		return $formatted === false ? $date->format('Y-m-d') : $formatted;
	}

	/**
	 * Returns the `<body>` classes as an attribute value (escape it with
	 * `attr()`).
	 */
	public function bodyClass(): string
	{
		return implode(' ', $this->context->classes());
	}

	/**
	 * Keeps only named values, since data becomes template variables.
	 *
	 * @param  array<array-key, mixed> $data
	 * @return array<string, mixed>
	 * @throws ViewException When a value isn't named.
	 */
	private static function named(array $data): array
	{
		foreach (array_keys($data) as $key) {
			if (! is_string($key)) {
				throw new ViewException('Pass view data by name, such as insert(\'parts/card\', entry: $entry).');
			}
		}

		/** @var array<string, mixed> $data */
		return $data;
	}
}
