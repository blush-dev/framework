/**
 * Relationships in the admin (D-593, D-610): what the Relationships list,
 * a relationship's own screen, and a type's Relationships panel share.
 *
 * - How a relationship is said: by its label (`relationLabel()`), as a
 *   sentence from one type's side (`sentenceFrom()`, "Credits **Profiles**
 *   as Cooks"), as a sentence about the whole of it (`sayRelation()`,
 *   "Recipes credit Profiles as Cooks."), and by what it takes
 *   (`takes()`, "At least 1, in order").
 * - Where it's stored: on the types whose files carry its key (its
 *   `from`, every type when that's empty; `storedOn()`), and where it's
 *   defined (`sourceOf()`).
 * - Changing one over entries that use it (`confirmRelationChange()`),
 *   in the five shapes the pickers sketch draws: refused, with the
 *   entries in the way and one button that keeps the setting; a new key,
 *   kept as an alias unless the files are rewritten; a type no longer
 *   filed, its values kept unless they're removed; tighter limits,
 *   counted; and removing (`removeRelation()`), its values kept unless
 *   they're stripped. The default is always the choice that touches no
 *   files, and every outcome is counted before it happens.
 */

import { request, type RelationCheck, type RelationCheckItem, type RelationInfo } from './api';
import { confirmAction, confirmChecked, type ConfirmItem } from './confirm';
import { humanize } from './fields';
import { plural, series } from './format';
import { toast } from './toast';
import { labelsOf, refreshTypes } from './types';
import type { IconName } from './icons';
import type { RouteLocationRaw } from 'vue-router';

export type RelationPurpose = 'classify' | 'reference' | 'credit';

// The list's tabs, by purpose.
export const purposes: { key: RelationPurpose; label: string; icon: IconName }[] = [
	{ key: 'credit', label: 'Credits', icon: 'user' },
	{ key: 'classify', label: 'Files Under Terms', icon: 'tag' },
	{ key: 'reference', label: 'Links', icon: 'link' }
];

/**
 * A relation's purpose: the parent and translation kinds are links.
 */
export function purposeOf(relation: Pick<RelationInfo, 'kind'>): RelationPurpose {
	return relation.kind === 'classify' || relation.kind === 'credit' ? relation.kind : 'reference';
}

export function purposeIcon(relation: Pick<RelationInfo, 'kind'>): IconName {
	return purposes.find((item) => item.key === purposeOf(relation))?.icon ?? 'link';
}

/**
 * What people call it: its label, else its name.
 */
export function relationLabel(relation: Pick<RelationInfo, 'label' | 'name'>): string {
	return relation.label || humanize(relation.name);
}

const plurals = (names: string[]): string => series(names.map((name) => labelsOf(name).plural));

/**
 * Whether a relation is stored on a type: its files carry the key. One
 * from every type is stored on each but its targets'.
 */
export function storedOn(relation: Pick<RelationInfo, 'from' | 'to'>, type: string): boolean {
	return relation.from.includes(type) || (relation.from.length === 0 && !relation.to.includes(type));
}

/**
 * Where a relation is defined, for people.
 */
export function sourceOf(relation: Pick<RelationInfo, 'origin'>): string {
	return { data: 'user/data/relations', config: 'config/content.php', extension: 'A plugin' }[relation.origin];
}

/**
 * A relation said from one type's side, in three parts so the type it
 * names can stand out: "Credits", "Profiles", "as Cooks".
 */
export function sentenceFrom(relation: RelationInfo, type: string): { before: string; type: string; after: string } {
	const label = relationLabel(relation);
	const to    = plurals(relation.to);
	const from  = relation.from.length === 0 ? 'every type' : plurals(relation.from);

	if (storedOn(relation, type)) {
		return {
			classify: { before: 'Filed under', type: to, after: '' },
			credit: { before: 'Credits', type: to, after: `as ${label}` },
			reference: { before: 'Links to', type: to, after: `as ${label}` }
		}[purposeOf(relation)];
	}

	return {
		classify: { before: 'Files', type: from, after: '' },
		credit: { before: 'Credited by', type: from, after: `as ${label}` },
		reference: { before: 'Linked from', type: from, after: `as ${label}` }
	}[purposeOf(relation)];
}

