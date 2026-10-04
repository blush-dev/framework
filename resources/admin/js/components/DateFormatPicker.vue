<script setup lang="ts">
/**
 * The site's date or time format (D-445): a menu of the formats the
 * language defines and a few fixed ones, each shown as it reads now with
 * its name or pattern beside it, then **Custom**, which opens a box for
 * any ICU pattern, with how it reads under it (`GET
 * settings/date-format`) or what's wrong with it. A format the menu
 * doesn't have starts with the box open.
 */

import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AdminSelect, { type SelectOption } from './AdminSelect.vue';
import { ApiError, request } from '../api';

const props = defineProps<{
	id: string;
	options: SelectOption[];
	kind: 'date' | 'time';
	// The language in the form, for the preview.
	locale?: string;
	describedBy?: string;
	invalid?: boolean;
	disabled?: boolean;
}>();

const model = defineModel<string>({ required: true });

// The menu's value for Custom, which no format can be.
const CUSTOM = '*custom';

const input   = ref<HTMLInputElement | null>(null);
const preview = ref('');
const problem = ref('');

const match  = computed(() => props.options.find((option) => option.value === model.value.trim()));
const custom = ref(match.value === undefined);

// A value set from outside (going back to the config's) that the menu
// doesn't have opens the box.
watch(model, () => {
	if (match.value === undefined) {
		custom.value = true;
	}
});

const menu = computed({
	get: () => custom.value ? CUSTOM : (match.value?.value ?? CUSTOM),
	set: (value: string) => {
		if (value === CUSTOM) {
			custom.value = true;
			void nextTick(() => input.value?.focus());

			return;
		}

		custom.value = false;
		model.value  = value;
	}
});

const choices = computed<SelectOption[]>(() => [
	...props.options,
	{ value: CUSTOM, label: 'Custom…', hint: 'An ICU pattern', pinned: true }
]);

let timer: ReturnType<typeof setTimeout> | undefined;
let asked = 0;

// How the typed pattern reads, asked for once typing pauses.
function ask(): void {
	clearTimeout(timer);

	if (!custom.value) {
		return;
	}

	const format = model.value.trim();
	const ticket = ++asked;

	timer = setTimeout(() => {
		const query = new URLSearchParams({ format, kind: props.kind, locale: props.locale ?? '' });

		request<{ text: string }>('GET', `/settings/date-format?${query.toString()}`)
			.then((answer) => {
				if (ticket === asked) {
					preview.value = answer.text;
					problem.value = '';
				}
			})
			.catch((caught: unknown) => {
				if (ticket === asked) {
					preview.value = '';
					problem.value = caught instanceof ApiError ? caught.message : 'It couldn\'t be checked.';
				}
			});
	}, 250);
}

watch([model, custom, () => props.locale], ask, { immediate: true });

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
	<div class="date-format-picker">
		<AdminSelect :id="id" v-model="menu" :options="choices" :described-by="describedBy" :invalid="invalid" :disabled="disabled" />
		<template v-if="custom">
			<input
				:id="`${id}-pattern`"
				ref="input"
				v-model="model"
				class="mono"
				:aria-label="kind === 'date' ? 'Date pattern' : 'Time pattern'"
				:placeholder="kind === 'date' ? 'such as MMMM d, y' : 'such as h:mm a'"
				:aria-describedby="`${id}-preview`"
				:aria-invalid="invalid || problem !== '' ? 'true' : undefined"
				:disabled="disabled"
				autocomplete="off"
				spellcheck="false"
			>
			<p :id="`${id}-preview`" class="date-format-picker__preview" :class="{ 'is-invalid': problem !== '' }" aria-live="polite">
				<template v-if="problem !== ''">{{ problem }}</template>
				<template v-else-if="preview !== ''">Reads as {{ preview }}</template>
			</p>
		</template>
	</div>
</template>

<style scoped>
.date-format-picker {
	display: grid;
	gap: var(--s-2);
}

.date-format-picker__preview {
	margin: 0;
	color: var(--fg-3);
	font-size: var(--text-sm);
}

.date-format-picker__preview.is-invalid {
	color: var(--danger);
}
</style>
