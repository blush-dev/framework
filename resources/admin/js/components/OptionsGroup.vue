<script setup lang="ts">
/**
 * A group on the editor's side panel (D-245, D-268, D-509): its heading
 * (`heading`, or the `heading` slot for one holding a label), a hint at
 * the heading's end (the `hint` slot: a count, or how it's kept), and
 * what's in it. A group without a heading is a note's.
 *
 * A `collapsible` group folds under its heading, closed until it's
 * opened, or while `open` says it should be (it has a value, or an
 * error to show; D-730). Once `open` turns true, it opens, and stays as
 * the reader leaves it.
 */
import { ref, watch } from 'vue';
import AdminIcon from './AdminIcon.vue';

const props = defineProps<{
	heading?: string;
	collapsible?: boolean;
	open?: boolean;
}>();

const shown = ref(props.open === true);

watch(() => props.open, (value) => {
	if (value === true) {
		shown.value = true;
	}
});

function toggled(event: Event): void {
	shown.value = (event.target as HTMLDetailsElement).open;
}
</script>

<template>
	<details v-if="collapsible" class="options__group options__group--collapsible" :open="shown" @toggle="toggled">
		<summary class="options__heading">
			<span class="options__toggle"><AdminIcon name="chevron-right" class="options__caret" /><slot name="heading">{{ heading }}</slot></span>
			<span v-if="$slots.hint" class="options__hint"><slot name="hint" /></span>
		</summary>
		<div class="options__body">
			<slot />
		</div>
	</details>
	<div v-else class="options__group">
		<p v-if="heading || $slots.heading" class="options__heading">
			<slot name="heading">{{ heading }}</slot>
			<span v-if="$slots.hint" class="options__hint"><slot name="hint" /></span>
		</p>
		<slot />
	</div>
</template>
