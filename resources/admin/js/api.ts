/**
 * Talks to the admin's JSON API (D-220). Requests send the session cookie
 * and, once signed in, the CSRF token in `X-CSRF-Token`. A failed request
 * throws an `ApiError` with the server's message.
 */

import { config } from './config';

export interface Account {
	username: string;
	// Its own name, if it has one (D-322).
	name: string | null;
	// What the admin calls it: the name, else the author page's title,
	// else the username.
	displayName: string;
	author: string | null;
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
	// or its authors page, pinned below that (D-329).
	index: boolean;
	authorsPage: boolean;
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
	kind: 'collection' | 'taxonomy' | 'pages' | 'authors';
	dated: boolean;
	// Whether its entries credit authors (D-329).
	authors: boolean;
	// A taxonomy's: the types its terms group, empty for every type, and
	// whether a term may have a parent. The authors type's: the types
	// that credit authors.
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
 * One content type (`GET types/{name}`, D-250).
 */
export interface ContentTypeDetail extends Omit<ContentTypeSummary, 'fields'> {
	public: boolean;
	feed: boolean;
	sitemap: boolean;
	// Whether it's defined in `user/data/types`, so the admin changes it (D-311).
	editable: boolean;
	taxonomies: string[];
	fields: FieldDescription[];
	// `none`, `year`, `month`, `day`, `hour`, `minute`, or `second`.
	dateArchives: string;
	// The URL prefix its folder gives it, without slashes.
	folderPrefix: string;
	// The data file it's defined in, from the site's root, or `null`.
	file: string | null;
	// Its index page (D-255), or `null`.
	index: { id: string; title: string } | null;
	// The word its author archives sit under, `false` for none, or `null`
	// for a type without URLs (D-329); and its authors page, or `null`.
	authorsWord: string | false | null;
	authorsPage: { id: string; title: string } | null;
}

/**
 * An installed theme (`GET appearance`).
 */
export interface ThemeSummary {
	slug: string;
	name: string;
	version: string;
	description: string;
	parent: string | null;
	// `framework` (the default theme), `local` (`user/themes`), or `composer`.
	source: 'framework' | 'local' | 'composer';
	active: boolean;
}

/**
 * The installed themes (`GET appearance`).
 */
export interface Appearance {
	active: string;
	// The active theme, its ancestors, then the default theme.
	chain: string[];
	// Whether `config/theme.php` exists.
	config: boolean;
	// Whether `?theme={slug}` previews another theme (development only).
	preview: boolean;
	themes: ThemeSummary[];
	invalid: { slug: string; reason: string }[];
}

/**
 * An installed extension and what it adds (`GET extensions`, D-308).
 */
export interface ExtensionSummary {
	name: string;
	version: string;
	description: string;
	source: 'local' | 'composer';
	// Where it's installed, from the site's root.
	path: string;
	// Requirement (`php`, `blush`, `ext-…`, or an extension) to constraint.
	requires: Record<string, string>;
	enabled: boolean;
	adds: {
		// `overridden` when the site redefines the type in `config/content.php`.
		types: { name: string; label: string; overridden: boolean }[];
		components: string[];
		icons: string[];
		actions: string[];
		commands: string[];
	};
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
	kind: 'text' | 'mono' | 'bool' | 'list';
	// Whether it's still the default; `null` when it follows from others.
	default: boolean | null;
	help: string | null;
	// Why it's risky where it is.
	warning: string | null;
	file: string | null;
	setting?: string;
	control?: 'text' | 'mono' | 'select' | 'checkbox' | 'checks' | 'number' | 'lines';
	input?: SettingValue;
	options?: { value: string; label: string }[] | null;
	saved?: boolean;
}

/**
 * A setting's value as the form holds it: a home page of `''` is the page
 * at `user/content/index.md`, and lines are a list.
 */
export type SettingValue = string | number | boolean | string[];

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
	kind?: 'image' | 'video' | 'audio' | 'file';
	integer?: boolean;
	min?: number;
	max?: number;
	[setting: string]: unknown;
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
	type: {
		name: string;
		kind: ContentTypeSummary['kind'];
		dated: boolean;
		fields: FieldDescription[];
	};
	values: Record<string, unknown>;
	extra: Record<string, unknown>;
	body: string;
	// `rename`: not for a landing page, whose slug is its folder's.
	can: { edit: boolean; publish: boolean; delete: boolean; rename: boolean; duplicate: boolean };
	violations: Violation[];
}

export interface Violation {
	field: string;
	message: string;
	severity: 'error' | 'warning' | 'notice';
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
	kind: 'image' | 'video' | 'audio' | 'file';
	fields: FieldDescription[];
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
	// The input the server blamed, when it named one.
	constructor(message: string, public readonly status: number, public readonly field: string | null = null) {
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
export async function request<T>(method: 'GET' | 'POST' | 'PATCH' | 'DELETE', path: string, body?: unknown): Promise<T> {
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

		throw new ApiError(message, response.status, field);
	}

	return data as T;
}
