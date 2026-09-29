/**
 * Turns schema fields (D-222, D-229) into form controls and back. Each
 * field's value becomes form state (text, a checkbox's `true` or
 * `false`), and the state back into a front matter value, or `null` to
 * remove the key. Types the form can't edit yet (objects, lists of
 * objects) are shown read-only and never change.
 */

import type { FieldDescription } from './api';

export type Control = 'text' | 'textarea' | 'checkbox' | 'number' | 'select' | 'datetime' | 'lines' | 'readonly';

export type FormValue = string | boolean;

/**
 * The control a field is edited with.
 */
export function control(field: FieldDescription): Control {
	switch (field.type) {
		case 'text':
		case 'slug':
		case 'media':
		case 'reference':
			return 'text';
		case 'markdown':
			return 'textarea';
		case 'bool':
			return 'checkbox';
		case 'number':
			return 'number';
		case 'enum':
			return 'select';
		case 'date':
			return 'datetime';
		case 'list':
			return field.item !== undefined && isScalar(field.item) ? 'lines' : 'readonly';
		default:
			return 'readonly';
	}
}

function isScalar(field: FieldDescription): boolean {
	return ['text', 'slug', 'media', 'number', 'enum', 'date', 'reference'].includes(field.type);
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
 * A name for use mid-sentence: its first letter lowercase ("New post"),
 * so acronyms and proper nouns after it stay as they are.
 */
export function inSentence(name: string): string {
	return name.charAt(0).toLowerCase() + name.slice(1);
}

/**
 * Help text for a field, with how to fill in the ones that take several
 * values.
 */
export function help(field: FieldDescription): string {
	const how = control(field) === 'lines'
		? 'One per line.'
		: (field.type === 'reference' && field.multiple !== false ? 'Separate slugs with commas.' : '');

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

	switch (kind) {
		case 'datetime':
			return splitDate(value)?.local ?? '';
		case 'lines':
			return (Array.isArray(value) ? value : [value]).map(String).join('\n');
		case 'text':
			return Array.isArray(value) ? value.map(String).join(', ') : String(value);
		case 'readonly':
			return JSON.stringify(value, null, 2);
		default:
			return String(value);
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

	switch (kind) {
		case 'number': {
			const number = Number(text);

			return Number.isNaN(number) ? text : number;
		}
		case 'datetime': {
			const offset = splitDate(original)?.offset ?? '';

			return `${text.replace('T', ' ')}:00${offset === '' ? '' : ` ${offset}`}`;
		}
		case 'lines':
			return text.split('\n').map((line) => line.trim()).filter((line) => line !== '');
		case 'text':
			if (field.type === 'reference' && field.multiple !== false) {
				return text.split(',').map((slug) => slug.trim()).filter((slug) => slug !== '');
			}

			return text;
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
