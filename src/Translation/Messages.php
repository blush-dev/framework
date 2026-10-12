<?php

/**
 * Messages.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Translation;

/**
 * The text of one domain (D-784), for any class that writes text: ask
 * for it in the constructor, and the container gives the one for the
 * extension the class belongs to, by its namespace (`ClassDomains`), or
 * core's for a class outside every extension.
 *
 * ```php
 * public function __construct(private Messages $messages) {}
 *
 * public function label(): Text
 * {
 *     return $this->messages->t('tools.export.label', 'Export');
 * }
 * ```
 */
final readonly class Messages
{
	public function __construct(public string $domain = Translator::CORE)
	{}

	/**
	 * Returns a message to translate when it's shown: its key, the text
	 * written beside it, and named parameters, with `context:` a note for
	 * translators, never a parameter.
	 *
	 * @throws TranslationException When a parameter isn't named.
	 */
	public function t(string $key, ?string $text = null, mixed ...$params): Text
	{
		return self::text($this->domain, $key, $text, $params);
	}

	/**
	 * Builds a message for a domain from `t()`'s arguments.
	 *
	 * @param  array<array-key, mixed> $params
	 * @throws TranslationException When a parameter isn't named.
	 */
	public static function text(string $domain, string $key, ?string $text, array $params): Text
	{
		$named = [];

		foreach ($params as $name => $value) {
			if (! is_string($name)) {
				throw new TranslationException(sprintf('Pass the parameters of "%s" by name, such as t(\'%s\', \'…\', count: 3).', $key, $key));
			}

			$named[$name] = $value;
		}

		$context = $named['context'] ?? null;
		unset($named['context']);

		return new Text($key, $text, $domain, $named, is_string($context) ? $context : null);
	}
}
