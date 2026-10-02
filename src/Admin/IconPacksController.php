<?php

/**
 * Admin icon packs controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Core\Paths;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Icon\IconPack;
use Blush\Icon\IconPacks;

/**
 * Answers `GET {path}/api/icon-packs` (D-378), for accounts with
 * `site.settings`: every installed icon pack, by label, with its `name`,
 * `label`, `namespace`, `version`, `description`, `source` (`local` or
 * `composer`), `path` (from the site's root), how many icons it has
 * (`count`), and the first of their names (`icons`, in full:
 * `brands/github`); then the `invalid` ones, by `where` they were found,
 * with the reason.
 *
 * Packs are installed in `user/icons` or with Composer, and every
 * installed pack is on, so the screen only shows them. Packs are data,
 * so they're the first kind the admin will install (planned).
 */
final readonly class IconPacksController
{
	/**
	 * How many icon names a pack lists.
	 */
	private const int SAMPLE = 12;

	public function __construct(
		private IconPacks $packs,
		private Paths $paths,
		private Permissions $permissions
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, Capability::SiteSettings)) {
			return Response::json(['error' => 'You aren\'t allowed to see the site\'s icon packs.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		$packs = array_values(array_map($this->pack(...), $this->packs->all()));

		usort($packs, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

		$invalid = [];

		foreach ($this->packs->invalid() as $where => $reason) {
			$invalid[] = ['where' => $where, 'reason' => $reason];
		}

		return Response::json(['packs' => $packs, 'invalid' => $invalid], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Describes a pack.
	 *
	 * @return array{name: string, label: string, namespace: string, version: string, description: string, source: string, path: string, count: int, icons: list<string>}
	 */
	private function pack(IconPack $pack): array
	{
		$files = glob($pack->iconsPath() . '/*.svg') ?: [];

		sort($files);

		return [
			'name'        => $pack->name,
			'label'       => $pack->label,
			'namespace'   => $pack->namespace,
			'version'     => $pack->version,
			'description' => $pack->description,
			'source'      => $pack->source->value,
			'path'        => $this->paths->relative($pack->path),
			'count'       => count($files),
			'icons'       => array_map(static fn (string $file): string => "{$pack->namespace}/" . basename($file, '.svg'), array_slice($files, 0, self::SAMPLE))
		];
	}
}
