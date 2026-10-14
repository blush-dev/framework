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

namespace Blush\Admin\Http;

use Blush\Extension\ExtensionText;
use JsonException;
use Throwable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\EmailChanges;
use Blush\Auth\ExtensionAction;
use Blush\Auth\Permissions;
use Blush\Auth\Roles;
use Blush\Cache\CacheException;
use Blush\Cache\ContentVersion;
use Blush\Content\Index\Indexer;
use Blush\Content\Type\ContentTypeCache;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Extension\ExtensionKind;
use Blush\Extension\ExtensionState;
use Blush\Extension\Requirements;
use Blush\Field\FieldContext;
use Blush\Field\InvalidField;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Mail\Address;
use Blush\Mail\MailTransport;
use Blush\Mail\Mailer;
use Blush\Mail\MailException;
use Blush\Mail\Message;
use Blush\Notification\MailContent;
use Blush\Notification\MailLayout;
use Blush\Notification\Notifiable;
use Blush\Routing\RouteCache;
use Blush\Settings\InvalidSetting;
use Blush\Settings\Setting;
use Blush\Settings\Settings;
use Blush\Settings\SettingsStore;
use Blush\Settings\SiteSettings;
use Blush\Theme\ThemeException;
use Blush\Theme\Themes;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Saves the site-wide settings the admin can change (D-324, D-325), for accounts
 * with `site.settings`, except three that belong to an extension screen
 * and need that kind's capability instead (D-389): the active theme
 * (`theme.active`, `extensions.themes.activate`), and the plugins and
 * icon packs turned on (`plugins.enabled`, `icons.enabled`,
 * `extensions.plugins.activate` and `extensions.icon-packs.activate`):
 *
 * - `PATCH settings`: `{"set": {setting: value}, "unset": [setting]}`,
 *   by `Setting` value (`feed.limit`), or `site.{name}` for a setting a
 *   field set adds (D-343), checked by its field and saved as sent (an
 *   empty one removed). `set` saves values in
 *   the saved settings (`user/data/settings/`); `unset` removes saved ones, so their
 *   config values are used again. Answers `{"saved", "refresh",
 *   "emailChange"}`: the saved settings as the file holds them, whether
 *   the admin should ask for `settings/refresh`, and the change of the
 *   site's email address that waits, if this one asked for it: where the
 *   site can send mail, a new `app.email` isn't saved but emailed a link
 *   to confirm, and the old address is told (D-849); `null` otherwise.
 * - `POST settings/refresh`: compiles the content types and routes again
 *   (when the site is compiled) and reindexes, so the next requests see
 *   the change. Any account that may change one of the settings may ask.
 * - `POST settings/email/test` (`site.settings`, D-846): sends a short
 *   email to the signed-in account's address, now rather than as a job,
 *   so a transport that fails says why. Answers `{"sent"}` with the
 *   address, or a `422` with the transport's error.
 *
 * A value that doesn't fit, a homepage that isn't a collection with
 * addresses, a role for new accounts that isn't one (D-518), or a theme (`theme.active`, saved by the Themes screen,
 * D-381) that isn't installed or can't build its chain, is a `422` with
 * the reason, and nothing is written.
 *
 * Settings are read when the site boots, so the next request has them.
 * A change to what has addresses or how dates are read (the homepage,
 * time zone, trailing slash, feed formats, sitemap, Markdown copies, or
 * theme) also deletes the
 * compiled content types and routes, so the site builds them from the
 * new settings until the admin's `settings/refresh`, which runs with
 * them, compiles them again. Every save moves the content version on,
 * so cached pages go.
 */
