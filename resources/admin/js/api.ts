/**
 * Talks to the admin's JSON API (D-220). Requests send the session cookie
 * and, once signed in, the CSRF token in `X-CSRF-Token`. A failed request
 * throws an `ApiError` with the server's message.
 */

import { config } from './config';
import type { AccountProfile } from './people';

export interface Account {
	username: string;
	// Its email address (D-370); `null` only for one saved before emails.
	email: string | null;
	// Its own name, if it has one (D-322).
	name: string | null;
	// What the admin calls it: its own name, else its profile's title,
	// else its username (D-370).
	displayName: string;
	author: string | null;
	// That profile, when it has a file (for Your Account, D-369).
	profile: AccountProfile | null;
	created: number;
	// Each role's key and its saved label, for showing (D-323).
	roles: { name: string; label: string }[];
	capabilities: string[];
	lastLogin: number | null;
	preferences: Preferences;
}

export type ColorScheme = 'system' | 'light' | 'dark';

export type AdminTheme = 'neutral' | 'editorial';

export interface Preferences {
	colorScheme: ColorScheme;
	adminTheme: AdminTheme;
}

export interface SessionState {
	account: Account | null;
	csrfToken?: string | null;
}

export interface ActionDescription {
	name: string;
	label: string;
	description: string;
	confirm: string | null;
}

export interface ActionResult {
	successful: boolean;
	message: string;
	details: string[];
}

export interface Dashboard {
	site: { name: string; url: string; environment: string; version: string };
	content: { total: number; published: number; draft: number; scheduled: number };
	actions: ActionDescription[];
}

export interface EntrySummary {
	// Its id, which names it to the API (D-481), `null` while its file
	// has no valid one (it can't be opened until Content Health gives it
	// one), and its file's path under the content folder.
	id: string | null;
	path: string;
	// How the admin's addresses name it (`post/hello-world`), or `null`
	// when only its path does (D-253).
	handle: string | null;
	title: string;
	type: string;
	status: EntryStatus;
	// When it was moved to the trash (ISO 8601), for one that's there.
	trashed: string | null;
	published: string | null;
	updated: string;
	// Its path on the site, where it is or will be once published, if it
	// has one (D-254).
	url: string | null;
	authors: string[];
	own: boolean;
	// Whether it's its type's index page, pinned above the rest (D-255),
	// or a people field's list page, pinned below that (D-329, D-353).
	index: boolean;
	authorsPage: boolean;
	// The status it's the site's error page for, pinned at the top of
	// Pages (D-411), or `null`.
	errorPage: number | null;
	// Whether it's what the site shows at `/`, and whether it's the root
	// page (`index.md`), pinned on Pages (D-420). A root page the
	// homepage doesn't show says what it shows instead ("The latest
	// posts"); else `null`.
	homepage: boolean;
	rootPage: boolean;
	homeInstead: string | null;
	// That list page's field's name ("Cooks"), or `null`.
	peopleLabel?: string | null;
	// A profile's: whether an account is linked to it, and which, when
	// you manage accounts (D-353).
	linked?: boolean;
	account?: { username: string; displayName: string } | null;
	// Duplicate: not for landing pages, and needs `content.create` (D-275).
	// Make homepage: a root page that isn't, with `site.settings` (D-420).
	can: { delete: boolean; duplicate: boolean; makeHomepage: boolean };
	// For a term, how many published entries use it; else `null` (D-236).
	uses: number | null;
	// The titles of the entries above it, from the top down: a page's
	// folders' pages, or a hierarchical term's parents.
	ancestors: string[];
	// In a tree-ordered list: its depth (0 at the top) and how many
	// children it has; else `null` (D-262). `continued` marks an entry
	// heading a later page for the entries under it (D-263).
	depth: number | null;
	children: number | null;
	continued: boolean;
}

// Every status an entry has (D-484). `trash` is set by Move to Trash
// and taken off by Restore, and `scheduled` by a future date, so a status
// control offers neither as such.
export type EntryStatus = 'draft' | 'scheduled' | 'published' | 'trash';

// What "any status" means: every status but the trash.
export type ActiveStatus = Exclude<EntryStatus, 'trash'>;

export type EntrySort = 'title' | 'status' | 'author' | 'published' | 'updated';

export interface EntryList {
	status: EntryStatus | 'any';
	type: string | null;
	search: string;
	// The other filters (D-300): an author's slug (`''` for any),
	// `taxonomy:slug` pairs, and how many days back it was updated.
	author: string;
	terms: string[];
	days: number | null;
	// The column it's sorted by and which way, or `null` for the usual
	// order; and whether it's a tree.
	sort: EntrySort | null;
	dir: 'asc' | 'desc' | null;
	tree: boolean;
	total: number;
	page: number;
	pages: number;
	per: number;
	entries: EntrySummary[];
	// The type's index page, when the filters find it: not one of the
	// entries or the total, and on the first page only (D-255, D-264).
	index: EntrySummary | null;
	// The type's authors page, the same way (D-329).
	authorsPage: EntrySummary | null;
	// Pages' error pages, by status, the same way (D-411).
	errorPages?: EntrySummary[];
	// What the list is in order of (D-413): `position`, `published`,
	// `updated`, or the column sorted by.
	by?: string;
}

// What people call a type and its entries (D-278). `item` and `items`
// are the names mid-sentence, as the site set them or as the server made
// them, so the admin never lowercases a name itself.
export interface TypeLabels {
	singular: string;
	plural: string;
	// The navigation's name for it; the plural unless the site shortens it.
	menu: string;
	item: string;
	items: string;
	newItem: string;
	editItem: string;
	searchItems: string;
}

