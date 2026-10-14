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
import { series, titleCase } from './format';
import { t } from './messages';

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

	return more > 0 ? { items, more: t('referrers.and_more_rest', 'and {count} more, {rest}.', { count: more, rest }) } : { items };
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

	const params = { name, action, count: found.live, uncredited: found.uncredited };
	const alone  = found.uncredited === 0
		? ''
		: ` ${found.uncredited === found.live
			? t('referrers.all_uncredited', '**{count, plural, one {It credits} other {They credit}} no one else**, so {uncredited, plural, one {it\'ll} other {they\'ll}} show no byline.', params)
			: t('referrers.some_uncredited', '**{uncredited} of them credit no one else**, so {uncredited, plural, one {it\'ll} other {they\'ll}} show no byline.', params)}`;
	const body   = credits
		? [t('referrers.credited_on', '{name} is credited on **{count, plural, one {# live entry} other {# live entries}}**. The name comes off {count, plural, one {it} other {all of them}} until {action, select, trash {it\'s restored} other {it\'s published again}}.', params) + alone]
		: [t('referrers.linked_from', '{name} is linked from **{count, plural, one {# live entry} other {# live entries}}**. {count, plural, one {It keeps the link, but stops} other {They keep the link, but stop}} showing {name} until {action, select, trash {it\'s restored} other {it\'s published again}}.', params)];

	return confirmAction({
		title: action === 'trash' ? t('referrers.move_to_trash_title', 'Move {name} to Trash?', { name }) : t('referrers.move_to_draft_title', 'Move {name} to Draft?', { name }),
		body,
		...listed(found, found.live, t('referrers.in_linked_from', 'in its Linked From')),
		confirm: action === 'trash' ? t('referrers.move_to_trash', 'Move to Trash') : t('referrers.move_to_draft', 'Move to Draft')
	});
}

/**
 * Asks before deleting an entry for good: with what links to it, whether
 * to take it out of them (checked at first). Resolves the query to send
 * with the delete, or `null` when it's not confirmed.
 */
export async function confirmPurge(id: string, name: string): Promise<string | null> {
	const found = await referrersOf(id);
	const title = t('referrers.delete_title', 'Delete {name} Permanently?', { name });

	if (found === null || found.count === 0) {
		return await confirmAction({ title, body: t('referrers.cant_be_undone', 'This can\'t be undone.'), confirm: t('referrers.delete_permanently', 'Delete Permanently'), danger: true }) ? '' : null;
	}

	const others = found.count - found.editable;
	const unlink = await confirmChecked({
		title,
		body: found.drafts > 0
			? t('referrers.gone_with_drafts', '{name} is gone for good after this. **{count, plural, one {# entry still links} other {# entries still link}} to it**, including {drafts, plural, one {# draft} other {# drafts}}.', { name, count: found.count, drafts: found.drafts })
			: t('referrers.gone', '{name} is gone for good after this. **{count, plural, one {# entry still links} other {# entries still link}} to it**.', { name, count: found.count }),
		...listed(found, found.count, t('referrers.in_linked_from', 'in its Linked From')),
		check: found.editable === found.count
			? t('referrers.also_remove_all', 'Also remove it from {count, plural, one {that entry} other {those # entries}}', { count: found.count })
			: t('referrers.also_remove_editable', 'Also remove it from the {count, plural, one {# entry} other {# entries}} you can edit', { count: found.editable }),
		checkHelp: t('referrers.left_in_place', 'Left in place, each link shows as not found in its editor and in Site Health.')
			+ (others > 0 ? ` ${t('referrers.others_keep', 'The {count, plural, one {# entry} other {# entries}} you can\'t edit keep theirs either way.', { count: others })}` : ''),
		confirm: t('referrers.delete_permanently', 'Delete Permanently'),
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
	const unlinked = ids.filter((id) => !linked.some((item) => item.id === id)).map((id) => titles[id] || t('referrers.untitled', 'Untitled'));
	const quiet    = unlinked.length === 0 ? [] : [unlinked.length <= 3
		? t('referrers.names_not_linked', '{names} {count, plural, one {isn\'t} other {aren\'t}} linked from anything live.', { names: series(unlinked), count: unlinked.length })
		: t('referrers.others_not_linked', 'The other {count} aren\'t linked from anything live.', { count: unlinked.length })];
	const what     = titleCase(count);
	const params   = { count: ids.length, linked: linked.length, links };

	return confirmAction({
		title: action === 'trash' ? t('referrers.move_many_to_trash_title', 'Move {what} to Trash?', { what }) : t('referrers.move_many_to_draft_title', 'Move {what} to Draft?', { what }),
		body: linked.length === ids.length
			? t('referrers.all_linked', '**{count, plural, one {It is} other {All # are}} linked from live entries**, {links, plural, one {# link} other {# links}} in all. Those entries keep the links but stop showing {count, plural, one {it} other {these}}.', params)
			: t('referrers.some_linked', '**{linked} of the {count} are linked from live entries**, {links, plural, one {# link} other {# links}} in all. Those entries keep the links but stop showing {count, plural, one {it} other {these}}.', params),
		items: shown.map((item) => ({ title: item.title, count: t('referrers.entry_count', '{count, plural, one {# entry} other {# entries}}', { count: item.live }) })),
		...(linked.length > shown.length ? { more: t('referrers.and_more', 'and {count} more.', { count: linked.length - shown.length }) } : {}),
		...(linked.length > shown.length && onlyLinked ? { action: { label: t('referrers.show_only_linked', 'Show Only the Linked {count}', { count: linked.length }), run: onlyLinked } } : {}),
		after: quiet,
		confirm: action === 'trash' ? t('referrers.move_many_to_trash', 'Move {what} to Trash', { what }) : t('referrers.move_many_to_draft', 'Move {what} to Draft', { what })
	});
}
