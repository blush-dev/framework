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

use DateTimeInterface;
use JsonSerializable;
use Override;
use Stringable;
use Blush\View\SafeHtml;

/**
 * A message from `t()` (D-784, D-816): its catalog key, the text written
 * beside it in the code (in whatever language the code is written in,
 * shown when no catalog has the key), the domains it's looked up in (the
 * code's own, then core's), its named parameters, and a note for
 * translators. Nothing is looked up until it's printed or cast to a
 * string, so building one costs nothing: then it's translated in the
 * current language (`CurrentLocale`). Code that knows a better language,
 * such as a screen in its reader's, gives it to `Translator::text()`.
 *
 * It's made by `Messages::t()`, never by hand.
 */
final readonly class Text implements Stringable, JsonSerializable
{
	/**
	 * The domains it's looked up in, in order.
	 *
	 * @var list<string>
	 */
	public array $domains;

	/**
	 * @param array<string, mixed> $params
	 * @param list<string>         $domains The domains it's looked up in; its domain and core's when empty.
	 */
	public function __construct(
		public string $key,
		public ?string $text = null,
		public string $domain = Translator::CORE,
		public array $params = [],
		public ?string $context = null,
		array $domains = [],
		private ?Translator $translator = null,
		private ?CurrentLocale $locale = null
	) {
		$this->domains = $domains === [] ? Translator::domainsOf($domain) : $domains;
	}

	/**
	 * Returns it translated, for JSON: text, never an object (D-816).
	 */
	#[Override]
	public function jsonSerialize(): string
	{
		return (string) $this;
	}

	/**
	 * Returns it to store and show later (D-836): its key, the text
	 * written beside it, its domain, and its parameters, a message among
	 * them stored the same way and a date as its timestamp. Read back with
	 * `Messages::restore()`, it's translated when it's shown, in the
	 * reader's language, with the text in place of a key a later version
	 * no longer has. Its note for translators isn't kept.
	 *
	 * @return array{key: string, text: ?string, domain: string, params: array<string, mixed>}
	 *
	 * @throws TranslationException When a parameter is HTML or an object that can't be stored.
	 */
	public function toArray(): array
	{
		$params = [];

		foreach ($this->params as $name => $value) {
			$params[$name] = match (true) {
				$value instanceof self                                         => $value->toArray(),
				$value instanceof DateTimeInterface                            => $value->getTimestamp(),
				$value === null, is_scalar($value)                             => $value,
				default => throw new TranslationException(sprintf('The "%s" parameter of "%s" can\'t be stored: only text, numbers, dates, and other messages can.', $name, $this->key))
			};
		}

		return ['key' => $this->key, 'text' => $this->text, 'domain' => $this->domain, 'params' => $params];
	}

	/**
	 * Returns whether it prints as HTML: one of its parameters is
	 * (`raw()`, any `SafeHtml`, D-559).
	 */
	public function isHtml(): bool
	{
		return array_any($this->params, static fn (mixed $value): bool => $value instanceof SafeHtml);
	}

	/**
	 * Returns it translated in the current language (as HTML when
	 * `isHtml()`), or, made without a translator, its text formatted
	 * (else its key).
	 */
	#[Override]
	public function __toString(): string
	{
		return $this->translator === null
			? Translator::render($this, $this->text ?? $this->key, $this->locale?->get() ?? Translator::LAST)
			: $this->translator->text($this, $this->locale?->get());
	}
}
