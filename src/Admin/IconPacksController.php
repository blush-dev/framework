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
use Blush\Auth\ExtensionAction;
use Blush\Auth\Permissions;
use Blush\Core\Framework;
use Blush\Core\Paths;
use Blush\Extension\ExtensionAuthor;
use Blush\Extension\ExtensionKind;
use Blush\Extension\ExtensionLicense;
use Blush\Extension\ExtensionState;
use Blush\Extension\LocalExtensions;
use Blush\Extension\Install\ExtensionInstaller;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Icon\IconPack;
use Blush\Icon\IconPacks;
use Blush\Icon\IconPackSource;
use Blush\Settings\Setting;
use Blush\Settings\SettingsFile;

/**
 * Answers the Icon Packs screens (D-378, D-385), for accounts with
 * `extensions.icon-packs.view` (D-389). Only icon packs are listed, not the icons themes and
 * plugins carry, plus Blush's own set:
 *
 * - `GET icon-packs`: every installed pack, by label, with its `name`,
 *   `label`, `namespace`, `version`, `description`, `authors` (D-384's
 *   shape), `license` and `licenses` (as `GET plugins` has them, D-426,
 *   D-427), `links` and `funding` (as `GET plugins` has them, D-428),
 *   `source` (`local` or `composer`), `path` (from the site's
 *   root), `folder` (its folder in `extensions/`, or `null`), whether it's
 *   `enabled` (turned on) and `running` (on, with its requirements met,
 *   so its icons load), its `requirements`, `blocked`, and `requiredBy`
 *   (as `GET plugins` has them, D-431), how many icons it has (`count`), the first twelve
 *   (`icons`, each `{"name", "svg"}`, the name in full, `weather/sun`),
 *   and whether it's `deletable` (a folder in `extensions/`); the `core`
 *   set the same way (`label`, `version`, `count`, `icons`, with short
 *   names, as the icon directive takes them); the `invalid` ones, by
 *   `where` they were found, with the `reason` and whether they're
 *   `deletable`; `saved` (the admin's list of packs turned on is in
 *   `user/data/settings.json`); and `config` (whether `config/icons.php`
 *   exists).
 * - `GET icon-packs/{vendor}/{name}`: one pack, with every icon.
 * - `GET icon-packs/core`: the core set, with every icon.
 *
 * An icon's `svg` is its file, for the admin to draw as a mask (so no
 * markup from it runs), or empty when it's too large to send.
 */