/**
 * A relation said whole, as its screen's title says it: "Recipes credit
 * Profiles as Cooks.", "Posts and Recipes are filed under Courses."
 */
export function sayRelation(purpose: RelationPurpose, from: string[], to: string, label: string): string {
	const one     = from.length === 0;
	const subject = one ? 'Every type' : plurals(from);
	const target  = to === '' ? '…' : labelsOf(to).plural;
	const end     = to === '' ? '' : '.';

	return {
		classify: `${subject} ${one ? 'is' : 'are'} filed under ${target}${end}`,
		credit: `${subject} ${one ? 'credits' : 'credit'} ${target} as ${label || target}${end}`,
		reference: `${subject} ${one ? 'links' : 'link'} to ${target}${label ? ` as ${label}` : ''}${end}`
	}[purpose];
}

/**
 * What a relation takes, from the side that stores it: "One",
 * "Several, created as typed", "At least 1, in order", "Up to 3", or per
 * entry of the type storing it, read from the other side ("Up to 12 per
 * collection").
 */
export function takes(relation: Pick<RelationInfo, 'multiple' | 'ordered' | 'min' | 'max' | 'create'>, per?: string): string {
	const { min, max } = relation;
	const base = !relation.multiple
		? (min > 0 ? 'Exactly one' : 'One')
		: (min > 0 && max !== null ? `${min} to ${max}` : (min > 0 ? `At least ${min}` : (max !== null ? `Up to ${max}` : (per ? 'Any number' : 'Several'))));

	return [
		per ? `${base} per ${per}` : base,
		...(relation.multiple && relation.ordered ? ['in order'] : []),
		...(relation.create && !per ? ['created as typed'] : [])
	].join(', ');
}

/**
 * Its limits as a rule: "Each recipe needs at least 1 cook and takes up
 * to 3. One profile can be named by any number of recipes."
 */
export function limitsSay(options: { item: string; singular: string; plural: string; target: string; items: string; multiple: boolean; min: number; max: number | null; inverseMax: number | null }): string {
	const { item, singular, plural: many, target, items, multiple, min, max, inverseMax } = options;
	const noun  = (count: number): string => count === 1 ? singular : many;
	const takes = !multiple
		? (min > 0 ? `needs one ${singular}` : `takes one ${singular} at most`)
		: (min > 0
			? `needs at least ${min} ${noun(min)}${max !== null ? ` and takes up to ${max}` : ''}`
			: (max !== null ? `takes up to ${max} ${noun(max)}` : `takes any number of ${many}`));
	const side  = inverseMax !== null ? `at most ${inverseMax} ${inverseMax === 1 ? item : items}` : `any number of ${items}`;

	return `Each ${item} ${takes}. One ${target} can be named by ${side}.`;
}

/**
 * "a" or "an", by how a word starts.
 */
export function article(word: string): string {
	return /^[aeiou]/i.test(word) ? 'an' : 'a';
}

// The entries a check names, for a confirmation's list.
function listed(items: RelationCheckItem[], one: string, many: string, mixed: boolean): ConfirmItem[] {
	return items.map((item) => ({ title: item.title, meta: mixed ? labelsOf(item.type).singular : undefined, count: plural(item.count, one, many) }));
}

const counted = (count: number): string => count.toLocaleString();

/**
 * What a change needs to know beyond the check: the relation as it was
 * and as it would be, and where to show a list of entries.
 */
export interface RelationChangeContext {
	name: string;
	label: string;
	singular: string;
	// The keys before and after.
	field: string;
	newField: string;
	// The types storing it before, and its `max` and inverse `max` before
	// and after.
	from: string[];
	oldMax: number | null;
	newMax: number | null;
	newMin: number;
	inverseMax: number | null;
	target: string;
	go: (to: RouteLocationRaw) => void;
}

