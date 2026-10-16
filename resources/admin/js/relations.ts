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
import { series } from './format';
import { toast } from './toast';
import { labelsOf, refreshTypes } from './types';
import { t } from './messages';
import type { IconName } from './icons';
import type { RouteLocationRaw } from 'vue-router';

export type RelationPurpose = 'classify' | 'reference' | 'credit';

// The list's tabs, by purpose.
export const purposes: { key: RelationPurpose; label: string; icon: IconName }[] = [
	{ key: 'credit', label: t('relations.purpose_credit', 'Credits'), icon: 'user' },
	{ key: 'classify', label: t('relations.purpose_classify', 'Files Under Terms'), icon: 'tag' },
	{ key: 'reference', label: t('relations.purpose_reference', 'Links'), icon: 'link' }
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
	return { data: 'user/data/relations', extension: t('relations.source_plugin', 'A plugin') }[relation.origin];
}

/**
 * A relation said from one type's side, as a sentence with `{type}` left
 * in it, so the type it names can stand out (`MessageText`): "Credits
 * {type} as Cooks", with `type` "Profiles".
 */
export function sentenceFrom(relation: RelationInfo, type: string): { text: string; type: string } {
	const label = relationLabel(relation);
	const to    = plurals(relation.to);
	const from  = relation.from.length === 0 ? t('relations.every_type_lower', 'every type') : plurals(relation.from);

	if (storedOn(relation, type)) {
		return {
			classify: { text: t('relations.from_classify', 'Filed under {type}'), type: to },
			credit: { text: t('relations.from_credit', 'Credits {type} as {label}', { label }), type: to },
			reference: { text: t('relations.from_reference', 'Links to {type} as {label}', { label }), type: to }
		}[purposeOf(relation)];
	}

	return {
		classify: { text: t('relations.to_classify', 'Files {type}'), type: from },
		credit: { text: t('relations.to_credit', 'Credited by {type} as {label}', { label }), type: from },
		reference: { text: t('relations.to_reference', 'Linked from {type} as {label}', { label }), type: from }
	}[purposeOf(relation)];
}

/**
 * A relation said whole, as its screen's title says it: "Recipes credit
 * Profiles as Cooks.", "Posts and Recipes are filed under Courses."
 */
