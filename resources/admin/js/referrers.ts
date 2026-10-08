/**
 * What links to an entry (D-598), asked before it leaves the site: a
 * move to draft or to the trash says how many live entries link to it,
 * and which, since the site stops showing it there, and deleting it for
 * good asks whether to take it out of them first
 * (`DELETE …?permanently=1&unlink=1`), so nothing names it after.
 *
 * Drawn as the pickers sketch's Leaving the Site (D-608): only live
 * linkers count toward a move (a draft that links loses nothing today),
 * nothing is asked when none link, so the move stays one click with its
 * Undo, and lists stop at 8, saying where the rest are.
 */

import { entryPath, request, type EntryStatus } from './api';
import { confirmAction, confirmChecked, type ConfirmItem } from './confirm';
import { plural, series, titleCase } from './format';

export interface EntryReferrers {
	// How many entries link to it, how many are live, how many are
	// drafts, and how many the account may edit.
	count: number;
	live: number;
	drafts: number;
	editable: number;
	// How many live ones credit it alone in their byline.
	uncredited: number;
	// The first 8, by title, with what they link through.
	entries: { id: string | null; title: string; type: string; status: EntryStatus; relations: string[] }[];
}

/**
 * Loads what links to an entry (only what's live, with `live`), or
 * `null` when it can't be told.
 */
export async function referrersOf(id: string, live = false): Promise<EntryReferrers | null> {
	try {
		return await request<EntryReferrers>('GET', `${entryPath(id)}/referrers${live ? '?live=1' : ''}`);
	} catch {
		return null;
	}
}

// The entries as a confirmation lists them: each, its type and what it
// links through, and where the rest are.
function listed(found: EntryReferrers, total: number, rest: string): { items: ConfirmItem[]; more?: string } {
	const items = found.entries.map((item) => ({ title: item.title, meta: [item.type, ...item.relations].join(' · '), status: item.status }));
	const more  = total - items.length;

	return more > 0 ? { items, more: `and ${more} more, ${rest}.` } : { items };
}

/**
 * Asks before an entry stops being live, when live entries link to it:
 * moving it to the trash, or to draft. With `credits` (a profile), it
 * says it's credited, and how many entries will show no byline. Resolves
 * whether to go on; with nothing live linking to it, at once.
 */
export async function confirmLeaving(id: string, name: string, action: 'trash' | 'draft', credits = false): Promise<boolean> {
	const found = await referrersOf(id, true);

	if (found === null || found.live === 0) {
		return true;
	}

	const until = action === 'trash' ? 'it\'s restored' : 'it\'s published again';
	const body  = credits
		? [
			`${name} is credited on **${plural(found.live, 'live entry', 'live entries')}**. The name comes off ${found.live === 1 ? 'it' : 'all of them'} until ${until}.${found.uncredited > 0 ? ` **${found.uncredited === found.live ? (found.live === 1 ? 'It credits' : 'They credit') : `${found.uncredited} of them credit`} no one else**, so ${found.uncredited === 1 ? 'it' : 'they'}'ll show no byline.` : ''}`
		]
		: [`${name} is linked from **${plural(found.live, 'live entry', 'live entries')}**. ${found.live === 1 ? 'It keeps' : 'They keep'} the link, but ${found.live === 1 ? 'stops' : 'stop'} showing ${name} until ${until}.`];

	return confirmAction({
		title: action === 'trash' ? `Move ${name} to Trash?` : `Move ${name} to Draft?`,
		body,
		...listed(found, found.live, 'in its Linked From'),
		confirm: action === 'trash' ? 'Move to Trash' : 'Move to Draft'
	});
}

/**
 * Asks before deleting an entry for good: with what links to it, whether
 * to take it out of them (checked at first). Resolves the query to send
 * with the delete, or `null` when it's not confirmed.
 */
export async function confirmPurge(id: string, name: string): Promise<string | null> {
	const found = await referrersOf(id);
	const title = `Delete ${name} Permanently?`;

	if (found === null || found.count === 0) {
		return await confirmAction({ title, body: 'This can\'t be undone.', confirm: 'Delete Permanently', danger: true }) ? '' : null;
	}

	const others = found.count - found.editable;
	const those  = found.count === 1 ? 'that entry' : `those ${found.count} entries`;
	const unlink = await confirmChecked({
		title,
		body: `${name} is gone for good after this. **${plural(found.count, 'entry still links', 'entries still link')} to it**${found.drafts > 0 ? `, including ${plural(found.drafts, 'draft')}` : ''}.`,
		...listed(found, found.count, 'in its Linked From'),
		check: found.editable === found.count
			? `Also remove it from ${those}`
			: `Also remove it from the ${plural(found.editable, 'entry', 'entries')} you can edit`,
		checkHelp: `Left in place, each link shows as not found in its editor and in Site Health.${others > 0 ? ` The ${plural(others, 'entry', 'entries')} you can't edit keep theirs either way.` : ''}`,
		confirm: 'Delete Permanently',
		danger: true
	});

	return unlink === null ? null : (unlink && found.editable > 0 ? '&unlink=1' : '');
}

/**
 * Asks before several entries stop being live at once (a bulk move to
 * draft or the trash), when live entries link to any of them: which, most
 * linked first, and how many each, with the unlinked ones in one quiet
 * line so the numbers add up. `titles` names the selection by id, `count`
 * says it ("5 recipes"), and `onlyLinked` shows the list's linked ones,
 * for a selection too long to list. Resolves whether to go on; with
 * nothing live linking, at once.
 */
export async function confirmLeavingMany(titles: Record<string, string>, count: string, action: 'trash' | 'draft', onlyLinked?: () => void): Promise<boolean> {
	const ids = Object.keys(titles);
	let linked: { id: string; title: string; live: number }[] = [];

	try {
		linked = (await request<{ linked: { id: string; title: string; live: number }[] }>('POST', '/entries/referrers', { ids })).linked;
	} catch {
		linked = [];
	}

	if (linked.length === 0) {
		return true;
	}

	const links    = linked.reduce((sum, item) => sum + item.live, 0);
	const shown    = linked.slice(0, 8);
	const unlinked = ids.filter((id) => !linked.some((item) => item.id === id)).map((id) => titles[id] || 'Untitled');
	const these    = ids.length === 1 ? 'it' : 'these';
	const quiet    = unlinked.length === 0 ? [] : [unlinked.length <= 3
		? `${series(unlinked)} ${unlinked.length === 1 ? 'isn\'t' : 'aren\'t'} linked from anything live.`
		: `The other ${unlinked.length} aren't linked from anything live.`];

	return confirmAction({
		title: `Move ${titleCase(count)} to ${action === 'trash' ? 'Trash' : 'Draft'}?`,
		body: `**${linked.length === ids.length ? (ids.length === 1 ? 'It is' : `All ${ids.length} are`) : `${linked.length} of the ${ids.length} are`} linked from live entries**, ${plural(links, 'link')} in all. Those entries keep the links but stop showing ${these}.`,
		items: shown.map((item) => ({ title: item.title, count: plural(item.live, 'entry', 'entries') })),
		...(linked.length > shown.length ? { more: `and ${linked.length - shown.length} more.` } : {}),
		...(linked.length > shown.length && onlyLinked ? { action: { label: `Show Only the Linked ${linked.length}`, run: onlyLinked } } : {}),
		after: quiet,
		confirm: `Move ${titleCase(count)} to ${action === 'trash' ? 'Trash' : 'Draft'}`
	});
}
