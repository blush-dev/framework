<?php

/**
 * Setting enum.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Settings;

use DateTimeZone;
use Blush\Admin\Theme\AdminThemeConfig;
use Blush\Api\ApiAccess;
use Blush\Api\ApiConfig;
use Blush\Auth\Account;
use Blush\Auth\AuthConfig;
use Blush\Backup\BackupConfig;
use Blush\Backup\BackupSchedule;
use Blush\Clock\DateFormat;
use Blush\Config\Config;
use Blush\Config\InvalidConfig;
use Blush\Content\ContentConfig;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\TypeKind;
use Blush\Core\AppConfig;
use Blush\Core\Untranslated;
use Blush\Embed\EmbedConfig;
use Blush\Extension\ExtensionName;
use Blush\Feed\FeedConfig;
use Blush\Feed\FeedFormat;
use Blush\Field\Control;
use Blush\Field\Controls\Checkboxes;
use Blush\Field\Controls\Code;
use Blush\Field\Controls\Radios;
use Blush\Field\Controls\ViewOnly;
use Blush\Field\Field;
use Blush\Field\Fields\BoolField;
use Blush\Field\Fields\EnumField;
use Blush\Field\Fields\ListField;
use Blush\Field\Fields\NumberField;
use Blush\Field\Fields\ObjectField;
use Blush\Field\Fields\TextField;
use Blush\Icon\IconConfig;
use Blush\Llms\LlmsConfig;
use Blush\Mail\MailConfig;
use Blush\Mcp\McpConfig;
use Blush\Markdown\MarkdownConfig;
use Blush\Markdown\RawHtml;
use Blush\Media\MediaConfig;
use Blush\Media\MediaUploads;
use Blush\Plugin\PluginConfig;
use Blush\Routing\RouteConfig;
use Blush\Sitemap\AiCrawlerGroup;
use Blush\Sitemap\SitemapConfig;
use Blush\Theme\ThemeConfig;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * A setting the site owner may change in the admin (D-324, D-325). Its
 * value is `{section}.{key}`: the group of settings it's saved in
 * (`SettingGroups`, D-673; on files `user/data/settings/{section}.json`),
 * named for the config file by convention (`feed` for
 * `config/feed.php`), and the key it sets in that config object's
 * `toArray()`. Everything else stays in `config/` and `.env`.
 *
 * Each is described as a field (`field()`, D-343), so the admin edits it
 * with the controls every form uses, on its screen (`screen()`), the
 * screen's own fields before any field set's; `normalize()` still checks
 * its value, more closely than the field type does. The active theme
 * (D-381) is on no Settings screen: the Themes screen saves it. Nor are
 * the plugins and icon packs turned off (D-385), which their screens
 * save.
 */
enum Setting: string
{
	case Name             = 'app.name';
	case Description      = 'app.description';
	case Email            = 'app.email';
	case Locale           = 'app.locale';
	case Untranslated     = 'app.untranslated';
	case Timezone         = 'app.timezone';
	case DateFormat       = 'app.dateFormat';
	case TimeFormat       = 'app.timeFormat';
	case Signups          = 'auth.signups';
	case SignupRole       = 'auth.signupRole';
	case Home             = 'content.home';
	case TrailingSlash    = 'routes.trailingSlash';
	case FeedFormats      = 'feed.formats';
	case FeedContent      = 'feed.fullContent';
	case FeedLimit        = 'feed.limit';
	case Api              = 'api.enabled';
	case ApiAccess        = 'api.access';
	case MediaUploads     = 'media.uploads';
	case AddArtwork       = 'media.addArtwork';
	case MailFromName     = 'mail.fromName';
	case MailFromAddress  = 'mail.fromAddress';
	case Mentions         = 'markdown.mentions';
	case SmartPunctuation = 'markdown.smartPunctuation';
	case HeadingAnchors   = 'markdown.headingAnchors';
	case Figures          = 'markdown.figures';
	case Html             = 'markdown.html';
	case Embeds           = 'embed.enabled';
	case Sitemap          = 'sitemap.enabled';
	case SitemapDisallow  = 'sitemap.disallow';
	case Llms             = 'llms.enabled';
	case LlmsFull         = 'llms.full';
	case BlockAi          = 'sitemap.blockAi';
	case Mcp              = 'mcp.enabled';
	case Theme            = 'theme.active';
	case Plugins          = 'plugins.enabled';
	case IconPacks        = 'icons.enabled';
	case AdminThemes      = 'admin-themes.enabled';
	case AdminThemeDefault = 'admin-themes.default';
	case Backups          = 'backups.schedule';

	/**
	 * The most entries a feed may hold.
	 */
	public const int FEED_LIMIT_MAX = 100;

	/**
	 * The longest a site's description may be.
	 */
	public const int DESCRIPTION_MAX = 300;

	/**
	 * The most paths robots.txt may be asked to skip.
	 */
	public const int DISALLOW_MAX = 50;

	/**
	 * Every section the settings are saved in, each a group of settings
	 * (`SettingGroups`, D-673), in the enum's order.
	 *
	 * @return list<string>
	 */
	public static function sections(): array
	{
		return array_values(array_unique(array_map(static fn (self $setting): string => $setting->section(), self::cases())));
	}

	/**
	 * The setting saved under a section and key, if there is one.
	 */
	public static function find(string $section, string $key): ?self
	{
		return self::tryFrom("{$section}.{$key}");
	}