final readonly class IconPacksController
{
	/**
	 * How many icons the list sends for each pack.
	 */
	private const int SAMPLE = 12;

	/**
	 * Larger files aren't sent; the screen shows the name instead.
	 */
	private const int MAX_SVG = 16384;

	public function __construct(
		private IconPacks $packs,
		private ExtensionState $extensions,
		private Paths $paths,
		private SettingsFile $settings,
		private Permissions $permissions,
		private ExtensionInstaller $installer
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::forbidden();
		}

		$packs = array_values(array_map(fn (IconPack $pack): array => $this->pack($pack, self::SAMPLE), $this->packs->all()));

		usort($packs, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

		$invalid = [];

		foreach ($this->packs->invalid() as $where => $reason) {
			$name      = LocalExtensions::nameAt($this->paths, $where);
			$invalid[] = ['where' => $where, 'reason' => $reason, 'deletable' => $name !== null && is_dir(LocalExtensions::path($this->paths, $name))];
		}

		return Response::json([
			'packs'   => $packs,
			'core'    => self::coreSet(self::SAMPLE),
			'invalid' => $invalid,
			'saved'   => $this->settings->read()->has(Setting::IconPacks),
			'config'  => is_file("{$this->paths->config}/icons.php"),
			'upload'  => ExtensionInstallController::upload($this->installer, ExtensionKind::IconPack)
		], headers: ['Cache-Control' => 'no-store']);
	}

	public function show(ServerRequestInterface $request, string $vendor, string $name): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::forbidden();
		}

		$pack = $this->packs->find("{$vendor}/{$name}");

		if ($pack === null) {
			return Response::json(['error' => sprintf('No icon pack named %s/%s is installed.', $vendor, $name)], Status::NotFound, ['Cache-Control' => 'no-store']);
		}

		return Response::json(['pack' => $this->pack($pack)], headers: ['Cache-Control' => 'no-store']);
	}

	public function core(ServerRequestInterface $request): ResponseInterface
	{
		return $this->allowed($request)
			? Response::json(['core' => self::coreSet()], headers: ['Cache-Control' => 'no-store'])
			: self::forbidden();
	}

	/**
	 * Describes a pack, with its first `$limit` icons, or all of them.
	 *
	 * @return array{name: string, label: string, namespace: string, version: string, description: string, authors: list<array<string, string>>, license: string, licenses: list<array{text: string, url: ?string, operator: bool}>, links: list<array{kind: string, url: string}>, funding: list<array{type: string, url: string}>, source: string, path: string, folder: ?string, enabled: bool, deletable: bool, backup: ?array{version: string}, count: int, icons: list<array{name: string, svg: string}>}
	 */
	private function pack(IconPack $pack, ?int $limit = null): array
	{
		$files  = self::files($pack->iconsPath());
		$folder = $pack->source === IconPackSource::Local && LocalExtensions::contains($this->paths, $pack->path)
			? $this->paths->relative($pack->path)
			: null;

		return [
			'name'        => $pack->name,
			'label'       => $pack->label,
			'namespace'   => $pack->namespace,
			'version'     => $pack->version,
			'description' => $pack->description,
			'authors'     => array_map(static fn (ExtensionAuthor $author): array => $author->toArray(), $pack->authors),
			'license'     => $pack->license,
			'licenses'    => ExtensionLicense::parts($pack->license),
			'links'       => $pack->links->links(),
			'funding'     => $pack->links->funding,
			'source'      => $pack->source->value,
			'path'        => $this->paths->relative($pack->path),
			'folder'      => $folder,
			'enabled'     => $this->packs->isEnabled($pack->name),
			'running'     => $this->extensions->runs($pack->name),
			...$this->extensions->report($pack),
			...$this->extensions->opposite($pack),
			'stops'       => $this->extensions->stops($pack),
			'deletable'   => $folder !== null,
			'backup'      => ExtensionInstallController::backup($this->installer, ExtensionKind::IconPack, $folder === null ? null : $pack->path, $pack->name),
			'count'       => count($files),
			'icons'       => self::icons(array_slice($files, 0, $limit), "{$pack->namespace}/")
		];
	}

	/**
	 * Describes the core set, with its first `$limit` icons, or all.
	 *
	 * @return array{label: string, version: string, count: int, icons: list<array{name: string, svg: string}>}
	 */
	private static function coreSet(?int $limit = null): array
	{
		$files = self::files(Framework::path('resources/icons/blush'));

		return [
			'label'   => 'Core',
			'version' => Framework::VERSION,
			'count'   => count($files),
			'icons'   => self::icons(array_slice($files, 0, $limit), '')
		];
	}

	/**
	 * A folder's SVG files, by name.
	 *
	 * @return list<string>
	 */
	private static function files(string $folder): array
	{
		$files = glob("{$folder}/*.svg") ?: [];

		sort($files);

		return $files;
	}

	/**
	 * Icons from their files, named with a prefix (`weather/`, or nothing
	 * for core icons).
	 *
	 * @param  list<string> $files
	 * @return list<array{name: string, svg: string}>
	 */
	private static function icons(array $files, string $prefix): array
	{
		return array_map(static fn (string $file): array => [
			'name' => $prefix . basename($file, '.svg'),
			'svg'  => is_readable($file) && filesize($file) <= self::MAX_SVG ? (string) file_get_contents($file) : ''
		], $files);
	}

	private function allowed(ServerRequestInterface $request): bool
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account && $this->permissions->can($account, ExtensionAction::View->on(ExtensionKind::IconPack));
	}

	private static function forbidden(): ResponseInterface
	{
		return Response::json(['error' => 'You aren\'t allowed to see the site\'s icon packs.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
	}
}
