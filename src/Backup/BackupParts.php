<?php

/**
 * Backup parts.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

use Blush\Secret\SecretStore;
use Blush\Session\RecordSessionStore;
use Blush\Storage\Record\Table;
use Blush\Storage\Record\TableRegistry;
use Blush\Translation\Messages;

/**
 * The parts a backup can hold (D-883): core's (`CorePart`), then the ones
 * plugins register, then **Other** for any registered table no part
 * claims, so nothing a plugin keeps is silently left out. A plugin
 * registers its part when the parts are built, in a provider's
 * `register()`:
 *
 *     $this->container->resolving(BackupParts::class, static function (object $parts): void {
 *         $parts->register(new BackupPart('acme/gallery', BackupGroup::Plugins, 'Gallery', 'Albums', ['gallery/albums']));
 *     });
 *
 * Sessions and secrets are never in a part (`LEFT_OUT`).
 */
final class BackupParts
{
	/**
	 * The part holding the tables no other part claims.
	 */
	public const string OTHER = 'other';

	/**
	 * The tables never backed up: sessions, so a restore never brings
	 * back a sign-in that ended, and secrets, ciphertext only the site's
	 * key reads.
	 *
	 * @var list<string>
	 */
	public const array LEFT_OUT = [RecordSessionStore::TABLE, SecretStore::TABLE];

	/**
	 * Parts plugins registered, by name.
	 *
	 * @var array<string, BackupPart>
	 */
	private array $registered = [];

	/**
	 * Every part, once worked out.
	 *
	 * @var array<string, BackupPart>|null
	 */
	private ?array $parts = null;

	public function __construct(
		private readonly TableRegistry $tables,
		private readonly Messages $messages
	) {}

	/**
	 * Registers a part. A core part's name can't be taken.
	 *
	 * @throws BackupException When the name is core's or `other`, or taken.
	 */
	public function register(BackupPart $part): void
	{
		if (CorePart::tryFrom($part->name) !== null || $part->name === self::OTHER || isset($this->registered[$part->name])) {
			throw new BackupException(sprintf('A backup part named "%s" is already registered.', $part->name));
		}

		$this->registered[$part->name] = $part;
		$this->parts                   = null;
	}

	/**
	 * Returns every part, core's first, by name. Only tables that are
	 * registered are in a part, and a part with none is left out.
	 *
	 * @return array<string, BackupPart>
	 */
	public function all(): array
	{
		if ($this->parts !== null) {
			return $this->parts;
		}

		$known   = $this->tables->all();
		$claimed = self::LEFT_OUT;
		$parts   = [];

		$candidates = [...array_map(fn (CorePart $part): BackupPart => $part->part($this->messages), CorePart::cases()), ...array_values($this->registered)];

		foreach ($candidates as $part) {
			$tables = array_values(array_filter($part->tables, static fn (string $table): bool => isset($known[$table]) && ! in_array($table, self::LEFT_OUT, true)));

			if ($tables !== []) {
				$parts[$part->name] = new BackupPart($part->name, $part->group, $part->label, $part->description, $tables);
				$claimed            = [...$claimed, ...$tables];
			}
		}

		$other = array_values(array_diff(array_keys($known), $claimed));

		if ($other !== []) {
			sort($other);

			$parts[self::OTHER] = new BackupPart(
				self::OTHER,
				BackupGroup::Plugins,
				$this->messages->t('backup.part.other', 'Other'),
				$this->messages->t('backup.part.other_description', 'Tables plugins keep: {tables}', tables: implode(', ', $other)),
				$other
			);
		}

		return $this->parts = $parts;
	}

	/**
	 * Returns a part by name, or `null`.
	 */
	public function get(string $name): ?BackupPart
	{
		return $this->all()[$name] ?? null;
	}

	/**
	 * Returns every part's name.
	 *
	 * @return list<string>
	 */
	public function names(): array
	{
		return array_keys($this->all());
	}

	/**
	 * Returns a part's tables.
	 *
	 * @return list<Table>
	 */
	public function tables(BackupPart $part): array
	{
		$known = $this->tables->all();

		return array_values(array_filter(array_map(static fn (string $name): ?Table => $known[$name] ?? null, $part->tables)));
	}
}