	/**
	 * The Settings screen it's on, or `null` for one another screen saves.
	 */
	public function screen(): ?SettingsScreen
	{
		return match ($this) {
			self::Theme, self::Plugins, self::IconPacks,
			self::AdminThemes, self::AdminThemeDefault, self::Backups     => null,
			self::Name, self::Description, self::Email, self::Locale, self::Untranslated,
			self::Timezone, self::DateFormat, self::TimeFormat,
			self::Signups, self::SignupRole                               => SettingsScreen::General,
			self::Home, self::FeedFormats, self::FeedContent, self::FeedLimit => SettingsScreen::Reading,
			self::Api, self::ApiAccess                                    => SettingsScreen::Api,
			self::MediaUploads, self::AddArtwork                          => SettingsScreen::Media,
			self::MailFromName, self::MailFromAddress                     => SettingsScreen::Email,
			self::Mentions, self::SmartPunctuation, self::HeadingAnchors,
			self::Figures, self::Html, self::Embeds                       => SettingsScreen::Writing,
			self::TrailingSlash, self::Sitemap, self::SitemapDisallow     => SettingsScreen::Search,
			self::Llms, self::LlmsFull, self::BlockAi, self::Mcp          => SettingsScreen::Ai
		};
	}

	/**
	 * The control the Settings screens draw the setting with, or `null`
	 * for its field's default (D-795).
	 */
	public function control(): ?Control
	{
		return match ($this) {
			self::Locale, self::DateFormat, self::TimeFormat, self::Theme => new Code(),
			self::Untranslated, self::Html, self::ApiAccess               => new Radios(),
			self::FeedFormats, self::BlockAi                              => new Checkboxes(),
			self::MediaUploads                                            => new ViewOnly(),
			default                                                       => null
		};
	}

