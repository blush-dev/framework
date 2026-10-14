/**
 * Site Health's check pages (D-612, from the Site Health sketch): what
 * each check found, as groups of rows. A group is one kind of problem,
 * with a heading that names it and says what the site does about it, so
 * the reason is read once, not in every row. A row says which file (its
 * entry's title, then its path), what was found (the key, as a code
 * chip), and what the site does because of it, in plain words; and,
 * when Blush can fix it, its fix, named for what it writes ("Add an
 * ID", "Create Profile"), never just "Fix".
 *
 * A fix is a request (`HealthFix`): one row's, or a group's when every
 * row would get the same kind of change. Groups whose rows need a choice
 * each (which file keeps a shared id) have no group fix.
 *
 * Problems from `content:lint` are grouped by their `kind`; each other
 * check is its own groups. One problem, one check: the report leaves
 * out of the files' problems those another check reports.
 */

import type { Health, Violation } from './api';
import { t } from './messages';

export type Severity = Violation['severity'];

export type HealthCheckKey = 'files' | 'ids' | 'terms' | 'parents' | 'refs' | 'taxonomies' | 'types' | 'folders' | 'names' | 'sizes';

export interface HealthChoice {
	path: string;
	title: string | null;
}

export interface HealthRow {
	// The same from one check to the next, so a fixed or ignored row is
	// known: the server's `ProblemKeys`, which ignoring keeps (D-613).
	key: string;
	severity: Severity;
	// The file, from the content or media folder, or the site's root.
	path: string;
	// The entry's title, or what the row is about, or `null` when there's
	// nothing to call it but its path.
	title: string | null;
	// Whether the title is a slug or a key, set in mono.
	mono?: boolean;
	// A line under the title: "Profile · named by 3 entries".
	meta?: string;
	// Where the entry opens, when it's one the site can read.
	entry?: { type: string; id: string | null };
	// The media file's key, for its screen.
	media?: string;
	// What was found: a key, set as code, then what it says.
	field?: string;
	found: string;
	// What the site does because of it.
	says?: string;
	// The fix, named for what it writes, and what it changes, for the
	// confirmation that lists every change.
	fix?: { label: string; change: string; done: string };
	// Files to choose between before fixing (which keeps a shared id).
	choices?: HealthChoice[];
}

// How a row leads: its title, and where it opens.
type Lead = Pick<HealthRow, 'title' | 'mono' | 'media' | 'entry'>;

/**
 * A count of something a group or a fix is about, with its noun, as one
 * message, so each language words its own plurals: "3 files".
 */
export type Counted = (count: number) => string;

/**
 * How a fix is sent: the endpoint and its body, for the rows it fixes
 * (`choice` is the path a row's choice picked).
 */
export interface HealthFix {
	path: string;
	body: (rows: HealthRow[], choice?: string) => Record<string, unknown>;
	// What it changes, counted, for a toast: "3 files".
	noun: Counted;
	// Whether it asks first even for one row: it takes out what's
	// written (D-786).
	ask?: boolean;
}

export interface HealthGroup {
	key: string;
	name: string;
	says: string;
	severity: Severity;
	// What a row is, counted: "3 files", "2 names", "1 image".
	unit: Counted;
	rows: HealthRow[];
	fix?: HealthFix;
	// The group's fix, offered when two or more rows are open: its
	// button, and the confirmation's question and paragraph.
	bulk?: { label: (count: number) => string; title: (count: number) => string; say: string; after?: string };
}

export interface HealthScreen {
	title: string;
	// What it checks, before a check has run.
	about: string;
	// What all clear says.
	clear: string;
	// Whether its rows can be grouped by file (one file, several
	// problems).
	byFile: boolean;
}

