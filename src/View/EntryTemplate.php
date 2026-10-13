<?php

/**
 * Entry template.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use Stringable;
use Blush\Translation\Text;

/**
 * A template someone can choose for a single entry in the editor
 * (D-783), declared by a theme: its view name (the path under `views/`
 * without the extension), what the editor calls it and says it's for,
 * and the types it's offered on, every type when none are named. An
 * entry that chooses it names it in front matter `template`, which
 * comes before the template hierarchy.
 *
 * ```php
 * $registry->register(new EntryTemplate(
 *     'template-compact',
 *     label: $this->messages->t('templates.compact.label', 'Compact List'),
 *     description: $this->messages->t('templates.compact.description', 'Every post on a line of its own.'),
 *     types: ['page']
 * ));
 * ```
 *
 * The label and description are translated where they're shown
 * (`Views::text()`, D-784); plain text is shown as it is.
 */
final readonly class EntryTemplate
{
	/**
	 * @param  list<string> $types The type names it's offered on; none for every type.
	 * @throws ViewException When the name isn't a view name or the label is empty.
	 */
	public function __construct(
		public string $name,
		public string|Stringable $label,
		public string|Stringable $description = '',
		public array $types = []
	) {
		if (! ViewFinder::isValidName($name)) {
			throw new ViewException(sprintf('"%s" isn\'t a view name for a template; name it as under views/, without the extension, such as "template-wide".', $name));
		}

		if (trim($label instanceof Text ? $label->text ?? $label->key : (string) $label) === '') {
			throw new ViewException(sprintf('The "%s" template needs a label.', $name));
		}
	}

	/**
	 * Returns whether it's offered on a type.
	 */
	public function isFor(string $type): bool
	{
		return $this->types === [] || in_array($type, $this->types, true);
	}
}
