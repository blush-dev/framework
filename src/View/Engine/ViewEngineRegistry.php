<?php

/**
 * View engine registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Engine;

use Override;
use Blush\Support\Registry;
use Blush\Support\RegistrationException;

/**
 * Maps file extensions (without the leading dot: `twig`, `blade.php`) to
 * view engine classes (D-502). A plugin adds an engine in its provider's
 * `boot()`:
 *
 *     $this->container->make(ViewEngineRegistry::class)->register('twig', TwigEngine::class);
 *
 * The order engines are registered in is their precedence when one view
 * folder has a template in two of them (`single.php` and `single.twig`):
 * the built-in PHP engine is first.
 *
 * @extends Registry<ViewEngine>
 */
final class ViewEngineRegistry extends Registry
{
	/**
	 * @inheritDoc
	 */
	protected const string CONTRACT = ViewEngine::class;

	/**
	 * An extension: lowercase letters and digits, in parts split by dots.
	 */
	private const string EXTENSION = '/^[a-z0-9]+(\.[a-z0-9]+)*$/';

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function register(string $key, string $className): void
	{
		if (preg_match(self::EXTENSION, $key) !== 1) {
			throw new RegistrationException(sprintf(
				'Cannot register a view engine for "%s"; an extension is lowercase letters and digits, split by dots, without the leading dot (such as "twig").',
				$key
			));
		}

		parent::register($key, $className);
	}
}
