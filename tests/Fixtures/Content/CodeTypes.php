<?php

/**
 * Code types fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Content;

use Override;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypeSource;
use Blush\Core\Paths;
use Blush\Field\FieldFactory;

/**
 * Types from code, as a plugin defines them, read from the scratch site's
 * `code.json` (`WritesContentConfig::codeConfig()`).
 */
final readonly class CodeTypes implements ContentTypeSource
{
	public function __construct(
		private Paths $paths,
		private FieldFactory $fields
	) {}

	#[Override]
	public function types(): iterable
	{
		foreach ($this->definitions('types') as $name => $definition) {
			yield ContentType::fromArray(['name' => $name, ...$definition], $this->fields);
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
