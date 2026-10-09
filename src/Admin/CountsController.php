<?php

/**
 * Admin counts controller.
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
use Blush\Auth\Accounts;
use Blush\Admin\Action\AdminActions;
use Blush\Auth\AuthException;
use Blush\Auth\Capability;
use Blush\Auth\ContentAction;
use Blush\Auth\ExtensionAction;
use Blush\Auth\Permissions;
use Blush\Auth\Roles;
use Blush\Content\Entries;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Tree;
use Blush\Core\Paths;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKind;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Icon\IconPacks;
use Blush\Media\Index\MediaLibrary;
use Blush\Media\Index\MediaQuery;
use Blush\Plugin\PluginDiscovery;
use Blush\Theme\Themes;

/**
 * Answers `GET {path}/api/counts` (D-371): how many things each of the
 * section panel's lists holds, for the count beside its link (the
 * profiles sketch's).
 *
 * - `types`: each content type the account may edit entries of, by name:
 *   how many entries its list shows the account (any status, without
 *   its index page, root page, or relation archive pages, as `GET entries` counts
 *   them).
 * - `actions`: how many actions the account may run, so the Tools screen
 *   is listed only with something on it (D-540).
 * - `health`: how many checks in Site Health's last report need a look,
 *   with `site.health`, when there's a report (D-545).
 * - `media`: the files in the library, with a media capability (D-372,
 *   D-407).
 * - `accounts` and `roles`, with `accounts.view`.
 * - `contentTypes`, `relations` (D-610), and `fieldSets`, with
 *   `site.settings`.
 * - `themes`, `plugins`, and `iconPacks` (installed), each with seeing
 *   its kind (`extensions.themes.view`, and so on, D-389; `themes` since
 *   D-372, counting broken ones since D-381, as the Themes screen lists
 *   them; `iconPacks` since D-378, counting broken ones and the core set
 *   since D-385, as the Icon Packs screen lists them).
 *
 * A count the account may not see is left out.
 */
final readonly class CountsController
{
	public function __construct(
		private Entries $content,
		private ContentTypes $types,
		private Permissions $permissions,
		private Accounts $accounts,
		private Roles $roles,
		private Paths $paths,
		private MediaLibrary $media,
		private Themes $themes,
		private IconPacks $iconPacks,
		private AdminActions $actions,
		private SiteHealth $health,
		private ArchivePages $archivePages
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account) {
			return self::json(['error' => 'Sign in first.'], Status::Unauthorized);
		}

		$types = [];

		foreach ($this->types->all() as $type) {
			if (! $this->permissions->can($account, ContentAction::Edit, $type->name)) {
				continue;
			}

			$query = $this->permissions->restrict($account, ContentAction::Edit, $this->content->query()->any()->type($type->name));
			$query = $query->withLanding(false);
			$query = $type instanceof Tree && $type->atRoot() ? $query : $query->exceptNames(...$this->archivePages->listPages($type))->exceptIn(...$this->archivePages->targetFolders($type));

			$types[$type->name] = $query->count();
		}

		$counts = ['types' => $types, 'actions' => count($this->actions->allowed($account))];

		if ($this->permissions->usesMedia($account)) {
			$counts['media'] = $this->media->query(new MediaQuery(per: 1))->total;
		}

		if ($this->permissions->can($account, Capability::AccountsView)) {
			try {
				$counts['accounts'] = count($this->accounts->all());
			} catch (AuthException) {
				// A damaged record leaves the count out.
			}

			$counts['roles'] = count($this->roles->all());
		}

		// From Site Health's last report, never a new check (D-545).
		if ($this->permissions->can($account, Capability::SiteHealth) && ($issues = $this->health->issues()) !== null) {
			$counts['health'] = $issues;
		}

		if ($this->permissions->can($account, Capability::SiteSettings)) {
			$counts['contentTypes'] = count($this->types->all());
			$counts['relations']    = count($this->types->relations());
			$counts['fieldSets']    = count($this->types->sets->all());
		}

		if ($this->permissions->can($account, ExtensionAction::View->on(ExtensionKind::Theme))) {
			$counts['themes'] = count($this->themes->all()) + count($this->themes->invalid());
		}

		if ($this->permissions->can($account, ExtensionAction::View->on(ExtensionKind::IconPack))) {
			$counts['iconPacks'] = count($this->iconPacks->all()) + count($this->iconPacks->invalid()) + 1;
		}

		if ($this->permissions->can($account, ExtensionAction::View->on(ExtensionKind::Plugin))) {
			try {
				$counts['plugins'] = PluginDiscovery::forPaths($this->paths)->discover()->count();
			} catch (ExtensionException) {
				// Left out, as the Plugins screen reports the problem.
			}
		}

		return self::json($counts);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
