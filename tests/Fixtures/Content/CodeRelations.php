<?php

/**
 * Code relations fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Content;

use Override;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\RelationSource;
use Blush\Core\Paths;

/**
 * Relations from code, as a plugin defines them, read from the scratch
 * site's `code.json` (`WritesContentConfig::codeConfig()`).
 */
final readonly class CodeRelations implements RelationSource
{
	public function __construct(
		private Paths $paths
	) {}

	#[Override]
	public function relations(): iterable
	{
		foreach ($this->definitions('relations') as $name => $definition) {
			yield Relation::fromArray(['name' => $name, ...$definition]);
		}
	}

	/**
	 * Returns the definitions under a key in `code.json`, by name.
	 *
	 * @return array<string, array<array-key, mixed>>
	 */
	private function definitions(string $key): array
	{
		$file = "{$this->paths->root}/code.json";
		$data = is_file($file) ? json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR) : [];

		/** @var array<string, array<array-key, mixed>> */
		return is_array($data) && is_array($data[$key] ?? null) ? $data[$key] : [];
	}
}
