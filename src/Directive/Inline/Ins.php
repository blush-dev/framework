<?php

/**
 * Insertion directive.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive\Inline;

use Override;
use Blush\Directive\Directive;
use Blush\Directive\DirectiveContent;
use Blush\Directive\DirectiveView;
use Blush\Core\Framework;
use Blush\Directive\DirectiveKind;

/**
 * Text added after the fact, the pair to Markdown's `~~deleted~~`
 * (D-305): `:ins[now free]{datetime=2026-10-06}`. `datetime` must be a
 * real date, or a date and time (with an optional time zone), as HTML
 * requires of `<ins>`; otherwise the element has none. `cite` is a URL
 * that explains the change.
 */
final class Ins extends Directive
{
	/**
	 * @inheritDoc
	 */
	public const DirectiveContent CONTENT = DirectiveContent::Text;

	/**
	 * @inheritDoc
	 */
	public const ?DirectiveKind KIND = DirectiveKind::Inline;

	/**
	 * A date, or a date and time.
	 */
	private const string DATETIME = '/^(\d{4})-(\d{2})-(\d{2})(?:[T ]([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d(?:\.\d{1,3})?)?(?:Z|[+-]\d{2}:?\d{2})?)?$/';

	/**
	 * The valid `datetime`, or `null`.
	 */
	public readonly ?string $machine;

	public function __construct(
		public readonly string $datetime = '',
		public readonly string $cite = '',
		public readonly string $label = ''
	) {
		$value = trim($datetime);

		$this->machine = preg_match(self::DATETIME, $value, $parts) === 1
			&& checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : null;
	}

	/**
	 * Returns the inserted text, as HTML: the content, else the label
	 * escaped.
	 */
	public function text(): string
	{
		return $this->contentOr($this->label);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function rootAttributes(): array
	{
		return ['datetime' => $this->machine, 'cite' => trim($this->cite)];
	}

	/**
	 * Renders the framework's template for it, `resources/directives/ins.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): DirectiveView
	{
		return $this->view(Framework::path('resources/directives/ins.php'));
	}
}
