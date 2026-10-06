<?php

/**
 * Domain translator.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Translation;

use Blush\Data\InvalidData;

/**
 * The translator bound to a list of domains, searched in order (D-451):
 * a theme chain's, child first, for `$template->t()`, or a directive's or
 * component's, the chain's and then its own extension's.
 */
final readonly class DomainTranslator
{
	/**
	 * @param list<string> $domains
	 */
	public function __construct(
		public Translator $translator,
		public array $domains
	) {}

	/**
	 * Returns a copy that also searches more domains, after these.
	 */
	public function with(string ...$domains): self
	{
		return new self($this->translator, array_values(array_unique([...$this->domains, ...$domains])));
	}

	/**
	 * Translates a key with named parameters.
	 *
	 * @param  array<string, mixed> $params
	 * @throws InvalidData When a catalog can't be parsed.
	 */
	public function translate(string $key, array $params = [], ?string $locale = null): string
	{
		return $this->translator->translate($key, $params, $this->domains, $locale);
	}

	/**
	 * Returns a group's messages, keyed by name.
	 *
	 * @param  array<string, mixed> $params
	 * @return array<string, string>
	 * @throws InvalidData When a catalog can't be parsed.
	 */
	public function group(string $key, array $params = [], ?string $locale = null): array
	{
		return $this->translator->group($key, $params, $this->domains, $locale);
	}

	/**
	 * Returns whether a key has a message.
	 *
	 * @throws InvalidData When a catalog can't be parsed.
	 */
	public function has(string $key, ?string $locale = null): bool
	{
		return $this->translator->has($key, $this->domains, $locale);
	}
}