export function sayRelation(purpose: RelationPurpose, from: string[], to: string, label: string): string {
	const one     = from.length === 0;
	const subject = one ? t('relations.every_type', 'Every type') : plurals(from);
	const target  = to === '' ? '…' : labelsOf(to).plural;
	const end     = to === '' ? '' : '.';
	const params  = { subject, every: one ? 'yes' : 'no', target, label, end };

	return {
		classify: t('relations.say_classify', '{subject} {every, select, yes {is} other {are}} filed under {target}{end}', params),
		credit: t('relations.say_credit', '{subject} {every, select, yes {credits} other {credit}} {target} as {as}{end}', { ...params, as: label || target }),
		reference: label
			? t('relations.say_reference_as', '{subject} {every, select, yes {links} other {link}} to {target} as {label}{end}', params)
			: t('relations.say_reference', '{subject} {every, select, yes {links} other {link}} to {target}{end}', params)
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
		? (min > 0 ? t('relations.takes_exactly_one', 'Exactly one') : t('relations.takes_one', 'One'))
		: (min > 0 && max !== null ? t('relations.takes_range', '{min} to {max}', { min, max }) : (min > 0 ? t('relations.takes_at_least', 'At least {min}', { min }) : (max !== null ? t('relations.takes_up_to', 'Up to {max}', { max }) : (per ? t('relations.takes_any', 'Any number') : t('relations.takes_several', 'Several')))));

	return [
		per ? t('relations.takes_per', '{base} per {per}', { base, per }) : base,
		...(relation.multiple && relation.ordered ? [t('relations.takes_ordered', 'in order')] : []),
		...(relation.create && !per ? [t('relations.takes_created', 'created as typed')] : [])
	].join(', ');
}

/**
 * Its limits as a rule: "Each recipe needs at least 1 cook and takes up
 * to 3. One profile can be named by any number of recipes."
 */
export function limitsSay(options: { item: string; singular: string; plural: string; target: string; items: string; multiple: boolean; min: number; max: number | null; inverseMax: number | null }): string {
	const { item, singular, plural: many, target, items, multiple, min, max, inverseMax } = options;
	const takes = !multiple
		? (min > 0 ? t('relations.limits_needs_one', 'needs one {singular}', { singular }) : t('relations.limits_one_at_most', 'takes one {singular} at most', { singular }))
		: (min > 0
			? (max !== null
				? t('relations.limits_at_least_up_to', 'needs at least {min, plural, one {# {singular}} other {# {plural}}} and takes up to {max}', { min, max, singular, plural: many })
				: t('relations.limits_at_least', 'needs at least {min, plural, one {# {singular}} other {# {plural}}}', { min, singular, plural: many }))
			: (max !== null ? t('relations.limits_up_to', 'takes up to {max, plural, one {# {singular}} other {# {plural}}}', { max, singular, plural: many }) : t('relations.limits_any', 'takes any number of {plural}', { plural: many })));
	const side  = inverseMax !== null ? t('relations.limits_side_at_most', 'at most {count, plural, one {# {item}} other {# {items}}}', { count: inverseMax, item, items }) : t('relations.limits_side_any', 'any number of {items}', { items });

	return t('relations.limits', 'Each {item} {takes}. One {target} can be named by {side}.', { item, takes, target, side });
}

// The entries a check names, for a confirmation's list.
function listed(items: RelationCheckItem[], one: string, many: string, mixed: boolean): ConfirmItem[] {
	return items.map((item) => ({ title: item.title, meta: mixed ? labelsOf(item.type).singular : undefined, count: t('relations.listed_count', '{count, plural, one {# {singular}} other {# {plural}}}', { count: item.count, singular: one, plural: many }) }));
}

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
		label: t('relations.show_all', 'Show All {count} in {types}', { count, types: names.plural }),
		run: () => context.go({ name: 'type', params: { type }, query: { over: context.name, above: String(above) } })
	});

	if (check.refused !== null) {
		const several = check.refused === 'one';

		await confirmAction({
			title: several ? t('relations.refused_one_title', '{label} Can\'t Take Exactly One Yet', { label: context.label }) : t('relations.refused_type_title', '{label} Can\'t Point at Another Type Yet', { label: context.label }),
			body: several
				? t('relations.refused_one_body', '**{count, plural, one {# {item}} other {# {items}}}** {count, plural, one {has} other {have}} more than one {singular}. Take them down to one each, or keep {label} as several.', { count: check.inWayCount, item: names.item, items: names.items, singular: one, label: context.label })
				: t('relations.refused_type_body', '**{count, plural, one {# {item}} other {# {items}}}** {count, plural, one {has} other {have}} {plural} already. Make a new relationship for the other type, or keep this one pointing at {target}.', { count: check.inWayCount, item: names.item, items: names.items, plural: many, target: labelsOf(context.target).plural }),
			items: listed(check.inWay, one, many, mixed),
			more: check.inWayCount > check.inWay.length ? t('relations.more', 'and {count} more', { count: check.inWayCount - check.inWay.length }) : undefined,
			action: type === '' || check.inWayCount <= check.inWay.length ? undefined : showAll(check.inWayCount, several ? 1 : 0),
			confirm: several ? t('relations.keep_several', 'Keep as Several') : t('relations.keep_target', 'Keep {target}', { target: labelsOf(context.target).plural }),
			alone: true
		});

		return 'keep';
	}

	let rewrite = false;
	let strip   = false;

	if (check.moved > 0) {
		const answer = await confirmChecked({
			title: t('relations.key_title', 'Change the Key From {from} to {to}?', { from: context.field, to: context.newField }),
			body: t('relations.key_body', '**{count, plural, one {# file} other {# files}}** use “{from}”. Saving keeps it working as an alias: {count, plural, one {that file is} other {those files are}} read as before, and new saves write “{to}”.', { count: check.moved, from: context.field, to: context.newField }),
			check: t('relations.key_rewrite', '{count, plural, one {Rewrite the file now} other {Rewrite all # files now}}', { count: check.moved }),
			checkHelp: t('relations.key_rewrite_help', 'Changes the key in each file and drops the alias. Each file\'s history shows the change.'),
			checked: false,
			confirm: t('relations.key_confirm', 'Change Key')
		});

		if (answer === null) {
			return null;
		}

		rewrite = answer;
	}

	if (check.stripped > 0) {
		const unfiled = plurals(check.unfiled);
		const single  = check.unfiled.length === 1 ? labelsOf(check.unfiled[0] ?? '') : null;
		const answer  = await confirmChecked({
			title: t('relations.unfile_title', 'Stop Filing {types} Under {label}?', { types: unfiled, label: context.label }),
			body: t('relations.unfile_body', '**{count, plural, one {# {item}} other {# {items}}}** {count, plural, one {has} other {have}} {plural}. They stop showing in {singular} lists. Their {plural} stay in their files, unused, unless you remove them.', { count: check.stripped, item: single?.item ?? t('relations.entry', 'entry'), items: single?.items ?? t('relations.entries', 'entries'), plural: many, singular: one }),
			check: t('relations.unfile_strip', 'Remove {plural} from {count, plural, one {that entry} other {those # entries}}', { plural: many, count: check.stripped }),
			checkHelp: t('relations.unfile_strip_help', 'Takes the key out of each file. Each file\'s history shows the change.'),
			checked: false,
			confirm: t('relations.unfile_confirm', 'Save {label}', { label: context.label })
		});

		if (answer === null) {
			return null;
		}

		strip = answer;
	}

	if (check.overCount > 0 || check.fewer > 0 || check.inverseOver > 0) {
		const after = [
			...(check.fewer > 0 ? [t('relations.limits_fewer', '**{count, plural, one {# published {item}} other {# published {items}}}** {count, plural, one {has} other {have}} fewer than {min, plural, one {# {singular}} other {# {plural}}}, so {count, plural, one {it can\'t be published again until it has} other {they can\'t be published again until they have}} more.', { count: check.fewer, item: names.item, items: names.items, min: context.newMin, singular: one, plural: many })] : []),
			...(check.inverseOver > 0 ? [t('relations.limits_inverse_over', '**{count, plural, one {# {target}} other {# {targets}}}** {count, plural, one {is} other {are}} named by more than {max, plural, one {# {item}} other {# {items}}}; lint reports them.', { count: check.inverseOver, target: labelsOf(context.target).item, targets: labelsOf(context.target).items, max: context.inverseMax, item: names.item, items: names.items })] : []),
			t('relations.limits_no_files', 'Nothing in any file changes.')
		];
		const confirmed = await confirmAction({
			title: check.overCount > 0 ? t('relations.limits_over_title', '{count, plural, one {# {singular} Goes} other {# {plural} Go}} Over the New Limit', { count: check.overCount, singular: names.singular, plural: names.plural }) : t('relations.limits_title', 'Save the Tighter Limits?'),
			body: check.overCount > 0
				? t('relations.limits_over_body', '{label} drops from {from} to {max}. **{count, plural, one {# {item}} other {# {items}}}** {count, plural, one {has} other {have}} more than {max}; {count, plural, one {it keeps them, and can\'t be published again until it\'s} other {they keep them, and can\'t be published again until they\'re}} down to {max}.', { label: context.label, from: context.oldMax ?? t('relations.limits_any_number', 'any number'), max: context.newMax, count: check.overCount, item: names.item, items: names.items })
				: undefined,
			items: check.overCount > 0 ? listed(check.over, one, many, mixed) : undefined,
			more: check.overCount > check.over.length ? t('relations.more', 'and {count} more', { count: check.overCount - check.over.length }) : undefined,
			action: check.overCount > check.over.length && type !== '' ? showAll(check.overCount, context.newMax ?? 0) : undefined,
			after,
			confirm: t('relations.limits_confirm', 'Save Limits')
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
	const picker  = t('relations.remove_picker', 'The picker leaves the editor, and {what} stop showing on the site.', { what: relation.label ? label.toLowerCase() : t('relations.remove_the_links', 'the links') });

	const strip = entries === 0
		? (await confirmAction({ title: t('relations.remove_title', 'Remove {label}?', { label }), body: [t('relations.remove_none', 'No entry has {label} yet.', { label: label.toLowerCase() }), picker], confirm: t('relations.remove_confirm', 'Remove Relationship'), danger: true }) ? false : null)
		: await confirmChecked({
			title: t('relations.remove_title', 'Remove {label}?', { label }),
			body: t('relations.remove_body', '**{count, plural, one {# {item}} other {# {items}}}** {count, plural, one {has} other {have}} {label}. {picker}', { count: entries, item: type?.item ?? t('relations.entry', 'entry'), items: type?.items ?? t('relations.entries', 'entries'), label: label.toLowerCase(), picker }),
			check: t('relations.remove_strip', 'Also strip {field} from {count, plural, one {its file} other {the # files}}', { field: relation.field, count: entries }),
			checkHelp: t('relations.remove_strip_help', 'Left in place, the key is ignored, and comes back if the relationship is added again.'),
			checked: false,
			confirm: t('relations.remove_confirm', 'Remove Relationship'),
			danger: true
		});

	if (strip === null) {
		return false;
	}

	const answer = await request<{ deleted: string; stripped: number }>('DELETE', `/relations/${name}${strip ? '?strip=1' : ''}`);

	refreshTypes();
	toast(answer.stripped > 0 ? t('relations.removed_stripped', 'Removed {label}, and its values from {count, plural, one {# entry} other {# entries}}', { label, count: answer.stripped }) : t('relations.removed', 'Removed {label}', { label }), { kind: 'danger' });

	return true;
}
