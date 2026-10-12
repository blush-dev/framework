<?php

/**
 * Text.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Translation;

/**
 * A message to translate when it's shown, not when it's written (D-784):
 * what a provider or a service hands a registry, such as a template's
 * label, before anyone knows the locale it'll be read in. It holds the
 * catalog key, the text written beside it in the code (in whatever
 * language the code is written in, shown when no catalog has the key),
 * the domain of the code that wrote it, its named parameters, and a note
 * for translators.
 *
 * It's made by `t()` (a provider's, a `Messages`'), never by hand, and
 * read by `Translator::text()` or `Views::text()`.
 */
final readonly class Text
{
	/**
	 * @param array<string, mixed> $params
	 */
	public function __construct(
		public string $key,
		public ?string $text = null,
		public string $domain = Translator::CORE,
		public array $params = [],
		public ?string $context = null
	) {}
}