export interface ContentTypeSummary {
	name: string;
	labels: TypeLabels;
	// What it's for, in a sentence; `''` for none.
	description: string;
	// A site icon's name to show it with, or `null` for its kind's.
	icon: string | null;
	kind: 'collection' | 'taxonomy' | 'tree' | 'profiles';
	dated: boolean;
	// Whether its entries credit people (D-351).
	authors: boolean;
	// A taxonomy's: the types its terms group, empty for every type, and
	// whether a term may have a parent. The profiles type's: the types
	// that credit people.
	types?: string[];
	hierarchical?: boolean;
	// Where it was defined, its folder, its URL prefix (`null` without
	// URLs), and how many fields it defines (D-250).
	origin: 'built-in' | 'extension' | 'config' | 'data';
	folder: string;
	prefix: string | null;
	fields: number;
}

/**
 * A route key a type answers at (D-350): its path and default, relative
 * to the type's prefix (or the site's root, for the home type's feeds),
 * and the placeholders it needs and may hold.
 */
export interface TypeRoute {
	key: string;
	path: string;
	default: string;
	requires: string[];
	allows: string[];
	root: boolean;
}

/**
 * One content type (`GET types/{name}`, D-250).
 */
// One of a type's people fields (D-353).
export interface PeopleFieldInfo {
	field: string;
	plural: string;
	singular: string;
	aliases: string[];
	// The word its archives sit under, or `false` for none.
	archive: string | false;
	multiple: boolean;
	required: boolean;
	// The page introducing its list of people, or `null`.
	listPage: { id: string | null; type: string; path: string; title: string } | null;
}

export interface ContentTypeDetail extends Omit<ContentTypeSummary, 'fields'> {
	public: boolean;
	feed: boolean;
	sitemap: boolean;
	// Whether entries are listed in `llms.txt` (D-398); off by default
	// for taxonomies and profiles (D-401).
	llms: boolean;
	// Whether the admin changes it: a type in `user/data/types` (D-311),
	// or a collection or taxonomy from code, through a file there (D-349).
	editable: boolean;
	// Whether it's from code with a file in `user/data/types` changing
	// it, and the options that file sets.
	overridden: boolean;
	overrides: string[];
	// Whether its fields can be changed here (none is a field class from
	// code).
	fieldsEditable: boolean;
	// The route keys it answers at (D-350).
	routes: TypeRoute[];
	taxonomies: string[];
	fields: FieldDescription[];
	// `none`, `year`, `month`, `day`, `hour`, `minute`, or `second`.
	dateArchives: string;
	// Its own file name pattern (D-511, D-514), or `null` for the
	// default, the slug alone (D-515).
	filename: string | null;
	// The URL prefix its folder gives it, without slashes.
	folderPrefix: string;
	// The data file it's defined or changed in, from the site's root, or `null`.
	file: string | null;
	// Its index page (D-255), or `null`.
	index: { id: string | null; type: string; path: string; title: string } | null;
	// How its entries credit people (D-353), in order.
	people: PeopleFieldInfo[];
	// The word its author archives sit under, `false` for none, or `null`
	// for a type without URLs (D-329); and its authors page, or `null`.
	authorsWord: string | false | null;
	authorsPage: { id: string | null; type: string; path: string; title: string } | null;
	// The field sets attached to it (D-337), with how many fields each has.
	sets: { name: string; label: string; fields: number }[];
}

/**
 * The palette roles a theme's preview declares (D-381), in order.
 */
export const PALETTE_ROLES = ['background', 'surface', 'text', 'muted', 'accent', 'border'] as const;

export type PaletteRole = typeof PALETTE_ROLES[number];

/**
 * What the admin sketches a theme's preview from (`theme.json`'s
 * `preview`, D-381): a layout, a line about its type, and its palette,
 * each color a light and a dark hex value.
 */
export interface ThemePreview {
	layout: 'centered' | 'sidebar' | 'wide';
	type: string;
	palette: Record<PaletteRole, [string, string]> | null;
}

/**
 * Someone who made an extension (D-384), as `composer.json` lists them.
 */
export interface ExtensionAuthor {
	name: string;
	email?: string;
	homepage?: string;
	role?: string;
}

/**
 * A part of an extension's license (D-426): a license it names, with a
 * link to a common one's text, or the `or`, `and`, or `with` between
 * them.
 */
export interface LicensePart {
	text: string;
	url: string | null;
	operator: boolean;
}

/**
 * One of an extension's homepage and support links (D-428), in the
 * admin's order: `homepage`, then `support`'s `docs`, `source`, `issues`,
 * `forum`, `chat`, `wiki`, `irc`, `rss`, `security`, and `email` (as a
 * `mailto:` URL).
 */
export interface ExtensionLink {
	kind: string;
	url: string;
}

/**
 * Where to fund an extension (D-428), as `composer.json`'s `funding`
 * has it: `type` is `github`, `patreon`, `custom`, and so on, or empty.
 */
export interface ExtensionFunding {
	type: string;
	url: string;
}

/**
 * An installed theme (`GET themes`).
 */
