<script setup lang="ts">
/**
 * The select picker (D-281, D-607): exactly one, or none, whatever the
 * relation's purpose, drawn by `AdminSelect`. Its list opens with None,
 * then the options, in tree order when the type nests; a nested value
 * shows its parent's name after it, so it reads in place. Up to 12
 * options, typing a letter jumps; from 13 the list has a filter; over
 * 50 it opens on a search, with the current value and the recently used
 * before anything's typed, and never on every option.
 *
 * A term's parent leaves out the term and its branch, and the list's
 * foot says so. A person keeps their avatar. A translation set to fall
 * back shows its original's value until it picks its own; a value that
 * names nothing is shown as written, with the closest to replace it.
 */

import { computed } from 'vue';
import { capitalized, withArticle } from '../format';
import { initials } from '../people';
import { CAP, moreLine, slugOf, type ReferenceItem } from '../references';
import { usePicker, type PickerProps } from '../picker';
import AdminSelect, { type SelectOption } from './AdminSelect.vue';
import ReferenceMissing from './ReferenceMissing.vue';

const props = defineProps<PickerProps & {
	// Whether it points at people, who keep their avatars.
	people?: boolean;
	// A term's parent: its own branch is left out.
	branch?: boolean;
}>();

const model = defineModel<string>({ required: true });

const picker = usePicker(props, model, { cap: CAP, suggest: 'recent', suggestions: 5, branch: props.branch === true });
const { names, slugs, itemOf, isMissing, whole, tree, total, excluded, inverseMax, before, query, results, matched, missing, inheritedShown, error } = picker;

const current = computed(() => slugs.value[0] ?? '');

// The parent's name after a nested value (`Sicilian Italian`).
function parentOf(item: ReferenceItem): string | null {
	return item.path?.split(' › ').at(-1) ?? null;
}

// Each target's count against the inverse's `max`, so a full one is
// seen before it's picked (D-608); it can still be picked, and the save
// says why it's refused.
function counted(item: ReferenceItem): string | null {
	const max = inverseMax.value;

	if (max === null || item.taken === undefined || item.taken === null) {
		return null;
	}

	return item.taken >= max && item.slug !== current.value ? `Full · ${item.taken} of ${max}` : `${item.taken} of ${max}`;
}

function option(item: ReferenceItem, group: string | null = null, depth = 0): SelectOption {
	const hint = [item.slug === current.value || depth === 0 ? parentOf(item) : null, counted(item)].filter((part) => part !== null).join(' · ');

	return {
		value: item.slug,
		label: item.title,
		depth,
		hint: hint === '' ? null : hint,
		group,
		...(props.people === true ? { mark: initials(item.title) } : {})
	};
}

const options = computed<SelectOption[]>(() => {
	const fallback = props.inherited !== undefined && props.inherited.rule === 'fallback' && current.value === '' ? itemOf(inheritedShown.value[0] ?? '') : null;
	const none: SelectOption = fallback === null || inheritedShown.value.length === 0
		? { value: '', label: 'None' }
		: { value: '', label: fallback.title, hint: props.inherited?.language ?? null };
	const list: SelectOption[] = [];

	if (whole.value !== null) {
		list.push(...whole.value.map((item) => option(item, null, tree.value ? item.depth ?? 0 : 0)));
	} else if (query.value.trim() !== '') {
		list.push(...results.value.map((item) => option(item)));
	} else {
		if (current.value !== '' && !isMissing(current.value)) {
			list.push(option(itemOf(current.value)));
		}

		list.push(...before.value.filter((item) => item.slug !== current.value).map((item) => option(item, 'Recently Used')));
	}

	// A value that names nothing, as written.
	if (current.value !== '' && !list.some((each) => each.value === current.value)) {
		list.push({ value: current.value, label: isMissing(current.value) ? slugs.value[0] ?? '' : itemOf(current.value).title, hint: isMissing(current.value) ? 'not found' : null });
	}

	// A value that must stay can be changed, not cleared; a search lists
	// only what it found, so Enter takes the first match.
	return (props.keepLast === true && current.value !== '') || (whole.value === null && query.value.trim() !== '') ? list : [none, ...list];
});

const searchable = computed(() => whole.value === null || whole.value.length > 12);

const note = computed(() => {
	if (excluded.value > 0) {
		return `This ${names.value.item} and the ${excluded.value} ${excluded.value === 1 ? names.value.item : names.value.items} inside it aren't listed. ${capitalized(withArticle(names.value.item))} can't sit inside itself.`;
	}

	if (whole.value === null) {
		return query.value.trim() === '' ? `${total.value.toLocaleString()} ${names.value.items}. Type to find the rest.` : (matched.value > 0 ? moreLine(results.value.length, matched.value) : '');
	}

	return '';
});

function pick(value: string): void {
	model.value = value;
	query.value = '';
}
</script>

<template>
	<div class="reference">
		<AdminSelect
			:id="id"
			:model-value="current"
			:options="options"
			:described-by="describedBy"
			:invalid="invalid || (current !== '' && isMissing(current))"
			:plain="plain"
			:searchable="searchable"
			:remote="whole === null"
			:note="note || undefined"
			:class="{ 'reference__select--inherited': current === '' && inheritedShown.length > 0 }"
			@update:model-value="pick($event === '' ? '' : slugOf($event))"
			@search="query = $event"
		/>
		<p v-if="current === '' && inheritedShown.length && inherited" class="field__help">Shown from {{ inherited.title }} ({{ inherited.language }}) until this translation sets its own.</p>
		<ReferenceMissing :missing="missing" :noun="names.item" @replace="picker.replace" />
		<p v-if="error" class="field__error">{{ error }}</p>
	</div>
</template>