	/**
	 * The setting as a field (D-343), named by its key, with its label
	 * and help; a choice has the options it can be (the
	 * homepage's, from the site's collections with addresses; a site with
	 * none has nothing to choose, and the admin only shows it).
	 */
	public function field(ContentTypes $types, Messages $messages): Field
	{
		$field = match ($this) {
			self::Name            => new TextField('name')->labeled($messages->t('settings.setting.name.label', 'Site name'))->required(),
			self::Description     => new TextField('description')->labeled($messages->t('settings.setting.description.label', 'Description'))->described($messages->t('settings.setting.description.description', 'One line about the site, for llms.txt, and for the homepage and feeds when nothing more specific describes them. Search results show about 160 characters.')),
			self::Email           => new TextField('email')->labeled($messages->t('settings.setting.email.label', 'Email address'))->described($messages->t('settings.setting.email.description', 'Where the site sends notices for its owner. It isn\'t shown on the site.'))->required(),
			self::Locale          => new TextField('locale')->labeled($messages->t('settings.setting.locale.label', 'Language and region'))->described($messages->t('settings.setting.locale.description', 'A language code, with a region if you like, such as en_US or fr.')),
			self::Untranslated    => new EnumField('untranslated', array_column(Untranslated::cases(), 'value'))->labeled($messages->t('settings.setting.untranslated.label', 'Untranslated pages'))->described($messages->t('settings.setting.untranslated.description', 'What another language\'s address does for an entry not translated into it.'))->required(),
			self::Timezone        => new EnumField('timezone', DateTimeZone::listIdentifiers())->labeled($messages->t('settings.setting.timezone.label', 'Time zone')),
			self::DateFormat      => new TextField('dateFormat')->labeled($messages->t('settings.setting.date_format.label', 'Date format'))->described(self::formatHelp($messages)),
			self::TimeFormat      => new TextField('timeFormat')->labeled($messages->t('settings.setting.time_format.label', 'Time format'))->described(self::formatHelp($messages)),
			self::Signups         => new BoolField('signups')->labeled($messages->t('settings.setting.signups.label', 'Sign-ups'))->described($messages->t('settings.setting.signups.description', 'Anyone can make an account on the site. Off, only someone who can add accounts makes them.')),
			self::SignupRole      => new TextField('signupRole')->labeled($messages->t('settings.setting.signup_role.label', 'Role for new accounts'))->described($messages->t('settings.setting.signup_role.description', 'The role an account made by signing up holds. Never the owner or an administrator.')),
			self::Home            => self::homeChoices($types, $messages) === []
				? new TextField('home')->labeled($messages->t('settings.setting.home.label', 'Homepage'))
				: new EnumField('home', array_keys(self::homeChoices($types, $messages)))->labeled($messages->t('settings.setting.home.label', 'Homepage')),
			self::FeedFormats     => new ListField('formats', new EnumField('', array_column(FeedFormat::cases(), 'value')))->labeled($messages->t('settings.setting.feed_formats.label', 'Formats'))->described($messages->t('settings.setting.feed_formats.description', 'None turns every feed off.')),
			self::FeedContent     => new BoolField('content')->labeled($messages->t('settings.setting.feed_content.label', 'Full content'))->described($messages->t('settings.setting.feed_content.description', 'Off, a feed carries each entry\'s summary only.')),
			self::MediaUploads    => new ObjectField('uploads')->labeled($messages->t('settings.setting.media_uploads.label', 'Uploads'))->described($messages->t('settings.setting.media_uploads.description', 'What may be uploaded, how large, and the folder under user/media it goes in.')),
			self::AddArtwork      => new BoolField('addArtwork')->labeled($messages->t('settings.setting.add_artwork.label', 'Artwork from uploads'))->described($messages->t('settings.setting.add_artwork.description', 'A sound or video\'s artwork is added to the library as an image of its own when it\'s uploaded, by someone who may upload images. Tracks with the same artwork share one image. Off, a file\'s screen can still add it.')),
			self::MailFromName    => new TextField('fromName')->labeled($messages->t('settings.setting.mail_from_name.label', 'From name'))->described($messages->t('settings.setting.mail_from_name.description', 'Who the site\'s email says it\'s from. Empty, the site\'s name.')),
			self::MailFromAddress => new TextField('fromAddress')->labeled($messages->t('settings.setting.mail_from_address.label', 'From address'))->described($messages->t('settings.setting.mail_from_address.description', 'The address the site\'s email is sent from, which your mail service must allow. Empty, the site\'s email address.')),
			self::Mentions        => new BoolField('mentions')->labeled($messages->t('settings.setting.mentions.label', 'Mentions'))->described($messages->t('settings.setting.mentions.description', '@name links to the profile with that slug, once it\'s published. A name that isn\'t anyone\'s stays text.')),
			self::SmartPunctuation => new BoolField('smartPunctuation')->labeled($messages->t('settings.setting.smart_punctuation.label', 'Smart punctuation'))->described($messages->t('settings.setting.smart_punctuation.description', 'Straight quotes become curly ones, -- and --- dashes, and ... an ellipsis. Code is left as written.')),
			self::HeadingAnchors  => new BoolField('headingAnchors')->labeled($messages->t('settings.setting.heading_anchors.label', 'Heading anchors'))->described($messages->t('settings.setting.heading_anchors.description', 'Each heading gets a link to itself, so a section can be shared.')),
			self::Figures         => new BoolField('figures')->labeled($messages->t('settings.setting.figures.label', 'Images as figures'))->described($messages->t('settings.setting.figures.description', 'An image on a line of its own becomes a figure, with its title as the caption.')),
			self::Html            => new EnumField('html', array_column(RawHtml::cases(), 'value'))->labeled($messages->t('settings.setting.html.label', 'Raw HTML'))->described($messages->t('settings.setting.html.description', 'What HTML written in content does on the page, whoever wrote it. Who may add HTML in the admin is up to their role.'))->required(),
			self::Embeds          => new ListField('enabled')->labeled($messages->t('settings.setting.embeds.label', 'Embeds turned on'))->described($messages->t('settings.setting.embeds.description', 'A site turned on plays its links on the page; the others keep their links as links.')),
			self::Api             => new BoolField('enabled')->labeled($messages->t('settings.setting.api.label', 'API'))->described($messages->t('settings.setting.api.description', 'An API of what the site shows, as JSON, for other sites, apps, and AI tools: its pages, types, media, and menus. Drafts and hidden pages are never in it without a token, and only a token may change content.')),
			self::ApiAccess       => new EnumField('access', array_column(ApiAccess::cases(), 'value'))->labeled($messages->t('settings.setting.api_access.label', 'Who can read it'))->described($messages->t('settings.setting.api_access.description', 'A token is made on Your Account, and acts as the account it belongs to.'))->required(),
			self::FeedLimit       => new NumberField('limit', integer: true, min: 1, max: self::FEED_LIMIT_MAX)->labeled($messages->t('settings.setting.feed_limit.label', 'Entries per feed'))->described($messages->t('settings.setting.feed_limit.description', 'From 1 to {max}.', max: self::FEED_LIMIT_MAX)),
			self::TrailingSlash   => new BoolField('trailingSlash')->labeled($messages->t('settings.setting.trailing_slash.label', 'Trailing slash'))->described($messages->t('settings.setting.trailing_slash.description', 'The other form redirects, so links to either still work.')),
			self::Sitemap         => new BoolField('enabled')->labeled($messages->t('settings.setting.sitemap.label', 'Sitemap and robots.txt'))->described($messages->t('settings.setting.sitemap.description', 'Off, the site has neither, and search engines find pages by their links.')),
			self::SitemapDisallow => new ListField('disallow')->labeled($messages->t('settings.setting.sitemap_disallow.label', 'Paths robots.txt asks to skip'))->described($messages->t('settings.setting.sitemap_disallow.description', 'One path a line, each starting with /, such as /drafts/.')),
			self::Llms            => new BoolField('enabled')->labeled($messages->t('settings.setting.llms.label', 'Markdown copies'))->described($messages->t('settings.setting.llms.description', 'A page\'s copy is at its address with .md, such as /about.md, and llms.txt lists them, for AI tools to read.')),
			self::Mcp             => new BoolField('enabled')->labeled($messages->t('settings.setting.mcp.label', 'MCP server'))->described($messages->t('settings.setting.mcp.description', 'Lets AI apps, such as Claude and ChatGPT, read and write entries and media as the people who connect them, with what those people may do. Each person approves an app when they connect it.')),
			self::LlmsFull        => new BoolField('full')->labeled('llms-full.txt')->described($messages->t('settings.setting.llms_full.description', 'Every page llms.txt lists, in full, in one file, so a tool can read the whole site at once. It can be several megabytes on a large site.')),
			self::BlockAi         => new ListField('blockAi', new EnumField('', array_column(AiCrawlerGroup::cases(), 'value')))->labeled($messages->t('settings.setting.block_ai.label', 'Ask to stay away'))->described($messages->t('settings.setting.block_ai.description', 'robots.txt is a request: well-behaved crawlers follow it, others may not.')),
			self::Theme           => new TextField('active')->labeled($messages->t('settings.setting.theme.label', 'Theme')),
			self::Plugins         => new ListField('enabled')->labeled($messages->t('settings.setting.plugins.label', 'Plugins turned on')),
			self::IconPacks       => new ListField('enabled')->labeled($messages->t('settings.setting.icon_packs.label', 'Icon packs turned on')),
			self::AdminThemes     => new ListField('enabled')->labeled($messages->t('settings.setting.admin_themes.label', 'Admin themes turned on')),
			self::AdminThemeDefault => new TextField('default')->labeled($messages->t('settings.setting.admin_theme_default.label', 'The site\'s admin theme')),
			self::Backups         => new ObjectField('schedule')->labeled($messages->t('settings.setting.backups.label', 'Backup schedule'))
		};

		// A screen's fields are one schema, so a key an earlier setting on
		// it has (`llms.enabled`, then `mcp.enabled`) takes its section.
		$first = array_find($this->screen()?->settings() ?? [], fn (self $other): bool => $other->key() === $this->key());

		return $field->named($first === null || $first === $this ? $this->key() : $this->section() . ucfirst($this->key()));
	}

