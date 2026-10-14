<?php

/**
 * Raw HTML enum.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown;

use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * What a page does with raw HTML written in Markdown, whoever wrote it
 * (`MarkdownConfig::$html`). Who may add HTML in the admin is a separate
 * matter: the `html.*` capabilities (`Html\HtmlAccess`).
 */
enum RawHtml: string
{
	/**
	 * Rendered as written.
	 */
	case Allow = 'allow';

	/**
	 * Rendered, but the tags HTML is always refused (`HtmlRules::REFUSED`)
	 * are shown as text, and links with an unsafe address
	 * (`javascript:` and the like) lose it.
	 */
	case Filter = 'filter';

	/**
	 * Shown as text.
	 */
	case Escape = 'escape';

	/**
	 * Returns what the choice does, as the Writing screen says it.
	 */
	public function description(Messages $messages): Text
	{
		return match ($this) {
			self::Allow  => $messages->t('markdown.raw_html.allow.description', 'HTML is part of the page, as written.'),
			self::Filter => $messages->t('markdown.raw_html.filter.description', 'HTML is part of the page, but script, frames, forms, and styles show as text, and javascript: links lose their address.'),
			self::Escape => $messages->t('markdown.raw_html.escape.description', 'HTML shows as text, as it was typed.')
		};
	}

	/**
	 * Returns the choice's name, as the Writing screen has it.
	 */
	public function label(Messages $messages): Text
	{
		return match ($this) {
			self::Allow  => $messages->t('markdown.raw_html.allow.label', 'Allowed'),
			self::Filter => $messages->t('markdown.raw_html.filter.label', 'Filtered'),
			self::Escape => $messages->t('markdown.raw_html.escape.label', 'Shown as Text')
		};
	}
}