export interface ThemeSummary {
	// The key it's known by: `vendor/name` (D-378).
	name: string;
	label: string;
	// What its components, icons, and catalog keys go by.
	namespace: string;
	version: string;
	description: string;
	parent: string | null;
	// `framework` (the default theme), `local` (`extensions/`), or `composer`.
	source: 'framework' | 'local' | 'composer';
	active: boolean;
	// Where it's installed, from the site's root; `null` for the default theme.
	folder: string | null;
	preview: ThemePreview | null;
	// Who made it: its manifest's `authors`, else its `composer.json`'s.
	authors: ExtensionAuthor[];
	// How it may be used, as written and as parts (D-427).
	license: string;
	licenses: LicensePart[];
	links: ExtensionLink[];
	funding: ExtensionFunding[];
	// Whether it's in the chain that runs.
	running: boolean;
	// Checked as if it were active (D-431).
	requirements: ExtensionRequirement[];
	// What it can't run with (D-435), each met when it doesn't conflict with what's on.
	conflicts: ExtensionRequirement[];
	// What it replaces (D-436), each met when that isn't on.
	replaces: ExtensionRequirement[];
	// What it provides (D-439), with the versions it provides (`self.version` resolved).
	provides: { name: string; constraint: string }[];
	// The other side (D-440): the extensions whose `conflict` hits it, that
	// replace it, and that provide it.
	conflictedBy: ExtensionDependent[];
	replacedBy: ExtensionDependent[];
	providedBy: ExtensionDependent[];
	// What turning it on (a theme: activating it) would stop, with what
	// stops because of that; empty for one that runs.
	stops: ExtensionDependent[];
	// Why it can't be activated (a theme it falls back to is missing, or a
	// requirement in its chain isn't met), or `null`.
	blocked: string | null;
	// The extensions, of every kind, that require it.
	requiredBy: ExtensionDependent[];
	// Whether it's abandoned: `true`, or the package to use instead (D-433).
	abandoned: boolean | string;
	// The package to use instead, when it's an installed extension.
	replacement: ExtensionDependent | null;
	// What it suggests (D-434), each checked against the site.
	suggests: ExtensionSuggestion[];
	// Whether it's a folder in `extensions/` the active theme doesn't use.
	deletable: boolean;
	// The version replacing it kept, which it can be rolled back to (D-393), or `null`.
	backup: { version: string } | null;
}

/**
 * The installed themes (`GET themes`).
 */
export interface Themes {
	// The active theme's name.
	active: string;
	// The active theme, its ancestors, then the default theme, by name;
	// empty when it can't be built, with the `problem`.
	chain: string[];
	problem: string | null;
	// Why the active chain doesn't run, so the default theme runs in its
	// place (D-431), or `null`.
	fallback: string | null;
	// Whether `config/theme.php` exists.
	config: boolean;
	// Whether the active theme is saved in `user/data/settings.json`.
	saved: boolean;
	// Whether `?theme={name}` previews another theme (development only).
	preview: boolean;
	themes: ThemeSummary[];
	// Broken themes, by where they were found (`extensions/{vendor}/{name}`, or
	// a Composer package's name).
	invalid: { where: string; reason: string; deletable: boolean }[];
	upload: ExtensionUpload;
}

/**
 * What a kind's Install modal shows before anything is chosen (D-392):
 * the largest archive taken, in bytes, and why nothing can be installed.
 */
export interface ExtensionUpload {
	limit: number;
	problem: string | null;
}

// An extension as installing describes it: one installed, or one in an archive.
export interface InstalledExtension {
	name: string;
	label: string;
	version: string;
	folder: string;
	// Whether it's abandoned: `true`, or the package to use instead (D-433).
	abandoned: boolean | string;
	// What it suggests, each with why (D-434).
	suggests: { name: string; reason: string }[];
}

// `POST {kind}/{vendor}/{name}/rollback` (D-393).
export interface RollbackAnswer {
	rolledBack: InstalledExtension;
	// The version it was.
	from: string | null;
	refresh: boolean;
}

// `POST themes`, `POST plugins`, `POST icon-packs` (D-392).
export interface InstallAnswer {
	installed: InstalledExtension;
	// The version it replaced, or `null` for a new one.
	replaced: string | null;
	backup: string | null;
	refresh: boolean;
}

// A `409` from installing: one with the archive's name is installed.
export interface InstallClash {
	installed: InstalledExtension;
	incoming: InstalledExtension;
}

/**
 * One of an extension's `require`, checked against the site the same for
 * every kind (D-385, D-431).
 */
export interface ExtensionRequirement {
	// `blush-dev/framework`, `php`, `ext-{name}`, or another extension's `vendor/name`.
	name: string;
	constraint: string;
	// `library` is a package Composer installed that isn't an extension;
	// `composer` is what only Composer checks (`lib-*`, `composer-runtime-api`;
	// D-438); `missing` is a `vendor/name` that isn't installed.
	kind: 'blush' | 'php' | 'extension' | 'plugin' | 'theme' | 'icon-pack' | 'library' | 'composer' | 'missing' | 'unknown';
	met: boolean;
	// What the site has: `this site runs 8.5.1`, `isn't installed`, `is turned off`, `isn't active`.
	note: string;
	// The required extension's label, when it's installed.
	label: string;
	// The extension that replaces or provides what it names (D-436, D-439),
	// meeting it or, for a conflict, conflicting; its kind is `kind`. Empty
	// otherwise.
	metBy: string;
}

/**
 * An extension that requires another (D-431).
 */
export interface ExtensionDependent {
	name: string;
	label: string;
	kind: 'plugin' | 'theme' | 'icon-pack';
}

// A package an extension suggests (D-434): only shown, never enforced.
export interface ExtensionSuggestion {
	// Another extension's `vendor/name`, a library's, or `ext-{name}`.
	name: string;
	// Why it's suggested (may be empty).
	reason: string;
	// The extension it names, when it's installed.
	extension: ExtensionDependent | null;
	// Whether a PHP extension (`ext-{name}`) is loaded; `null` for anything else.
	loaded: boolean | null;
}