	/**
	 * Words for the field's values, where its options aren't words: a
	 * time zone without underscores, the untranslated settings' names,
	 * the homepage's collections, and the feed formats' names.
	 *
	 * @return array<string, string|Text>
	 */
	public function choices(ContentTypes $types, Messages $messages): array
	{
		return match ($this) {
			self::Timezone     => array_combine(DateTimeZone::listIdentifiers(), array_map(static fn (string $zone): string => str_replace('_', ' ', $zone), DateTimeZone::listIdentifiers())),
			self::Untranslated => array_combine(array_column(Untranslated::cases(), 'value'), array_map(static fn (Untranslated $case): Text => $case->label($messages), Untranslated::cases())),
			self::Home         => self::homeChoices($types, $messages),
			self::FeedFormats  => array_combine(array_column(FeedFormat::cases(), 'value'), array_map(static fn (FeedFormat $format): Text => $format->label($messages), FeedFormat::cases())),
			self::BlockAi      => array_combine(array_column(AiCrawlerGroup::cases(), 'value'), array_map(static fn (AiCrawlerGroup $group): Text => $group->label($messages), AiCrawlerGroup::cases())),
			self::Html         => array_combine(array_column(RawHtml::cases(), 'value'), array_map(static fn (RawHtml $case): Text => $case->label($messages), RawHtml::cases())),
			self::ApiAccess    => array_combine(array_column(ApiAccess::cases(), 'value'), array_map(static fn (ApiAccess $case): Text => $case->label($messages), ApiAccess::cases())),
			default            => []
		};
	}

	/**
	 * More about each of the field's options, where a name alone doesn't
	 * say enough (D-404): a sentence (`text`) and the machine names it
	 * covers (`code`, or `''`), as the AI crawler groups' user agents, and
	 * what each untranslated setting does (D-469).
	 *
	 * @return array<string, array{text: string|Text, code: string}>
	 */
	public function details(Messages $messages): array
	{
		return match ($this) {
			self::BlockAi      => array_combine(array_column(AiCrawlerGroup::cases(), 'value'), array_map(static fn (AiCrawlerGroup $group): array => ['text' => $group->description($messages), 'code' => implode(' · ', $group->agents())], AiCrawlerGroup::cases())),
			self::Untranslated => array_combine(array_column(Untranslated::cases(), 'value'), array_map(static fn (Untranslated $case): array => ['text' => $case->description($messages), 'code' => ''], Untranslated::cases())),
			self::Html         => array_combine(array_column(RawHtml::cases(), 'value'), array_map(static fn (RawHtml $case): array => ['text' => $case->description($messages), 'code' => ''], RawHtml::cases())),
			self::ApiAccess    => array_combine(array_column(ApiAccess::cases(), 'value'), array_map(static fn (ApiAccess $case): array => ['text' => $case->description($messages), 'code' => ''], ApiAccess::cases())),
			default            => []
		};
	}