export const SCREENS: Record<string, HealthScreen> = {
	'content:files': { title: t('health.screen_content_files_title', 'Content Files'), about: t('health.screen_content_files_about', 'Reads every entry\'s file: whether it can be read, and whether each value is one the site can use.'), clear: t('health.screen_content_files_clear', 'Every entry\'s file can be read, and every value is one the site can use.'), byFile: true },
	'content:ids': { title: t('health.screen_content_ids_title', 'Entry IDs'), about: t('health.screen_content_ids_about', 'Every entry\'s file needs an id of its own. Links between entries are kept by id, and the admin opens an entry by its id.'), clear: t('health.screen_content_ids_clear', 'Every content file has an id of its own.'), byFile: false },
	'content:terms': { title: t('health.screen_content_terms_title', 'Terms and Profiles'), about: t('health.screen_content_terms_about', 'Looks for terms and profiles entries name that have no file, so the site has nothing to show for them.'), clear: t('health.screen_content_terms_clear', 'Every term and profile entries name has a file.'), byFile: false },
	'content:parents': { title: t('health.screen_content_parents_title', 'Parent Pages'), about: t('health.screen_content_parents_about', 'A page is under its parent page, so a page kept in a folder with no page of its own is at the top of its tree, its address without the folder.'), clear: t('health.screen_content_parents_clear', 'Every page is under a parent page that exists.'), byFile: false },
	'content:refs': { title: t('health.screen_content_refs_title', 'Links Between Entries'), about: t('health.screen_content_refs_about', 'A link filed with its id follows what it links to through a rename or a move.'), clear: t('health.screen_content_refs_clear', 'Every link between entries is filed with its id.'), byFile: false },
	'content:taxonomies': { title: t('health.screen_content_taxonomies_title', 'Taxonomies'), about: t('health.screen_content_taxonomies_about', 'Content types still written as taxonomies are read as collections and their relationships until they\'re migrated.'), clear: t('health.screen_content_taxonomies_clear', 'Every content type is written as a collection or a tree.'), byFile: false },
	'content:types': { title: t('health.screen_content_types_title', 'Type Folders'), about: t('health.screen_content_types_about', 'Every content type is kept in _ and its name. One that still names its own folder is read there, so its entries aren\'t found until they move.'), clear: t('health.screen_content_types_clear', 'Every content type is kept in _ and its name.'), byFile: false },
	'content:folders': { title: t('health.screen_content_folders_title', 'Collection Folders'), about: t('health.screen_content_folders_about', 'A collection\'s entries are files directly in its folder.'), clear: t('health.screen_content_folders_clear', 'Every collection\'s entries are files in its folder.'), byFile: false },
	'content:names': { title: t('health.screen_content_names_title', 'File Names'), about: t('health.screen_content_names_about', 'Each type names its entries\' files by a pattern. Older names keep working; renaming them changes no address.'), clear: t('health.screen_content_names_clear', 'Every entry is named by its type\'s pattern.'), byFile: false },
	'media:files': { title: t('health.screen_media_files_title', 'Media Details'), about: t('health.screen_media_files_about', 'Reads every media file\'s details: whether they can be read, and whether each value is one the site can use.'), clear: t('health.screen_media_files_clear', 'Every media file\'s details can be read, and every value is one the site can use.'), byFile: true },
	'media:ids': { title: t('health.screen_media_ids_title', 'Media IDs'), about: t('health.screen_media_ids_about', 'Every media file needs an id of its own; an image\'s sizes share its.'), clear: t('health.screen_media_ids_clear', 'Every media file has an id of its own.'), byFile: false },
	'media:sizes': { title: t('health.screen_media_sizes_title', 'Image Sizes'), about: t('health.screen_media_sizes_about', 'An image\'s other sizes are listed in its details.'), clear: t('health.screen_media_sizes_clear', 'Every image lists its sizes.'), byFile: false }
};

// The files' problems' groups, by kind, in the order they're listed
// (within a severity), each with what the site does about them.

const problems: Counted = (count) => t('health.count_problems', '{count, plural, one {# problem} other {# problems}}', { count });
const entries: Counted = (count) => t('health.count_entries', '{count, plural, one {# entry} other {# entries}}', { count });
const idCount: Counted = (count) => t('health.count_ids', '{count, plural, one {# id} other {# ids}}', { count });
const names: Counted = (count) => t('health.count_names', '{count, plural, one {# name} other {# names}}', { count });
const folders: Counted = (count) => t('health.count_folders', '{count, plural, one {# folder} other {# folders}}', { count });
const pages: Counted = (count) => t('health.count_pages', '{count, plural, one {# page} other {# pages}}', { count });
const files: Counted = (count) => t('health.count_files', '{count, plural, one {# file} other {# files}}', { count });
const mediaFiles: Counted = (count) => t('health.count_media_files', '{count, plural, one {# media file} other {# media files}}', { count });
const types: Counted = (count) => t('health.count_types', '{count, plural, one {# type} other {# types}}', { count });
const images: Counted = (count) => t('health.count_images', '{count, plural, one {# image} other {# images}}', { count });

