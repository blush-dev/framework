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
import { plural } from './format';

export type Severity = Violation['severity'];

export type HealthCheckKey = 'files' | 'ids' | 'terms' | 'refs' | 'taxonomies' | 'folders' | 'names' | 'sizes';

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
 * How a fix is sent: the endpoint and its body, for the rows it fixes
 * (`choice` is the path a row's choice picked).
 */
export interface HealthFix {
	path: string;
	body: (rows: HealthRow[], choice?: string) => Record<string, unknown>;
	// What it changes, for a toast: "Gave 3 files new ids".
	noun: [string, string];
}

export interface HealthGroup {
	key: string;
	name: string;
	says: string;
	severity: Severity;
	// What a row is: a file, a name, an image.
	unit: [string, string];
	rows: HealthRow[];
	fix?: HealthFix;
	// The group's fix, offered when two or more rows are open: its
	// button, and the confirmation's question and paragraph.
	bulk?: { label: (count: number) => string; title: (count: number) => string; say: string };
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
	'content:files': { title: 'Content Files', about: 'Reads every entry\'s file: whether it can be read, and whether each value is one the site can use.', clear: 'Every entry\'s file can be read, and every value is one the site can use.', byFile: true },
	'content:ids': { title: 'Entry IDs', about: 'Every entry\'s file needs an id of its own. Links between entries are kept by id, and the admin opens an entry by its id.', clear: 'Every content file has an id of its own.', byFile: false },
	'content:terms': { title: 'Terms and Profiles', about: 'Looks for terms and profiles entries name that have no file, so the site has nothing to show for them.', clear: 'Every term and profile entries name has a file.', byFile: false },
	'content:refs': { title: 'Links Between Entries', about: 'A link filed with its id follows what it links to through a rename or a move.', clear: 'Every link between entries is filed with its id.', byFile: false },
	'content:taxonomies': { title: 'Taxonomies', about: 'Content types still written as taxonomies are read as collections and their relationships until they\'re migrated.', clear: 'Every content type is written as a collection or a tree.', byFile: false },
	'content:folders': { title: 'Collection Folders', about: 'A collection\'s entries are files directly in its folder.', clear: 'Every collection\'s entries are files in its folder.', byFile: false },
	'content:names': { title: 'File Names', about: 'Each type names its entries\' files by a pattern. Older names keep working; renaming them changes no address.', clear: 'Every entry is named by its type\'s pattern.', byFile: false },
	'media:files': { title: 'Media Details', about: 'Reads every media file\'s details: whether they can be read, and whether each value is one the site can use.', clear: 'Every media file\'s details can be read, and every value is one the site can use.', byFile: true },
	'media:ids': { title: 'Media IDs', about: 'Every media file needs an id of its own; an image\'s sizes share its.', clear: 'Every media file has an id of its own.', byFile: false },
	'media:sizes': { title: 'Image Sizes', about: 'An image\'s other sizes are listed in its details.', clear: 'Every image lists its sizes.', byFile: false }
};

// The files' problems' groups, by kind, in the order they're listed
// (within a severity), each with what the site does about them.
const KINDS: Record<string, { name: string; says: string; notice?: { name: string; says: string } }> = {
	unreadable: { name: 'Files That Can\'t Be Read', says: 'The site skips a file it can\'t read, so these aren\'t anywhere on the site.' },
	duplicate: { name: 'Two Files for One Entry', says: 'Only one file can be an entry. The site uses the one that wins and leaves the other out.' },
	required: { name: 'Missing Required Fields', says: 'Every entry of the type needs these.' },
	value: { name: 'Values the Site Can\'t Use', says: 'A value that doesn\'t fit its field is left out, as if it weren\'t there.' },
	unknown: {
		name: 'Keys That Aren\'t Fields',
		says: 'This type takes only its own fields, so the site leaves these keys out.',
		notice: { name: 'Fields Nothing Reads', says: 'Nothing in the site reads these keys. They do nothing here, so nothing on the site changes.' }
	},
	date: { name: 'Dates That Aren\'t Real', says: 'Each is read as another date, so these entries can sort into the wrong place.' },
	parent: { name: 'Parents That Can\'t Be Used', says: 'An entry whose parent can\'t be used is shown at the top level.' },
	translation: { name: 'Translations That Don\'t Match', says: 'A translation names its original by id. Until it matches one, it stands alone.' },
	relation: { name: 'Links That Can\'t Be Followed', says: 'A link to something the site can\'t show shows nothing.' },
	route: { name: 'Pages a Route Hides', says: 'Another route answers at these addresses, so the pages can\'t be reached.' },
	prefix: { name: 'Order Prefixes Where They Aren\'t Used', says: 'Only collections use an order prefix in a file name.' },
	collection: { name: 'Collection Settings', says: 'A folder\'s collection settings, read as each one says.' },
	variant: { name: 'Variants the Theme Doesn\'t Have', says: 'Each renders as the component\'s Default.' },
	details: { name: 'Details Not Read', says: 'These details describe a file that isn\'t there or isn\'t allowed, or another file\'s are read in their place.' },
	artwork: { name: 'Cover Art That Can\'t Be Found', says: 'The file shows no cover art until it\'s chosen again.' },
	alias: { name: 'Older Field Names', says: 'Each is read as the field it names now. Nothing on the site changes.' }
};

const OTHER = { name: 'Other Problems', says: 'As the check reads them.' };

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

	return violation.field === 'file' ? { found: `The file ${violation.message}` } : { field: violation.field, found: violation.message };
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
				group = { key, name: copy.name, says: copy.says, severity: violation.severity, unit: ['problem', 'problems'], rows: [] };
				groups.set(key, group);
			}

			group.rows.push({
				key: `${area}:files:${file.path}:${violation.field}:${violation.message}`,
				severity: violation.severity,
				path: file.path,
				title: entry ? (entry.title || 'Untitled') : null,
				entry: entry ? { type: entry.type, id: entry.id } : undefined,
				...found(violation)
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
	const noun    = media ? 'media file' : 'file';
	const base    = media ? '/health/media-ids' : '/health/ids';
	const titleOf = (path: string): string | null => media ? path : (health.entries[path]?.title || null);
	const describe = (path: string): Lead => media
		? { title: path, mono: true, media: path }
		: (health.entries[path] ? { title: titleOf(path) ?? 'Untitled', entry: { type: health.entries[path].type, id: health.entries[path].id } } : { title: null });

	const groups: HealthGroup[] = [];

	if (ids.missing.length) {
		groups.push({
			key: 'ids:missing',
			name: 'No ID, or One That Isn\'t Valid',
			says: media
				? 'Their details can\'t be told apart from a copy\'s, and entries can\'t keep a link to them through a rename. Each gets a new id; nothing else changes.'
				: 'These can\'t be opened in the admin or linked to from another entry. Each gets a new id; nothing else in the file changes.',
			severity: 'warning',
			unit: [noun, `${noun}s`],
			rows: ids.missing.map((path) => ({
				key: `${area}:ids:${path}`,
				severity: 'warning',
				path,
				...describe(path),
				field: 'id',
				found: 'is missing, or isn\'t a UUID.',
				says: media ? 'The file has no id of its own.' : 'Opening it from its list says it can\'t be opened.',
				fix: { label: 'Add an ID', change: 'id added', done: media ? 'Has an id of its own.' : 'Has an id of its own, so it opens in the editor and can be linked to.' }
			})),
			fix: { path: base, body: (rows) => ({ paths: rows.map((row) => row.path) }), noun: [noun, `${noun}s`] },
			bulk: {
				label: (count) => count === 2 ? 'Give Both New IDs' : `Give ${count} Files New IDs`,
				title: (count) => `Give ${plural(count, noun, `${noun}s`)} New IDs?`,
				say: 'One line is written at the top of each one\'s front matter. Ids never change once written.'
			}
		});
	}

	if (ids.duplicates.length) {
		groups.push({
			key: 'ids:shared',
			name: 'Files Sharing One ID',
			says: 'One looks copied from the other. Links to the id open whichever file the site finds first.',
			severity: 'warning',
			unit: ['id', 'ids'],
			rows: ids.duplicates.map((shared) => ({
				key: `${area}:ids-shared:${shared.id}`,
				severity: 'warning',
				path: shared.paths.join(', '),
				title: shared.paths.map((path) => titleOf(path) ?? basename(path)).join(', '),
				mono: media,
				field: 'id',
				found: shared.id,
				says: `${plural(shared.paths.length, noun, `${noun}s`)} have it. Choose the one that keeps it; the others get new ids.`,
				choices: shared.paths.map((path) => ({ path, title: titleOf(path) })),
				fix: { label: 'Give the Others New IDs', change: 'id replaced', done: 'Kept on one; the others have ids of their own.' }
			})),
			fix: { path: `${base}/keep`, body: (rows, choice) => ({ path: choice ?? rows[0]?.choices?.[0]?.path ?? '' }), noun: [noun, `${noun}s`] }
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

		return entry ? { title: entry.title || 'Untitled', entry: { type: entry.type, id: entry.id } } : { title: null };
	};

	switch (check) {
		case 'files':
			return fileGroups(health, area);
		case 'ids':
			return idGroups(health, area);
		case 'terms':
			return health.terms.items.length ? [{
				key: 'terms',
				name: 'Named With No File',
				says: 'The site leaves out a term or profile with no file. Each is created published, titled as entries name it, so the entries that name it show it at once.',
				severity: 'warning',
				unit: ['name', 'names'],
				rows: health.terms.items.map((item) => ({
					key: `content:terms:${item.type}/${item.slug}`,
					severity: 'warning',
					path: `${item.type}/${item.slug}`,
					title: item.slug,
					mono: true,
					meta: `${item.label} · named by ${plural(item.entries, 'entry', 'entries')}`,
					found: `No ${item.label.toLowerCase()} has this name.`,
					says: 'It isn\'t shown with the entries that name it, and it has no page.',
					fix: { label: `Create ${item.label}`, change: `new file, titled “${item.title}”`, done: `Created as “${item.title}”, published.` }
				})),
				fix: { path: '/health/terms', body: (rows) => ({ terms: rows.map((row) => row.path) }), noun: ['file', 'files'] },
				bulk: {
					label: (count) => `Create ${count} Files`,
					title: (count) => `Create ${plural(count, 'File')}?`,
					say: 'Each is written published, titled as entries name it, and shows with them at once.'
				}
			}] : [];
		case 'refs':
			return health.refs.items.length ? [{
				key: 'refs',
				name: 'Links Not Filed With Their IDs',
				says: 'Each link\'s id is filed beside it, so it follows what it links to through a rename or a move. Nothing on the site changes.',
				severity: 'warning',
				unit: ['file', 'files'],
				rows: health.refs.items.map((item) => ({
					key: `content:refs:${item.path}`,
					severity: 'warning',
					path: item.path,
					...entryOf(item.path),
					field: item.relations.join(', '),
					found: item.relations.length === 1 ? 'is filed by slug alone.' : 'are filed by slug alone.',
					says: 'A rename or move of what it links to can break the link.',
					fix: { label: 'File Links', change: 'ids filed under refs', done: 'Filed with their ids.' }
				})),
				fix: { path: '/health/refs', body: (rows) => ({ paths: rows.map((row) => row.path) }), noun: ['file', 'files'] },
				bulk: {
					label: (count) => `File Links in ${count} Files`,
					title: (count) => `File Links in ${plural(count, 'File')}?`,
					say: 'Each link\'s id is filed under refs, and a value naming an id or an old slug is written as the slug it has now.'
				}
			}] : [];
		case 'taxonomies':
			return health.taxonomies.length ? [{
				key: 'taxonomies',
				name: 'Written as Taxonomies',
				says: 'Each is read as a collection and its relationship until it\'s migrated. Its file in user/data/types becomes a collection, keeping its other settings, and what it files moves to a relationship.',
				severity: 'warning',
				unit: ['type', 'types'],
				rows: health.taxonomies.map((name) => ({
					key: `content:taxonomies:${name}`,
					severity: 'warning',
					path: `user/data/types/${name}`,
					title: name,
					mono: true,
					found: 'Written as a taxonomy.',
					says: 'Read as a collection and a relationship that files entries under it.',
					// The migration is every type's at once.
					fix: health.taxonomies.length === 1 ? { label: 'Migrate It', change: 'migrated to a collection', done: 'Migrated to a collection and a relationship.' } : undefined
				})),
				fix: { path: '/health/taxonomies', body: () => ({}), noun: ['type', 'types'] },
				bulk: {
					label: (count) => `Migrate ${count} Types`,
					title: (count) => `Migrate ${plural(count, 'Type')}?`,
					say: 'Each file in user/data/types becomes a collection, and what it files moves to a relationship in user/data/relations.'
				}
			}] : [];
		case 'folders':
			return health.folders.items.length ? [{
				key: 'folders',
				name: 'Entries Not in Their Folders',
				says: 'A collection\'s entries are files in its folder, or in the folders its folder pattern gives them, such as one for each year. Moving one changes no address.',
				severity: 'warning',
				unit: ['entry', 'entries'],
				rows: health.folders.items.map((item) => ({
					key: `content:folders:${item.path}`,
					severity: 'warning',
					path: item.path,
					...entryOf(item.path),
					found: `Moves to ${item.to}.`,
					fix: { label: 'Move to Its Folder', change: `→ ${item.to}`, done: `Moved to ${item.to}.` }
				})),
				fix: { path: '/health/folders', body: (rows) => ({ paths: rows.map((row) => row.path) }), noun: ['entry', 'entries'] },
				bulk: {
					label: (count) => `Move ${count} Entries`,
					title: (count) => `Move ${plural(count, 'Entry', 'Entries')}?`,
					say: 'Each moves into the folder its collection keeps it in. Addresses stay the same.'
				}
			}] : [];
		case 'names':
			return health.fileNames.map((names) => ({
				key: `names:${names.type}`,
				name: `${names.label} Named by an Older Pattern`,
				says: `${names.label} are named ${names.pattern}. Older names keep working; renaming changes no address.${names.skipped ? ` ${plural(names.skipped, 'entry', 'entries')} kept as ${names.skipped === 1 ? 'a folder keeps its' : 'folders keep their'} name.` : ''}`,
				severity: 'warning' as const,
				unit: ['entry', 'entries'] as [string, string],
				rows: names.items.map((item) => ({
					key: `content:names:${item.path}`,
					severity: 'warning' as const,
					path: item.path,
					...entryOf(item.path),
					found: `Named ${basename(item.to)} by the pattern.`,
					fix: { label: 'Rename It', change: `→ ${item.to}`, done: `Renamed ${basename(item.to)}.` }
				})),
				fix: { path: '/health/filenames', body: (rows: HealthRow[]) => ({ type: names.type, paths: rows.map((row) => row.path) }), noun: ['entry', 'entries'] as [string, string] },
				bulk: {
					label: (count: number) => `Rename ${count} Files`,
					title: (count: number) => `Rename ${plural(count, 'File')}?`,
					say: `Each is renamed by the pattern ${names.pattern}, with its translations. Addresses stay the same.`
				}
			}));
		case 'sizes':
			return health.mediaSizes.items.length ? [{
				key: 'sizes',
				name: 'Sizes Not Listed',
				says: 'An image\'s other sizes are listed in its details. Listing them changes nothing on the site.',
				severity: 'warning',
				unit: ['image', 'images'],
				rows: health.mediaSizes.items.map((item) => ({
					key: `media:sizes:${item.key}`,
					severity: 'warning',
					path: item.key,
					title: item.key,
					mono: true,
					media: item.key,
					field: 'sizes',
					found: [item.unrecorded ? `doesn't list ${plural(item.unrecorded, 'size')}` : '', item.stale ? `lists ${plural(item.stale, 'file')} that ${item.stale === 1 ? 'isn\'t' : 'aren\'t'} its sizes` : ''].filter(Boolean).join(', and ') + '.',
					fix: { label: 'Record Sizes', change: 'sizes listed', done: 'Lists its sizes.' }
				})),
				fix: { path: '/health/media-sizes', body: (rows) => ({ paths: rows.map((row) => row.path) }), noun: ['image', 'images'] },
				bulk: {
					label: (count) => `Record Sizes of ${count} Images`,
					title: (count) => `Record the Sizes of ${plural(count, 'Image')}?`,
					say: 'Each image\'s details list its sizes as they are.'
				}
			}] : [];
		default:
			return [];
	}
}
