/**
 * Turns schema fields (D-222, D-229) into form controls and back. The
 * server names the control each field is edited with (`control`, D-337);
 * this keeps no mapping from field types of its own. Each field's value
 * becomes form state (text, a checkbox's `true` or `false`), and the
 * state back into a front matter value, or `null` to remove the key.
 * Fields shown read-only (objects, lists of objects) never change.
 */

import type { FieldDescription } from './api';

/**
 * The controls the admin draws (`Blush\Field\Control`).
 */
export const CONTROLS = ['text', 'textarea', 'mono', 'checkbox', 'number', 'select', 'radios', 'checks', 'date', 'lines', 'reference', 'media', 'readonly'] as const;

export type Control = typeof CONTROLS[number];

export type FormValue = string | boolean;

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
	return kind === 'lines' || kind === 'checks';
}

/**
 * Whether a field's text holds several slugs, separated by commas.
 */
function holdsSlugs(field: FieldDescription, kind: Control): boolean {
	return field.type === 'reference' && field.multiple !== false && (kind === 'reference' || kind === 'mono');
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
	const how  = kind === 'lines'
		? 'One per line.'
		: (kind === 'mono' && holdsSlugs(field, kind) ? 'Separate slugs with commas.' : '');

	return [field.description ?? '', how].filter((text) => text !== '').join(' ');
}

/**
 * A front matter value as form state.
 */
export function toForm(field: FieldDescription, value: unknown): FormValue {
	const kind = control(field);

	if (kind === 'checkbox') {
		return value === true;
	}

	if (value === undefined || value === null) {
		return '';
	}

	if (holdsLines(kind)) {
		return (Array.isArray(value) ? value : [value]).map(String).join('\n');
	}

	switch (kind) {
		case 'date':
			return splitDate(value)?.local ?? '';
		case 'readonly':
			return JSON.stringify(value, null, 2);
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

	if (kind === 'textarea') {
		return state.trim() === '' ? null : state;
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
			const offset = splitDate(original)?.offset ?? '';

			return `${text.replace('T', ' ')}:00${offset === '' ? '' : ` ${offset}`}`;
		}
		default:
			return text;
	}
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