const KINDS: Record<string, { name: string; says: string; notice?: { name: string; says: string } }> = {
	unreadable: { name: t('health.kind_unreadable_name', 'Files That Can\'t Be Read'), says: t('health.kind_unreadable_says', 'The site skips a file it can\'t read, so these aren\'t anywhere on the site.') },
	duplicate: { name: t('health.kind_duplicate_name', 'Two Files for One Entry'), says: t('health.kind_duplicate_says', 'Only one file can be an entry. The site uses the one that wins and leaves the other out.') },
	required: { name: t('health.kind_required_name', 'Missing Required Fields'), says: t('health.kind_required_says', 'Every entry of the type needs these.') },
	value: { name: t('health.kind_value_name', 'Values the Site Can\'t Use'), says: t('health.kind_value_says', 'A value that doesn\'t fit its field is left out, as if it weren\'t there.') },
	unknown: {
		name: t('health.kind_unknown_name', 'Keys That Aren\'t Fields'),
		says: t('health.kind_unknown_says', 'This type takes only its own fields, so the site leaves these keys out.'),
		notice: { name: t('health.kind_unknown_notice_name', 'Fields Nothing Reads'), says: t('health.kind_unknown_notice_says', 'Nothing declares these keys: no field of the type, a field set, or a plugin. They stay in their files, but the site never reads them. Add one to a field set to use it, or remove them.') }
	},
	date: { name: t('health.kind_date_name', 'Dates That Aren\'t Real'), says: t('health.kind_date_says', 'Each is read as another date, so these entries can sort into the wrong place.') },
	parent: { name: t('health.kind_parent_name', 'Parents That Can\'t Be Used'), says: t('health.kind_parent_says', 'An entry whose parent can\'t be used is shown at the top level.') },
	translation: { name: t('health.kind_translation_name', 'Translations That Don\'t Match'), says: t('health.kind_translation_says', 'A translation names its original by id. Until it matches one, it stands alone.') },
	relation: { name: t('health.kind_relation_name', 'Links That Can\'t Be Followed'), says: t('health.kind_relation_says', 'A link to something the site can\'t show shows nothing.') },
	route: { name: t('health.kind_route_name', 'Pages a Route Hides'), says: t('health.kind_route_says', 'Another route answers at these addresses, so the pages can\'t be reached.') },
	prefix: { name: t('health.kind_prefix_name', 'Order Prefixes Where They Aren\'t Used'), says: t('health.kind_prefix_says', 'Only collections use an order prefix in a file name.') },
	collection: { name: t('health.kind_collection_name', 'Collection Settings'), says: t('health.kind_collection_says', 'A folder\'s collection settings, read as each one says.') },
	variant: { name: t('health.kind_variant_name', 'Variants the Theme Doesn\'t Have'), says: t('health.kind_variant_says', 'Each renders as the component\'s Default.') },
	details: { name: t('health.kind_details_name', 'Details Not Read'), says: t('health.kind_details_says', 'These details describe a file that isn\'t there or isn\'t allowed, or another file\'s are read in their place.') },
	artwork: { name: t('health.kind_artwork_name', 'Cover Art That Can\'t Be Found'), says: t('health.kind_artwork_says', 'The file shows no cover art until it\'s chosen again.') },
	media: { name: t('health.kind_media_name', 'Media Named by Path'), says: t('health.kind_media_says', 'A media field names its file by id, so the file can be renamed or moved. Each path is still read; rewriting it changes nothing on the site.') },
	alias: { name: t('health.kind_alias_name', 'Older Field Names'), says: t('health.kind_alias_says', 'Each is read as the field it names now. Nothing on the site changes.') }
};

const OTHER = { name: t('health.other_name', 'Other Problems'), says: t('health.other_says', 'As the check reads them.') };

const SEVERITIES: Severity[] = ['error', 'warning', 'notice'];

const basename = (path: string): string => path.slice(path.lastIndexOf('/') + 1);

/**
 * What a lint problem found, as a sentence: "`title` is required.", or
 * "The file has an order prefix…", or a message of its own (a parser's)
 * as it is.
 */
function found(violation: Violation): { field?: string; found: string } {
	const first = violation.message.charAt(0);

	if (first !== first.toLowerCase()) {
		return { found: violation.message };
	}

	return violation.field === 'file' ? { found: t('health.found_file', 'The file {message}', { message: violation.message }) } : { field: violation.field, found: violation.message };
}