/**
 * An installed plugin (`GET plugins`, D-308, D-378, D-385).
 */
export interface PluginSummary {
	// The key it's known by: `vendor/name`.
	name: string;
	label: string;
	// What its components, icons, and translations go by.
	namespace: string;
	version: string;
	description: string;
	authors: ExtensionAuthor[];
	license: string;
	// The license's parts, in order: each license it names, with a link
	// to a common one's text, and the `or`, `and`, or `with` between them.
	licenses: LicensePart[];
	links: ExtensionLink[];
	funding: ExtensionFunding[];
	source: 'local' | 'composer';
	// Where it's installed, from the site's root.
	path: string;
	// Its folder in `extensions/`, or `null` for a Composer plugin.
	folder: string | null;
	// Turned on, and whether it runs: an enabled one doesn't when its
	// requirements aren't met.
	enabled: boolean;
	running: boolean;
	// For one that's off, checked as if it were turned on.
	requirements: ExtensionRequirement[];
	// What it can't run with (D-435), each met when it doesn't conflict with what's on.
	conflicts: ExtensionRequirement[];
	// What it replaces (D-436), each met when that isn't on.
	replaces: ExtensionRequirement[];
	// What it provides (D-439), with the versions it provides (`self.version` resolved).
	provides: { name: string; constraint: string }[];
	// The other side (D-440): the extensions whose `conflict` hits it, that
	// replace it, and that provide it.
	conflictedBy: ExtensionDependent[];
	replacedBy: ExtensionDependent[];
	providedBy: ExtensionDependent[];
	// What turning it on (a theme: activating it) would stop, with what
	// stops because of that; empty for one that runs.
	stops: ExtensionDependent[];
	// Why it can't run, or `null`.
	blocked: string | null;
	// The extensions, of every kind, that require it.
	requiredBy: ExtensionDependent[];
	// Whether it's abandoned: `true`, or the package to use instead (D-433).
	abandoned: boolean | string;
	// The package to use instead, when it's an installed extension.
	replacement: ExtensionDependent | null;
	// What it suggests (D-434), each checked against the site.
	suggests: ExtensionSuggestion[];
	// A folder plugin that isn't running.
	deletable: boolean;
	// The version replacing it kept, which it can be rolled back to (D-393), or `null`.
	backup: { version: string } | null;
}

/**
 * What every kind of extension has (D-509), for the pieces their screens
 * share: who made it, its license and links, and its package relations.
 */
export type ExtensionSummary = Pick<PluginSummary, 'name' | 'label' | 'namespace' | 'version' | 'description' | 'authors' | 'licenses' | 'links' | 'funding' | 'requirements' | 'conflicts' | 'replaces' | 'provides' | 'conflictedBy' | 'replacedBy' | 'providedBy' | 'requiredBy' | 'suggests'>;

/**
 * A plugin whose manifest can't be read (D-394): listed, never run.
 */
export interface BrokenPluginSummary {
	// A Composer package's name, or its folder from the site's root.
	where: string;
	reason: string;
	// Its `vendor/name`, or `null` when the manifest doesn't say.
	name: string | null;
	// Whether config turns it on, though it can't run.
	enabled: boolean;
	// A folder in `extensions/` that config doesn't turn on by name.
	deletable: boolean;
}

/**
 * The installed plugins (`GET plugins`).
 */
export interface Plugins {
	plugins: PluginSummary[];
	invalid: BrokenPluginSummary[];
	// Whether the plugins turned on are saved in `user/data/settings.json`.
	saved: boolean;
	// Whether `config/plugins.php` exists.
	config: boolean;
	upload: ExtensionUpload;
}

/**
 * An icon, drawn from its SVG (as a mask, so nothing in the file runs).
 */
export interface PackIcon {
	// As it's referenced: `weather/sun`, or a core icon's short name.
	name: string;
	// Empty when the file is too large to send.
	svg: string;
}

/**
 * An installed icon pack (`GET icon-packs`, D-378, D-385).
 */
export interface IconPackSummary {
	// The key it's known by: `vendor/name`.
	name: string;
	label: string;
	// What its icons go by: `{namespace}/{icon}`.
	namespace: string;
	version: string;
	description: string;
	authors: ExtensionAuthor[];
	// How it may be used, as written and as parts (D-427).
	license: string;
	licenses: LicensePart[];
	links: ExtensionLink[];
	funding: ExtensionFunding[];
	source: 'local' | 'composer';
	// Where it's installed, from the site's root.
	path: string;
	// Its folder in `extensions/`, or `null` for a Composer pack.
	folder: string | null;
	// Turned on, and whether its icons load: one that's on doesn't when
	// its requirements aren't met (D-431).
	enabled: boolean;
	running: boolean;
	// For one that's off, checked as if it were turned on.
	requirements: ExtensionRequirement[];
	// What it can't run with (D-435), each met when it doesn't conflict with what's on.
	conflicts: ExtensionRequirement[];
	// What it replaces (D-436), each met when that isn't on.
	replaces: ExtensionRequirement[];
	// What it provides (D-439), with the versions it provides (`self.version` resolved).
	provides: { name: string; constraint: string }[];
	// The other side (D-440): the extensions whose `conflict` hits it, that
	// replace it, and that provide it.
	conflictedBy: ExtensionDependent[];
	replacedBy: ExtensionDependent[];
	providedBy: ExtensionDependent[];
	// What turning it on (a theme: activating it) would stop, with what
	// stops because of that; empty for one that runs.
	stops: ExtensionDependent[];
	// Why it can't load, or `null`.
	blocked: string | null;
	// The extensions, of every kind, that require it.
	requiredBy: ExtensionDependent[];
	// Whether it's abandoned: `true`, or the package to use instead (D-433).
	abandoned: boolean | string;
	// The package to use instead, when it's an installed extension.
	replacement: ExtensionDependent | null;
	// What it suggests (D-434), each checked against the site.
	suggests: ExtensionSuggestion[];
	deletable: boolean;
	count: number;
	// The first twelve on the list; every one from `GET icon-packs/{name}`.
	icons: PackIcon[];
	// The version replacing it kept, which it can be rolled back to (D-393), or `null`.
	backup: { version: string } | null;
}

