<?php

/**
 * Document parsers.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Parser;

use Blush\Container\Container;

/**
 * Builds and caches document parsers by file extension (the "Factory" of
 * the enum + registry pattern, D-019). Each parser is built through the
 * container with an `extension` argument, which parsers that serve several
 * formats (such as `DataDocumentParser`) use.
 */
final class DocumentParsers
{
	/**
	 * Parsers built so far, keyed by extension.
	 *
	 * @var array<string, DocumentParser>
	 */
	private array $parsers = [];

	public function __construct(
		private readonly DocumentParserRegistry $registry,
		private readonly Container $container
	) {}

	/**
	 * Returns whether a file is content: whether a parser handles its
	 * extension.
	 */
	public function supports(string $path): bool
	{
		return $this->registry->isRegistered(strtolower(pathinfo($path, PATHINFO_EXTENSION)));
	}

	/**
	 * Returns the extensions that are content.
	 *
	 * @return list<string>
	 */
	public function extensions(): array
	{
		return array_keys($this->registry->all());
	}

	/**
	 * Returns the parser for a file extension.
	 *
	 * @throws InvalidDocument When no parser handles it.
	 */
	public function for(string $extension): DocumentParser
	{
		$extension = strtolower($extension);

		if (isset($this->parsers[$extension])) {
			return $this->parsers[$extension];
		}

		$class = $this->registry->get($extension)
			?? throw new InvalidDocument(sprintf('No content parser is registered for ".%s" files.', $extension));

		return $this->parsers[$extension] = $this->container->make($class, ['extension' => $extension]);
	}

	/**
	 * Parses a content file's contents by the file's extension.
	 *
	 * @throws InvalidDocument
	 */
	public function parse(string $path, string $contents): Document
	{
		return $this->for(pathinfo($path, PATHINFO_EXTENSION))->parse($contents);
	}
}
