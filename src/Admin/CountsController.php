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
use Blush\Auth\AccountStore;
use Blush\Auth\AuthException;
use Blush\Auth\Capability;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Auth\Roles;
use Blush\Content\ContentRepository;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\TypeKind;
use Blush\Core\Paths;
use Blush\Extension\ExtensionException;
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
 *   its index page or people pages, as `GET entries` counts them).
 * - `media`: the files in the library, with `media.upload` (D-372).
 * - `accounts` and `roles`, with `accounts.view`.
 * - `contentTypes`, `fieldSets`, `themes`, `plugins`, and `iconPacks`
 *   (installed), with `site.settings` (`themes` since D-372,
 *   `iconPacks` since D-378).
 *
 * A count the account may not see is left out.
 */
final readonly class CountsController
{
	public function __construct(
		private ContentRepository $content,
		private ContentTypes $types,
		private Permissions $permissions,
		private AccountStore $accounts,
		private Roles $roles,
		private Paths $paths,
		private MediaLibrary $media,
		private Themes $themes,
		private IconPacks $iconPacks
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
			$query = $type->kind() === TypeKind::Pages ? $query : $query->withLanding(false)->exceptNames(...PeoplePage::listPages($type))->exceptIn(...PeoplePage::personFolders($type));

			$types[$type->name] = $query->count();
		}

		$counts = ['types' => $types];

		if ($this->permissions->can($account, Capability::MediaUpload)) {
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

		if ($this->permissions->can($account, Capability::SiteSettings)) {
			$counts['contentTypes'] = count($this->types->all());
			$counts['fieldSets']    = count($this->types->sets->all());
			$counts['themes']       = count($this->themes->all());
			$counts['iconPacks']    = count($this->iconPacks->all());

			try {
				$counts['plugins'] = count(PluginDiscovery::forPaths($this->paths)->discover());
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