/**
 * Blush's own icons, always on (`GET icon-packs`, `GET icon-packs/core`).
 */
export interface CoreIcons {
	label: string;
	version: string;
	count: number;
	icons: PackIcon[];
}

/**
 * The installed icon packs (`GET icon-packs`), the core set, and the
 * broken ones by where they were found.
 */
export interface IconPacks {
	packs: IconPackSummary[];
	core: CoreIcons;
	invalid: { where: string; reason: string; deletable: boolean }[];
	// Whether the packs turned on are saved in `user/data/settings.json`.
	saved: boolean;
	// Whether `config/icons.php` exists.
	config: boolean;
	upload: ExtensionUpload;
}

/**
 * One site-wide setting (`GET settings/{screen}`, D-309, D-325). A
 * `bool`'s value is `true` or `false` and a `list`'s a list; the rest are
 * text. `file` is where it's set by convention (`null` when it follows
 * from others). One the admin can change (D-324) adds the `setting` it
 * saves as (`feed.limit`), its control, the value the form starts from,
 * and whether it's saved in `user/data/settings.json`; its `file` is
 * where the value comes from when it isn't.
 */
export interface SettingItem {
	key: string;
	label: string;
	value: string | boolean | string[];
	// `uploads` is Media's upload rules, drawn as a grid (D-406).
	kind: 'text' | 'mono' | 'bool' | 'list' | 'uploads';
	// Whether it's still the default; `null` when it follows from others.
	default: boolean | null;
	help: string | null;
	// Why it's risky where it is.
	warning: string | null;
	file: string | null;
	// What a setting the admin changes saves as: a `Setting`'s value
	// (`feed.limit`), or `site.{name}` for one a field set adds (D-343).
	setting?: string;
	// The field it's edited as, with words for its options and a caption.
	field?: FieldDescription;
	// The value the form starts from.
	input?: unknown;
	saved?: boolean;
	// A page on the site a shown setting links to (D-398), such as
	// `llms.txt`, or admin screens it's set on.
	link?: { label: string; href: string } | null;
	links?: { label: string; to: string }[];
	// A setting this one needs on (D-402): while that's off in the form,
	// this one is locked, with the note saying why.
	requires?: { setting: string; note: string };
	// What the upload rules' grid needs (D-406).
	uploads?: UploadsInfo;
	// The language menu (D-441): each locale, named in its own language
	// with its English name as the `hint` (D-442), regions under their
	// language.
	locales?: { value: string; label: string; hint: string | null; depth: number }[];
	// A searchable menu drawn from these options in place of the field's
	// own (the time zones, D-444).
	menu?: { value: string; label: string; hint: string | null; group: string | null; search: string }[];
	// The date or time format's menu (D-445): each format as it reads
	// now, with its name or pattern as the `hint`.
	formats?: { value: string; label: string; hint: string; group: string }[];
}

/**
 * What the Media screen's upload grid needs (D-406): each kind (the
 * `folder` its `{kind}` becomes, an `example` file, and the `extensions`
 * the site allows for it), the path tokens, the site's date for the
 * examples, the most the server accepts in bytes, and how many files the
 * library has.
 */
export interface UploadsInfo {
	kinds: { key: string; label: string; folder: string; example: string; extensions: string[] }[];
	tokens: string[];
	now: { year: string; month: string; day: string };
	serverLimit: number | null;
	files: number | null;
}

/**
 * A panel of settings, with a note (backticks mark code).
 */
export interface SettingGroup {
	key: string;
	title: string;
	hint: string;
	note: string | null;
	items: SettingItem[];
}

/**
 * A schema field as the server describes it (`Field::toArray()`); the
 * type's own settings (`options`, `item`, `to`, …) sit beside the shared
 * ones.
 */
export interface FieldDescription {
	name: string;
	type: string;
	aliases?: string[];
	required?: boolean;
	default?: unknown;
	label?: string;
	description?: string;
	options?: string[];
	item?: FieldDescription;
	to?: string;
	multiple?: boolean;
	// A media field's kind of file (D-314).
	kind?: 'image' | 'video' | 'audio' | 'document' | 'file';
	integer?: boolean;
	min?: number;
	max?: number;
	// What a checkbox beside it says, or what an empty choice means.
	caption?: string;
	// More about each option (D-404): a sentence, and the machine names
	// it covers.
	details?: Record<string, { text: string; code: string }>;
	// The control it's edited with (D-337): in a form's fields, always the
	// one to draw; in a type's definitions, only one chosen over the
	// type's default.
	control?: string;
	[setting: string]: unknown;
}

/**
 * A place a field set can attach to (`type:post`), by its name for
 * people.
 */
export interface FieldSetTargetOption {
	key: string;
	label: string;
	// The kind of place, in the list of every place ("Content types",
	// "Media files", D-341), and its key (`type`).
	group?: string;
	kind?: string;
}

