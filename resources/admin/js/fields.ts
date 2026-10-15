/**
 * Turns schema fields (D-222, D-229) into form controls and back. The
 * server names the control each field is edited with (`control`, D-337);
 * this keeps no mapping from field types of its own. Each field's value
 * becomes form state (text, a toggle's `true` or `false`), and the
 * state back into a front matter value, or `null` to remove the key.
 * Fields shown read-only never change.
 *
 * A group's state is its fields' states, and a repeating group's is a
 * list of those, each kept as JSON in one string (`''` while nothing's
 * filled in), so a form's state stays text and true or false wherever
 * it's compared, kept, or restored (D-768).
 */

import type { FieldDescription } from './api';
import { t } from './messages';

/**
 * The controls the admin draws (`Blush\Field\ControlType`, D-766).
 */
export const CONTROLS = [
	'object', 'list', 'text', 'textarea', 'markdown', 'link', 'email', 'radios', 'select', 'checkboxes', 'multiselect',
	'toggle', 'number', 'date', 'image', 'video', 'audio', 'file', 'icon', 'code', 'reference', 'readonly'
] as const;

export type Control = typeof CONTROLS[number];

export type FormValue = string | boolean;

/**
 * A group's state: each of its fields' states, by name.
 */
export type FormGroup = Record<string, FormValue>;

/**
 * A step from a field down to one inside it: a group's field, by name, or
 * a repeating group's item, by index.
 */
export type FieldStep = string | number;

/**
 * The control a field is edited with: the one the server names, or
 * read-only for a field without one (a definition, not a form's field).
 */
export function control(field: FieldDescription): Control {
	return (CONTROLS as readonly string[]).includes(field.control ?? '') ? field.control as Control : 'readonly';
}

/**
 * Whether a control holds several values, one per line of its state.
 */
function holdsLines(kind: Control): boolean {
	return kind === 'checkboxes' || kind === 'multiselect';
}

/**
 * Whether a control picks from a set of options shown together, labeled
 * as a group.
 */
export function isChoiceGroup(kind: string): boolean {
	return kind === 'radios' || kind === 'checkboxes' || kind === 'multiselect';
}

/**
 * Whether a control picks a media file.
 */
export function isMedia(kind: Control): boolean {
	return kind === 'image' || kind === 'video' || kind === 'audio' || kind === 'file';
}

/**
 * Whether a control only shows its value.
 */
export function isShownOnly(kind: Control): boolean {
	return kind === 'readonly';
}

/**
 * Whether a control holds other fields: a group, or a repeating group.
 */
export function isContainer(kind: Control): boolean {
	return kind === 'object' || kind === 'list';
}

/**
 * The fields inside a group, or inside each of a repeating group's items.
 */
export function childrenOf(field: FieldDescription): FieldDescription[] {
	if (holdsValues(field)) {
		return [{ ...field.item!, name: ITEM_VALUE, label: field.item?.label ?? field.itemLabel ?? t('fields.item', 'Item') }];
	}

	return (control(field) === 'list' ? field.item?.fields : field.fields) ?? [];
}

// What a repeating group of plain values keeps each item's value under
// in its state.
const ITEM_VALUE = '_value';

/**
 * Whether a repeating group's items are one value each (text, a number),
 * not a group of fields (D-777): each item is then one box.
 */
export function holdsValues(field: FieldDescription): boolean {
	return control(field) === 'list' && field.item !== undefined && field.item.type !== 'object';
}

/**
 * Whether a state has nothing in it: no text, no group field filled, no
 * items. A toggle's `false` counts as something.
 */
export function isEmptyState(state: FormValue | undefined): boolean {
	return state === undefined || (typeof state === 'string' && state.trim() === '');
}

/**
 * A group's state from its form state.
 */
export function groupOf(state: FormValue | undefined): FormGroup {
	const parsed = parse(state);

	return typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed) ? parsed as FormGroup : {};
}

/**
 * A repeating group's items from its form state.
 */
export function itemsOf(state: FormValue | undefined): FormGroup[] {
	const parsed = parse(state);

	return Array.isArray(parsed) ? parsed.filter((item): item is FormGroup => typeof item === 'object' && item !== null && !Array.isArray(item)) : [];
}

