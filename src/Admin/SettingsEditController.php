<?php

/**
 * Admin settings editing controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use JsonException;
use Throwable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Cache\ContentVersion;
use Blush\Content\Index\Indexer;
use Blush\Content\Type\ContentTypeCache;
use Blush\Content\Type\ContentTypes;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Routing\RouteCache;
use Blush\Settings\InvalidSetting;
use Blush\Settings\Setting;
use Blush\Settings\Settings;
use Blush\Settings\SettingsFile;

/**
 * Saves the site-wide settings the admin can change (D-324, D-325), for accounts
 * with `site.settings`:
 *
 * - `PATCH settings`: `{"set": {setting: value}, "unset": [setting]}`,
 *   by `Setting` value (`feed.limit`). `set` saves values in
 *   `user/data/settings.json`; `unset` removes saved ones, so their
 *   config values are used again. Answers `{"saved", "refresh"}`: the
 *   saved settings as the file holds them, and whether the admin should
 *   ask for `settings/refresh`.
 * - `POST settings/refresh`: compiles the content types and routes again
 *   (when the site is compiled) and reindexes, so the next requests see
 *   the change.
 *
 * A value that doesn't fit, or a home page that isn't a collection with
 * addresses, is a `422` with the reason, and nothing is written.
 *
 * Settings are read when the site boots, so the next request has them.
 * A change to what has addresses or how dates are read (the home page,
 * time zone, trailing slash, feed formats, or sitemap) also deletes the
 * compiled content types and routes, so the site builds them from the
 * new settings until the admin's `settings/refresh`, which runs with
 * them, compiles them again. Every save moves the content version on,
 * so cached pages go.
 */
final readonly class SettingsEditController
{
	public function __construct(
		private SettingsFile $file,
		private ContentTypes $types,
		private ContentTypeCache $typeCache,
		private RouteCache $routes,
		private Indexer $indexer,
		private ContentVersion $version,
		private Bootstrap $bootstrap,
		private Permissions $permissions
	) {}

	public function update(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::error('You aren\'t allowed to change the site\'s settings.', Status::Forbidden);
		}

		$input = self::input($request);
		$set   = $input['set'] ?? [];
		$unset = $input['unset'] ?? [];

		if (! is_array($set) || ($set !== [] && array_is_list($set)) || ! is_array($unset) || ! array_is_list($unset)) {
			return self::error('Send "set" (settings to values) and "unset" (a list of settings).', Status::BadRequest);
		}

		try {
			$removed = array_map(
				static fn (mixed $key): Setting => Setting::tryFrom(is_string($key) ? $key : '') ?? throw new InvalidSetting(sprintf('"%s" isn\'t a setting the admin can change.', is_string($key) ? $key : get_debug_type($key))),
				$unset
			);
			$check = Settings::none()->with($set);
			$this->assertHome($check);

			$changed  = [...array_filter(Setting::cases(), $check->has(...)), ...$removed];
			$settings = $this->file->update(static fn (Settings $settings): Settings => $settings->without(...$removed)->with($set));
		} catch (InvalidSetting $error) {
			return self::error($error->getMessage(), Status::UnprocessableContent);
		}

		$refresh = array_any($changed, static fn (Setting $setting): bool => $setting->needsRefresh());

		if ($refresh) {
			$this->bootstrap->clearCompiled(CompiledCache::ContentTypes, CompiledCache::Routes);
		}

		$this->version->bump();

		return Response::json(['saved' => $settings->toArray() ?: (object) [], 'refresh' => $refresh], headers: ['Cache-Control' => 'no-store']);
	}

	public function refresh(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::error('You aren\'t allowed to change the site\'s settings.', Status::Forbidden);
		}

		try {
			$compiled = is_file($this->bootstrap->compiledPath(CompiledCache::Config));

			if ($compiled) {
				$this->typeCache->write();
				$this->routes->write();
			}

			$report = $this->indexer->index();
		} catch (Throwable $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		$this->version->bump();

		return Response::json(['compiled' => $compiled, 'indexed' => $report->total], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Checks that a home page being set is a collection with addresses.
	 *
	 * @throws InvalidSetting
	 */
	private function assertHome(Settings $settings): void
	{
		$home = $settings->get(Setting::Home);

		if (! is_string($home)) {
			return;
		}

		$allowed = array_column(SettingsController::homeOptions($this->types), 'value');

		if (! in_array($home, $allowed, true)) {
			throw new InvalidSetting(sprintf('"%s" can\'t be the home page: it must be a collection type with addresses.', $home));
		}
	}

	/**
	 * The request's JSON object, or an empty array.
	 *
	 * @return array<array-key, mixed>
	 */
	private static function input(ServerRequestInterface $request): array
	{
		try {
			$input = json_decode((string) $request->getBody(), true, 64, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return [];
		}

		return is_array($input) ? $input : [];
	}

	private function allowed(ServerRequestInterface $request): bool
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account && $this->permissions->can($account, Capability::SiteSettings);
	}

	private static function error(string $message, Status $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
