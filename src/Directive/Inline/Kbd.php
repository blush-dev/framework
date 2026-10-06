<?php

/**
 * Keyboard input directive.
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
 * Keyboard input (D-175, D-180): `:kbd[Ctrl+S]`. A label joined with `+`
 * is a key combination, so each key gets its own `<kbd>` inside the
 * outer one, as HTML recommends; `$keys` lists them (one entry for a
 * single key, none when the directive is used from a template with
 * content instead of a label).
 */
final class Kbd extends Directive
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
	 * The keys, in order.
	 *
	 * @var list<string>
	 */
	public readonly array $keys;

	public function __construct(public readonly string $label = '')
	{
		$this->keys = self::keys($label);
	}

	/**
	 * Returns the keys in a label. A `+` with nothing on one side (such as
	 * in `Ctrl++`) doesn't split, so the label stays one key.
	 *
	 * @return list<string>
	 */
	private static function keys(string $label): array
	{
		$label = trim($label);

		if ($label === '') {
			return [];
		}

		$keys = preg_split('/\s*\+\s*/', $label) ?: [];

		return count($keys) > 1 && ! in_array('', $keys, true) ? $keys : [$label];
	}

	/**
	 * Returns a single key's text, as HTML: the content, else the label
	 * escaped. (A combination is `keys`.)
	 */
	public function text(): string
	{
		return $this->contentOr($this->label);
	}

	/**
	 * Returns whether it's a key combination, with each key in `keys`.
	 */
	public function isCombination(): bool
	{
		return count($this->keys) > 1;
	}

	/**
	 * Renders the framework's template for it, `resources/directives/kbd.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): DirectiveView
	{
		return $this->view(Framework::path('resources/directives/kbd.php'));
	}
}