	/**
	 * What a checkbox beside the setting says, or what a choice left
	 * empty means.
	 */
	public function caption(Messages $messages): ?Text
	{
		return match ($this) {
			self::TrailingSlash => $messages->t('settings.setting.trailing_slash.caption', 'Addresses end in a slash'),
			self::FeedContent   => $messages->t('settings.setting.feed_content.caption', 'Feeds carry each entry\'s full content'),
			self::Signups       => $messages->t('settings.setting.signups.caption', 'Anyone can sign up'),
			self::Sitemap       => $messages->t('settings.setting.sitemap.caption', 'The site has a sitemap and robots.txt'),
			self::Llms          => $messages->t('settings.setting.llms.caption', 'Every page has a Markdown copy, and the site has llms.txt'),
			self::Api           => $messages->t('settings.setting.api.caption', 'The site has an API'),
			self::LlmsFull      => $messages->t('settings.setting.llms_full.caption', 'The site has llms-full.txt'),
			self::Mcp           => $messages->t('settings.setting.mcp.caption', 'Apps can connect'),
			self::Home          => $messages->t('settings.setting.home.caption', 'The page at user/content/index.md'),
			self::Mentions      => $messages->t('settings.setting.mentions.caption', '@name links to a profile'),
			self::AddArtwork    => $messages->t('settings.setting.add_artwork.caption', 'Uploads add their artwork to the library'),
			self::SmartPunctuation => $messages->t('settings.setting.smart_punctuation.caption', 'Quotes, dashes, and ellipses are typographic'),
			self::HeadingAnchors => $messages->t('settings.setting.heading_anchors.caption', 'Headings link to themselves'),
			self::Figures       => $messages->t('settings.setting.figures.caption', 'A lone image is a figure'),
			default             => null
		};
	}

	/**
	 * The collection types that can be the homepage, by name, with what
	 * each shows.
	 *
	 * @return array<string, Text>
	 */
	public static function homeChoices(ContentTypes $types, Messages $messages): array
	{
		$choices = [];

		foreach ($types->all() as $type) {
			if ($type->kind() === TypeKind::Collection && $type->hasUrls()) {
				$choices[$type->name] = $messages->t('settings.setting.home_choice', 'The latest {type}', type: mb_strtolower($type->labels->plural));
			}
		}

		return $choices;
	}

	/**
	 * The file section it's saved in.
	 */
	public function section(): string
	{
		return strstr($this->value, '.', true) ?: $this->value;
	}

	/**
	 * The key it sets in its config object's `toArray()`.
	 */
	public function key(): string
	{
		return substr($this->value, strlen($this->section()) + 1);
	}

	/**
	 * The key it replaces in its config object: its own key, except the
	 * admin's lists of what's on, which are kept apart from the config
	 * file's (`saved`, D-391) because they name Composer extensions too,
	 * and feeds' full content, saved as `fullContent` since `content` is a
	 * record's own (D-673).
	 */
	public function configKey(): string
	{
		return match ($this) {
			self::Plugins, self::IconPacks, self::AdminThemes => 'saved',
			self::FeedContent              => 'content',
			default                        => $this->key()
		};
	}

	/**
	 * The config object the setting is laid over.
	 *
	 * @return class-string<Config>
	 */
	public function config(): string
	{
		return match ($this->section()) {
			'app'     => AppConfig::class,
			'auth'    => AuthConfig::class,
			'content' => ContentConfig::class,
			'routes'  => RouteConfig::class,
			'feed'    => FeedConfig::class,
			'theme'   => ThemeConfig::class,
			'plugins' => PluginConfig::class,
			'icons'   => IconConfig::class,
			'admin-themes' => AdminThemeConfig::class,
			'llms'    => LlmsConfig::class,
			'api'     => ApiConfig::class,
			'mcp'     => McpConfig::class,
			'media'   => MediaConfig::class,
			'markdown' => MarkdownConfig::class,
			'embed'   => EmbedConfig::class,
			'mail'    => MailConfig::class,
			'backups' => BackupConfig::class,
			default   => SitemapConfig::class
		};
	}

	/**
	 * The file its value comes from when it isn't saved, by convention.
	 */
	public function file(): string
	{
		return "config/{$this->section()}.php";
	}

	/**
	 * Whether a change needs the compiled routes and content types written
	 * again, and the content reindexed: the homepage, the time zone dates
	 * are read in, what has addresses, and the theme and plugins (their
	 * providers run at boot, so what's compiled is built again with them,
	 * to be safe).
	 */
	public function needsRefresh(): bool
	{
		return match ($this) {
			self::Home, self::Timezone, self::TrailingSlash, self::FeedFormats, self::Sitemap, self::Llms, self::LlmsFull, self::Api, self::ApiAccess, self::Mcp, self::Theme, self::Plugins => true,
			default => false
		};
	}

	/**
	 * Checks a value and returns it as it's saved: trimmed, lists without
	 * repeats, and feed formats in their usual order. Whether the
	 * homepage's type exists is checked where the types are known.
	 *
	 * @throws InvalidSetting
	 */
	public function normalize(mixed $value, Messages $messages): mixed
	{
		return match ($this) {
			self::Name            => self::name($value, $messages),
			self::Description     => self::description($value, $messages),
			self::Email           => self::email($value, $messages),
			self::MailFromName    => self::fromName($value, $messages),
			self::MailFromAddress => self::fromAddress($value, $messages),
			self::Locale          => self::locale($value, $messages),
			self::Untranslated    => self::untranslated($value, $messages),
			self::Html            => self::html($value, $messages),
			self::ApiAccess       => self::apiAccess($value, $messages),
			self::SignupRole      => self::signupRole($value, $messages),
			self::Timezone        => self::timezone($value, $messages),
			self::DateFormat,
			self::TimeFormat      => self::dateFormat($value, $messages),
			self::Home            => self::home($value, $messages),
			self::FeedFormats     => self::formats($value, $messages),
			self::FeedLimit       => self::limit($value, $messages),
			self::SitemapDisallow => self::disallow($value, $messages),
			self::BlockAi         => self::blockAi($value, $messages),
			self::MediaUploads    => self::uploads($value, $messages),
			self::Theme           => self::theme($value, $messages),
			self::Plugins         => self::names($value, 'plugins', $messages),
			self::IconPacks       => self::names($value, 'icon_packs', $messages),
			self::AdminThemes     => self::names($value, 'admin_themes', $messages),
			self::AdminThemeDefault => self::adminTheme($value, $messages),
			self::Backups         => self::backupSchedule($value, $messages),
			self::Embeds          => self::providers($value, $messages),
			default               => is_bool($value) ? $value : throw self::invalid($messages->t('settings.setting.invalid.bool', '"{setting}" must be true or false.', setting: $this->value))
		};
	}