/**
 * A kind of place sets attach to, with the slots it offers (D-347), the
 * first being its default.
 */
export interface FieldKindDescription {
	kind: string;
	label: string;
	slots: { name: string; label: string; description: string }[];
}

/**
 * A field set (`GET fields/sets`, D-337): where it's from, whether it's
 * edited here, its file, the places it's added to (`found` false for one
 * that doesn't exist), and how many fields it has.
 */
export interface FieldSetSummary {
	name: string;
	label: string;
	description: string;
	// The kind of place its targets are (`type`), and the slot it's in.
	kind: string | null;
	slot: string | null;
	origin: 'extension' | 'config' | 'data';
	editable: boolean;
	file: string | null;
	targets: (FieldSetTargetOption & { found: boolean })[];
	fields: number;
}

/**
 * One field set (`GET fields/sets/{name}`): its fields as definitions,
 * and every place it could be added to.
 */
export interface FieldSetDetail extends Omit<FieldSetSummary, 'fields'> {
	fields: FieldDescription[];
	options: FieldSetTargetOption[];
	kinds: FieldKindDescription[];
}

/**
 * The field sets (`GET fields/sets`): whether sets can be created here,
 * and every place one could be added to.
 */
export interface FieldSetList {
	sets: FieldSetSummary[];
	create: boolean;
	targets: FieldSetTargetOption[];
	kinds: FieldKindDescription[];
}

/**
 * A field set attached to an entry's type (D-337): its name, its label
 * and help, and its fields' names.
 */
export interface FieldSetGroup {
	name: string;
	label: string;
	description: string;
	// The slot it's in (D-347), such as `details` or `content`; the screen
	// decides where a slot shows. Media and Settings screens leave it out.
	slot?: string;
	fields: string[];
}

/**
 * A control the admin draws, with its name for people.
 */
export interface ControlDescription {
	value: string;
	label: string;
}

/**
 * A field type (`GET fields/types`, D-337): its key, its name for
 * people, what it holds, the controls it can be edited with (the first
 * is its default), and its own definition keys as JSON Schemas.
 */
export interface FieldTypeDescription {
	type: string;
	label: string;
	description: string;
	controls: ControlDescription[];
	options: Record<string, JsonSchema>;
}

/**
 * The part of JSON Schema field types describe their options with.
 */
export interface JsonSchema {
	type?: string | string[];
	enum?: unknown[];
	items?: JsonSchema;
	description?: string;
	default?: unknown;
	minimum?: number;
	maximum?: number;
	[keyword: string]: unknown;
}

/**
 * The field types and every control (`GET fields/types`).
 */
export interface FieldTypeCatalog {
	types: FieldTypeDescription[];
	controls: ControlDescription[];
}

/**
 * An entry for editing (`GET entries/{id}`, D-229).
 */
export interface EntryDetail {
	// Its id, which names it to the API (D-481), and its file's path.
	id: string;
	path: string;
	handle: string | null;
	// The last part of its key; renaming changes it (D-277).
	slug: string;
	key: string;
	// A tree's page: its parent's key, `''` at the top (D-410); else `null`.
	parent: string | null;
	revision: string;
	// When the file was last written (ISO 8601), if known.
	modified: string | null;
	title: string;
	status: EntryStatus;
	// When it was moved to the trash (ISO 8601); `null` unless it's there.
	// One that's there is looked at, not edited (`can.edit` is false).
	trashed: string | null;
	own: boolean;
	url: string | null;
	// Whether it's its type's index page (D-274): edited without the
	// type's fields or scheduling, and never trashed; or its authors page
	// (D-329), edited the same way, with its slug fixed.
	index: boolean;
	authorsPage: boolean;
	// The people page it is (D-353): a field's list page, or the page
	// written for one person's archive under it (`profile` set).
	peoplePage: { field: string; label: string; profile: string | null; profileTitle: string | null } | null;
	// The status it's the site's error page for (D-411), or `null`.
	errorPage: number | null;
	// Its part in the homepage, as `EntrySummary` says (D-420).
	homepage: boolean;
	rootPage: boolean;
	homeInstead: string | null;
	type: {
		name: string;
		kind: ContentTypeSummary['kind'];
		dated: boolean;
		fields: FieldDescription[];
		// The field sets attached to the type (D-337), with the names of
		// their fields, which the editor groups under each set's label.
		sets: FieldSetGroup[];
	};
	values: Record<string, unknown>;
	extra: Record<string, unknown>;
	body: string;
	// `rename`: not for a landing page, whose slug is its folder's.
	// `move`: a tree's page, under another (D-410).
	// `makeHomepage`: a root page that isn't, with `site.settings` (D-420).
	can: { edit: boolean; publish: boolean; delete: boolean; rename: boolean; move: boolean; duplicate: boolean; makeHomepage: boolean };
	violations: Violation[];
}

// A new entry, described but not yet written (D-336): no file, so no
// id, path, or revision.
export type NewEntryDetail = Omit<EntryDetail, 'id' | 'path' | 'revision'> & { id: null; path: null; revision: null };

export interface Violation {
	field: string;
	message: string;
	severity: 'error' | 'warning' | 'notice';
}

/**
 * A month of dated entries (`GET calendar`, D-368): each on its `day`
 * at its `time`, both in the site's timezone. `today` is the site's date.
 */
export interface CalendarEntry {
	id: string | null;
	path: string;
	handle: string | null;
	title: string;
	type: string;
	status: ActiveStatus;
	published: string;
	day: number;
	time: string;
}

export interface CalendarMonth {
	month: string;
	today: string;
	status: ActiveStatus | 'any';
	type: string | null;
	total: number;
	entries: CalendarEntry[];
}

