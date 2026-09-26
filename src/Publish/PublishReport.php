<?php

/**
 * Publish report.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Publish;

use Blush\Content\Index\IndexReport;

/**
 * What a publish did: the pull (if one ran), the indexing run, which
 * compiled caches were rewritten, the store namespaces cleared, and the
 * new content version. A failed pull stops the publish before anything
 * changes, so `version` is `null`.
 */
final readonly class PublishReport
{
	/**
	 * @param list<string> $cleared The store namespaces cleared.
	 */
	public function __construct(
		public ?PullResult $pull = null,
		public ?IndexReport $index = null,
		public bool $routes = false,
		public bool $types = false,
		public array $cleared = [],
		public ?string $version = null,
		public int $milliseconds = 0
	) {}

	/**
	 * Returns whether the site was published (the pull, if any, worked).
	 */
	public function isPublished(): bool
	{
		return $this->version !== null;
	}

	/**
	 * Returns whether everything worked: published, with every file
	 * indexed.
	 */
	public function isSuccessful(): bool
	{
		return $this->isPublished() && ($this->index === null || $this->index->failures === []);
	}

	/**
	 * Returns the report as plain data, for the webhook's response.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'published' => $this->isPublished(),
			'pull'      => $this->pull === null ? null : ['successful' => $this->pull->successful, 'output' => $this->pull->output],
			'index'     => $this->index === null ? null : [
				'total'    => $this->index->total,
				'added'    => $this->index->added,
				'changed'  => $this->index->changed,
				'removed'  => $this->index->removed,
				'failures' => $this->index->failures
			],
			'routes'       => $this->routes,
			'types'        => $this->types,
			'cleared'      => $this->cleared,
			'version'      => $this->version,
			'milliseconds' => $this->milliseconds
		];
	}
}