	/**
	 * What the date and time formats are (D-445).
	 */
	private static function formatHelp(Messages $messages): Text
	{
		return $messages->t('settings.setting.format_help', 'How themes show dates and times. One from the language follows the site\'s language; a fixed one keeps its order whatever the language.');
	}

	/**
	 * Returns the exception for a value that doesn't fit, its message
	 * translated now, in the current language.
	 */
	private static function invalid(Text $message): InvalidSetting
	{
		return new InvalidSetting((string) $message);
	}

	/**
	 * @throws InvalidSetting
	 */
	private static function name(mixed $value, Messages $messages): string
	{
		$name = is_string($value) ? trim($value) : throw self::invalid($messages->t('settings.setting.invalid.name_text', 'The site name must be text.'));

		return match (true) {
			$name === ''                                    => throw self::invalid($messages->t('settings.setting.invalid.name_empty', 'The site needs a name.')),
			mb_strlen($name) > 100                          => throw self::invalid($messages->t('settings.setting.invalid.name_long', 'The site name can be at most {max} characters.', max: 100)),
			preg_match('/[\p{Cc}\p{Zl}\p{Zp}]/u', $name) !== 0 => throw self::invalid($messages->t('settings.setting.invalid.name_line', 'The site name can\'t hold line breaks or control characters.')),
			default                                         => $name
		};
	}

	/**
	 * @throws InvalidSetting
	 */
	private static function description(mixed $value, Messages $messages): string
	{
		$description = is_string($value) ? trim($value) : throw self::invalid($messages->t('settings.setting.invalid.description_text', 'The site\'s description must be text.'));

		return match (true) {
			mb_strlen($description) > self::DESCRIPTION_MAX                => throw self::invalid($messages->t('settings.setting.invalid.description_long', 'The site\'s description can be at most {max} characters.', max: self::DESCRIPTION_MAX)),
			preg_match('/[\p{Cc}\p{Zl}\p{Zp}]/u', $description) !== 0 => throw self::invalid($messages->t('settings.setting.invalid.description_line', 'The site\'s description is one line, without control characters.')),
			default                                                       => $description
		};
	}

	/**
	 * Checks the site's email address, which it can't be without (D-723).
	 *
	 * @throws InvalidSetting
	 */
	private static function email(mixed $value, Messages $messages): string
	{
		$email = is_string($value) ? trim($value) : throw self::invalid($messages->t('settings.setting.invalid.email_text', 'The site\'s email address must be text.'));

		return match (true) {
			$email === ''                     => throw self::invalid($messages->t('settings.setting.invalid.email_empty', 'The site needs an email address.')),
			! Account::isValidEmail($email) => throw self::invalid($messages->t('settings.setting.invalid.email', '"{email}" isn\'t an email address.', email: $email)),
			default                         => $email
		};
	}

	/**
	 * Checks who the site's mail is from (D-846): one line, or empty for
	 * the site's name.
	 *
	 * @throws InvalidSetting
	 */
	private static function fromName(mixed $value, Messages $messages): string
	{
		$name = is_string($value) ? trim($value) : throw self::invalid($messages->t('settings.setting.invalid.from_name_text', 'The from name must be text.'));

		return match (true) {
			mb_strlen($name) > 100                          => throw self::invalid($messages->t('settings.setting.invalid.from_name_long', 'The from name can be at most {max} characters.', max: 100)),
			preg_match('/[\p{Cc}\p{Zl}\p{Zp}]/u', $name) !== 0 => throw self::invalid($messages->t('settings.setting.invalid.from_name_line', 'The from name can\'t hold line breaks or control characters.')),
			default                                         => $name
		};
	}

	/**
	 * Checks the address the site's mail is from (D-846), or empty for
	 * the site's own.
	 *
	 * @throws InvalidSetting
	 */
	private static function fromAddress(mixed $value, Messages $messages): string
	{
		$email = is_string($value) ? trim($value) : throw self::invalid($messages->t('settings.setting.invalid.from_address_text', 'The from address must be text.'));

		return $email === '' || Account::isValidEmail($email)
			? $email
			: throw self::invalid($messages->t('settings.setting.invalid.email', '"{email}" isn\'t an email address.', email: $email));
	}

	/**
	 * @throws InvalidSetting
	 */
	private static function locale(mixed $value, Messages $messages): string
	{
		$locale = is_string($value) ? trim($value) : '';

		return preg_match('/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/', $locale) === 1
			? $locale
			: throw self::invalid($messages->t('settings.setting.invalid.locale', 'Use a language code, with a region if you like, such as en_US or fr.'));
	}

