<?php

/**
 * Source catalog.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Translation;

use JsonException;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Support\Filesystem;

/**
 * An extension's catalog of the messages its code writes (D-784), the
 * one translators work from: `lang/en.json` unless another language is
 * named. `lang:extract` writes it from the messages `MessageExtractor`
 * finds, and `theme:check` and `plugin:check` compare it with them.
 *
 * Writing keeps what's there (its order, its metadata, keys the code
 * looks up without text beside them), adds new keys, and replaces text
 * that changed in the code. A message's `context` is written beside it
 * as `@{key}`, as ARB does.
 */
final readonly class SourceCatalog
{
	/**
	 * The language a source catalog is in unless another is named.
	 */
	public const string LOCALE = 'en';

	public function __construct(private Filesystem $files = new Filesystem())
	{}

	/**
	 * Returns the source catalog's path in an extension's `lang/`.
	 */
	public static function path(string $lang, string $locale = self::LOCALE): string
	{
		return "{$lang}/{$locale}.json";
	}

	/**
	 * Compares a catalog with the messages the code writes: a key it
	 * lacks, or text it has otherwise, is a warning to run `lang:extract`.
	 * Without the catalog, one notice says so.
	 *
	 * @param  array<string, ExtractedMessage> $messages
	 * @return list<Violation>
	 */
	public function compare(array $messages, string $file): array
	{
		if ($messages === []) {
			return [];
		}

		$catalog = $this->read($file);
		$name    = basename(dirname($file)) . '/' . basename($file);

		if ($catalog === null) {
			return [new Violation($name, sprintf('The code writes %d %s, but there\'s no %s; run lang:extract to write it for translators.', count($messages), count($messages) === 1 ? 'message' : 'messages', $name), Severity::Notice)];
		}

		$problems = [];

		foreach ($messages as $key => $message) {
			$found = self::at($catalog, $key);

			if ($found === null) {
				$problems[] = new Violation($message->where(), sprintf('"%s" isn\'t in %s; run lang:extract.', $key, $name), Severity::Warning);
			} elseif ($found !== $message->text) {
				$problems[] = new Violation($message->where(), sprintf('"%s" reads otherwise in %s; run lang:extract.', $key, $name), Severity::Warning);
			}
		}

		return $problems;
	}

	/**
	 * Writes the messages to a catalog, returning the keys added, the
	 * keys whose text changed, and the keys that couldn't be written
	 * (one nested under a key that holds text, or the other way round).
	 *
	 * @param  array<string, ExtractedMessage> $messages
	 * @return array{added: list<string>, changed: list<string>, conflicts: list<string>}
	 * @throws TranslationException When the catalog can't be read.
	 */
	public function write(array $messages, string $file, string $domain, string $locale = self::LOCALE, bool $dryRun = false): array
	{
		$catalog = $this->read($file) ?? [Catalog::LOCALE => $locale, Catalog::DOMAIN => $domain];
		$before  = $catalog;
		$result  = ['added' => [], 'changed' => [], 'conflicts' => []];

		foreach ($messages as $key => $message) {
			$found = self::at($catalog, $key);

			if (! self::put($catalog, explode('.', $key), $message->text, $message->context)) {
				$result['conflicts'][] = $key;
			} elseif ($found === null) {
				$result['added'][] = $key;
			} elseif ($found !== $message->text) {
				$result['changed'][] = $key;
			}
		}

		if (! $dryRun && ($catalog !== $before || ! is_file($file))) {
			$json = json_encode($catalog, JSON_FORCE_OBJECT | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

			$this->files->writeAtomic($file, preg_replace_callback('/^(?: {4})+/m', static fn (array $match): string => str_repeat("\t", intdiv(strlen($match[0]), 4)), $json) . "\n");
		}

		return $result;
	}

	/**
	 * Reads a catalog, or `null` when there's none.
	 *
	 * @return ?array<array-key, mixed>
	 * @throws TranslationException When it isn't a JSON object.
	 */
	private function read(string $file): ?array
	{
		if (! is_file($file)) {
			return null;
		}

		try {
			$data = json_decode((string) file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new TranslationException(sprintf('%s isn\'t valid JSON: %s', $file, $e->getMessage()));
		}

		return is_array($data) ? $data : throw new TranslationException(sprintf('%s isn\'t a JSON object.', $file));
	}

	/**
	 * Returns the text at a dotted key, or `null`.
	 *
	 * @param array<array-key, mixed> $catalog
	 */
	private static function at(array $catalog, string $key): ?string
	{
		$value = $catalog;

		foreach (explode('.', $key) as $segment) {
			if (! is_array($value) || ! array_key_exists($segment, $value)) {
				return null;
			}

			$value = $value[$segment];
		}

		return is_string($value) ? $value : null;
	}

	/**
	 * Puts text at a key's segments, with its context as `@{last}`,
	 * returning `false` when a segment holds text, or the key an object.
	 *
	 * @param array<array-key, mixed> $catalog
	 * @param list<string>            $segments
	 */
	private static function put(array &$catalog, array $segments, string $text, ?string $context): bool
	{
		$last = array_pop($segments);

		if ($last === null) {
			return false;
		}

		$level = &$catalog;

		foreach ($segments as $segment) {
			$level[$segment] ??= [];

			if (! is_array($level[$segment])) {
				return false;
			}

			$level = &$level[$segment];
		}

		if (isset($level[$last]) && is_array($level[$last])) {
			return false;
		}

		$level[$last] = $text;

		if ($context !== null) {
			$level["@{$last}"] = ['context' => $context];
		}

		return true;
	}
}