/**
 * The files' problems, for Content Files or Media Details, grouped by
 * kind and severity, errors first.
 */
function fileGroups(health: Health, area: 'content' | 'media'): HealthGroup[] {
	const groups = new Map<string, HealthGroup>();

	for (const file of health.files) {
		if (file.area !== area) {
			continue;
		}

		const entry = health.entries[file.path];

		file.violations.forEach((violation) => {
			const kind  = violation.kind !== null && KINDS[violation.kind] !== undefined ? violation.kind : 'other';
			const key   = `${kind}:${violation.severity}`;
			const copy  = kind === 'other' ? OTHER : (violation.severity === 'notice' ? (KINDS[kind]?.notice ?? KINDS[kind]) : KINDS[kind]) ?? OTHER;
			let group   = groups.get(key);

			if (group === undefined) {
				group = { key, name: copy.name, says: copy.says, severity: violation.severity, unit: problems, rows: [] };
				groups.set(key, group);
			}

			// A media path that names a file is rewritten to its id (D-773);
			// one that names none can only be chosen again (told by its key,
			// which has the English in every language, D-836).
			const rewrite = kind === 'media' && entry?.id && !(violation.problem ?? violation.message).includes('names no media file');

			if (rewrite && group.fix === undefined) {
				group.fix  = { path: '/health/media-fields', body: (rows) => ({ ids: [...new Set(rows.map((row) => row.entry?.id).filter((id): id is string => typeof id === 'string'))] }), noun: entries };
				group.bulk = {
					label: (count) => t('health.media_bulk_label', 'Rewrite {count} Paths', { count }),
					title: (count) => t('health.media_bulk_title', 'Rewrite {count, plural, one {# Path} other {# Paths}} as IDs?', { count }),
					say: t('health.media_bulk_say', 'Each media field written as a path is rewritten to its file\'s id. A path to an image\'s size becomes the image\'s id.')
				};
			}

			// A key nothing declares can be taken out of its file (D-786),
			// asking first.
			const remove = kind === 'unknown' && entry?.id;

			if (remove && group.fix === undefined) {
				group.fix  = { path: '/health/undeclared', body: (rows) => ({ ids: [...new Set(rows.map((row) => row.entry?.id).filter((id): id is string => typeof id === 'string'))] }), noun: entries, ask: true };
				group.bulk = {
					label: (count) => t('health.undeclared_bulk_label', 'Remove {count, plural, one {# Key} other {# Keys}}', { count }),
					title: (count) => t('health.undeclared_bulk_title', 'Remove {count, plural, one {# Key} other {# Keys}}?', { count }),
					say: t('health.undeclared_bulk_say', 'Every key nothing declares is taken out of each entry\'s file, with what it holds.'),
					after: t('health.undeclared_bulk_after', 'Nothing on the site changes, since nothing reads them.')
				};
			}

			group.rows.push({
				key: violation.problem ?? `${area}:files:${file.path}:${violation.field}:${violation.message}`,
				severity: violation.severity,
				path: file.path,
				title: entry ? (entry.title || t('health.untitled', 'Untitled')) : null,
				entry: entry ? { type: entry.type, id: entry.id } : undefined,
				...found(violation),
				...(rewrite ? { fix: { label: t('health.media_fix_label', 'Rewrite as ID'), change: t('health.media_fix_change', 'the path rewritten as its file\'s id'), done: t('health.media_fix_done', 'Rewritten as its id.') } } : {}),
				...(remove ? { fix: { label: t('common.remove', 'Remove'), change: t('health.undeclared_fix_change', 'every key nothing declares removed'), done: t('health.undeclared_fix_done', 'Removed.') } } : {})
			});
		});
	}

	const order = [...Object.keys(KINDS), 'other'];

	return [...groups.values()].sort((a, b) => SEVERITIES.indexOf(a.severity) - SEVERITIES.indexOf(b.severity) || order.indexOf(a.key.split(':')[0] ?? '') - order.indexOf(b.key.split(':')[0] ?? ''));
}

/**
 * Entry or media ids: those missing one (or with one that isn't valid),
 * and ids files share.
 */