	/**
	 * @throws InvalidSetting
	 */
	private static function untranslated(mixed $value, Messages $messages): string
	{
		return (is_string($value) ? Untranslated::tryFrom($value) : null)->value
			?? throw self::invalid($messages->t('settings.setting.invalid.untranslated', 'Untranslated pages are one of: {values}.', values: implode(', ', array_column(Untranslated::cases(), 'value'))));
	}

	/**
	 * @throws InvalidSetting
	 */
	private static function apiAccess(mixed $value, Messages $messages): string
	{
		return (is_string($value) ? ApiAccess::tryFrom($value) : null)->value
			?? throw self::invalid($messages->t('settings.setting.invalid.api_access', 'Who can read the API is one of: {values}.', values: implode(', ', array_column(ApiAccess::cases(), 'value'))));
	}

	/**
	 * @throws InvalidSetting
	 */
	private static function html(mixed $value, Messages $messages): string
	{
		return (is_string($value) ? RawHtml::tryFrom($value) : null)->value
			?? throw self::invalid($messages->t('settings.setting.invalid.html', 'Raw HTML is one of: {values}.', values: implode(', ', array_column(RawHtml::cases(), 'value'))));
	}

	/**
	 * Checks a role for accounts made by signing up (D-518); whether it
	 * exists is checked where the roles are known.
	 *
	 * @throws InvalidSetting
	 */
	private static function signupRole(mixed $value, Messages $messages): string
	{
		$role    = is_string($value) ? trim($value) : throw self::invalid($messages->t('settings.setting.invalid.signup_role', 'The role for new accounts must be a role\'s name.'));
		$problem = AuthConfig::signupRoleProblem($role);

		return $problem === null ? $role : throw new InvalidSetting($problem);
	}

	/**
	 * @throws InvalidSetting
	 */
	private static function timezone(mixed $value, Messages $messages): string
	{
		return is_string($value) && in_array($value, DateTimeZone::listIdentifiers(), true)
			? $value
			: throw self::invalid($messages->t('settings.setting.invalid.timezone', '"{zone}" isn\'t a time zone; use one such as America/Chicago or UTC.', zone: is_string($value) ? $value : get_debug_type($value)));
	}

	/**
	 * @throws InvalidSetting
	 */
	private static function dateFormat(mixed $value, Messages $messages): string
	{
		$format  = is_string($value) ? trim($value) : throw self::invalid($messages->t('settings.setting.invalid.date_format', 'A date or time format must be text.'));
		$problem = DateFormat::problem($format, $messages);

		return $problem === null ? $format : throw self::invalid($problem);
	}

	/**
	 * @throws InvalidSetting
	 */
	private static function home(mixed $value, Messages $messages): ?string
	{
		return $value === null || (is_string($value) && preg_match('/^[a-z][a-z0-9_-]*$/', $value) === 1)
			? $value
			: throw self::invalid($messages->t('settings.setting.invalid.home', 'The homepage must name a content type, or be null for the page at user/content/index.md.'));
	}

	/**
	 * @return list<string>
	 * @throws InvalidSetting
	 */
	private static function formats(mixed $value, Messages $messages): array
	{
		if (! is_array($value) || ! array_is_list($value)) {
			throw self::invalid($messages->t('settings.setting.invalid.feed_formats', 'Feed formats must be a list: rss, atom, or json.'));
		}

		foreach ($value as $format) {
			if (! is_string($format) || FeedFormat::tryFrom($format) === null) {
				throw self::invalid($messages->t('settings.setting.invalid.feed_format', '"{format}" isn\'t a feed format; use rss, atom, or json.', format: is_string($format) ? $format : get_debug_type($format)));
			}
		}

		return array_values(array_map(
			static fn (FeedFormat $format): string => $format->value,
			array_filter(FeedFormat::cases(), static fn (FeedFormat $format): bool => in_array($format->value, $value, true))
		));
	}

	/**
	 * @throws InvalidSetting
	 */
	private static function limit(mixed $value, Messages $messages): int
	{
		return is_int($value) && $value >= 1 && $value <= self::FEED_LIMIT_MAX
			? $value
			: throw self::invalid($messages->t('settings.setting.invalid.feed_limit', 'A feed holds from 1 to {max} entries.', max: self::FEED_LIMIT_MAX));
	}

	/**
	 * Checks a theme's name; whether it's installed is checked where the
	 * themes are known.
	 *
	 * @throws InvalidSetting
	 */
	private static function theme(mixed $value, Messages $messages): string
	{
		return is_string($value) && ExtensionName::isValid($value)
			? $value
			: throw self::invalid($messages->t('settings.setting.invalid.theme', 'The theme must be a theme\'s name (vendor/name), such as "acme/nova".'));
	}

	/**
	 * @throws InvalidSetting
	 */
	private static function adminTheme(mixed $value, Messages $messages): string
	{
		return is_string($value) && ExtensionName::isValid($value)
			? $value
			: throw self::invalid($messages->t('settings.setting.invalid.admin_theme', 'The site\'s admin theme must be an admin theme\'s name (vendor/name), such as "blush/library".'));
	}