/**
 * A group's form state: `''` when none of its fields has anything.
 */
export function fromGroup(group: FormGroup): FormValue {
	return Object.values(group).every(isEmptyState) ? '' : JSON.stringify(group);
}

/**
 * A repeating group's form state: `''` when it has no items. Items kept
 * empty stay, so an item just added is there to fill in.
 */
export function fromItems(items: FormGroup[]): FormValue {
	return items.length === 0 ? '' : JSON.stringify(items);
}

/**
 * The state at a path inside a field's state.
 */
export function stateAt(state: FormValue | undefined, steps: FieldStep[]): FormValue {
	let current: FormValue = state ?? '';

	for (const step of steps) {
		current = typeof step === 'number' ? JSON.stringify(itemsOf(current)[step] ?? {}) : (groupOf(current)[step] ?? '');
	}

	return current;
}

/**
 * A field's state with the state at a path inside it replaced.
 */
export function withStateAt(state: FormValue | undefined, steps: FieldStep[], value: FormValue): FormValue {
	const [step, ...rest] = steps;

	if (step === undefined) {
		return value;
	}

	if (typeof step === 'number') {
		const items = itemsOf(state);

		items[step] = groupOf(withStateAt(JSON.stringify(items[step] ?? {}), rest, value));

		return fromItems(items);
	}

	const group = groupOf(state);

	return fromGroup({ ...group, [step]: withStateAt(group[step], rest, value) });
}

function parse(state: FormValue | undefined): unknown {
	if (typeof state !== 'string' || state.trim() === '') {
		return null;
	}

	try {
		return JSON.parse(state) as unknown;
	} catch {
		return null;
	}
}

/**
 * Whether a field's text holds several slugs, separated by commas.
 */
function holdsSlugs(field: FieldDescription, kind: Control): boolean {
	return field.type === 'reference' && field.multiple !== false && (kind === 'reference' || kind === 'code');
}

/**
 * An option's words: its `choices` label, a list's item's, or the option.
 */
export function choiceLabel(field: FieldDescription, option: string): string {
	const choices = field.choices ?? field.item?.choices;

	return typeof choices === 'object' && choices !== null && option in choices ? String((choices as Record<string, unknown>)[option]) : option;
}

/**
 * A field's label: its own, or its name made readable (`literary_form`
 * → "Literary form").
 */
export function label(field: FieldDescription): string {
	return field.label ?? humanize(field.name);
}

export function humanize(name: string): string {
	const words = name.replace(/[_-]+/g, ' ').trim();

	return words.charAt(0).toUpperCase() + words.slice(1);
}

/**
 * A name for use mid-sentence: its first letter lowercase ("Choose cover
 * image"), unless its first word has another capital ("FAQ link"), as
 * the server makes type labels (D-278).
 */
export function inSentence(name: string): string {
	return /^\S+\p{Lu}/u.test(name) ? name : name.charAt(0).toLowerCase() + name.slice(1);
}

/**
 * Help text for a field, with how to fill in the ones that take several
 * values.
 */
export function help(field: FieldDescription): string {
	const kind = control(field);
	const how  = kind === 'code' && holdsSlugs(field, kind) ? t('fields.separate_slugs', 'Separate slugs with commas.') : '';

	return [field.description ?? '', how].filter((text) => text !== '').join(' ');
}

/**
 * A front matter value as form state.
 */
