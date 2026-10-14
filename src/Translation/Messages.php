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
 * The one way to write text (D-784, D-816), from core, a plugin, or a
 * theme, in a class or a template: `$messages->t($key, $text)`. A class
 * asks for it in its constructor, and the container gives the one for
 * the extension the class belongs to, by its namespace (`ClassDomains`),
 * or core's; a template has it as `$messages`, for the domains of the
 * code that wrote the file; service providers, components, and
 * blocks have it as `$this->messages`.
 *
 * ```php
 * public function __construct(private Messages $messages) {}
 *
 * public function label(): Text
 * {
 *     return $this->messages->t('tools.export.label', 'Export');
 * }
 * ```
 *
 * `t()` returns a `Text`, translated when it's printed, in the current
 * language (`CurrentLocale`), or where it's shown, in that screen's.
 */
final readonly class Messages
{
	/**
	 * The domains its text is looked up in, in order, core's last.
	 *
	 * @var list<string>
	 */
	public array $domains;

	/**
	 * The domain its text belongs to: the first it's looked up in.
	 */
	public string $domain;

	/**
	 * @param string|list<string> $domains Its domain, or the domains to look in, in order (core's is added last).
	 */
	public function __construct(
		string|array $domains = Translator::CORE,
		private ?Translator $translator = null,
		private ?CurrentLocale $locale = null
	) {
		$domains       = is_string($domains) ? [$domains] : $domains;
		$this->domains = array_values(array_unique([...$domains, Translator::CORE]));
		$this->domain  = $this->domains[0];
	}

	/**
	 * Returns a message: its key, the text written beside it (an ICU
	 * message, shown when no catalog has the key; without it, the key
	 * shows), and named parameters, with `context:` a note for
	 * translators, never a parameter. It's translated when it's printed.
	 *
	 * A parameter marked with `raw()` (any `SafeHtml`) is HTML (D-559): the
	 * message prints as HTML, with that parameter as given and the
	 * message's own text and every other parameter escaped, so
	 * `e($messages->t('by', names: raw($links)))` keeps the links.
	 *
	 * @throws TranslationException When a parameter isn't named.
	 */
	public function t(string $key, ?string $text = null, mixed ...$params): Text
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

		return new Text($key, $text, $this->domain, $named, is_string($context) ? $context : null, $this->domains, $this->translator, $this->locale);
	}

	/**
	 * Returns a message stored with `Text::toArray()` (D-836), translated
	 * when it's shown, as its own domain's text, or `null` when the value
	 * isn't one (such as text stored as a sentence before it was a
	 * message, which the caller shows as it is).
	 */
	public function restore(mixed $data): ?Text
	{
		if (! is_array($data) || ! is_string($data['key'] ?? null) || $data['key'] === '') {
			return null;
		}

		$text   = $data['text'] ?? null;
		$domain = $data['domain'] ?? null;
		$params = [];

		foreach (is_array($data['params'] ?? null) ? $data['params'] : [] as $name => $value) {
			if (is_string($name)) {
				$params[$name] = is_array($value) ? $this->restore($value) : $value;
			}
		}

		$domain = is_string($domain) && $domain !== '' ? $domain : Translator::CORE;

		return new Text($data['key'], is_string($text) ? $text : null, $domain, $params, translator: $this->translator, locale: $this->locale);
	}

	/**
	 * Returns whether a key has a message in a catalog.
	 */
	public function has(string $key): bool
	{
		return $this->translator?->has($key, $this->domains, $this->locale?->get()) ?? false;
	}

	/**
	 * Returns one for other domains, with the same translator.
	 *
	 * @param string|list<string> $domains
	 */
	public function for(string|array $domains): self
	{
		return new self($domains, $this->translator, $this->locale);
	}
}