	/**
	 * Checks a list of extensions' names, and returns it without repeats,
	 * in order; whether they're installed doesn't matter, so a list
	 * outlives a plugin that's removed and put back. `$kind` is the
	 * extensions' kind, as the messages' `select` names it.
	 *
	 * @param  'plugins'|'icon_packs'|'admin_themes' $kind
	 * @return list<string>
	 * @throws InvalidSetting
	 */
	private static function names(mixed $value, string $kind, Messages $messages): array
	{
		if (! is_array($value) || ! array_is_list($value)) {
			throw self::invalid($messages->t('settings.setting.invalid.names', 'The {kind, select, plugins {plugins} icon_packs {icon packs} other {admin themes}} turned off must be a list of names.', kind: $kind));
		}

		foreach ($value as $name) {
			if (! is_string($name) || ! ExtensionName::isValid($name)) {
				throw self::invalid($messages->t('settings.setting.invalid.name', '"{name}" isn\'t a name; the {kind, select, plugins {plugins} icon_packs {icon packs} other {admin themes}} turned off are named vendor/name, such as "acme/gallery".', name: is_string($name) ? $name : get_debug_type($name), kind: $kind));
			}
		}

		$names = array_values(array_unique($value));
		sort($names);

		return $names;
	}

	/**
	 * The embed providers turned on (D-633, D-705): names, sorted, once each.
	 * Any name is kept, as a plugin's provider may not be loaded.
	 *
	 * @return list<string>
	 * @throws InvalidSetting
	 */
	private static function providers(mixed $value, Messages $messages): array
	{
		if (! is_array($value) || ! array_is_list($value)) {
			throw self::invalid($messages->t('settings.setting.invalid.embeds', 'The embeds turned on must be a list of providers\' names.'));
		}

		foreach ($value as $name) {
			if (! is_string($name) || ! EmbedConfig::isName($name)) {
				throw self::invalid($messages->t('settings.setting.invalid.embed', '"{name}" isn\'t an embed provider\'s name, such as "codepen".', name: is_string($name) ? $name : get_debug_type($name)));
			}
		}

		$names = array_values(array_unique($value));
		sort($names);

		return $names;
	}

	/**
	 * Checks the upload rules as `MediaUploads` does, and returns them as
	 * it writes them (D-406).
	 *
	 * @return array<string, mixed>
	 * @throws InvalidSetting
	 */
	private static function uploads(mixed $value, Messages $messages): array
	{
		if (! is_array($value) || ($value !== [] && array_is_list($value))) {
			throw self::invalid($messages->t('settings.setting.invalid.uploads', 'The upload rules must be an object: enabled, maxSize, path, and kinds.'));
		}

		try {
			return MediaUploads::fromArray($value)->toArray();
		} catch (InvalidConfig $error) {
			throw new InvalidSetting($error->getMessage(), previous: $error);
		}
	}

	/**
	 * Checks the backup schedule (D-887), saved from Tools → Backups.
	 *
	 * @return array<string, mixed>
	 * @throws InvalidSetting
	 */
	private static function backupSchedule(mixed $value, Messages $messages): array
	{
		if (! is_array($value) || ($value !== [] && array_is_list($value))) {
			throw self::invalid($messages->t('settings.setting.invalid.backups', 'The backup schedule must be an object: frequency, day, hour, keep, parts, notice, and to.'));
		}

		try {
			return BackupSchedule::fromArray($value)->toArray();
		} catch (InvalidConfig $error) {
			throw new InvalidSetting($error->getMessage(), previous: $error);
		}
	}

	/**
	 * Checks the AI crawler groups to block, and returns them in their
	 * usual order.
	 *
	 * @return list<string>
	 * @throws InvalidSetting
	 */
	private static function blockAi(mixed $value, Messages $messages): array
	{
		if (! is_array($value) || ! array_is_list($value)) {
			throw self::invalid($messages->t('settings.setting.invalid.block_ai', 'The AI crawlers to block must be a list: training, search, or fetchers.'));
		}

		foreach ($value as $group) {
			if (! is_string($group) || AiCrawlerGroup::tryFrom($group) === null) {
				throw self::invalid($messages->t('settings.setting.invalid.crawler', '"{group}" isn\'t a kind of AI crawler; use training, search, or fetchers.', group: is_string($group) ? $group : get_debug_type($group)));
			}
		}

		return array_values(array_map(
			static fn (AiCrawlerGroup $group): string => $group->value,
			array_filter(AiCrawlerGroup::cases(), static fn (AiCrawlerGroup $group): bool => in_array($group->value, $value, true))
		));
	}

	/**
	 * @return list<string>
	 * @throws InvalidSetting
	 */
	private static function disallow(mixed $value, Messages $messages): array
	{
		if (! is_array($value) || ! array_is_list($value)) {
			throw self::invalid($messages->t('settings.setting.invalid.disallow', 'The paths robots.txt asks to skip must be a list.'));
		}

		$paths = [];

		foreach ($value as $path) {
			$path = is_string($path) ? trim($path) : '';

			if ($path === '') {
				continue;
			}

			if (! str_starts_with($path, '/') || preg_match('/[\s\p{Cc}]/u', $path) !== 0) {
				throw self::invalid($messages->t('settings.setting.invalid.path', '"{path}" isn\'t a path; a path starts with / and has no spaces, such as /drafts/.', path: $path));
			}

			$paths[$path] = true;
		}

		if (count($paths) > self::DISALLOW_MAX) {
			throw self::invalid($messages->t('settings.setting.invalid.disallow_many', 'robots.txt can be asked to skip at most {max} paths.', max: self::DISALLOW_MAX));
		}

		return array_keys($paths);
	}
}
