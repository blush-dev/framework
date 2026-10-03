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

use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\ExtensionAction;
use Blush\Auth\Permissions;
use Blush\Cache\ContentVersion;
use Blush\Content\Index\Indexer;
use Blush\Content\Type\ContentTypeCache;
use Blush\Content\Type\ContentTypes;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Extension\ExtensionKind;
use Blush\Field\FieldContext;
use Blush\Field\InvalidField;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Routing\RouteCache;
use Blush\Settings\InvalidSetting;
use Blush\Settings\Setting;
use Blush\Settings\Settings;
use Blush\Settings\SettingsFile;
use Blush\Settings\SiteSettings;
use Blush\Theme\ThemeException;
use Blush\Theme\Themes;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Saves the site-wide settings the admin can change (D-324, D-325), for accounts
 * with `site.settings`, except three that belong to an extension screen
 * and need that kind's capability instead (D-389): the active theme
 * (`theme.active`, `extensions.themes.activate`), and the plugins and
 * icon packs turned off (`plugins.disabled`, `icons.disabled`,
 * `extensions.plugins.activate` and `extensions.icon-packs.activate`):
 *
 * - `PATCH settings`: `{"set": {setting: value}, "unset": [setting]}`,
 *   by `Setting` value (`feed.limit`), or `site.{name}` for a setting a
 *   field set adds (D-343), checked by its field and saved as sent (an
 *   empty one removed). `set` saves values in
 *   `user/data/settings.json`; `unset` removes saved ones, so their
 *   config values are used again. Answers `{"saved", "refresh"}`: the
 *   saved settings as the file holds them, and whether the admin should
 *   ask for `settings/refresh`.
 * - `POST settings/refresh`: compiles the content types and routes again
 *   (when the site is compiled) and reindexes, so the next requests see
 *   the change. Any account that may change one of the settings may ask.
 *
 * A value that doesn't fit, a home page that isn't a collection with
 * addresses, or a theme (`theme.active`, saved by the Themes screen,
 * D-381) that isn't installed or can't build its chain, is a `422` with
 * the reason, and nothing is written.
 *
 * Settings are read when the site boots, so the next request has them.
 * A change to what has addresses or how dates are read (the home page,
 * time zone, trailing slash, feed formats, sitemap, or theme) also deletes the
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
		private Permissions $permissions,
		private SiteSettings $site,
		private FieldContext $context,
		private Themes $themes
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

		if (! $this->allowed($request, [...array_keys($set), ...$unset])) {
			return self::error('You aren\'t allowed to change those settings.', Status::Forbidden);
		}

		// Field sets' settings (D-343) are `site.{name}`; the rest are `Setting`s.
		$prefix   = Settings::SITE . '.';
		$isSite   = static fn (mixed $key): bool => is_string($key) && str_starts_with($key, $prefix);
		$siteSet  = [];
		$builtIns = [];

		foreach ($set as $key => $value) {
			if ($isSite($key)) {
				$siteSet[substr((string) $key, strlen($prefix))] = $value;
			} else {
				$builtIns[$key] = $value;
			}
		}

		try {
			$removed = array_map(
				static fn (mixed $key): Setting => Setting::tryFrom(is_string($key) ? $key : '') ?? throw new InvalidSetting(sprintf('"%s" isn\'t a setting the admin can change.', is_string($key) ? $key : get_debug_type($key))),
				array_values(array_filter($unset, static fn (mixed $key): bool => ! $isSite($key)))
			);
			$site = [
				...array_fill_keys(array_map(static fn (mixed $key): string => is_string($key) ? substr($key, strlen($prefix)) : '', array_values(array_filter($unset, $isSite))), null),
				...$this->checkSite($siteSet)
			];
			$check = Settings::none()->with($builtIns);
			$this->assertHome($check);
			$this->assertTheme($check);

			$changed  = [...array_filter(Setting::cases(), $check->has(...)), ...$removed];
			$settings = $this->file->update(static fn (Settings $settings): Settings => $settings->without(...$removed)->with($builtIns)->withSite($site));
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
	 * Checks field sets' settings by their fields, and returns them to
	 * save as they were sent; an empty one is removed (`null`).
	 *
	 * @param  array<array-key, mixed> $values By field name.
	 * @return array<string, mixed>
	 * @throws InvalidSetting When a name isn't a setting, or a value doesn't fit.
	 */
	private function checkSite(array $values): array
	{
		$schema  = $this->site->schema();
		$checked = [];

		foreach ($values as $name => $value) {
			$name  = (string) $name;
			$field = $schema->field($name) ?? throw new InvalidSetting(sprintf('"%s.%s" isn\'t a setting the admin can change.', Settings::SITE, $name));
			$label = $field->label === '' ? ucfirst(str_replace('_', ' ', $field->name)) : $field->label;
			$empty = $value === null || $value === '' || $value === [];

			if ($empty && $field->required) {
				throw new InvalidSetting(sprintf('%s is required.', $label));
			}

			if (! $empty) {
				try {
					$field->normalize($value, $this->context);
				} catch (InvalidField $error) {
					throw new InvalidSetting(sprintf('%s %s', $label, $error->getMessage()), previous: $error);
				}
			}

			$checked[$field->name] = $empty ? null : $value;
		}

		return $checked;
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

		if (! array_key_exists($home, Setting::homeChoices($this->types))) {
			throw new InvalidSetting(sprintf('"%s" can\'t be the home page: it must be a collection type with addresses.', $home));
		}
	}

	/**
	 * Checks that a theme being made active is installed, with every
	 * theme it falls back to.
	 *
	 * @throws InvalidSetting
	 */
	private function assertTheme(Settings $settings): void
	{
		$theme = $settings->get(Setting::Theme);

		if (! is_string($theme)) {
			return;
		}

		try {
			$this->themes->chain($theme);
		} catch (ThemeException $error) {
			throw new InvalidSetting($error->getMessage(), previous: $error);
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

	/**
	 * Whether the account may change every setting named, or, with none
	 * named, any setting at all (to refresh after one).
	 *
	 * @param list<mixed> $keys
	 */
	private function allowed(ServerRequestInterface $request, ?array $keys = null): bool
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account) {
			return false;
		}

		$needs = array_map(static fn (mixed $key): string => self::capability(is_string($key) ? Setting::tryFrom($key) : null), $keys ?? []);

		return $keys === null
			? array_any([null, Setting::Theme, Setting::Plugins, Setting::IconPacks], fn (?Setting $setting): bool => $this->permissions->can($account, self::capability($setting)))
			: array_all(array_unique($needs ?: [Capability::SiteSettings->value]), fn (string $capability): bool => $this->permissions->can($account, $capability));
	}

	/**
	 * Returns the capability changing a setting needs: an extension
	 * kind's for the settings its screen saves, `site.settings` for the
	 * rest (and for a field set's `site.{name}`).
	 */
	private static function capability(?Setting $setting): string
	{
		return match ($setting) {
			Setting::Theme     => ExtensionAction::Activate->on(ExtensionKind::Theme),
			Setting::Plugins   => ExtensionAction::Activate->on(ExtensionKind::Plugin),
			Setting::IconPacks => ExtensionAction::Activate->on(ExtensionKind::IconPack),
			default            => Capability::SiteSettings->value
		};
	}

	private static function error(string $message, Status $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
