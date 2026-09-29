<?php

/**
 * Document editor.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Writer;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use JsonException;
use Blush\Content\Parser\DataDocumentParser;
use Blush\Content\Parser\Document;
use Blush\Content\Parser\DocumentFormat;
use Blush\Content\Parser\DocumentParsers;
use Blush\Content\Parser\InvalidDocument;

/**
 * Applies `EntryChanges` to a content file's text, by format (D-228):
 *
 * - **Markdown and HTML:** the front matter is edited key by key
 *   (`YamlMap`), so its formatting stays; the body after it is replaced
 *   as given. A file without front matter gets a block.
 * - **YAML entries:** the whole file is the map, with the body under
 *   `body`.
 * - **JSON entries:** decoded, changed, and written back pretty-printed
 *   (key order kept).
 *
 * Every result is parsed again before it's returned. The keys that were
 * set must read back as the given values, removed keys must be gone,
 * everything else must read as it did, and the body must be the given
 * one (or unchanged). Anything else is a `WriteException`, so a file is
 * never saved in a shape Blush would misread.
 */
final readonly class DocumentEditor
{
	public function __construct(private DocumentParsers $parsers)
	{}

	/**
	 * Returns the file's new text.
	 *
	 * @param  Closure(string): list<string> $keys A field name's keys: the name, then its aliases.
	 * @throws WriteException
	 */
	public function edit(string $path, string $contents, EntryChanges $changes, Closure $keys): string
	{
		$before = $this->parse($path, $contents);
		$format = DocumentFormat::tryFrom(strtolower(pathinfo($path, PATHINFO_EXTENSION)));

		$edited = match ($format) {
			DocumentFormat::Md, DocumentFormat::Markdown, DocumentFormat::Html => $this->editFrontMatter($contents, $changes, $keys),
			DocumentFormat::Yaml, DocumentFormat::Yml                          => $this->editYaml($contents, $changes, $keys),
			DocumentFormat::Json                                               => $this->editJson($contents, $changes, $keys),
			null => throw new WriteException(sprintf('"%s" isn\'t a content file Blush can edit.', $path))
		};

		$this->verify($path, $before, $this->parse($path, $edited), $changes, $keys);

		return $edited;
	}

	/**
	 * Edits a Markdown or HTML file's front matter and body.
	 *
	 * @param Closure(string): list<string> $keys
	 */
	private function editFrontMatter(string $contents, EntryChanges $changes, Closure $keys): string
	{
		$bom = str_starts_with($contents, "\xEF\xBB\xBF") ? "\xEF\xBB\xBF" : '';
		$eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";

		if (preg_match('/\A(?:\xEF\xBB\xBF)?(---[ \t]*)(\r?\n)(.*?)^(---|\.\.\.)([ \t]*)(\r?\n|\z)/ms', $contents, $match) === 1) {
			[$block, $open, , $yaml, $close, $trail, $ending] = $match;
			$body = substr($contents, strlen($block));
		} else {
			[$open, $yaml, $close, $trail, $ending, $body] = ['---', '', '---', '', $eol, $contents];
			$block = '';
		}

		// A file that ends at its closing line keeps doing so, unless a
		// body is added.
		if ($ending === '' && ($changes->body ?? '') !== '') {
			$ending = $eol;
		}

		$map = $this->apply(YamlMap::fromText($yaml), $changes, $keys);

		if ($block === '' && $map->text() === '') {
			return $changes->body ?? $contents;
		}

		return $bom . $open . $eol . $map->text() . $close . $trail . $ending . ($changes->body ?? ($block === '' ? ltrim($body, "\xEF\xBB\xBF") : $body));
	}

	/**
	 * Edits a YAML entry, whose body is its `body` key.
	 *
	 * @param Closure(string): list<string> $keys
	 */
	private function editYaml(string $contents, EntryChanges $changes, Closure $keys): string
	{
		$map = $this->apply(YamlMap::fromText($contents), $changes, $keys);

		if ($changes->body !== null) {
			$map = $map->with([DataDocumentParser::BODY], $changes->body);
		}

		return $map->text();
	}

	/**
	 * Edits a JSON entry, whose body is its `body` key.
	 *
	 * @param Closure(string): list<string> $keys
	 * @throws WriteException
	 */
	private function editJson(string $contents, EntryChanges $changes, Closure $keys): string
	{
		try {
			$data = trim($contents) === '' ? [] : json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new WriteException(sprintf('The file isn\'t valid JSON: %s', $e->getMessage()), previous: $e);
		}

		if (! is_array($data)) {
			throw new WriteException('A JSON entry must be an object.');
		}

		foreach ($changes->remove as $name) {
			foreach ($keys($name) as $key) {
				unset($data[$key]);
			}
		}

		foreach ($changes->set as $name => $value) {
			$candidates = $keys((string) $name);
			$key        = array_find($candidates, static fn (string $key): bool => array_key_exists($key, $data)) ?? $candidates[0] ?? (string) $name;

			$data[$key] = $value;
		}

		if ($changes->body !== null) {
			$data[DataDocumentParser::BODY] = $changes->body;
		}

		try {
			return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
		} catch (JsonException $e) {
			throw new WriteException(sprintf('The entry couldn\'t be written as JSON: %s', $e->getMessage()), previous: $e);
		}
	}

	/**
	 * Removes and sets front matter keys in a map.
	 *
	 * @param Closure(string): list<string> $keys
	 */
	private function apply(YamlMap $map, EntryChanges $changes, Closure $keys): YamlMap
	{
		foreach ($changes->remove as $name) {
			$map = $map->without($keys($name));
		}

		foreach ($changes->set as $name => $value) {
			$map = $map->with($keys((string) $name), $value);
		}

		return $map;
	}

	/**
	 * Checks that the edited text reads back as intended.
	 *
	 * @param  Closure(string): list<string> $keys
	 * @throws WriteException
	 */
	private function verify(string $path, Document $before, Document $after, EntryChanges $changes, Closure $keys): void
	{
		$touched = [];

		foreach ([...array_keys($changes->set), ...$changes->remove] as $name) {
			array_push($touched, ...$keys((string) $name));
		}

		foreach ($changes->set as $name => $value) {
			$present = array_find($keys((string) $name), static fn (string $key): bool => array_key_exists($key, $after->frontMatter));

			if ($present === null || ! self::same($value, $after->frontMatter[$present])) {
				throw self::unsafe($path, sprintf('"%s" wouldn\'t read back as given', $name));
			}
		}

		foreach ($changes->remove as $name) {
			if (array_any($keys($name), static fn (string $key): bool => array_key_exists($key, $after->frontMatter))) {
				throw self::unsafe($path, sprintf('"%s" wouldn\'t be removed', $name));
			}
		}

		$untouched = static fn (array $data): array => array_diff_key($data, array_flip($touched));

		if ($untouched($before->frontMatter) !== $untouched($after->frontMatter)) {
			throw self::unsafe($path, 'other front matter would change');
		}

		if ($after->body !== ($changes->body ?? $before->body)) {
			throw self::unsafe($path, 'the body wouldn\'t read back as given');
		}
	}

	/**
	 * Whether a written value reads back as the one given. A date reads
	 * back in YAML's own format (`2026-06-01T12:00:00-05:00`), so dates
	 * match when they're the same moment.
	 */
	private static function same(mixed $given, mixed $read): bool
	{
		if ($given === $read) {
			return true;
		}

		if (! is_string($given) || ! is_string($read) || preg_match(YamlMap::TIMESTAMP, $given) !== 1) {
			return false;
		}

		try {
			$utc = new DateTimeZone('UTC');

			return new DateTimeImmutable($given, $utc) == new DateTimeImmutable($read, $utc);
		} catch (Exception) {
			return false;
		}
	}

	/**
	 * Parses a file's text.
	 *
	 * @throws WriteException
	 */
	private function parse(string $path, string $contents): Document
	{
		try {
			return $this->parsers->parse($path, $contents);
		} catch (InvalidDocument $e) {
			throw new WriteException(sprintf('%s can\'t be read, so it can\'t be edited safely: %s', $path, $e->getMessage()), previous: $e);
		}
	}

	/**
	 * Builds the exception for an edit that wouldn't read back.
	 */
	private static function unsafe(string $path, string $reason): WriteException
	{
		return new WriteException(sprintf('Blush couldn\'t edit %s safely (%s), so nothing was saved.', $path, $reason));
	}
}
