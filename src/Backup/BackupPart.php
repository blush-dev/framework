<?php

/**
 * Backup part.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

use Stringable;

/**
 * A part of a backup (D-883): a named set of tables, made and restored
 * together, such as Content (entries and their relations) or Access (API
 * tokens and OAuth clients and grants). Core's are `CorePart`'s; a plugin
 * registers its own with `BackupParts`:
 *
 *     $parts->register(new BackupPart('acme/gallery', BackupGroup::Plugins, 'Gallery', 'Albums and their photos\' details', ['gallery/albums']));
 */
final readonly class BackupPart
{
	/**
	 * @param list<string> $tables The tables' names, as `TableRegistry` knows them.
	 */
	public function __construct(
		public string $name,
		public BackupGroup $group,
		public string|Stringable $label,
		public string|Stringable $description,
		public array $tables
	) {}
}