function idGroups(health: Health, area: 'content' | 'media'): HealthGroup[] {
	const ids     = area === 'content' ? health.ids : health.mediaIds;
	const media   = area === 'media';
	const counted = media ? mediaFiles : files;
	const base    = media ? '/health/media-ids' : '/health/ids';
	const titleOf = (path: string): string | null => media ? path : (health.entries[path]?.title || null);
	const describe = (path: string): Lead => media
		? { title: path, mono: true, media: path }
		: (health.entries[path] ? { title: titleOf(path) ?? t('health.untitled', 'Untitled'), entry: { type: health.entries[path].type, id: health.entries[path].id } } : { title: null });

	const groups: HealthGroup[] = [];

	if (ids.missing.length) {
		groups.push({
			key: 'ids:missing',
			name: t('health.ids_missing_name', 'No ID, or One That Isn\'t Valid'),
			says: media
				? t('health.ids_missing_says_media', 'Their details can\'t be told apart from a copy\'s, and entries can\'t keep a link to them through a rename. Each gets a new id; nothing else changes.')
				: t('health.ids_missing_says', 'These can\'t be opened in the admin or linked to from another entry. Each gets a new id; nothing else in the file changes.'),
			severity: 'warning',
			unit: counted,
			rows: ids.missing.map((path) => ({
				key: `${area}:ids:${path}`,
				severity: 'warning',
				path,
				...describe(path),
				field: 'id',
				found: t('health.ids_missing_found', 'is missing, or isn\'t a UUID.'),
				says: media ? t('health.ids_missing_row_media', 'The file has no id of its own.') : t('health.ids_missing_row', 'Opening it from its list says it can\'t be opened.'),
				fix: { label: t('health.ids_missing_fix_label', 'Add an ID'), change: t('health.ids_missing_fix_change', 'id added'), done: media ? t('health.ids_missing_fix_done_media', 'Has an id of its own.') : t('health.ids_missing_fix_done', 'Has an id of its own, so it opens in the editor and can be linked to.') }
			})),
			fix: { path: base, body: (rows) => ({ paths: rows.map((row) => row.path) }), noun: counted },
			bulk: {
				label: (count) => t('health.ids_missing_bulk_label', '{count, plural, =2 {Give Both New IDs} other {Give # Files New IDs}}', { count }),
				title: (count) => media
					? t('health.ids_missing_bulk_title_media', 'Give {count, plural, one {# media file} other {# media files}} New IDs?', { count })
					: t('health.ids_missing_bulk_title', 'Give {count, plural, one {# file} other {# files}} New IDs?', { count }),
				say: t('health.ids_missing_bulk_say', 'One line is written at the top of each one\'s front matter. Ids never change once written.')
			}
		});
	}

	if (ids.duplicates.length) {
		groups.push({
			key: 'ids:shared',
			name: t('health.ids_shared_name', 'Files Sharing One ID'),
			says: t('health.ids_shared_says', 'One looks copied from the other. Links to the id open whichever file the site finds first.'),
			severity: 'warning',
			unit: idCount,
			rows: ids.duplicates.map((shared) => ({
				key: `${area}:ids-shared:${shared.id}`,
				severity: 'warning',
				path: shared.paths.join(', '),
				title: shared.paths.map((path) => titleOf(path) ?? basename(path)).join(', '),
				mono: media,
				field: 'id',
				found: shared.id,
				says: media
					? t('health.ids_shared_row_media', '{count, plural, one {# media file} other {# media files}} have it. Choose the one that keeps it; the others get new ids.', { count: shared.paths.length })
					: t('health.ids_shared_row', '{count, plural, one {# file} other {# files}} have it. Choose the one that keeps it; the others get new ids.', { count: shared.paths.length }),
				choices: shared.paths.map((path) => ({ path, title: titleOf(path) })),
				fix: { label: t('health.ids_shared_fix_label', 'Give the Others New IDs'), change: t('health.ids_shared_fix_change', 'id replaced'), done: t('health.ids_shared_fix_done', 'Kept on one; the others have ids of their own.') }
			})),
			fix: { path: `${base}/keep`, body: (rows, choice) => ({ path: choice ?? rows[0]?.choices?.[0]?.path ?? '' }), noun: counted }
		});
	}

	return groups;
}

/**
 * Builds a check's groups from the report.
 */
