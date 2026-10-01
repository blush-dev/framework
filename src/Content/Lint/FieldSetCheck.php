<?php

/**
 * Field set check.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Lint;

use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\ContentTypeTarget;
use Blush\Core\Paths;
use Blush\Field\FieldSetLoader;
use Blush\Field\FieldSetOrigin;
use Blush\Field\Severity;
use Blush\Field\Violation;

/**
 * Finds field set targets that attach to nothing (D-337): a `type:` naming
 * no content type (perhaps one that's turned off), or a kind of target
 * Blush doesn't have. They're notices, since a set may name a type that
 * comes and goes. Each set's notes are keyed by its file (a data set's
 * `user/data/fields` file, `config/fields.php`), or by the set's name
 * for an extension's.
 */
final readonly class FieldSetCheck
{
	/**
	 * The field its violations use.
	 */
	public const string FIELD = 'targets';

	public function __construct(
		private ContentTypes $types,
		private Paths $paths
	) {}

	/**
	 * Checks every set.
	 *
	 * @return array<string, list<Violation>>
	 */
	public function check(): array
	{
		$violations = [];

		foreach ($this->types->sets as $name => $set) {
			foreach ($set->targets as $target) {
				[$kind, $targetName] = explode(':', $target, 2);

				$message = match (true) {
					$kind !== ContentTypeTarget::KIND => sprintf('"%s" names %s, but fields can\'t attach to a "%s" yet.', $name, $target, $kind),
					! $this->types->has($targetName)  => sprintf('"%s" names %s, which isn\'t a content type, so it isn\'t used there.', $name, $target),
					default                           => null
				};

				if ($message !== null) {
					$violations[$this->key($name)][] = new Violation(self::FIELD, $message, Severity::Notice);
				}
			}
		}

		return $violations;
	}

	/**
	 * Returns where a set's notes are listed.
	 */
	private function key(string $name): string
	{
		return match ($this->types->sets->origin($name)) {
			FieldSetOrigin::Data      => $this->dataFile($name),
			FieldSetOrigin::Config    => 'config/fields.php',
			FieldSetOrigin::Extension => sprintf('the "%s" field set', $name)
		};
	}

	/**
	 * Returns a data set's file from the site's root.
	 */
	private function dataFile(string $name): string
	{
		$directory = $this->paths->data . '/' . FieldSetLoader::DATA_DIRECTORY;
		$file      = array_find(['json', 'yaml', 'yml'], static fn (string $extension): bool => is_file("{$directory}/{$name}.{$extension}"));

		return $this->paths->relative("{$directory}/{$name}" . ($file === null ? '' : ".{$file}"));
	}
}
