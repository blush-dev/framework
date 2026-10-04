<script setup lang="ts">
/**
 * The site's language and region (D-441): a menu of the locales PHP
 * knows, regions under their language, each named in its own language
 * with its English name beside it (D-442), then **Other**, which opens a
 * box for any code the menu doesn't have (`en_US_POSIX`, `x-klingon`).
 * A code the menu has matches however it's written (`en-us` is
 * `English (United States)`); one it doesn't starts with the box open.
 * The value stays as it's written until something is chosen or typed.
 *
 * The menu has a search (D-443), by either name or the code; Other is
 * always at its foot, and choosing it when nothing matched fills the
 * box with what was searched for.
 */

import { computed, nextTick, ref, watch } from 'vue';
import AdminSelect, { type SelectOption } from './AdminSelect.vue';

const props = defineProps<{
	id: string;
	options: SelectOption[];
	describedBy?: string;
	invalid?: boolean;
	disabled?: boolean;
}>();

const model = defineModel<string>({ required: true });

// The menu's value for Other, which no locale can be.
const OTHER = '*other';

const input = ref<HTMLInputElement | null>(null);

function normalize(code: string): string {
	return code.trim().replace(/-/g, '_').toLowerCase();
}

const match = computed(() => props.options.find((option) => normalize(option.value) === normalize(model.value)));
const other = ref(match.value === undefined);

// A value set from outside (going back to the config's) that the menu
// doesn't have opens the box.
watch(model, () => {
	if (match.value === undefined) {
		other.value = true;
	}
});

const menu = computed({
	get: () => other.value ? OTHER : (match.value?.value ?? OTHER),
	set: (value: string) => {
		if (value === OTHER) {
			other.value = true;
			void nextTick(() => input.value?.focus());

			return;
		}

		other.value = false;
		model.value = value;
	}
});

// Each name is marked as the language it's written in.
const choices = computed<SelectOption[]>(() => [
	...props.options.map((option) => ({ ...option, lang: option.value.replace(/_/g, '-'), search: option.value })),
	{ value: OTHER, label: 'Other…', pinned: true }
]);

// Other, chosen when the search found nothing, takes the search as the
// code.
function picked(value: string, query: string, matched: boolean): void {
	if (value === OTHER && query !== '' && !matched) {
		model.value = query;
	}
}
</script>

<template>
	<div class="locale-picker">
		<AdminSelect :id="id" v-model="menu" :options="choices" searchable :described-by="describedBy" :invalid="invalid" :disabled="disabled" @picked="picked" />
		<input
			v-if="other"
			:id="`${id}-code`"
			ref="input"
			v-model="model"
			class="mono"
			aria-label="Language code"
			placeholder="such as en_US or fr"
			:aria-describedby="describedBy"
			:aria-invalid="invalid ? 'true' : undefined"
			:disabled="disabled"
			autocomplete="off"
			spellcheck="false"
		>
	</div>
</template>

<style scoped>
.locale-picker {
	display: grid;
	gap: var(--s-2);
}
</style>
