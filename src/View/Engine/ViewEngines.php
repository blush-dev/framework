<?php

/**
 * View engines.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Engine;

use Throwable;
use Blush\Container\Container;
use Blush\View\ViewException;

/**
 * Builds the registered view engines through the container, once each,
 * and picks the one for a template file by its extension (D-502): the
 * longest registered extension the file name ends with, so
 * `card.blade.php` goes to a `blade.php` engine before `php`.
 */
final class ViewEngines
{
	/**
	 * Engines built so far, by extension.
	 *
	 * @var array<string, ViewEngine>
	 */
	private array $engines = [];

	public function __construct(
		private readonly ViewEngineRegistry $registry,
		private readonly Container $container
	) {}

	/**
	 * Returns the registered extensions, in registration order: the
	 * order a view folder's templates in more than one engine win in.
	 *
	 * @return list<string>
	 */
	public function extensions(): array
	{
		return array_keys($this->registry->all());
	}

	/**
	 * Returns the extension of a template file's engine, or `null` when
	 * no engine renders it.
	 */
	public function extensionOf(string $file): ?string
	{
		$extensions = $this->extensions();

		usort($extensions, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

		return array_find($extensions, static fn (string $extension): bool => str_ends_with($file, ".{$extension}"));
	}

	/**
	 * Returns the engine for a template file.
	 *
	 * @throws ViewException When no engine renders it, or it can't be built.
	 */
	public function forFile(string $file): ViewEngine
	{
		$extension = $this->extensionOf($file) ?? throw new ViewException(sprintf(
			'No view engine renders %s; registered extensions: %s.',
			$file,
			implode(', ', $this->extensions())
		));

		return $this->engines[$extension] ??= $this->build($extension);
	}

	/**
	 * Builds an engine through the container.
	 *
	 * @throws ViewException
	 */
	private function build(string $extension): ViewEngine
	{
		$class = $this->registry->get($extension) ?? throw new ViewException(sprintf('No view engine is registered for "%s".', $extension));

		try {
			return $this->container->build($class);
		} catch (Throwable $e) {
			throw new ViewException(sprintf('Unable to build the "%s" view engine: %s', $extension, $e->getMessage()), 0, $e);
		}
	}
}
