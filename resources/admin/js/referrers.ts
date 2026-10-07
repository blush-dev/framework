/**
 * What links to an entry (D-598), asked before it leaves the site: a
 * move to draft or to the trash says how many live entries link to it,
 * since the site stops showing it there, and deleting it for good asks
 * whether to take it out of them first (`DELETE …?permanently=1&unlink=1`),
 * so nothing names it after.
 */

import { entryPath, request, type EntryStatus } from './api';
import { confirmAction, confirmChecked } from './confirm';
import { plural } from './format';

export interface EntryReferrers {
	// How many entries link to it, how many are live, and how many the
	// account may edit.
	count: number;
	live: number;
	editable: number;
	// The first few, by title.
	entries: { id: string | null; title: string; type: string; status: EntryStatus }[];
}

/**
 * Loads what links to an entry, or `null` when it can't be told.
 */
export async function referrersOf(id: string): Promise<EntryReferrers | null> {
	try {
		return await request<EntryReferrers>('GET', `${entryPath(id)}/referrers`);
	} catch {
		return null;
	}
}

// A few titles, and how many more.
function some(referrers: EntryReferrers, total: number, live: boolean): string {
	const shown = referrers.entries.filter((item) => !live || item.status === 'published').map((item) => `“${item.title || 'Untitled'}”`);
	const more  = total - shown.length;

	return shown.length === 0 ? '' : `, such as ${shown.join(', ')}${more > 0 ? `, and ${more} more` : ''}`;
}

/**
 * Asks before an entry stops being live, when live entries link to it:
 * moving it to the trash, or to draft. `always` asks about the trash
 * even when nothing does, as the editor does. Resolves whether to go on.
 */
export async function confirmLeaving(id: string, name: string, action: 'trash' | 'draft', always = false): Promise<boolean> {
	const found  = await referrersOf(id);
	const linked = found !== null && found.live > 0
		? [
			`**${plural(found.live, 'live entry links', 'live entries link')} to it**${some(found, found.live, true)}.`,
			action === 'trash'
				? 'The site stops showing it there. Restoring and publishing it brings the links back.'
				: 'The site stops showing it there until it\'s published again.'
		]
		: [];

	if (linked.length === 0 && !always) {
		return true;
	}

	return confirmAction(action === 'trash'
		? { title: `Move ${name} to the Trash?`, body: [...linked, 'You can restore it from the Trash tab.'], confirm: 'Move to Trash', danger: true }
		: { title: `Switch ${name} to Draft?`, body: linked, confirm: 'Switch to Draft' });
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
	const unlink = await confirmChecked({
		title,
		body: [
			`**${plural(found.count, 'entry links', 'entries link')} to it**${some(found, found.count, false)}.`,
			`Left as they are, they'll name something that no longer exists.${others > 0 ? ` The ${plural(others, 'entry', 'entries')} you can't edit will either way.` : ''} This can't be undone.`
		],
		check: found.editable === found.count
			? `Remove it from ${found.count === 1 ? 'that entry' : `those ${found.count} entries`}`
			: `Remove it from the ${plural(found.editable, 'entry', 'entries')} you can edit`,
		confirm: 'Delete Permanently',
		danger: true
	});

	return unlink === null ? null : (unlink && found.editable > 0 ? '&unlink=1' : '');
}
