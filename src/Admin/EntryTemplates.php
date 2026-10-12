<?php

/**
 * Entry templates in the editor.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Throwable;
use Blush\Theme\ThemeResolver;
use Blush\View\EntryTemplate;
use Blush\View\ViewFactory;

/**
 * The templates the editor's Template row offers on a type (D-783): the
 * ones declared for it that have a view in the active theme's chain.
 * A chain whose views can't be built offers none.
 */
final readonly class EntryTemplates
{
	public function __construct(
		private ThemeResolver $themes,
		private ViewFactory $views
	) {}

	/**
	 * Returns the templates offered on a type, by label, as the editor
	 * reads them: the name, and the label and description in the site's
	 * language (D-784).
	 *
	 * @return list<array{name: string, label: string, description: string}>
	 */
	public function for(string $type): array
	{
		try {
			$views = $this->views->forChain($this->themes->active());

			return array_map(static fn (EntryTemplate $template): array => [
				'name'        => $template->name,
				'label'       => $views->text($template->label),
				'description' => $views->text($template->description)
			], $views->entryTemplates($type));
		} catch (Throwable) {
			return [];
		}
	}

	/**
	 * Returns whether a name is offered on a type.
	 */
	public function offers(string $type, string $name): bool
	{
		return in_array($name, array_column($this->for($type), 'name'), true);
	}
}