export function toForm(field: FieldDescription, value: unknown): FormValue {
	const kind = control(field);

	if (kind === 'toggle') {
		return value === true;
	}

	if (value === undefined || value === null) {
		return '';
	}

	if (kind === 'object') {
		const data = typeof value === 'object' && !Array.isArray(value) ? value as Record<string, unknown> : {};

		return fromGroup(Object.fromEntries(childrenOf(field).map((child) => [child.name, toForm(child, data[child.name])])));
	}

	if (kind === 'list') {
		const items = Array.isArray(value) ? value : [value];

		if (holdsValues(field)) {
			const [child] = childrenOf(field);

			return fromItems(items.map((item) => ({ [ITEM_VALUE]: toForm(child!, item) })));
		}

		return fromItems(items.map((item) => {
			const data = typeof item === 'object' && item !== null && !Array.isArray(item) ? item as Record<string, unknown> : {};

			return Object.fromEntries(childrenOf(field).map((child) => [child.name, toForm(child, data[child.name])]));
		}));
	}

	if (holdsLines(kind)) {
		return (Array.isArray(value) ? value : [value]).map(String).join('\n');
	}

	if (isShownOnly(kind)) {
		return JSON.stringify(value, null, 2);
	}

	switch (kind) {
		case 'date':
			return field.time === false ? (splitDate(value)?.local ?? '').slice(0, 10) : (splitDate(value)?.local ?? '');
		default:
			return Array.isArray(value) ? value.map(String).join(', ') : String(value);
	}
}

/**
 * Form state as a front matter value, or `null` to remove the key. A
 * date keeps the offset its old value was written with; a new one has
 * none, so the site's timezone applies.
 */
export function fromForm(field: FieldDescription, state: FormValue, original: unknown): unknown {
	const kind = control(field);

	if (typeof state === 'boolean') {
		return state;
	}

	if (kind === 'textarea' || kind === 'markdown') {
		return state.trim() === '' ? null : state;
	}

	if (kind === 'object') {
		return fromGroupState(field, groupOf(state), original);
	}

	if (kind === 'list' && holdsValues(field)) {
		const [child] = childrenOf(field);
		const values  = itemsOf(state).map((item) => fromForm(child!, item[ITEM_VALUE] ?? '', undefined)).filter((value) => value !== null);

		return values.length === 0 ? null : values;
	}

	if (kind === 'list') {
		// Items move, so none keeps keys from the one that was in its place.
		const items = itemsOf(state).map((item) => fromGroupState(field, item, undefined)).filter((item) => item !== null);

		return items.length === 0 ? null : items;
	}

	const text = state.trim();

	if (text === '') {
		return null;
	}

	if (holdsLines(kind)) {
		return text.split('\n').map((line) => line.trim()).filter((line) => line !== '');
	}

	if (holdsSlugs(field, kind)) {
		return text.split(',').map((slug) => slug.trim()).filter((slug) => slug !== '');
	}

	switch (kind) {
		case 'number': {
			const number = Number(text);

			return Number.isNaN(number) ? text : number;
		}
		case 'date': {
			if (field.time === false) {
				return text;
			}

			const offset = splitDate(original)?.offset ?? '';

			return `${text.replace('T', ' ')}:00${offset === '' ? '' : ` ${offset}`}`;
		}
		default:
			return text;
	}
}

/**
 * A group's state as a front matter map, or `null` when nothing in it is
 * filled in. Keys the group doesn't declare are kept from what it was.
 */
function fromGroupState(field: FieldDescription, group: FormGroup, original: unknown): Record<string, unknown> | null {
	const before = typeof original === 'object' && original !== null && !Array.isArray(original) ? original as Record<string, unknown> : {};
	const data: Record<string, unknown> = { ...before };

	for (const child of childrenOf(field)) {
		const value = fromForm(child, group[child.name] ?? '', before[child.name]);

		if (value === null) {
			delete data[child.name];
		} else {
			data[child.name] = value;
		}
	}

	return Object.keys(data).length === 0 ? null : data;
}

/**
 * Splits a date into the `datetime-local` form (`2026-09-29T14:30`) and
 * its offset (`-05:00`, `Z`, or none).
 */
export function splitDate(value: unknown): { local: string; offset: string } | null {
	if (typeof value === 'number') {
		return { local: new Date(value * 1000).toISOString().slice(0, 16), offset: 'Z' };
	}

	if (typeof value !== 'string') {
		return null;
	}

	const match = /^\s*(\d{4}-\d{2}-\d{2})(?:[T ](\d{2}:\d{2})(?::\d{2}(?:\.\d+)?)?)?\s*(.*)$/.exec(value);

	return match === null ? null : { local: `${match[1]}T${match[2] ?? '00:00'}`, offset: (match[3] ?? '').trim() };
}
