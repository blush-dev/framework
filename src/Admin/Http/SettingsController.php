<?php

/**
 * Admin settings controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Http;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Admin\Provenance;
use Blush\Api\ApiAccess;
use Blush\Api\ApiConfig;
use Blush\Auth\Account;
use Blush\Auth\AuthConfig;
use Blush\Auth\Capability;
use Blush\Auth\EmailChanges;
use Blush\Auth\Permissions;
use Blush\Auth\Role;
use Blush\Auth\Roles;
use Blush\Cache\CacheConfig;
use Blush\Cache\CacheException;
use Blush\Cache\PageCache;
use Blush\Clock\DateFormat;
use Blush\Clock\TimeZones;
use Blush\Content\ContentConfig;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
use Blush\Core\Environment;
use Blush\Embed\EmbedConfig;
use Blush\Embed\EmbedProvider;
use Blush\Embed\EmbedProviders;
use Blush\Feed\FeedConfig;
use Blush\Feed\FeedFormat;
use Blush\Field\ControlDefaults;
use Blush\Field\FieldControls;
use Blush\Field\FormField;
use Blush\Field\Field;
use Blush\Field\FieldSets;
use Blush\Field\Fields\EnumField;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Llms\LlmsConfig;
use Blush\Llms\LlmsRoutes;
use Blush\Llms\LlmsTxt;
use Blush\Mail\MailConfig;
use Blush\Mail\MailTransport;
use Blush\Markdown\MarkdownConfig;
use Blush\Media\Index\MediaLibrary;
use Blush\Media\Index\MediaQuery;
use Blush\Mcp\McpConfig;
use Blush\Mcp\McpTools;
use Blush\Mcp\OAuth\OAuthServer;
use Blush\Media\MediaConfig;
use Blush\Media\MediaException;
use Blush\Media\MediaKind;
use Blush\Media\MediaResolver;
use Blush\Media\MediaUploads;
use Blush\Preview\PreviewConfig;
use Blush\Publish\PublishConfig;
use Blush\Routing\RouteConfig;
use Blush\Settings\Setting;
use Blush\Settings\Settings;
use Blush\Settings\SettingsStore;
use Blush\Settings\SettingsScreen;
use Blush\Settings\SettingsTarget;
use Blush\Sitemap\AiCrawlerGroup;
use Blush\Sitemap\SitemapConfig;
use Blush\Tool\ToolRegistry;
use Blush\Tool\Tools;
use Blush\Translation\Locales;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Answers `GET {path}/api/settings/{screen}` (D-309, D-324, D-325), for
 * accounts with `site.settings`: one Settings screen (`general`,
 * `reading`, `writing` (D-494), `media` (D-406), `email` (D-846), `search`, `ai` (D-398), or `system`) as `groups` of settings (`key`,
 * `title`, `hint`, and a `note`, where backticks mark code), each with
 * `items`: a `key`, `label`, the `value` to show, its `kind` (`text`,
 * `mono`, `bool`, or `list`; a `bool`'s value is `true` or `false`, a
 * `list`'s a list), whether it's still the `default` (`null` for one that
 * follows from others, such as the environment), the `file` it's set in
 * by convention (`null` when it follows from others), and optional
 * `help`. A `warning` marks a value that's risky where it is (detailed
 * errors on a live site). A shown setting may have a `link` (`label`
 * and `href`, a page on the site) or `links` (`label` and `to`, admin
 * paths), such as `llms.txt` and the types it lists, and an `aside`, a
 * short fact a `compact` group's row shows at its end (D-765). An
 * editable one may name the setting it `requires` (`setting`, and a `note` saying
 * so): while that's off in the form, it's locked.
 *
 * A setting the admin can change (`Setting`) adds the `setting` it saves
 * as (`feed.limit`), its `field` (D-343: as forms take it, with the
 * `control` to draw, `choices` naming its options, and a `caption` for a
 * checkbox or an empty choice), the `input` the form starts from, and
 * whether it's `saved` in the saved settings (`user/data/settings/`); its `file` is where
 * its value comes from when it isn't. After a screen's own groups, each
 * field set on it (`settings:{screen}`) adds a group of its settings,
 * saved as `site.{name}`, with no `file` behind them. Media's upload
 * rules are one setting drawn as a grid (`kind` `uploads`, D-406; see
 * `media()`). AI's crawler groups are one list drawn as a switch for
 * each option (`kind` `switches`, with the `words` a switch says), and
 * its tools one item of `kind` `tools` (D-765; see `toolGroups()`).
 * The site's email address (`app.email`) adds `emailChange`, a new one
 * waiting to be confirmed (`email`, `expires`; D-849), or `null`.
 * The language (`app.locale`) adds `locales`, the menu it's
 * picked from (`Locales::options()`, D-441), and may be any code typed
 * instead. The time zone adds `menu`, the options its searchable menu
 * is drawn from (`TimeZones::options()`, D-444). The date and time
 * formats add `formats`, their menus (`DateFormat::options()`, D-445),
 * and may be any pattern typed instead; each links to ICU's pattern
 * letters (D-446). The role for new accounts (`auth.signupRole`,
 * D-518) is chosen from the site's roles, but for those signing up can
 * never give. The rest live in `config/` and `.env`
 * (D-039) and are only shown, beside the ones they relate to. Secrets are
 * never sent: only whether one is set.
 */