final readonly class SettingsEditController
{
	public function __construct(
		private SettingsStore $store,
		private ContentTypes $types,
		private ContentTypeCache $typeCache,
		private RouteCache $routes,
		private Indexer $indexer,
		private ContentVersion $version,
		private Bootstrap $bootstrap,
		private Permissions $permissions,
		private SiteSettings $site,
		private FieldContext $context,
		private Themes $themes,
		private ExtensionState $extensions,
		private Roles $roles,
		private ExtensionText $text,
		private Mailer $mailer,
		private MailTransport $mail,
		private AppConfig $app,
		private MailLayout $layout,
		private EmailChanges $changes,
		private Messages $messages
	) {}

	/**
	 * @throws CacheException
	 */
	public function update(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::error($this->messages->t('admin.settings_edit_controller.not_allowed', 'You aren\'t allowed to change the site\'s settings.'), Status::Forbidden);
		}

		$input = self::input($request);
		$set   = $input['set'] ?? [];
		$unset = $input['unset'] ?? [];

		if (! is_array($set) || ($set !== [] && array_is_list($set)) || ! is_array($unset) || ! array_is_list($unset)) {
			return self::error('Send "set" (settings to values) and "unset" (a list of settings).', Status::BadRequest);
		}

		if (! $this->allowed($request, [...array_keys($set), ...$unset])) {
			return self::error($this->messages->t('admin.settings_edit_controller.not_allowed_those', 'You aren\'t allowed to change those settings.'), Status::Forbidden);
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

		// A new site address waits on a confirmation link (D-849) where the
		// site can send mail and has an address to change from.
		$account = $request->getAttribute(Account::class);
		$address = $builtIns[Setting::Email->value] ?? null;
		$confirm = is_string($address) && $account instanceof Account && $this->changes->needsConfirming($this->app->email, $address);

		if ($confirm) {
			unset($builtIns[Setting::Email->value]);
		}

		try {
			if ($confirm) {
				Settings::none()->with([Setting::Email->value => $address], $this->messages);
			}

			$removed = array_map(
				static fn (mixed $key): Setting => Setting::tryFrom(is_string($key) ? $key : '') ?? throw new InvalidSetting(sprintf('"%s" isn\'t a setting the admin can change.', is_string($key) ? $key : get_debug_type($key))),
				array_values(array_filter($unset, static fn (mixed $key): bool => ! $isSite($key)))
			);
			$site = [
				...array_fill_keys(array_map(static fn (mixed $key): string => is_string($key) ? substr($key, strlen($prefix)) : '', array_values(array_filter($unset, $isSite))), null),
				...$this->checkSite($siteSet)
			];
			$check = Settings::none()->with($builtIns, $this->messages);
			$this->assertHome($check);
			$this->assertSignupRole($check);
			$this->assertTheme($check);
			$this->assertUploads($check);

			$changed  = [...array_filter(Setting::cases(), $check->has(...)), ...$removed];
			$settings = $this->store->update(function (Settings $settings) use ($removed, $builtIns, $site): Settings {
				$updated = $settings->without(...$removed)->with($builtIns, $this->messages)->withSite($site);
				$this->bootstrap->checkSettings($updated, $this->messages);

				return $updated;
			});
		} catch (InvalidSetting $error) {
			return self::error($error->getMessage(), Status::UnprocessableContent);
		}

		$refresh = array_any($changed, static fn (Setting $setting): bool => $setting->needsRefresh());

		if ($refresh) {
			$this->bootstrap->clearCompiled(CompiledCache::ContentTypes, CompiledCache::Routes);
		}

		$this->version->bump();

		$change = $confirm ? $this->changes->askForSite($address, $account) : null;

		return Response::json([
			'saved'       => $settings->toArray() ?: (object) [],
			'refresh'     => $refresh,
			'emailChange' => $change === null ? null : ['email' => $change->email, 'expires' => $change->expires]
		], headers: ['Cache-Control' => 'no-store']);
	}

	public function testEmail(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, Capability::SiteSettings)) {
			return self::error($this->messages->t('admin.settings_edit_controller.not_allowed', 'You aren\'t allowed to change the site\'s settings.'), Status::Forbidden);
		}

		if (! $this->mail->isSetUp()) {
			return self::error($this->messages->t('admin.settings_edit_controller.no_mail', 'The site can\'t send email: set MAIL_DSN in .env to a transport first.'), Status::UnprocessableContent);
		}

		if ($account->email === null || $account->email === '') {
			return self::error($this->messages->t('admin.settings_edit_controller.test_no_address', 'Your account has no email address to send a test to.'), Status::UnprocessableContent);
		}

		try {
			$to      = new Notifiable(new Address($account->email, str_replace(["\r", "\n"], ' ', $account->name ?? '')), $account);
			$content = new MailContent($this->messages->t('admin.settings_edit_controller.test_subject', 'A test from {site}', site: $this->app->name))
				->line($this->messages->t('admin.settings_edit_controller.test_hello', 'Hi {name},', name: $to->name()))
				->line($this->messages->t('admin.settings_edit_controller.test_body', 'This is the test you sent from Settings → Email. It arrived, so the site\'s email works.'));

			$this->mailer->send(new Message([$to->address], $content->subject, $this->layout->text($content, $to), $this->layout->html($content, $to)));
		} catch (MailException $error) {
			return self::error($this->messages->t('admin.settings_edit_controller.test_failed', 'The test wasn\'t sent: {error}', error: $error->getMessage()), Status::UnprocessableContent);
		}

		return Response::json(['sent' => $account->email], headers: ['Cache-Control' => 'no-store']);
	}

	public function refresh(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::error($this->messages->t('admin.settings_edit_controller.not_allowed', 'You aren\'t allowed to change the site\'s settings.'), Status::Forbidden);
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
				throw new InvalidSetting((string) $this->messages->t('admin.settings_edit_controller.required', '{field} is required.', field: $label));
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
	 * Checks that a homepage being set is a collection with addresses.
	 *
	 * @throws InvalidSetting
	 */
	private function assertHome(Settings $settings): void
	{
		$home = $settings->get(Setting::Home);

		if (! is_string($home)) {
			return;
		}

		if (! array_key_exists($home, Setting::homeChoices($this->types, $this->messages))) {
			throw new InvalidSetting((string) $this->messages->t('admin.settings_edit_controller.not_home', '"{type}" can\'t be the homepage: it must be a collection type with addresses.', type: $home));
		}
	}

	/**
	 * Checks that a role for new accounts being set is one of the site's.
	 *
	 * @throws InvalidSetting
	 */
	private function assertSignupRole(Settings $settings): void
	{
		$role = $settings->get(Setting::SignupRole);

		if (is_string($role) && ! $this->roles->has($role)) {
			throw new InvalidSetting((string) $this->messages->t('admin.settings_edit_controller.not_role', '"{role}" isn\'t a role on this site.', role: $role));
		}
	}

	/**
	 * Checks that no largest file in the upload rules is more than PHP
	 * takes (D-406), which would never hold. It's checked here, not when
	 * the settings are read, so a server's limit going down later doesn't
	 * stop the site.
	 *
	 * @throws InvalidSetting
	 */
	private function assertUploads(Settings $settings): void
	{
		$rules  = $settings->get(Setting::MediaUploads);
		$server = MediaUploadController::limit();

		if (! is_array($rules) || $server === null) {
			return;
		}

		$sizes = [$rules['maxSize'] ?? null, ...array_map(static fn (mixed $rule): mixed => is_array($rule) ? $rule['maxSize'] ?? null : null, is_array($rules['kinds'] ?? null) ? $rules['kinds'] : [])];

		foreach ($sizes as $size) {
			if (is_int($size) && $size * 1024 * 1024 > $server) {
				throw new InvalidSetting((string) $this->messages->t('admin.settings_edit_controller.upload_too_big', 'The server takes files of at most {server} MB, so a largest file of {size} MB won\'t hold.', server: intdiv($server, 1024 * 1024), size: $size));
			}
		}
	}

	/**
	 * Checks that a theme being made active is installed, with every
	 * theme it falls back to, and that its chain's requirements are met
	 * (D-431).
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
			$this->themes->chain($theme, $this->messages);
		} catch (ThemeException $error) {
			throw new InvalidSetting($error->getMessage(), previous: $error);
		}

		$unmet = $this->extensions->with(theme: $theme)->themes->unmet();

		if ($unmet !== []) {
			$found = $this->themes->find($theme);

			throw new InvalidSetting((string) $this->messages->t('admin.settings_edit_controller.theme_unmet', '{theme} can\'t be activated. {reason}', theme: $found === null ? $theme : (string) $this->text->label($found), reason: Requirements::reason(array_merge(...array_values($unmet)), $this->messages)));
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
			? array_any([null, Setting::Theme, Setting::Plugins, Setting::IconPacks, Setting::AdminThemes], fn (?Setting $setting): bool => $this->permissions->can($account, self::capability($setting)))
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
			Setting::AdminThemes,
			Setting::AdminThemeDefault => ExtensionAction::Activate->on(ExtensionKind::AdminTheme),
			default            => Capability::SiteSettings->value
		};
	}

	private static function error(string|Text $message, Status $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
