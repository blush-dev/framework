/**
 * The controls fields are entered with (D-766), as the catalog describes
 * them (`GET fields/controls`, loaded by `field-types.ts`): the ones the
 * picker offers by section, the ones a saved field may switch to (the
 * same shape of value, D-768), each one's tile icon, and how a field's
 * definition changes when its control does.
 */

import { computed } from 'vue';
import type { CatalogControl, FieldDescription } from './api';
import { catalog } from './field-types';
import type { IconName } from './icons';

// Each core control's tile, from the admin's icons.
const ICONS: Record<string, IconName> = {
	object: 'group',
	list: 'rows-3',
	text: 'type',
	textarea: 'text',
	markdown: 'pilcrow',
	link: 'link',
	email: 'at-sign',
	select: 'list',
	radios: 'circle-dot',
	checkboxes: 'list-checks',
	multiselect: 'tag',
	toggle: 'toggle-right',
	number: 'hash',
	date: 'calendar',
	image: 'image',
	video: 'video',
	audio: 'headphones',
	file: 'file',
	icon: 'shapes',
	code: 'code',
	reference: 'link',
	readonly: 'lock'
};

// The controls that hold their options on the field (a choice), and on
// its item (several choices).
const ONE_CHOICE  = ['select', 'radios'];
const MANY_CHOICE = ['checkboxes', 'multiselect'];

// The controls that put a box in front of someone, and so take a
// placeholder.
const BOXES = ['text', 'textarea', 'markdown', 'link', 'email', 'number'];

/**
 * A control's tile icon.
 */
export function controlIcon(key: string): IconName {
	return ICONS[key] ?? 'sliders-horizontal';
}

/**
 * A control as the catalog describes it.
 */
export function catalogControl(key: string): CatalogControl | undefined {
	return catalog.value?.controls.find((control) => control.value === key);
}

/**
 * A control's name for people: "Radio Buttons".
 */
export function controlLabel(key: string): string {
	return catalogControl(key)?.label ?? key;
}

/**
 * The controls the picker offers, by section, in the catalog's order.
 */
export const offered = computed(() => {
	const sections = new Map<string, { value: string; label: string; controls: CatalogControl[] }>();

	for (const control of catalog.value?.controls ?? []) {
		if (control.section === null) {
			continue;
		}

		const section = sections.get(control.section.value) ?? { ...control.section, controls: [] };

		section.controls.push(control);
		sections.set(control.section.value, section);
	}

	return [...sections.values()];
});

/**
 * The controls a saved field with a control may switch to: the offered
 * ones of the same shape (D-768).
 */
export function switchesTo(key: string): string[] {
	const shape = catalogControl(key)?.shape;

	return (catalog.value?.controls ?? []).filter((control) => control.section !== null && control.shape === shape).map((control) => control.value);
}

/**
 * Whether a control holds other fields: an Object, or a List (D-858).
 */
export function holdsFields(key: string): boolean {
	return key === 'object' || key === 'list';
}

export function isChoice(key: string): boolean {
	return ONE_CHOICE.includes(key) || MANY_CHOICE.includes(key);
}

export function isManyChoice(key: string): boolean {
	return MANY_CHOICE.includes(key);
}

export function takesPlaceholder(key: string): boolean {
	return BOXES.includes(key);
}

/**
 * A choice field's options, each with its label: from a choice's own
 * `options` (with `choices` labels), or a list's item's.
 */
export function optionsOf(field: FieldDescription): { value: string; label: string }[] {
	const holder  = isManyChoice(field.control ?? '') ? field.item : field;
	const choices = (holder?.choices ?? {}) as Record<string, unknown>;

	return (holder?.options ?? []).map((option) => {
		const value = typeof option === 'string' ? option : String((option as { value?: unknown }).value ?? '');
		const label = typeof option === 'object' && option !== null && typeof (option as { label?: unknown }).label === 'string'
			? (option as { label: string }).label
			: (typeof choices[value] === 'string' ? choices[value] as string : value);

		return { value, label };
	});
}

/**
 * A choice field with its options set: as values, or with labels when
 * any differs from its value.
 */
export function withOptions(field: FieldDescription, options: { value: string; label: string }[]): FieldDescription {
	const written = options.some((option) => option.label !== option.value) ? options : options.map((option) => option.value);
	const next    = { ...field } as FieldDescription;

	if (isManyChoice(field.control ?? '')) {
		next.item = { ...(field.item ?? { name: '', type: 'enum' }), type: 'enum', options: written as string[] };
		delete next.item.choices;
	} else {
		next.options = written as string[];
		delete next.choices;
	}

	return next;
}

/**
 * A new field's definition for a control: its type and what the control
 * implies, with nothing else set.
 */
export function blankField(key: string): FieldDescription {
	const control = catalogControl(key);
	const field: FieldDescription = { name: '', type: control?.type ?? 'text', control: key, ...(control?.implies ?? {}) };

	if (ONE_CHOICE.includes(key)) {
		field.options = [];
	} else if (MANY_CHOICE.includes(key)) {
		field.item = { name: '', type: 'enum', options: [] };
	} else if (key === 'list') {
		field.item = { name: '', type: 'object' };
	}

	return field;
}

/**
 * A field with another control: just the control when it stores the
 * same value; otherwise the new control's blank definition, keeping what
 * every field has (and a choice's options, between the choice controls).
 */
export function withControl(field: FieldDescription, key: string): FieldDescription {
	const from = field.control ?? '';

	if (catalogControl(from)?.shape === catalogControl(key)?.shape) {
		return { ...field, control: key };
	}

	const next: FieldDescription = { ...blankField(key), name: field.name };

	for (const shared of ['label', 'description', 'required', 'aliases'] as const) {
		if (field[shared] !== undefined) {
			next[shared] = field[shared] as never;
		}
	}

	return isChoice(from) && isChoice(key) ? withOptions(next, optionsOf(field)) : next;
}