final readonly class SettingsController
{
	public function __construct(
		private AppConfig $app,
		private ContentConfig $content,
		private ContentTypes $types,
		private RouteConfig $routes,
		private MediaConfig $media,
		private MarkdownConfig $markdown,
		private FeedConfig $feeds,
		private SitemapConfig $sitemap,
		private LlmsConfig $llms,
		private LlmsTxt $llmsTxt,
		private CacheConfig $cache,
		private PublishConfig $publish,
		private PreviewConfig $preview,
		private SettingsStore $store,
		private ClockInterface $clock,
		private Permissions $permissions,
		private FieldSets $fieldSets,
		private MediaLibrary $library,
		private AuthConfig $auth,
		private Roles $roles,
		private EmbedConfig $embed,
		private EmbedProviders $embeds,
		private ApiConfig $api,
		private McpConfig $mcp,
		private McpTools $mcpTools,
		private Tools $tools,
		private Provenance $provenance,
		private OAuthServer $oauth,
		private ControlDefaults $defaults,
		private MailConfig $mail,
		private MailTransport $transport,
		private EmailChanges $changes,
		private Messages $messages
	) {}

	public function __invoke(ServerRequestInterface $request, string $screen): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, Capability::SiteSettings)) {
			return Response::json(['error' => $this->messages->t('admin.settings_controller.not_allowed', 'You aren\'t allowed to see the site\'s settings.')], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		$saved = $this->store->read();

		$groups = match ($screen) {
			'general' => $this->general($saved),
			'reading' => $this->reading($saved),
			'writing' => $this->writing($saved),
			'media'   => $this->media($saved),
			'email'   => $this->email($saved, $account),
			'search'  => $this->search($saved),
			'ai'      => $this->ai($saved),
			'api'     => $this->apiScreen($saved, $account),
			'system'  => $this->system(),
			default   => null
		};

		$groups = $groups === null ? null : array_map(self::sourced(...), $groups);

		$target = SettingsScreen::tryFrom($screen);

		if ($groups !== null && $target !== null) {
			$groups = [...$groups, ...$this->sets($target, $saved)];
		}

		return $groups === null
			? Response::json(['error' => $this->messages->t('admin.settings_controller.no_screen', 'There\'s no such settings screen.')], Status::NotFound, ['Cache-Control' => 'no-store'])
			: Response::json(['groups' => $groups], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Answers `GET settings/date-format` (D-445): how a date or time
	 * format shows now, for the admin's preview of one typed in. Takes
	 * `format`, `kind` (`date` or `time`), and the `locale` the form has
	 * (the site's when it isn't a language code). Answers `{"text"}`, or
	 * a `422` saying what's wrong with the format.
	 */
	public function format(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, Capability::SiteSettings)) {
			return Response::json(['error' => $this->messages->t('admin.settings_controller.not_allowed', 'You aren\'t allowed to see the site\'s settings.')], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		$query   = $request->getQueryParams();
		$format  = is_string($query['format'] ?? null) ? trim($query['format']) : '';
		$locale  = is_string($query['locale'] ?? null) && preg_match('/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/', $query['locale']) === 1 ? $query['locale'] : $this->app->locale;
		$problem = DateFormat::problem($format, $this->messages);

		if ($problem !== null) {
			return Response::json(['error' => $problem], Status::UnprocessableContent, ['Cache-Control' => 'no-store']);
		}

		$now  = $this->clock->now();
		$text = ($query['kind'] ?? 'date') === 'time'
			? DateFormat::format($now, $locale, $this->app->timezone, null, $format)
			: DateFormat::format($now, $locale, $this->app->timezone, $format);

		return Response::json(['text' => $text], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * The groups the field sets on a screen add (D-343): one per set,
	 * headed by its label, each of its fields a setting saved in
	 * the `site` group of saved settings (`user/data/settings/site.json`) (`site.{name}`), with no
	 * config value behind it.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function sets(SettingsScreen $screen, Settings $saved): array
	{
		$values = $saved->site();
		$groups = [];

		foreach ($this->fieldSets->for(SettingsTarget::keyFor($screen)) as $set) {
			$items = [];

			foreach ($set->schema->fields as $name => $field) {
				$has     = array_key_exists($name, $values);
				$items[] = [
					...self::item("site-{$name}", $field->label === '' ? ucfirst(str_replace('_', ' ', $name)) : $field->label, '', ! $has, help: $field->description === '' ? null : $field->description),
					'setting' => Settings::SITE . ".{$name}",
					'field'   => FormField::of($field, $set->controls, $this->defaults)->toArray(),
					'input'   => $has ? $values[$name] : $field->default,
					'saved'   => $has
				];
			}

			$groups[] = self::group("set-{$set->name}", $set->label, $set->description === '' ? $this->messages->t('admin.settings_controller.field_set_hint', 'From the {set} field set', set: $set->label) : $set->description, $items);
		}

		return $groups;
	}

	/**
	 * The change of the site's email address waiting on its new address
	 * (D-849), as `{email, expires}`, or `null` for none.
	 *
	 * @return ?array{email: string, expires: int}
	 * @throws CacheException
	 */
	private function emailChange(): ?array
	{
		$change = $this->changes->pending(EmailChanges::SITE);

		return $change === null ? null : ['email' => $change->email, 'expires' => $change->expires];
	}

	/**
	 * General: the site's name, email address, language, and time, who can make an
	 * account, and where it runs.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function general(Settings $saved): array
	{
		$app         = new AppConfig();
		$environment = $this->app->environment;
		$now         = DateTimeImmutable::createFromInterface($this->clock->now())->setTimezone($this->app->timezone());

		return [
			self::group('site', $this->messages->t('admin.settings_controller.groups.site.title', 'Site'), $this->messages->t('admin.settings_controller.groups.site.hint', 'Its name, email, and language'), [
				$this->edit(self::item('name', $this->messages->t('admin.settings_controller.general.name', 'Site name'), $this->app->name, $this->app->name === $app->name), $saved, Setting::Name, $this->app->name),
				$this->edit(self::item('description', $this->messages->t('admin.settings_controller.general.description', 'Description'), $this->app->description, $this->app->description === '', help: Setting::Description->field($this->types, $this->messages)->description), $saved, Setting::Description, $this->app->description),
				[...$this->edit(self::item('email', $this->messages->t('admin.settings_controller.general.email', 'Email address'), $this->app->email, $this->app->email === '', help: Setting::Email->field($this->types, $this->messages)->description), $saved, Setting::Email, $this->app->email), 'emailChange' => $this->emailChange()],
				[...$this->edit(self::item('locale', $this->messages->t('admin.settings_controller.general.locale', 'Language and region'), $this->app->locale, $this->app->locale === $app->locale, 'mono', $this->messages->t('admin.settings_controller.general.locale_help', 'Choose Other to type a code the menu doesn\'t have.')), $saved, Setting::Locale, $this->app->locale), 'locales' => Locales::options()],
				...$this->untranslated($saved, $app),
				self::item('url', $this->messages->t('admin.settings_controller.general.url', 'Site address'), $this->app->url, $this->app->url === $app->url, 'mono', $this->messages->t('admin.settings_controller.general.url_help', 'From APP_URL in .env by default.'), 'config/app.php')
			]),
			self::group('dates', $this->messages->t('admin.settings_controller.groups.dates.title', 'Dates and Time'), $this->messages->t('admin.settings_controller.groups.dates.hint', 'How times are read and shown'), [
				[...$this->edit(self::item('timezone', $this->messages->t('admin.settings_controller.general.timezone', 'Time zone'), $this->app->timezone, $this->app->timezone === $app->timezone, 'mono', $this->messages->t('admin.settings_controller.general.timezone_help', 'It\'s {time} there now.', time: $now->format('D, j M Y, H:i'))), $saved, Setting::Timezone, $this->app->timezone), 'menu' => TimeZones::options($now)],
				[...$this->edit(self::item('dateFormat', $this->messages->t('admin.settings_controller.general.date_format', 'Date format'), $this->app->dateFormat, $this->app->dateFormat === $app->dateFormat, 'mono', Setting::DateFormat->field($this->types, $this->messages)->description), $saved, Setting::DateFormat, $this->app->dateFormat), 'formats' => DateFormat::options('date', $now, $this->app->locale, $this->app->timezone, $this->messages), 'link' => ['label' => $this->messages->t('admin.settings_controller.general.pattern_letters', 'Pattern letters'), 'href' => DateFormat::REFERENCE]],
				[...$this->edit(self::item('timeFormat', $this->messages->t('admin.settings_controller.general.time_format', 'Time format'), $this->app->timeFormat, $this->app->timeFormat === $app->timeFormat, 'mono', Setting::TimeFormat->field($this->types, $this->messages)->description), $saved, Setting::TimeFormat, $this->app->timeFormat), 'formats' => DateFormat::options('time', $now, $this->app->locale, $this->app->timezone, $this->messages), 'link' => ['label' => $this->messages->t('admin.settings_controller.general.pattern_letters', 'Pattern letters'), 'href' => DateFormat::REFERENCE]]
			]),
			self::group('accounts', $this->messages->t('admin.settings_controller.groups.accounts.title', 'Accounts'), $this->messages->t('admin.settings_controller.groups.accounts.hint', 'Who can make one'), $this->accounts($saved)),
			self::group('environment', $this->messages->t('admin.settings_controller.groups.environment.title', 'Environment'), $this->messages->t('admin.settings_controller.groups.environment.hint', 'Set where the site runs'), [
				self::item('environment', $this->messages->t('admin.settings_controller.general.environment', 'Environment'), $this->environment($environment), $environment === $app->environment, help: match ($environment) {
					Environment::Development => $this->messages->t('admin.settings_controller.general.environment_help.development', 'Content changes show up on the next request, caching is off, and search engines are asked not to index the site.'),
					Environment::Staging     => $this->messages->t('admin.settings_controller.general.environment_help.staging', 'Search engines are asked not to index the site.'),
					Environment::Production  => null
				}, file: 'config/app.php'),
				[
					...self::item('debug', $this->messages->t('admin.settings_controller.general.debug', 'Detailed error pages'), $this->app->debug, $this->app->debug === $app->debug, 'bool', file: 'config/app.php'),
					'warning' => $this->app->debug && $environment === Environment::Production ? $this->messages->t('admin.settings_controller.general.debug_warning', 'Never on a live site: error pages show the site\'s code and settings.') : null
				]
			], $this->messages->t('admin.settings_controller.groups.environment.note', 'From `.env` (`APP_ENV`, `APP_DEBUG`) when `config/app.php` reads them, as it does by default.'))
		];
	}

	/**
	 * Email (D-846, D-848): who the site's mail is from, which the admin
	 * saves, and the transport (`MailTransport`, without its password):
	 * `MAIL_DSN`'s, else PHP's own mail where the server has it, which
	 * the help says is less reliable, with a `warning` on a live site, as
	 * `log` and `null` have there too. When there is one, an item of
	 * `kind` `test` is **Send a Test**, which sends to the signed-in
	 * account's address (its `value`; empty when it has none).
	 *
	 * @return list<array<string, mixed>>
	 */
	private function email(Settings $saved, Account $account): array
	{
		$transport = $this->transport->describe();
		$live      = $this->app->environment === Environment::Production;
		$help      = match (true) {
			$this->transport->isOff()      => $this->messages->t('admin.settings_controller.email.off_help', 'MAIL_DSN is empty, so the site sends no email: password links are copied, and sign-in has no "Forgot your password?".'),
			$transport === null            => $this->messages->t('admin.settings_controller.email.not_set_up_help', 'The site can\'t send email: no transport is set, and PHP on this server has no mail program. Set MAIL_DSN in .env to your host\'s SMTP server or a mail service\'s.'),
			$this->transport->isFallback() => $this->messages->t('admin.settings_controller.email.fallback_help', 'No transport is set, so email goes out through PHP\'s own mail, which often lands in spam or is refused. For email people get, set MAIL_DSN in .env to your host\'s SMTP server or a mail service\'s.'),
			default                        => $this->messages->t('admin.settings_controller.email.transport_help', 'Where the site hands its email to be sent. Its password, if it has one, is never shown.')
		};
		$warning   = match (true) {
			! $live                                        => null,
			$this->transport->isFallback()                 => $this->messages->t('admin.settings_controller.email.fallback_warning', 'Password links may land in spam: set an SMTP transport for a live site.'),
			in_array($this->transport->scheme(), ['log', 'null'], true) => $this->messages->t('admin.settings_controller.email.undelivered_warning', 'Email isn\'t delivered with this transport, so nobody gets password links.'),
			default                                        => null
		};
		$delivery  = [
			[
				...self::item('transport', $this->messages->t('admin.settings_controller.email.transport', 'Transport'), match (true) {
					$this->transport->isOff()      => $this->messages->t('admin.settings_controller.email.off', 'Turned off'),
					$transport === null            => $this->messages->t('admin.settings_controller.email.not_set_up', 'Not set up'),
					$this->transport->isFallback() => $this->messages->t('admin.settings_controller.email.fallback', 'PHP\'s own mail (no setup)'),
					default                        => $transport
				}, $this->mail->dsn === null, $transport === null || $this->transport->isFallback() ? 'text' : 'mono', $help, 'config/mail.php'),
				'warning' => $warning
			]
		];

		if ($transport !== null) {
			$delivery[] = self::item('test', $this->messages->t('admin.settings_controller.email.test', 'Test email'), $account->email ?? '', null, 'test', $account->email === null || $account->email === ''
				? $this->messages->t('admin.settings_controller.email.test_no_address', 'Your account has no email address to send a test to.')
				: $this->messages->t('admin.settings_controller.email.test_help', 'Sends a short email to {email}, to check that the site\'s email arrives.', email: $account->email));
		}

		return [
			self::group('sender', $this->messages->t('admin.settings_controller.groups.sender.title', 'Sender'), $this->messages->t('admin.settings_controller.groups.sender.hint', 'Who the site\'s email is from'), [
				$this->edit(self::item('fromName', $this->messages->t('admin.settings_controller.email.from_name', 'From name'), $this->mail->fromName !== '' ? $this->mail->fromName : $this->app->name, $this->mail->fromName === '', help: Setting::MailFromName->field($this->types, $this->messages)->description), $saved, Setting::MailFromName, $this->mail->fromName),
				$this->edit(self::item('fromAddress', $this->messages->t('admin.settings_controller.email.from_address', 'From address'), $this->mail->fromAddress !== '' ? $this->mail->fromAddress : $this->app->email, $this->mail->fromAddress === '', 'mono', Setting::MailFromAddress->field($this->types, $this->messages)->description), $saved, Setting::MailFromAddress, $this->mail->fromAddress)
			]),
			self::group('delivery', $this->messages->t('admin.settings_controller.groups.delivery.title', 'Delivery'), $this->messages->t('admin.settings_controller.groups.delivery.hint', 'How it\'s sent'), $delivery, $this->messages->t('admin.settings_controller.groups.delivery.note', 'From `.env` (`MAIL_DSN`) when `config/mail.php` reads it, as it does by default.'))
		];
	}

	/**
	 * Whether anyone can sign up, and the role they're given (D-518);
	 * nothing reads them yet. The role is locked while sign-ups are off.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function accounts(Settings $saved): array
	{
		$defaults = new AuthConfig();
		$roles    = array_filter($this->roles->all(), static fn (Role $role): bool => AuthConfig::signupRoleProblem($role->name) === null);
		$role     = $this->roles->get($this->auth->signupRole);
		$field    = new EnumField('signupRole', array_keys($roles))
			->labeled(Setting::SignupRole->field($this->types, $this->messages)->label)
			->described(Setting::SignupRole->field($this->types, $this->messages)->description)
			->required();
		$item     = $this->edit(
			self::item('signupRole', $field->label, $role->label ?? $this->auth->signupRole, $this->auth->signupRole === $defaults->signupRole, help: $field->description),
			$saved,
			Setting::SignupRole,
			$this->auth->signupRole,
			$field
		);

		return [
			$this->edit(self::item('signups', $this->messages->t('admin.settings_controller.accounts.signups', 'Sign-ups'), $this->auth->signups, $this->auth->signups === $defaults->signups, 'bool', Setting::Signups->field($this->types, $this->messages)->description), $saved, Setting::Signups, $this->auth->signups),
			[
				...$item,
				'field'    => [...is_array($item['field'] ?? null) ? $item['field'] : [], 'choices' => array_map(static fn (Role $role): string => (string) $role->label, $roles)],
				'requires' => ['setting' => Setting::Signups->value, 'note' => $this->messages->t('admin.settings_controller.accounts.signup_role_requires', 'It needs sign-ups on.')]
			]
		];
	}

	/**
	 * What another language's address does for an untranslated entry
	 * (D-467, D-468), on a site with other languages; a site without
	 * them has nothing for it to do, so it isn't shown.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function untranslated(Settings $saved, AppConfig $app): array
	{
		$setting = $this->app->untranslated;
		$item    = self::item('untranslated', $this->messages->t('admin.settings_controller.general.untranslated', 'Untranslated pages'), $setting->label($this->messages), $setting === $app->untranslated, help: Setting::Untranslated->field($this->types, $this->messages)->description);

		return $this->app->languages->isMultilingual() ? [$this->edit($item, $saved, Setting::Untranslated, $setting->value)] : [];
	}

	/**
	 * Reading: what the homepage shows, and the feeds.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function reading(Settings $saved): array
	{
		$feeds = new FeedConfig();

		return [
			self::group('home', $this->messages->t('admin.settings_controller.groups.home.title', 'Homepage'), $this->messages->t('admin.settings_controller.groups.home.hint', 'What the site opens with'), [
				Setting::homeChoices($this->types, $this->messages) === []
					? self::item('home', $this->messages->t('admin.settings_controller.reading.home', 'Homepage'), $this->homeLabel(), $this->content->home === null, help: $this->messages->t('admin.settings_controller.reading.home_help', 'The site has no collection with addresses to show instead.'), file: Setting::Home->file())
					: $this->edit(self::item('home', $this->messages->t('admin.settings_controller.reading.home', 'Homepage'), $this->homeLabel(), $this->content->home === null), $saved, Setting::Home, $this->content->home)
			]),
			self::group('feeds', $this->messages->t('admin.settings_controller.groups.feeds.title', 'Feeds'), $this->messages->t('admin.settings_controller.groups.feeds.hint', 'For types with a feed'), [
				$this->edit(self::item('formats', $this->messages->t('admin.settings_controller.reading.formats', 'Formats'), array_map(fn (FeedFormat $format): string => (string) $format->label($this->messages), $this->feeds->formats), $this->feeds->formats === $feeds->formats, 'list', $this->messages->t('admin.settings_controller.reading.formats_help', 'None turns every feed off.')), $saved, Setting::FeedFormats, array_map(static fn (FeedFormat $format): string => $format->value, $this->feeds->formats)),
				$this->edit(self::item('content', $this->messages->t('admin.settings_controller.reading.content', 'Full content'), $this->feeds->content, $this->feeds->content, 'bool', $this->messages->t('admin.settings_controller.reading.content_help', 'Off, a feed carries each entry\'s summary only.')), $saved, Setting::FeedContent, $this->feeds->content),
				$this->edit(self::item('limit', $this->messages->t('admin.settings_controller.reading.limit', 'Entries per feed'), (string) $this->feeds->limit, $this->feeds->limit === $feeds->limit, help: $this->messages->t('admin.settings_controller.reading.limit_help', 'From 1 to {max}.', max: Setting::FEED_LIMIT_MAX)), $saved, Setting::FeedLimit, $this->feeds->limit)
			])
		];
	}

	/**
	 * The API (D-741, D-764): its switch, who may read it (D-745), and
	 * where it's served; what a token may change (D-762), its limit set
	 * in `config/api.php`, and where tokens are made; and the MCP server
	 * beside it, which the AI screen sets.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function apiScreen(Settings $saved, Account $account): array
	{
		$defaults = new ApiConfig();
		$tokens   = array_values(array_map(
			static fn (Role $role): string => (string) $role->label,
			array_filter($this->roles->all(), static fn (Role $role): bool => $role->allows(Capability::ApiTokens->value))
		));

		return [
			self::group('api', $this->messages->t('admin.settings_controller.groups.api.title', 'API'), $this->messages->t('admin.settings_controller.groups.api.hint', 'For other sites, apps, and AI tools'), [
				$this->edit(self::item('api', $this->messages->t('admin.settings_controller.api.api', 'API'), $this->api->enabled, ! $this->api->enabled, 'bool', Setting::Api->field($this->types, $this->messages)->description), $saved, Setting::Api, $this->api->enabled),
				$this->edit(self::item('access', $this->messages->t('admin.settings_controller.api.access', 'Who can read it'), $this->api->access->label($this->messages), $this->api->access === ApiAccess::Anyone, 'segmented', Setting::ApiAccess->field($this->types, $this->messages)->description), $saved, Setting::ApiAccess, $this->api->access->value),
				[
					...self::item('path', $this->messages->t('admin.settings_controller.api.path', 'Address'), $this->api->base(), $this->api->path === $defaults->path, 'mono', $this->messages->t('admin.settings_controller.api.path_help', 'Where the API is served, with its version.'), 'config/api.php'),
					'link' => $this->api->enabled ? ['label' => $this->messages->t('admin.settings_controller.api.view', 'View the API'), 'href' => $this->app->absoluteUrl($this->api->base())] : null
				]
			]),
			self::group('changes', $this->messages->t('admin.settings_controller.groups.changes.title', 'Changes'), $this->messages->t('admin.settings_controller.groups.changes.hint', 'What a token may change'), [
				self::item('maxWrites', $this->messages->t('admin.settings_controller.api.max_writes', 'Changes per token'), $this->messages->t('admin.settings_controller.api.max_writes_value', '{count, plural, one {# change an hour} other {# changes an hour}}', count: $this->api->maxWrites), $this->api->maxWrites === $defaults->maxWrites, help: $this->messages->t('admin.settings_controller.api.max_writes_help', 'How many entries and media files a token may create or change through the API in an hour. Past it, changes are refused until the hour is up. Each is listed under Tools → Activity.'), file: 'config/api.php'),
				[
					...self::item('tokens', $this->messages->t('admin.settings_controller.api.tokens', 'Who can make tokens'), $tokens, null, 'list', $this->messages->t('admin.settings_controller.api.tokens_help', 'Roles with Make API tokens. A token is made on an account\'s screen, under API Tokens, and does what its account may, or less.')),
					'links' => [['label' => $this->messages->t('admin.settings_controller.api.your_account', 'Your Account'), 'to' => '/accounts/' . rawurlencode($account->username)], ['label' => $this->messages->t('admin.settings_controller.api.roles', 'Roles'), 'to' => '/roles']]
				]
			]),
			self::group('mcp', $this->messages->t('admin.settings_controller.groups.api_mcp.title', 'MCP Server'), $this->messages->t('admin.settings_controller.groups.api_mcp.hint', 'For AI apps, beside the API'), [
				[
					...self::item('mcpStatus', $this->messages->t('admin.settings_controller.api.mcp', 'MCP server'), $this->mcp->enabled ? $this->messages->t('admin.settings_controller.api.mcp_on', 'On') : $this->messages->t('admin.settings_controller.api.mcp_off', 'Off'), ! $this->mcp->enabled, help: $this->messages->t('admin.settings_controller.api.mcp_help', 'AI apps connect to it with the API\'s tokens, or by signing in. It\'s set on the AI screen.')),
					'links' => [['label' => $this->messages->t('admin.settings_controller.api.ai_settings', 'AI Settings'), 'to' => '/settings/ai']]
				]
			])
		];
	}

	/**
	 * Media (D-406): the upload rules, one setting (`media.uploads`) the
	 * admin draws as a grid. Its item's `input` is the rules as
	 * `MediaUploads::toArray()` writes them, and `uploads` says what the
	 * grid needs: each kind (`key`, `label`, the `folder` its `{kind}`
	 * becomes, an `example` file name, and the `extensions` the site
	 * allows for it, none for a kind nothing can be uploaded as), the path
	 * `tokens`, the site's date `now` (`year`, `month`, `day`) for the
	 * examples, the most PHP accepts (`serverLimit`, in bytes, or `null`),
	 * and how many `files` the library has.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function media(Settings $saved): array
	{
		$uploads = $this->media->uploads;
		$now     = DateTimeImmutable::createFromInterface($this->clock->now())->setTimezone($this->app->timezone());
		$kinds   = [];

		foreach (MediaKind::cases() as $kind) {
			$kinds[] = [
				'key'        => $kind->value,
				'label'      => $kind->label($this->messages),
				'folder'     => $kind->folder(),
				'example'    => match ($kind) {
					MediaKind::Image    => 'photo.jpg',
					MediaKind::Video    => 'clip.mp4',
					MediaKind::Audio    => 'track.mp3',
					MediaKind::Document => 'notes.pdf',
					MediaKind::File     => 'captions.vtt'
				},
				'extensions' => array_keys(array_filter(MediaResolver::EXTENSIONS, fn (string $mime): bool => MediaKind::fromMime($mime) === $kind && $this->media->allows($mime) && ! MediaUploads::refuses($mime)))
			];
		}

		try {
			$files = $this->library->query(new MediaQuery(per: 1))->total;
		} catch (MediaException) {
			$files = null;
		}

		return [
			self::group('uploads', $this->messages->t('admin.settings_controller.groups.uploads.title', 'Uploads'), $this->messages->t('admin.settings_controller.groups.uploads.hint', 'What may be uploaded, and where it goes'), [[
				'key'     => 'uploads',
				'label'   => $this->messages->t('admin.settings_controller.media.uploads', 'Uploads'),
				'value'   => [],
				'kind'    => 'uploads',
				'default' => $uploads->toArray() === new MediaUploads()->toArray(),
				'help'    => null,
				'warning' => null,
				'file'    => Setting::MediaUploads->file(),
				'setting' => Setting::MediaUploads->value,
				'input'   => $uploads->toArray(),
				'saved'   => $saved->has(Setting::MediaUploads),
				'uploads' => [
					'kinds'       => $kinds,
					'tokens'      => MediaUploads::TOKENS,
					'now'         => ['year' => $now->format('Y'), 'month' => $now->format('m'), 'day' => $now->format('d')],
					'serverLimit' => MediaUploadController::limit(),
					'files'       => $files
				]
			]]),
			self::group('artwork', $this->messages->t('admin.settings_controller.groups.artwork.title', 'Artwork'), $this->messages->t('admin.settings_controller.groups.artwork.hint', 'The pictures sounds and videos carry'), [
				$this->edit(self::item('addArtwork', Setting::AddArtwork->field($this->types, $this->messages)->label, $this->media->addArtwork, ! $this->media->addArtwork, 'bool', Setting::AddArtwork->field($this->types, $this->messages)->description), $saved, Setting::AddArtwork, $this->media->addArtwork)
			])
		];
	}

	/**
	 * Writing (D-494): how what's written in Markdown renders, what raw
	 * HTML in it does (kind `segmented`, its options side by side), and
	 * the sites links embed from (D-633): a chip for each provider, built
	 * in or not, saved together as the list of those turned on
	 * (`embed.enabled`, kind `embeds`; none by default, D-705), with each provider's `name`, `label`,
	 * and the `hosts` its links are on, by label (D-695).
	 *
	 * @return list<array<string, mixed>>
	 */
	private function writing(Settings $saved): array
	{
		$markdown = $this->markdown;
		$defaults = new MarkdownConfig();
		$switch   = fn (Setting $setting, string $key, bool $value, bool $default): array => $this->edit(
			self::item($key, $setting->field($this->types, $this->messages)->label, $value, $value === $default, 'bool', $setting->field($this->types, $this->messages)->description),
			$saved,
			$setting,
			$value
		);

		return [
			self::group('markdown', $this->messages->t('admin.settings_controller.groups.markdown.title', 'Markdown'), $this->messages->t('admin.settings_controller.groups.markdown.hint', 'How what\'s written renders'), [
				$switch(Setting::Mentions, 'mentions', $markdown->mentions, $defaults->mentions),
				$switch(Setting::SmartPunctuation, 'smartPunctuation', $markdown->smartPunctuation, $defaults->smartPunctuation),
				$switch(Setting::HeadingAnchors, 'headingAnchors', $markdown->headingAnchors, $defaults->headingAnchors),
				$switch(Setting::Figures, 'figures', $markdown->figures, $defaults->figures)
			]),
			self::group('html', $this->messages->t('admin.settings_controller.groups.html.title', 'HTML'), $this->messages->t('admin.settings_controller.groups.html.hint', 'Raw HTML in content'), [
				$this->edit(
					self::item('html', $this->messages->t('admin.settings_controller.writing.html', 'Raw HTML'), $markdown->html->label($this->messages), $markdown->html === $defaults->html, 'segmented', Setting::Html->field($this->types, $this->messages)->description),
					$saved,
					Setting::Html,
					$markdown->html->value
				)
			]),
			self::group('embeds', $this->messages->t('admin.settings_controller.groups.embeds.title', 'Embeds'), $this->messages->t('admin.settings_controller.groups.embeds.hint', 'Sites whose links play on the page'), [
				[
					...$this->edit(
						self::item('enabled', Setting::Embeds->field($this->types, $this->messages)->label, $this->embed->enabled, $this->embed->enabled === [], 'embeds', Setting::Embeds->field($this->types, $this->messages)->description),
						$saved,
						Setting::Embeds,
						$this->embed->enabled
					),
					'providers' => self::byLabel(array_map(static fn (EmbedProvider $provider): array => [
						'name'  => $provider->name,
						'label' => $provider->label,
						'hosts' => self::hosts($provider)
					], $this->embeds->all()))
				]
			])
		];
	}

	/**
	 * Returns the sites a provider's links are on, from its schemes, as
	 * they're written (`youtube.com`, `youtu.be`).
	 *
	 * @return list<string>
	 */
	private static function hosts(EmbedProvider $provider): array
	{
		$hosts = [];

		foreach ($provider->schemes as $scheme) {
			$host = parse_url(str_replace('*.', 'x.', $scheme), PHP_URL_HOST);

			if (is_string($host)) {
				$host         = preg_replace('/^(?:www|x)\./', '', strtolower($host)) ?? $host;
				$hosts[$host] = true;
			}
		}

		return array_keys($hosts);
	}

	/**
	 * Addresses and Search: how URLs are written, and what search engines
	 * are told.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function search(Settings $saved): array
	{
		$environment = $this->app->environment;

		return [
			self::group('addresses', $this->messages->t('admin.settings_controller.groups.addresses.title', 'Addresses'), $this->messages->t('admin.settings_controller.groups.addresses.hint', 'How URLs are written'), [
				$this->edit(self::item('trailingSlash', $this->messages->t('admin.settings_controller.search.trailing_slash', 'Trailing slash'), $this->routes->trailingSlash, ! $this->routes->trailingSlash, 'bool', $this->messages->t('admin.settings_controller.search.trailing_slash_help', 'The other form redirects, so links to either still work.')), $saved, Setting::TrailingSlash, $this->routes->trailingSlash),
				[
					...self::item('media', $this->messages->t('admin.settings_controller.search.media', 'Media address'), $this->media->url, $this->media->url === new MediaConfig()->url, 'mono', $this->messages->t('admin.settings_controller.search.media_help', 'Where user/media is served. The folders under it are named on Media.'), 'config/media.php'),
					'links' => [['label' => $this->messages->t('admin.settings_controller.search.media_settings', 'Media settings'), 'to' => '/settings/media']]
				]
			]),
			self::group('search', $this->messages->t('admin.settings_controller.groups.search.title', 'Search Engines'), $this->messages->t('admin.settings_controller.groups.search.hint', 'The sitemap and robots.txt'), [
				$this->edit(self::item('sitemap', $this->messages->t('admin.settings_controller.search.sitemap', 'Sitemap and robots.txt'), $this->sitemap->enabled, $this->sitemap->enabled, 'bool', $this->messages->t('admin.settings_controller.search.sitemap_help', 'Off, the site has neither, and search engines find pages by their links.')), $saved, Setting::Sitemap, $this->sitemap->enabled),
				$this->edit(self::item('disallow', $this->messages->t('admin.settings_controller.search.disallow', 'Paths robots.txt asks to skip'), $this->sitemap->disallow, $this->sitemap->disallow === [], 'list', $this->messages->t('admin.settings_controller.search.disallow_help', 'One path a line, each starting with /, such as /drafts/.')), $saved, Setting::SitemapDisallow, $this->sitemap->disallow),
				self::item('indexing', $this->messages->t('admin.settings_controller.search.indexing', 'Asks not to be indexed'), $environment !== Environment::Production, null, 'bool', $this->messages->t('admin.settings_controller.search.indexing_help', 'Outside production, robots.txt asks search engines to skip the whole site. It follows the environment.'))
			])
		];
	}

	/**
	 * AI (D-398), drawn as the AI sketch's Compact (D-765): the Markdown
	 * copies and `llms.txt` AI tools read, the MCP server, what
	 * `robots.txt` asks of AI crawlers, and the tools a connected app can
	 * be asked to do. The first two panels are `compact`: each row's words
	 * first, with its value under its label, and its switch, links, and a
	 * short `aside` at its end.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function ai(Settings $saved): array
	{
		$types   = $this->llmsTxt->types();
		$listed  = $this->llms->enabled ? $this->llmsTxt->count() : 0;
		$blocked = array_map(fn (AiCrawlerGroup $group): string => (string) $group->label($this->messages), $this->sitemap->blockAi);

		return [
			[
				...self::group('markdown', $this->messages->t('admin.settings_controller.groups.ai_markdown.title', 'Markdown Copies'), $this->messages->t('admin.settings_controller.groups.ai_markdown.hint', 'What AI tools read'), [
					$this->edit(self::item('llms', $this->messages->t('admin.settings_controller.ai.llms', 'Markdown copies'), $this->llms->enabled, $this->llms->enabled, 'bool', Setting::Llms->field($this->types, $this->messages)->description), $saved, Setting::Llms, $this->llms->enabled),
					[
						...self::item('llmsTxt', 'llms.txt', $this->llms->enabled ? '' : $this->messages->t('admin.settings_controller.ai.llms_txt_off', 'Off, with the Markdown copies'), null, help: $this->messages->t('admin.settings_controller.ai.llms_txt_help', 'A Markdown map of the site: its name and description, then each listed type\'s pages.')),
						'aside' => $this->llms->enabled ? $this->messages->t('admin.settings_controller.ai.llms_txt_pages', '{count, plural, one {# page} other {# pages}}', count: $listed) : null,
						'link'  => $this->llms->enabled ? ['label' => $this->messages->t('admin.settings_controller.view', 'View'), 'href' => $this->app->absoluteUrl('/llms.txt')] : null
					],
					$this->full($saved),
					[
						...self::item('types', $this->messages->t('admin.settings_controller.ai.types', 'Types in llms.txt'), array_map(static fn (ContentType $type): string => $type->labels->plural, $types), null, 'list', $this->messages->t('admin.settings_controller.ai.types_help', 'Each content type chooses with its "Listed in llms.txt" option: on for collections and trees unless they say otherwise, off for taxonomies and profiles unless they say so.')),
						'links' => [['label' => $this->messages->t('admin.settings_controller.ai.manage_types', 'Manage Content Types'), 'to' => '/types']]
					],
					[
						...self::item('description', $this->messages->t('admin.settings_controller.ai.description', 'Description'), $this->app->description === '' ? $this->messages->t('admin.settings_controller.ai.description_none', 'None') : $this->app->description, null, help: $this->messages->t('admin.settings_controller.ai.description_help', 'The line under the site\'s name in llms.txt.')),
						'links' => [['label' => $this->messages->t('admin.settings_controller.ai.change_in_general', 'Change in General Settings'), 'to' => '/settings/general']]
					]
				]),
				'compact' => true
			],
			[...$this->mcpGroup($saved), 'compact' => true],
			self::group('crawlers', $this->messages->t('admin.settings_controller.groups.crawlers.title', 'AI Crawlers'), $this->messages->t('admin.settings_controller.groups.crawlers.hint', 'What robots.txt asks of them'), [
				[
					...$this->edit(self::item('blockAi', $this->messages->t('admin.settings_controller.ai.block_ai', 'Ask to stay away'), $blocked, $blocked === [], 'switches', Setting::BlockAi->field($this->types, $this->messages)->description), $saved, Setting::BlockAi, array_map(static fn (AiCrawlerGroup $group): string => $group->value, $this->sitemap->blockAi)),
					'words'   => ['on' => $this->messages->t('admin.settings_controller.ai.block_ai_on', 'Asked to Stay Away'), 'off' => $this->messages->t('admin.settings_controller.ai.block_ai_off', 'Allowed')],
					'warning' => match (true) {
						$this->sitemap->robots !== null                          => $this->messages->t('admin.settings_controller.ai.block_ai_robots', 'config/sitemap.php\'s own robots.txt replaces the generated one, so these choices aren\'t used.'),
						$this->app->environment !== Environment::Production => $this->messages->t('admin.settings_controller.ai.block_ai_environment', 'Outside production, robots.txt asks every crawler to stay away, so these choices apply once the site is live.'),
						default                                              => null
					}
				]
			]),
			...$this->toolGroups()
		];
	}

	/**
	 * The MCP server (D-754): its switch, the address an app connects to,
	 * with Copy, and who may connect apps (`api.connect`, D-752).
	 *
	 * @return array<string, mixed>
	 */
	private function mcpGroup(Settings $saved): array
	{
		$connect = array_values(array_map(
			static fn (Role $role): string => (string) $role->label,
			array_filter($this->roles->all(), static fn (Role $role): bool => $role->allows(Capability::ApiConnect->value))
		));

		return self::group('mcp', $this->messages->t('admin.settings_controller.groups.mcp.title', 'MCP Server'), $this->messages->t('admin.settings_controller.groups.mcp.hint', 'How AI apps work with the site'), [
			$this->edit(self::item('mcp', $this->messages->t('admin.settings_controller.ai.mcp', 'MCP server'), $this->mcp->enabled, ! $this->mcp->enabled, 'bool', Setting::Mcp->field($this->types, $this->messages)->description), $saved, Setting::Mcp, $this->mcp->enabled),
			[
				...self::item('mcpAddress', $this->messages->t('admin.settings_controller.ai.mcp_address', 'Server address'), $this->mcp->enabled ? $this->oauth->resource() : $this->messages->t('admin.settings_controller.ai.mcp_address_off', 'Off, with the MCP server'), null, $this->mcp->enabled ? 'mono' : 'text', $this->messages->t('admin.settings_controller.ai.mcp_address_help', 'Paste it into an app\'s connector settings, such as Claude\'s. Each user signs in and approves the app, and it acts as them. Every change it makes is listed under Tools → Activity.')),
				'copy' => $this->mcp->enabled
			],
			[
				...self::item('mcpConnect', $this->messages->t('admin.settings_controller.ai.mcp_connect', 'Who can connect apps'), $connect, null, 'list', $this->messages->t('admin.settings_controller.ai.mcp_connect_help', 'Roles with Connect apps. Long-lived API tokens are made on Your Account, with Make API tokens.')),
				'links' => [['label' => $this->messages->t('admin.settings_controller.ai.manage_roles', 'Manage Roles'), 'to' => '/roles'], ['label' => $this->messages->t('admin.settings_controller.ai.api_settings', 'API Settings'), 'to' => '/settings/api']]
			]
		]);
	}

	/**
	 * What a connected app can be asked to do (D-757, D-765): one panel
	 * of every tool, as one setting of kind `tools`, its `sources` the
	 * tools grouped by who registered them (`Provenance::ofClass()`):
	 * Blush's first, then the site's, then each plugin's by label. Each
	 * tool has its MCP `name`, `title`, `description`, and the capability
	 * it `needs` (`null` for anyone who connects an app).
	 *
	 * @return list<array<string, mixed>>
	 */
	private function toolGroups(): array
	{
		$sources = [];

		foreach ($this->mcpTools->all() as $key => $tool) {
			$source = $this->provenance->ofClass($this->tools->classOf($key) ?? $tool::class);
			$group  = "{$source['kind']}:{$source['label']}";

			$sources[$group] ??= [...$source, 'tools' => []];
			$sources[$group]['tools'][] = [
				'name'        => ToolRegistry::flatName($key),
				'title'       => (string) $tool->title(),
				'description' => (string) $tool->description(),
				'needs'       => Tools::capabilityName($tool)
			];
		}

		if ($sources === []) {
			return [];
		}

		$order = ['core' => 0, 'site' => 1, 'plugin' => 2, 'other' => 3];

		uasort($sources, static fn (array $a, array $b): int => [$order[$a['kind']] ?? 3, $a['label']] <=> [$order[$b['kind']] ?? 3, $b['label']]);

		return [
			self::group('tools', $this->messages->t('admin.settings_controller.groups.tools.title', 'Tools'), $this->messages->t('admin.settings_controller.groups.tools.hint', 'What a connected app can be asked to do'), [
				[...self::item('tools', $this->messages->t('admin.settings_controller.ai.tools', 'Tools'), [], null, 'tools'), 'sources' => array_values($sources)]
			], $this->messages->t('admin.settings_controller.groups.tools.note', 'Core tools ship with the site. A plugin\'s tools arrive when it\'s turned on and leave when it\'s turned off. Every change a tool makes is listed under Tools → Activity.'))
		];
	}

	/**
	 * The `llms-full.txt` switch (D-402), locked while the Markdown copies
	 * are off (`requires`). When it's served, its link gives its size,
	 * and a warning says when it's too big for the page cache, so a site
	 * served by PHP builds it on every request.
	 *
	 * @return array<string, mixed>
	 */
	private function full(Settings $saved): array
	{
		$served = $this->llms->full && $this->llms->enabled;
		$size   = $served ? strlen($this->llmsTxt->renderFull()) : 0;
		$cached = $this->cache->isEnabled($this->app->environment) && $this->cache->pages;

		return [
			...$this->edit(self::item('full', 'llms-full.txt', $this->llms->full, ! $this->llms->full, 'bool', Setting::LlmsFull->field($this->types, $this->messages)->description), $saved, Setting::LlmsFull, $this->llms->full),
			'requires' => ['setting' => Setting::Llms->value, 'note' => $this->messages->t('admin.settings_controller.ai.full_requires', 'It needs the Markdown copies on.')],
			'aside'    => $served ? $this->bytes($size) : null,
			'link'     => $served ? ['label' => $this->messages->t('admin.settings_controller.view', 'View'), 'href' => $this->app->absoluteUrl(LlmsRoutes::FULL)] : null,
			'warning'  => $served && $cached && $size > PageCache::MAX_BYTES
				? $this->messages->t('admin.settings_controller.ai.full_too_big', 'At {size}, it\'s over the page cache\'s {max}, so it\'s built again on every request for it.', size: $this->bytes($size), max: $this->bytes(PageCache::MAX_BYTES))
				: null
		];
	}

	/**
	 * A size in words: `3.6 MB`, `140 KB`.
	 */
	private function bytes(int $bytes): Text
	{
		return match (true) {
			$bytes >= 1_048_576 => $this->messages->t('admin.settings_controller.bytes.mb', '{size} MB', size: rtrim(rtrim(number_format($bytes / 1_048_576, 1), '0'), '.')),
			$bytes >= 1_024     => $this->messages->t('admin.settings_controller.bytes.kb', '{size} KB', size: (int) round($bytes / 1_024)),
			default             => $this->messages->t('admin.settings_controller.bytes.bytes', '{count, plural, one {# byte} other {# bytes}}', count: $bytes)
		};
	}

	/**
	 * System: how the site is put together and run, all set in code.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function system(): array
	{
		$environment = $this->app->environment;

		return [
			self::group('types', $this->messages->t('admin.settings_controller.groups.types.title', 'Content Types'), $this->messages->t('admin.settings_controller.groups.types.hint', 'Where types come from'), [
				self::item('dataTypes', $this->messages->t('admin.settings_controller.system.data_types', 'Content types from user/data/types'), $this->content->dataTypes, $this->content->dataTypes, 'bool', file: 'config/content.php'),
				self::item('disabled', $this->messages->t('admin.settings_controller.system.disabled', 'Built-in types turned off'), $this->content->disabled, $this->content->disabled === [], 'list', file: 'config/content.php')
			]),
			self::group('caching', $this->messages->t('admin.settings_controller.groups.caching.title', 'Caching'), $this->messages->t('admin.settings_controller.groups.caching.hint', 'How pages are kept'), [
				self::item('enabled', $this->messages->t('admin.settings_controller.system.caching', 'Caching'), $this->cache->isEnabled($environment), $this->cache->enabled === null, 'bool', $this->cache->enabled === null ? $this->messages->t('admin.settings_controller.system.caching_help', 'Off in development, on elsewhere.') : null, 'config/cache.php'),
				self::item('driver', $this->messages->t('admin.settings_controller.system.driver', 'Where it\'s kept'), $this->cache->driver, $this->cache->driver === new CacheConfig()->driver, 'mono', file: 'config/cache.php'),
				self::item('pages', $this->messages->t('admin.settings_controller.system.pages', 'Whole pages'), $this->cache->pages, $this->cache->pages, 'bool', file: 'config/cache.php'),
				self::item('maxAge', $this->messages->t('admin.settings_controller.system.max_age', 'Browsers keep a page'), $this->cache->maxAge === 0 ? $this->messages->t('admin.settings_controller.system.max_age_none', 'Not at all; they ask each time') : $this->messages->t('admin.settings_controller.system.max_age_seconds', '{count, plural, one {# second} other {# seconds}}', count: $this->cache->maxAge), $this->cache->maxAge === 0, file: 'config/cache.php')
			]),
			self::group('publishing', $this->messages->t('admin.settings_controller.groups.publishing.title', 'Publishing and Previews'), $this->messages->t('admin.settings_controller.groups.publishing.hint', 'Secrets are never shown'), [
				self::item('webhook', $this->messages->t('admin.settings_controller.system.webhook', 'Publish webhook'), $this->publish->secret !== null, $this->publish->secret === null, 'bool', $this->publish->secret === null ? $this->messages->t('admin.settings_controller.system.webhook_help', 'On with a PUBLISH_SECRET in .env.') : null, 'config/publish.php'),
				self::item('git', $this->messages->t('admin.settings_controller.system.git', 'Pull with git when publishing'), $this->publish->git, ! $this->publish->git, 'bool', file: 'config/publish.php'),
				self::item('preview', $this->messages->t('admin.settings_controller.system.preview', 'Preview links'), $this->preview->secret !== null, $this->preview->secret === null, 'bool', $this->preview->secret === null ? $this->messages->t('admin.settings_controller.system.preview_help', 'On with an APP_SECRET in .env.') : null, 'config/preview.php')
			], $this->messages->t('admin.settings_controller.groups.publishing.note', 'From `.env` (`PUBLISH_*`, `APP_SECRET`) when these files don\'t exist.'))
		];
	}

	/**
	 * What the homepage shows, in words.
	 */
	private function homeLabel(): Text
	{
		$home = $this->content->home === null ? null : $this->types->find($this->content->home);

		return $home === null
			? $this->messages->t('admin.settings_controller.reading.home_page', 'The page at user/content/index.md')
			: $this->messages->t('admin.settings_controller.reading.home_latest', 'The latest {types}', types: mb_strtolower($home->labels->plural));
	}

	/**
	 * Where the site runs, in words.
	 */
	private function environment(Environment $environment): Text
	{
		return match ($environment) {
			Environment::Development => $this->messages->t('admin.settings_controller.environments.development', 'Development'),
			Environment::Staging     => $this->messages->t('admin.settings_controller.environments.staging', 'Staging'),
			Environment::Production  => $this->messages->t('admin.settings_controller.environments.production', 'Production')
		};
	}

	/**
	 * Names the file a group's settings are from once (`source`, D-695),
	 * when every setting it edits is from the same one, so its rows say
	 * where they're from only when they're saved here. Upload rules,
	 * embeds, and switches by option say it in their own foot, and a field set's settings
	 * (`site.…`) have no file.
	 *
	 * @param  array<string, mixed> $group
	 * @return array<string, mixed>
	 */
	private static function sourced(array $group): array
	{
		$files = [];

		foreach (is_array($group['items'] ?? null) ? $group['items'] : [] as $item) {
			if (! is_array($item) || ! isset($item['setting'])) {
				continue;
			}

			if (in_array($item['kind'] ?? null, ['uploads', 'embeds', 'switches'], true) || ! is_string($item['file'] ?? null)) {
				return $group;
			}

			$files[$item['file']] = true;
		}

		return count($files) === 1 ? [...$group, 'source' => array_key_first($files)] : $group;
	}

	/**
	 * Sorts the embed providers by label, built in or not.
	 *
	 * @param  list<array{name: string, label: string, hosts: list<string>}> $providers
	 * @return list<array{name: string, label: string, hosts: list<string>}>
	 */
	private static function byLabel(array $providers): array
	{
		usort($providers, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

		return $providers;
	}

	/**
	 * A group of settings.
	 *
	 * @param  list<array<string, mixed>> $items
	 * @return array<string, mixed>
	 */
	private static function group(string $key, string|Text $title, string|Text $hint, array $items, string|Text|null $note = null): array
	{
		return ['key' => $key, 'title' => $title, 'hint' => $hint, 'note' => $note, 'items' => $items];
	}

	/**
	 * Makes a setting editable: what it saves as, its field (D-343, with
	 * words for its options and its `caption`), the value the form starts
	 * from, and whether it's saved. A setting whose options are known only
	 * here passes its `$field`.
	 *
	 * @param  array<string, mixed> $item
	 * @return array<string, mixed>
	 */
	private function edit(array $item, Settings $saved, Setting $setting, mixed $input, ?Field $field = null): array
	{
		$field   = $field ?? $setting->field($this->types, $this->messages);
		$control = $setting->control();
		$field   = FormField::of($field, $control === null ? new FieldControls() : new FieldControls([$field->name => $control]), $this->defaults)->toArray();
		$choices = $setting->choices($this->types, $this->messages);
		$details = $setting->details($this->messages);

		if ($choices !== []) {
			if (is_array($field['item'] ?? null)) {
				$field['item']['choices'] = $choices;
			} else {
				$field['choices'] = $choices;
			}
		}

		if ($details !== []) {
			$field['details'] = $details;
		}

		if ($setting->caption($this->messages) !== null) {
			$field['caption'] = $setting->caption($this->messages);
		}

		return [...$item, 'setting' => $setting->value, 'field' => $field, 'input' => $input, 'saved' => $saved->has($setting), 'file' => $setting->file()];
	}

	/**
	 * One setting.
	 *
	 * @param  string|bool|list<string>|Text $value
	 * @param  ?bool                         $default `null` for a value that follows from others.
	 * @return array<string, mixed>
	 */
	private static function item(string $key, string|Text $label, string|bool|array|Text $value, ?bool $default, string $kind = 'text', string|Text|null $help = null, ?string $file = null): array
	{
		return ['key' => $key, 'label' => $label, 'value' => $value, 'kind' => $kind, 'default' => $default, 'help' => $help, 'warning' => null, 'file' => $file];
	}
}