/**
 * Asks about a change, in the shape of what it does (D-610). Resolves
 * `keep` when it's refused (the setting goes back as it was), `null` when
 * it isn't confirmed, else the options to send.
 */
export async function confirmRelationChange(check: RelationCheck, context: RelationChangeContext): Promise<{ rewrite: boolean; strip: boolean } | 'keep' | null> {
	const one   = context.singular.toLowerCase();
	const many  = context.label.toLowerCase();
	const type  = check.inWay[0]?.type ?? context.from[0] ?? '';
	const names = labelsOf(type);
	const mixed = new Set([...check.inWay, ...check.over].map((item) => item.type)).size > 1;

	// The list of a type's entries over a number, in the way.
	const showAll = (count: number, above: number): { label: string; run: () => void } => ({
		label: `Show All ${counted(count)} in ${names.plural}`,
		run: () => context.go({ name: 'type', params: { type }, query: { over: context.name, above: String(above) } })
	});

	if (check.refused !== null) {
		const several = check.refused === 'one';

		await confirmAction({
			title: several ? `${context.label} Can't Take Exactly One Yet` : `${context.label} Can't Point at Another Type Yet`,
			body: several
				? `**${counted(check.inWayCount)} ${check.inWayCount === 1 ? names.item : names.items}** ${check.inWayCount === 1 ? 'has' : 'have'} more than one ${one}. Take them down to one each, or keep ${context.label} as several.`
				: `**${counted(check.inWayCount)} ${check.inWayCount === 1 ? names.item : names.items}** ${check.inWayCount === 1 ? 'has' : 'have'} ${many} already. Make a new relationship for the other type, or keep this one pointing at ${labelsOf(context.target).plural}.`,
			items: listed(check.inWay, one, many, mixed),
			more: check.inWayCount > check.inWay.length ? `and ${counted(check.inWayCount - check.inWay.length)} more` : undefined,
			action: type === '' || check.inWayCount <= check.inWay.length ? undefined : showAll(check.inWayCount, several ? 1 : 0),
			confirm: several ? 'Keep as Several' : `Keep ${labelsOf(context.target).plural}`,
			alone: true
		});

		return 'keep';
	}

	let rewrite = false;
	let strip   = false;

	if (check.moved > 0) {
		const files = check.moved === 1 ? '1 file' : `${counted(check.moved)} files`;
		const answer = await confirmChecked({
			title: `Change the Key From ${context.field} to ${context.newField}?`,
			body: `**${files}** use “${context.field}”. Saving keeps it working as an alias: ${check.moved === 1 ? 'that file is' : 'those files are'} read as before, and new saves write “${context.newField}”.`,
			check: check.moved === 1 ? 'Rewrite the file now' : `Rewrite all ${counted(check.moved)} files now`,
			checkHelp: 'Changes the key in each file and drops the alias. Each file\'s history shows the change.',
			checked: false,
			confirm: 'Change Key'
		});

		if (answer === null) {
			return null;
		}

		rewrite = answer;
	}

	if (check.stripped > 0) {
		const unfiled = plurals(check.unfiled);
		const entries = check.stripped === 1 ? `1 ${check.unfiled.length === 1 ? labelsOf(check.unfiled[0] ?? '').item : 'entry'}` : `${counted(check.stripped)} ${check.unfiled.length === 1 ? labelsOf(check.unfiled[0] ?? '').items : 'entries'}`;
		const answer  = await confirmChecked({
			title: `Stop Filing ${unfiled} Under ${context.label}?`,
			body: `**${entries}** ${check.stripped === 1 ? 'has' : 'have'} ${many}. They stop showing in ${one} lists. Their ${many} stay in their files, unused, unless you remove them.`,
			check: `Remove ${many} from ${check.stripped === 1 ? 'that entry' : `those ${counted(check.stripped)} entries`}`,
			checkHelp: 'Takes the key out of each file. Each file\'s history shows the change.',
			checked: false,
			confirm: `Save ${context.label}`
		});

		if (answer === null) {
			return null;
		}

		strip = answer;
	}

	if (check.overCount > 0 || check.fewer > 0 || check.inverseOver > 0) {
		const after = [
			...(check.fewer > 0 ? [`**${plural(check.fewer, `published ${names.item}`, `published ${names.items}`)}** ${check.fewer === 1 ? 'has' : 'have'} fewer than ${context.newMin} ${context.newMin === 1 ? one : many}, so ${check.fewer === 1 ? 'it' : 'they'} can't be published again until ${check.fewer === 1 ? 'it has' : 'they have'} more.`] : []),
			...(check.inverseOver > 0 ? [`**${plural(check.inverseOver, labelsOf(context.target).item, labelsOf(context.target).items)}** ${check.inverseOver === 1 ? 'is' : 'are'} named by more than ${context.inverseMax} ${context.inverseMax === 1 ? names.item : names.items}; lint reports them.`] : []),
			'Nothing in any file changes.'
		];
		const confirmed = await confirmAction({
			title: check.overCount > 0 ? `${counted(check.overCount)} ${check.overCount === 1 ? names.singular : names.plural} Go${check.overCount === 1 ? 'es' : ''} Over the New Limit` : 'Save the Tighter Limits?',
			body: check.overCount > 0
				? `${context.label} drops from ${context.oldMax ?? 'any number'} to ${context.newMax}. **${counted(check.overCount)} ${check.overCount === 1 ? names.item : names.items}** ${check.overCount === 1 ? 'has' : 'have'} more than ${context.newMax}; ${check.overCount === 1 ? 'it keeps them, and can\'t' : 'they keep them, and can\'t'} be published again until ${check.overCount === 1 ? 'it\'s' : 'they\'re'} down to ${context.newMax}.`
				: undefined,
			items: check.overCount > 0 ? listed(check.over, one, many, mixed) : undefined,
			more: check.overCount > check.over.length ? `and ${counted(check.overCount - check.over.length)} more` : undefined,
			action: check.overCount > check.over.length && type !== '' ? showAll(check.overCount, context.newMax ?? 0) : undefined,
			after,
			confirm: 'Save Limits'
		});

		if (!confirmed) {
			return null;
		}
	}

	return { rewrite, strip };
}

