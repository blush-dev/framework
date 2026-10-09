<?php

/**
 * Ignored Site Health problems.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

/**
 * Keeps the Site Health problems ignored (D-613): per site, so everyone
 * stops seeing one, with who ignored it and when. Each is a record keyed
 * by its problem's key (`ProblemKeys`). A site keeping its data in a
 * database (D-486) can keep them there by binding another store.
 */
interface IgnoredProblems
{
	/**
	 * Returns every ignored problem, by key: who ignored it (an account id, D-668)
	 * and when (ISO 8601).
	 *
	 * @return array<string, array{by: string, at: string}>
	 */
	public function all(): array;

	/**
	 * Ignores a problem.
	 */
	public function ignore(string $key, string $by, string $at): void;

	/**
	 * Stops ignoring a problem.
	 */
	public function unignore(string $key): void;

	/**
	 * Forgets the ignored problems not among `$keys`, those a check no
	 * longer finds, so a problem that comes back is seen again.
	 *
	 * @param list<string> $keys
	 */
	public function keep(array $keys): void;
}
