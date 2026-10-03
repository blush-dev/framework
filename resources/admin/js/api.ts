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
	id: string;
	// How the admin's addresses name it (`post/hello-world`), or `null`
	// when only its path does (D-253).
	handle: string | null;
	title: string;
	type: string;
	status: EntryStatus;
	published: string | null;
	updated: string;
	path: string | null;
	// Its path on the site, where it is or will be once published, if it
	// has one (D-254).
	url: string | null;
	authors: string[];
	own: boolean;
	// Whether it's its type's index page, pinned above the rest (D-255),
	// or a people field's list page, pinned below that (D-329, D-353).
	index: boolean;
	authorsPage: boolean;
	// That list page's field's name ("Cooks"), or `null`.
	peopleLabel?: string | null;
	// A profile's: whether an account is linked to it, and which, when
	// you manage accounts (D-353).
	linked?: boolean;
	account?: { username: string; displayName: string } | null;
	// Duplicate: not for landing pages, and needs `content.create` (D-275).
	can: { delete: boolean; duplicate: boolean };
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

export type EntryStatus = 'draft' | 'scheduled' | 'published';

/**
 * An entry in the trash (`GET trash`, D-237).
 */
export interface TrashedSummary {
	id: string;
	entry: string;
	title: string;
	type: string | null;
	bundle: boolean;
	trashed: string;
	authors: string[];
	own: boolean;
}

/**
 * A trashed entry with what's in it (`GET trash/{id}`, D-276).
 */
export interface TrashedDetail extends TrashedSummary {
	frontMatter: Record<string, unknown>;
	body: string;
}

export type EntrySort = 'title' | 'status' | 'author' | 'updated';

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
	listPage: { id: string; title: string } | null;
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
	// The URL prefix its folder gives it, without slashes.
	folderPrefix: string;
	// The data file it's defined or changed in, from the site's root, or `null`.
	file: string | null;
	// Its index page (D-255), or `null`.
	index: { id: string; title: string } | null;
	// How its entries credit people (D-353), in order.
	people: PeopleFieldInfo[];
	// The word its author archives sit under, `false` for none, or `null`
	// for a type without URLs (D-329); and its authors page, or `null`.
	authorsWord: string | false | null;
	authorsPage: { id: string; title: string } | null;
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
 * An installed theme (`GET appearance`).
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
	// `framework` (the default theme), `local` (`user/themes`), or `composer`.
	source: 'framework' | 'local' | 'composer';
	active: boolean;
	// Where it's installed, from the site's root; `null` for the default theme.
	folder: string | null;
	preview: ThemePreview | null;
	// Who made it: its manifest's `authors`, else its `composer.json`'s.
	authors: ExtensionAuthor[];
	// Why it can't be activated (a theme it falls back to is missing), or `null`.
	blocked: string | null;
	// Whether it's a folder in `user/themes` the active theme doesn't use.
	deletable: boolean;
	// The version replacing it kept, which it can be rolled back to (D-393), or `null`.
	backup: { version: string } | null;
}

/**
 * The installed themes (`GET appearance`).
 */
export interface Appearance {
	// The active theme's name.
	active: string;
	// The active theme, its ancestors, then the default theme, by name;
	// empty when it can't be built, with the `problem`.
	chain: string[];
	problem: string | null;
	// Whether `config/theme.php` exists.
	config: boolean;
	// Whether the active theme is saved in `user/data/settings.json`.
	saved: boolean;
	// Whether `?theme={name}` previews another theme (development only).
	preview: boolean;
	themes: ThemeSummary[];
	// Broken themes, by where they were found (`user/themes/{folder}`, or
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
 * One of a plugin's `requires`, checked against the site (D-385).
 */
export interface PluginRequirement {
	// `blush`, `php`, `ext-{name}`, or another plugin's `vendor/name`.
	name: string;
	constraint: string;
	kind: 'blush' | 'php' | 'extension' | 'plugin' | 'unknown';
	met: boolean;
	// What the site has: `this site runs 8.5.1`, `isn't installed`, `is turned off`.
	note: string;
	// The required plugin's label, when it's installed.
	label: string;
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
	source: 'local' | 'composer';
	// Where it's installed, from the site's root.
	path: string;
	// Its folder in `user/plugins`, or `null` for a Composer plugin.
	folder: string | null;
	// Turned on, and whether it runs: an enabled one doesn't when its
	// requirements aren't met.
	enabled: boolean;
	running: boolean;
	// For one that's off, checked as if it were turned on.
	requirements: PluginRequirement[];
	// Why it can't run, or `null`.
	blocked: string | null;
	// The plugins that require it, by name.
	requiredBy: string[];
	// A folder plugin that isn't running.
	deletable: boolean;
	// The version replacing it kept, which it can be rolled back to (D-393), or `null`.
	backup: { version: string } | null;
}

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
	// A folder in `user/plugins` that config doesn't turn on by name.
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
	source: 'local' | 'composer';
	// Where it's installed, from the site's root.
	path: string;
	// Its folder in `user/icons`, or `null` for a Composer pack.
	folder: string | null;
	enabled: boolean;
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
	id: string;
	handle: string | null;
	// The last part of its key; renaming changes it (D-277).
	slug: string;
	revision: string;
	// When the file was last written (ISO 8601), if known.
	modified: string | null;
	title: string;
	status: EntryStatus;
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
	can: { edit: boolean; publish: boolean; delete: boolean; rename: boolean; duplicate: boolean };
	violations: Violation[];
}

// A new entry, described but not yet written (D-336): no file, so no id
// or revision.
export type NewEntryDetail = Omit<EntryDetail, 'id' | 'revision'> & { id: null; revision: null };

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
	id: string;
	handle: string | null;
	title: string;
	type: string;
	status: EntryStatus;
	published: string;
	day: number;
	time: string;
}

export interface CalendarMonth {
	month: string;
	today: string;
	status: EntryStatus | 'any';
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
}

export interface MediaList {
	search: string;
	kind: string;
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
 * The editor's route for an entry: by its handle (`/content/post/hello`),
 * or by its path when it has none (D-253).
 */
export function entryRoute(entry: { id: string; handle: string | null }): { name: string; params: Record<string, string | string[]> } {
	if (entry.handle !== null) {
		const [type = '', ...key] = entry.handle.split('/');

		return { name: 'entry', params: { type, key } };
	}

	return { name: 'entry-file', params: { id: entry.id.split('/') } };
}

/**
 * The API path of an entry, from its id (its source path).
 */
export function entryPath(id: string): string {
	return `/entries/${id.split('/').map(encodeURIComponent).join('/')}`;
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