export interface Health {
	checked: number;
	metadata: number;
	strict: boolean;
	counts: { error: number; warning: number; notice: number | null };
	files: { path: string; violations: Violation[] }[];
	// Files missing a valid id, and ids files share (D-477), for fixing
	// here (`POST health/ids`, `POST health/ids/keep`, D-478).
	ids: HealthIds;
	// The same for media files (D-487), by path in the media folder
	// (`POST health/media-ids`, `POST health/media-ids/keep`).
	mediaIds: HealthIds;
	// Sizes that images' details don't list as they are (D-488), and the
	// images they're of, and how many images list files that aren't
	// their sizes (`POST health/media-sizes`).
	mediaSizes: { sizes: number; images: number; stale: number };
	// By type, the entries named by another pattern than its `filename`
	// (D-511): how many, the first few renames, and how many it leaves
	// as they are, kept as folders (`POST health/filenames` with the type
	// renames them, D-512, D-514).
	fileNames: { type: string; label: string; pattern: string; count: number; examples: { path: string; to: string }[]; skipped: number }[];
	// Collections' files that aren't flat (D-514): how many, and the
	// first few moves (`POST health/flatten` moves them).
	flat: { count: number; examples: { path: string; to: string }[] };
}

/**
 * Files missing a valid id, and the ids files share.
 */
export interface HealthIds {
	missing: string[];
	duplicates: { id: string; paths: string[] }[];
}

/**
 * What fixing ids did: the new id of each file changed, by path, and
 * why each file that couldn't be was left.
 */
export interface AssignedIds {
	assigned: Record<string, string>;
	failed: Record<string, string>;
}

/**
 * A media file an entry can use (`GET media`, D-246).
 */
export interface MediaItem {
	// What to write: the library's URL path.
	reference: string;
	name: string;
	folder: string;
	url: string;
	mime: string;
	kind: 'image' | 'video' | 'audio' | string;
	size: number;
	width: number | null;
	height: number | null;
	// How long a sound or video lasts, in seconds, when known (D-291).
	duration: number | null;
	modified: string;
	// What the library calls it (D-290), and its alt text and caption for
	// it (D-269), `''` for none.
	title: string;
	alt: string;
	caption: string;
	// The uploader's username (D-407), `''` for none.
	owner: string;
	// The file's id (D-487), `''` for none.
	id: string;
	// How many sizes an image has (D-488); they aren't listed as items.
	sizeCount: number;
}

/**
 * One of an image's sizes (D-488).
 */
export interface MediaSize {
	path: string;
	reference: string;
	name: string;
	url: string;
	width: number | null;
	height: number | null;
	size: number;
}

/**
 * One library file with its metadata fields (`GET media/{path}`,
 * D-287): the fields its kind has, their values, the keys its metadata
 * file keeps that aren't fields, and what doesn't fit.
 */
export interface MediaDetail extends MediaItem {
	kind: 'image' | 'video' | 'audio' | 'document' | 'file';
	fields: FieldDescription[];
	// The field sets attached to its kind (D-341), grouped on its screen.
	sets: FieldSetGroup[];
	values: Record<string, unknown>;
	extra: Record<string, unknown>;
	violations: { field: string; message: string; severity: 'error' | 'warning' | 'notice' }[];
	// What the file says about itself (D-289): values read from its EXIF,
	// IPTC, and XMP, and whether it has a location (never the location).
	embedded: { values: Record<string, string | number | string[]>; location: boolean };
	// Who uploaded it (D-407), or `null` when no one's recorded.
	uploader: { username: string; name: string } | null;
	// What the account may do to it.
	may: { edit: boolean; delete: boolean };
	// The entries that use it, or its sizes: their document's path,
	// title, and type.
	usedIn: { id: string | null; path: string; title: string; type: string; typeLabel: string }[];
	// An image's sizes, smallest first (D-488).
	sizes: MediaSize[];
	// For a size, the image it's a size of, whose details it goes by.
	original: { path: string; reference: string; name: string; title: string } | null;
}

export interface MediaList {
	search: string;
	kind: string;
	// Only the account's own uploads (D-407).
	mine: boolean;
	total: number;
	page: number;
	pages: number;
	per: number;
	files: MediaItem[];
	// When the account may upload: the largest file the server takes, in
	// bytes (`null` for no limit), and the extensions the library takes.
	upload: { limit: number | null; extensions: string[] } | null;
}

export interface PreviewLink {
	url: string;
	expires: string;
}

export class ApiError extends Error {
	// The input the server blamed, when it named one, and the whole answer.
	constructor(message: string, public readonly status: number, public readonly field: string | null = null, public readonly data: unknown = null) {
		super(message);
	}
}

/**
 * What to say about a failure: the server's message, else the fallback
 * (a failure that isn't the API's, such as a script error, says nothing
 * worth showing).
 */
export function errorMessage(caught: unknown, fallback: string): string {
	return caught instanceof ApiError ? caught.message : fallback;
}

/**
 * The editor's route for an entry: its type and id
 * (`/content/post/0199b6e2-…`, D-483), which stay the same through a
 * rename or a move. An entry whose file has no id can't be edited until
 * it has one, so it goes to Content Health, where that's fixed.
 */
export function entryRoute(entry: { id: string | null; type: string }): { name: string; params: Record<string, string | string[]> } {
	return entry.id === null ? { name: 'health', params: {} } : { name: 'entry', params: { type: entry.type, id: entry.id } };
}

/**
 * The API path of an entry, from its id (D-481).
 */