export function healthGroups(health: Health, area: 'content' | 'media', check: HealthCheckKey): HealthGroup[] {
	const entryOf = (path: string): Lead => {
		const entry = health.entries[path];

		return entry ? { title: entry.title || t('health.untitled', 'Untitled'), entry: { type: entry.type, id: entry.id } } : { title: null };
	};

	switch (check) {
		case 'files':
			return fileGroups(health, area);
		case 'ids':
			return idGroups(health, area);
		case 'terms':
			return health.terms.items.length ? [{
				key: 'terms',
				name: t('health.terms_name', 'Named With No File'),
				says: t('health.terms_says', 'The site leaves out a term or profile with no file. Each is created published, titled as entries name it, so the entries that name it show it at once.'),
				severity: 'warning',
				unit: names,
				rows: health.terms.items.map((item) => ({
					key: `content:terms:${item.type}/${item.slug}`,
					severity: 'warning',
					path: `${item.type}/${item.slug}`,
					title: item.slug,
					mono: true,
					meta: t('health.terms_meta', '{label} · named by {count, plural, one {# entry} other {# entries}}', { label: item.label, count: item.entries }),
					found: t('health.terms_found', 'No {label} has this name.', { label: item.label.toLowerCase() }),
					says: t('health.terms_row', 'It isn\'t shown with the entries that name it, and it has no page.'),
					fix: { label: t('health.create_label', 'Create {label}', { label: item.label }), change: t('health.terms_fix_change', 'new file, titled “{title}”', { title: item.title }), done: t('health.terms_fix_done', 'Created as “{title}”, published.', { title: item.title }) }
				})),
				fix: { path: '/health/terms', body: (rows) => ({ terms: rows.map((row) => row.path) }), noun: files },
				bulk: {
					label: (count) => t('health.terms_bulk_label', 'Create {count} Files', { count }),
					title: (count) => t('health.terms_bulk_title', 'Create {count, plural, one {# File} other {# Files}}?', { count }),
					say: t('health.terms_bulk_say', 'Each is written published, titled as entries name it, and shows with them at once.')
				}
			}] : [];
		case 'parents':
			return health.parents.items.length ? [{
				key: 'parents',
				name: t('health.parents_name', 'Folders With No Page'),
				says: t('health.parents_says', 'A page kept in a folder with no page of its own is at the top of its tree. Each parent is created as a draft, titled by its folder, so the pages under it are back at their addresses at once.'),
				severity: 'warning',
				unit: folders,
				rows: health.parents.items.map((item) => ({
					key: `content:parents:${item.type}/${item.key}`,
					severity: 'warning',
					path: `${item.type}/${item.key}`,
					title: item.key,
					mono: true,
					meta: t('health.parents_meta', '{label} · {count, plural, one {# page} other {# pages}} under it', { label: item.label, count: item.pages }),
					found: t('health.parents_found', 'No {label} is kept at this folder.', { label: item.label.toLowerCase() }),
					says: t('health.parents_row', 'The pages under it are at the top of their tree, without the folder in their addresses.'),
					fix: { label: t('health.create_label', 'Create {label}', { label: item.label }), change: t('health.parents_fix_change', 'new draft, titled “{title}”', { title: item.title }), done: t('health.parents_fix_done', 'Created as “{title}”, a draft.', { title: item.title }) }
				})),
				fix: { path: '/health/parents', body: (rows) => ({ parents: rows.map((row) => row.path) }), noun: pages },
				bulk: {
					label: (count) => t('health.parents_bulk_label', 'Create {count} Pages', { count }),
					title: (count) => t('health.parents_bulk_title', 'Create {count, plural, one {# Page} other {# Pages}}?', { count }),
					say: t('health.parents_bulk_say', 'Each is written as a draft, titled by its folder, and the pages under it are back at their addresses at once.')
				}
			}] : [];
		case 'refs':
			return health.refs.items.length ? [{
				key: 'refs',
				name: t('health.refs_name', 'Links Not Filed With Their IDs'),
				says: t('health.refs_says', 'Each link\'s id is filed beside it, so it follows what it links to through a rename or a move. Nothing on the site changes.'),
				severity: 'warning',
				unit: files,
				rows: health.refs.items.map((item) => ({
					key: `content:refs:${item.path}`,
					severity: 'warning',
					path: item.path,
					...entryOf(item.path),
					field: item.relations.join(', '),
					found: t('health.refs_found', '{count, plural, one {is} other {are}} filed by slug alone.', { count: item.relations.length }),
					says: t('health.refs_row', 'A rename or move of what it links to can break the link.'),
					fix: { label: t('health.refs_fix_label', 'File Links'), change: t('health.refs_fix_change', 'ids filed under refs'), done: t('health.refs_fix_done', 'Filed with their ids.') }
				})),
				fix: { path: '/health/refs', body: (rows) => ({ paths: rows.map((row) => row.path) }), noun: files },
				bulk: {
					label: (count) => t('health.refs_bulk_label', 'File Links in {count} Files', { count }),
					title: (count) => t('health.refs_bulk_title', 'File Links in {count, plural, one {# File} other {# Files}}?', { count }),
					say: t('health.refs_bulk_say', 'Each link\'s id is filed under refs, and a value naming an id or an old slug is written as the slug it has now.')
				}
			}] : [];
		case 'taxonomies':
			return health.taxonomies.length ? [{
				key: 'taxonomies',
				name: t('health.taxonomies_name', 'Written as Taxonomies'),
				says: t('health.taxonomies_says', 'Each is read as a collection and its relationship until it\'s migrated. Its file in user/data/types becomes a collection, keeping its other settings, and what it files moves to a relationship.'),
				severity: 'warning',
				unit: types,
				rows: health.taxonomies.map((name) => ({
					key: `content:taxonomies:${name}`,
					severity: 'warning',
					path: `user/data/types/${name}`,
					title: name,
					mono: true,
					found: t('health.taxonomies_found', 'Written as a taxonomy.'),
					says: t('health.taxonomies_row', 'Read as a collection and a relationship that files entries under it.'),
					// The migration is every type's at once.
					fix: health.taxonomies.length === 1 ? { label: t('health.taxonomies_fix_label', 'Migrate It'), change: t('health.taxonomies_fix_change', 'migrated to a collection'), done: t('health.taxonomies_fix_done', 'Migrated to a collection and a relationship.') } : undefined
				})),
				fix: { path: '/health/taxonomies', body: () => ({}), noun: types },
				bulk: {
					label: (count) => t('health.taxonomies_bulk_label', 'Migrate {count} Types', { count }),
					title: (count) => t('health.taxonomies_bulk_title', 'Migrate {count, plural, one {# Type} other {# Types}}?', { count }),
					say: t('health.taxonomies_bulk_say', 'Each file in user/data/types becomes a collection, and what it files moves to a relationship in user/data/relations.')
				}
			}] : [];
		case 'types':
			return health.typeFolders.length ? [{
				key: 'types',
				name: t('health.types_name', 'Types Naming Their Folder'),
				says: t('health.types_says', 'Each is kept in _ and its name, so its entries aren\'t found until they move there. Moving them writes its file in user/data/types without the folder, with the prefix its addresses came from, so no address changes.'),
				severity: 'warning',
				unit: types,
				rows: health.typeFolders.map((item) => ({
					key: `content:types:${item.name}`,
					severity: 'warning',
					path: `user/data/types/${item.name}`,
					title: item.name,
					mono: true,
					found: t('health.types_found', 'Names {from}/ as its folder.', { from: item.from }),
					says: t('health.types_row', 'Its entries move to {to}/.', { to: item.to }),
					// The move is every type's at once.
					fix: health.typeFolders.length === 1 ? { label: t('health.types_fix_label', 'Move It'), change: t('health.types_fix_change', 'moved to {to}/', { to: item.to }), done: t('health.types_fix_done', 'Moved to {to}/.', { to: item.to }) } : undefined
				})),
				fix: { path: '/health/type-folders', body: () => ({}), noun: types },
				bulk: {
					label: (count) => t('health.types_bulk_label', 'Move {count} Types', { count }),
					title: (count) => t('health.types_bulk_title', 'Move {count, plural, one {# Type} other {# Types}}?', { count }),
					say: t('health.types_bulk_say', 'Each type\'s entries move into _ and its name, and its file in user/data/types is written without the folder. Addresses stay the same.')
				}
			}] : [];
		case 'folders':
			return health.folders.items.length ? [{
				key: 'folders',
				name: t('health.folders_name', 'Entries Not in Their Folders'),
				says: t('health.folders_says', 'A collection\'s entries are files in its folder, or in the folders its folder pattern gives them, such as one for each year. Moving one changes no address.'),
				severity: 'warning',
				unit: entries,
				rows: health.folders.items.map((item) => ({
					key: `content:folders:${item.path}`,
					severity: 'warning',
					path: item.path,
					...entryOf(item.path),
					found: t('health.folders_found', 'Moves to {to}.', { to: item.to }),
					fix: { label: t('health.folders_fix_label', 'Move to Its Folder'), change: `→ ${item.to}`, done: t('health.folders_fix_done', 'Moved to {to}.', { to: item.to }) }
				})),
				fix: { path: '/health/folders', body: (rows) => ({ paths: rows.map((row) => row.path) }), noun: entries },
				bulk: {
					label: (count) => t('health.folders_bulk_label', 'Move {count} Entries', { count }),
					title: (count) => t('health.folders_bulk_title', 'Move {count, plural, one {# Entry} other {# Entries}}?', { count }),
					say: t('health.folders_bulk_say', 'Each moves into the folder its collection keeps it in. Addresses stay the same.')
				}
			}] : [];
		case 'names':
			return health.fileNames.map((names) => ({
				key: `names:${names.type}`,
				name: t('health.names_name', '{label} Named by an Older Pattern', { label: names.label }),
				says: names.skipped
					? t('health.names_says_skipped', '{label} are named {pattern}. Older names keep working; renaming changes no address. {count, plural, one {# entry kept as a folder keeps its name.} other {# entries kept as folders keep their name.}}', { label: names.label, pattern: names.pattern, count: names.skipped })
					: t('health.names_says', '{label} are named {pattern}. Older names keep working; renaming changes no address.', { label: names.label, pattern: names.pattern }),
				severity: 'warning' as const,
				unit: entries,
				rows: names.items.map((item) => ({
					key: `content:names:${item.path}`,
					severity: 'warning' as const,
					path: item.path,
					...entryOf(item.path),
					found: t('health.names_found', 'Named {name} by the pattern.', { name: basename(item.to) }),
					fix: { label: t('health.names_fix_label', 'Rename It'), change: `→ ${item.to}`, done: t('health.names_fix_done', 'Renamed {name}.', { name: basename(item.to) }) }
				})),
				fix: { path: '/health/filenames', body: (rows: HealthRow[]) => ({ type: names.type, paths: rows.map((row) => row.path) }), noun: entries },
				bulk: {
					label: (count: number) => t('health.names_bulk_label', 'Rename {count} Files', { count }),
					title: (count: number) => t('health.names_bulk_title', 'Rename {count, plural, one {# File} other {# Files}}?', { count }),
					say: t('health.names_bulk_say', 'Each is renamed by the pattern {pattern}, with its translations. Addresses stay the same.', { pattern: names.pattern })
				}
			}));
		case 'sizes':
			return health.mediaSizes.items.length ? [{
				key: 'sizes',
				name: t('health.sizes_name', 'Sizes Not Listed'),
				says: t('health.sizes_says', 'An image\'s other sizes are listed in its details. Listing them changes nothing on the site.'),
				severity: 'warning',
				unit: images,
				rows: health.mediaSizes.items.map((item) => ({
					key: `media:sizes:${item.key}`,
					severity: 'warning',
					path: item.key,
					title: item.key,
					mono: true,
					media: item.key,
					field: 'sizes',
					found: item.unrecorded && item.stale
						? t('health.sizes_found_both', 'doesn\'t list {unrecorded, plural, one {# size} other {# sizes}}, and lists {stale, plural, one {# file that isn\'t} other {# files that aren\'t}} its sizes.', { unrecorded: item.unrecorded, stale: item.stale })
						: (item.unrecorded
							? t('health.sizes_found_unrecorded', 'doesn\'t list {count, plural, one {# size} other {# sizes}}.', { count: item.unrecorded })
							: (item.stale ? t('health.sizes_found_stale', 'lists {count, plural, one {# file that isn\'t} other {# files that aren\'t}} its sizes.', { count: item.stale }) : '.')),
					fix: { label: t('health.sizes_fix_label', 'Record Sizes'), change: t('health.sizes_fix_change', 'sizes listed'), done: t('health.sizes_fix_done', 'Lists its sizes.') }
				})),
				fix: { path: '/health/media-sizes', body: (rows) => ({ paths: rows.map((row) => row.path) }), noun: images },
				bulk: {
					label: (count) => t('health.sizes_bulk_label', 'Record Sizes of {count} Images', { count }),
					title: (count) => t('health.sizes_bulk_title', 'Record the Sizes of {count, plural, one {# Image} other {# Images}}?', { count }),
					say: t('health.sizes_bulk_say', 'Each image\'s details list its sizes as they are.')
				}
			}] : [];
		default:
			return [];
	}
}
