<script setup lang="ts">
/**
 * An entry's summary (its `summary` field, D-728): the text as written,
 * and a link under it that opens a small form beside it to write it,
 * "Add a summary…" while there's none. The text changes as it's typed;
 * Done or Escape closes the form.
 */
import { computed, nextTick, ref } from 'vue';
import { usePopover } from '../popover';

const props = defineProps<{
	id: string;
	error?: string;
}>();

const model = defineModel<string>({ required: true });

// How long a summary reads well in listings and link previews.
const LENGTH = 160;

const button = ref<HTMLButtonElement | null>(null);
const panel  = ref<HTMLElement | null>(null);

const popover = usePopover(button, panel, { gap: 6 });
const { open, place, layer, close } = popover;

const text = computed(() => model.value.trim());

async function show(): Promise<void> {
	await popover.show();
	await nextTick();
	panel.value?.querySelector('textarea')?.focus();
}

function keydown(event: KeyboardEvent): void {
	if (event.key === 'Escape') {
		event.preventDefault();
		event.stopPropagation();
		close();
	}
}
</script>

<template>
	<div class="summary">
		<p v-if="text" class="summary__text">{{ text }}</p>
		<button
			ref="button"
			type="button"
			class="lnk summary__edit"
			aria-haspopup="dialog"
			:aria-expanded="open"
			:aria-invalid="props.error ? 'true' : undefined"
			@click="open ? close() : show()"
		>{{ text ? 'Edit summary' : 'Add a summary…' }}</button>
		<p v-if="props.error" class="field__error">{{ props.error }}</p>
	</div>
	<Teleport :to="layer">
		<form v-if="open" ref="panel" class="popover-form popover-form--placed" role="dialog" aria-label="Summary" :style="place ?? { visibility: 'hidden' }" @submit.prevent="close()" @keydown="keydown">
			<div class="field">
				<label :for="props.id" class="summary__label">Summary <span class="summary__count mono">{{ model.length }} / {{ LENGTH }}</span></label>
				<textarea :id="props.id" v-model="model" rows="4" placeholder="Used in listings and feeds. Left empty, the opening words are used." />
			</div>
			<p class="summary__buttons">
				<button type="submit" class="button button--small button--primary">Done</button>
			</p>
		</form>
	</Teleport>
</template>

<style scoped>
.summary {
	display: grid;
	gap: var(--s-1);
	justify-items: start;
}

.summary__text {
	margin: 0;
	color: var(--fg-2);
	font-size: var(--text-sm);
	line-height: 1.5;
	overflow-wrap: anywhere;
}

.summary__edit {
	font-size: var(--text-sm);
}

.summary__label {
	display: flex;
	justify-content: space-between;
	gap: var(--s-2);
}

.summary__count {
	color: var(--fg-3);
	font-weight: 400;
}

.summary__buttons {
	display: flex;
	justify-content: flex-end;
	margin: 0;
}
</style>