export function entryPath(id: string): string {
	return `/entries/${encodeURIComponent(id)}`;
}

let csrfToken: string | null = null;

/**
 * Sets the token later requests send.
 */
export function setCsrfToken(token: string | null): void {
	csrfToken = token;
}

/**
 * Sends a request and returns the decoded answer (`undefined` for a 204).
 */
export async function request<T>(method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE', path: string, body?: unknown): Promise<T> {
	const headers: Record<string, string> = { Accept: 'application/json' };

	if (body !== undefined) {
		headers['Content-Type'] = 'application/json';
	}

	if (csrfToken !== null && method !== 'GET') {
		headers['X-CSRF-Token'] = csrfToken;
	}

	return answer<T>(config.api + path, {
		method,
		headers,
		credentials: 'same-origin',
		body: body === undefined ? undefined : JSON.stringify(body)
	});
}

/**
 * Asks the server to compile and reindex, when a save's answer says it
 * changed what has addresses; a failure there leaves the save standing.
 */
export async function refreshIfAsked(answer: { refresh: boolean }): Promise<void> {
	if (answer.refresh) {
		await request('POST', '/settings/refresh').catch(() => undefined);
	}
}

/**
 * Saves settings in `user/data/settings.json` (`set` some, `unset` others,
 * back to `config/`), then refreshes if the server asks.
 */
export async function saveSettings(changes: { set?: Record<string, unknown>; unset?: string[] }): Promise<void> {
	await refreshIfAsked(await request<{ refresh: boolean }>('PATCH', '/settings', changes));
}

/**
 * Changes an entry as it is now: a change names the revision it was made
 * to, so it's read first (D-481).
 */
export async function patchEntry(id: string, changes: Record<string, unknown>): Promise<EntryDetail> {
	const loaded = await request<EntryDetail>('GET', entryPath(id));

	return request<EntryDetail>('PATCH', entryPath(id), { ...changes, revision: loaded.revision });
}

/**
 * Moves an entry to the trash (D-484), at the revision given, or as it is
 * now. Resolves its Undo (D-525), which puts it back with the status it
 * had, unless it's changed in the trash since.
 */
export async function trashEntry(id: string, revision?: string): Promise<() => Promise<void>> {
	const at       = revision ?? (await request<EntryDetail>('GET', entryPath(id))).revision;
	const answered = await request<{ trashed: string; restore: { status: string | null; revision: string } }>('DELETE', `${entryPath(id)}?revision=${encodeURIComponent(at)}`);

	return async () => {
		await request<{ id: string }>('POST', `${entryPath(id)}/restore`, answered.restore);
	};
}

/**
 * Uploads a file as the multipart field `file` and returns the decoded
 * answer.
 */
export async function upload<T>(path: string, file: File): Promise<T> {
	const form = new FormData();

	form.append('file', file);

	return answer<T>(config.api + path, {
		method: 'POST',
		headers: csrfToken === null ? { Accept: 'application/json' } : { Accept: 'application/json', 'X-CSRF-Token': csrfToken },
		credentials: 'same-origin',
		body: form
	});
}

/**
 * Sends a request and decodes its answer, throwing an `ApiError` for a
 * failure.
 */
async function answer<T>(url: string, init: RequestInit): Promise<T> {
	let response: Response;

	try {
		response = await fetch(url, init);
	} catch {
		throw new ApiError('The site couldn\'t be reached. Check your connection and try again.', 0);
	}

	if (response.status === 204) {
		return undefined as T;
	}

	const data: unknown = await response.json().catch(() => null);

	if (!response.ok) {
		const message = typeof data === 'object' && data !== null && 'error' in data && typeof data.error === 'string'
			? data.error
			: (response.status === 413 ? 'That\'s larger than the server takes.' : `The request failed (${response.status}).`);

		const field = typeof data === 'object' && data !== null && 'field' in data && typeof data.field === 'string' ? data.field : null;

		throw new ApiError(message, response.status, field, data);
	}

	return data as T;
}

/**
 * Uploads a file as the multipart field `file`, with other fields,
 * saying how much has been sent (from 0 to 1) as it goes, and returns the
 * decoded answer. A failure is an `ApiError` carrying the answer.
 */
export function uploadWithProgress<T>(path: string, file: File, fields: Record<string, string>, progress: (sent: number) => void): Promise<T> {
	const form = new FormData();

	form.append('file', file);

	for (const [key, value] of Object.entries(fields)) {
		form.append(key, value);
	}

	return new Promise((resolve, reject) => {
		const request = new XMLHttpRequest();

		request.open('POST', config.api + path);
		request.withCredentials = true;
		request.setRequestHeader('Accept', 'application/json');

		if (csrfToken !== null) {
			request.setRequestHeader('X-CSRF-Token', csrfToken);
		}

		request.upload.onprogress = (event) => {
			if (event.lengthComputable && event.total > 0) {
				progress(event.loaded / event.total);
			}
		};

		request.onerror = () => reject(new ApiError('The site couldn\'t be reached. Check your connection and try again.', 0));

		request.onload = () => {
			let data: unknown = null;

			try {
				data = JSON.parse(request.responseText);
			} catch {
				data = null;
			}

			if (request.status >= 200 && request.status < 300) {
				resolve(data as T);

				return;
			}

			const message = typeof data === 'object' && data !== null && 'error' in data && typeof data.error === 'string'
				? data.error
				: (request.status === 413 ? 'That\'s larger than the server takes.' : `The request failed (${request.status}).`);

			reject(new ApiError(message, request.status, null, data));
		};

		request.send(form);
	});
}