/**
 * Removes a relationship, asking first (D-600, D-610): the entries with
 * values in it keep them unless they're stripped. Resolves whether it was
 * removed.
 */
export async function removeRelation(relation: RelationInfo): Promise<boolean> {
	const name    = encodeURIComponent(relation.name);
	const label   = relationLabel(relation);
	const type    = relation.from.length === 1 ? labelsOf(relation.from[0] ?? '') : null;
	const entries = await request<{ entries: number }>('GET', `/relations/${name}/uses`).then((answer) => answer.entries, () => 0);
	const who     = entries === 1 ? `1 ${type?.item ?? 'entry'}` : `${counted(entries)} ${type?.items ?? 'entries'}`;
	const picker  = `The picker leaves the editor, and ${relation.label ? label.toLowerCase() : 'the links'} stop showing on the site.`;

	const strip = entries === 0
		? (await confirmAction({ title: `Remove ${label}?`, body: [`No entry has ${label.toLowerCase()} yet.`, picker], confirm: 'Remove Relationship', danger: true }) ? false : null)
		: await confirmChecked({
			title: `Remove ${label}?`,
			body: `**${who}** ${entries === 1 ? 'has' : 'have'} ${label.toLowerCase()}. ${picker}`,
			check: `Also strip ${relation.field} from ${entries === 1 ? 'its file' : `the ${counted(entries)} files`}`,
			checkHelp: 'Left in place, the key is ignored, and comes back if the relationship is added again.',
			checked: false,
			confirm: 'Remove Relationship',
			danger: true
		});

	if (strip === null) {
		return false;
	}

	const answer = await request<{ deleted: string; stripped: number }>('DELETE', `/relations/${name}${strip ? '?strip=1' : ''}`);

	refreshTypes();
	toast(answer.stripped > 0 ? `Removed ${label}, and its values from ${plural(answer.stripped, 'entry', 'entries')}` : `Removed ${label}`, { kind: 'danger' });

	return true;
}
