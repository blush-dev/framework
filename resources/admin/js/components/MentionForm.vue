<script setup lang="ts">
/**
 * The editor's mention form (D-493, D-509), opened under the inline
 * menu: a search for a profile by name, listing the published ones
 * (only they have pages), moved through with the arrow keys and chosen
 * with Enter or a press. The editor writes the choice as `@slug` where
 * the caret was; Escape asks it to close the form and put the caret
 * back.
 */

import { nextTick, ref, watch } from 'vue';
import { errorMessage } from '../api';
import { t } from '../messages';
import { debounced, latest } from '../action';
import { listMove } from '../grid';
import { loadReferences, type ReferenceItem } from '../references';
import { profileType } from '../types';

const props = defineProps<{
	// What the search opens with (the selection, or the `@word` the
	// caret is in); a new one starts it again.
	start: { query: string };
}>();

const emit = defineEmits<{
	choose: [item: ReferenceItem];
	close: [];
}>();

const query = ref('');
const items = ref<ReferenceItem[] | null>(null);
const index = ref(0);
const error = ref('');
const field = ref<HTMLInputElement | null>(null);
const ask   = latest();

async function find(): Promise<void> {
	const type  = profileType.value;
	const still = ask();

	if (type === null) {
		return;
	}

	try {
		const found = await loadReferences(type, { search: query.value.trim().replace(/^@/, ''), limit: 8 });

		if (still()) {
			items.value = found.items.filter((item) => item.status === 'published');
			index.value = 0;
			error.value = '';
		}
	} catch (caught) {
		if (still()) {
			items.value = [];
			error.value = errorMessage(caught, t('mention_form.errors.not_loaded', 'The profiles couldn\'t be loaded.'));
		}
	}
}

const search = debounced(() => void find(), 150);

watch(() => props.start, async (start) => {
	query.value = start.query;
	items.value = null;
	void find();
	await nextTick();
	field.value?.focus();
}, { immediate: true });

function choose(item: ReferenceItem | undefined): void {
	if (item !== undefined) {
		emit('choose', item);
	}
}

function key(event: KeyboardEvent): void {
	if (event.key === 'Escape') {
		event.preventDefault();
		event.stopPropagation();
		emit('close');
	} else {
		const next = listMove(event.key, index.value, items.value?.length ?? 0);

		if (next !== null) {
			event.preventDefault();
			index.value = next;
		}
	}
}
</script>

<template>
	<form id="editor-mention" class="popover-form" role="dialog" :aria-label="t('mention_form.mention', 'Mention')" @submit.prevent="choose(items?.[index])" @keydown="key">
		<div class="field">
			<label for="editor-mention-search">{{ t('mention_form.mention', 'Mention') }}</label>
			<input id="editor-mention-search" ref="field" v-model="query" type="search" autocomplete="off" spellcheck="false" :placeholder="t('mention_form.placeholder', 'Find a profile')" role="combobox" aria-autocomplete="list" aria-controls="editor-mention-list" :aria-expanded="(items?.length ?? 0) > 0" :aria-activedescendant="items?.length ? `editor-mention-${index}` : undefined" @input="search">
		</div>
		<p v-if="error" class="mention-form__note" role="alert">{{ error }}</p>
		<p v-else-if="items === null" class="mention-form__note">{{ t('mention_form.finding', 'Finding profiles…') }}</p>
		<p v-else-if="items.length === 0" class="mention-form__note">{{ t('mention_form.none', 'No published profile matches.') }}</p>
		<ul v-else id="editor-mention-list" class="mention-form__list" role="listbox" :aria-label="t('mention_form.profiles', 'Profiles')">
			<li v-for="(item, at) in items" :id="`editor-mention-${at}`" :key="item.slug" role="option" class="mention-form__item" :class="{ 'is-active': at === index }" :aria-selected="at === index" @mousedown.prevent @click="choose(item)" @mouseenter="index = at">
				<span>{{ item.title }}</span>
				<code class="mention-form__slug">@{{ item.slug }}</code>
			</li>
		</ul>
	</form>
</template>

<style scoped>
.mention-form__note {
	margin: 0;
	color: var(--color-text-muted);
	font-size: var(--font-size-small);
}

.mention-form__list {
	display: grid;
	max-height: min(320px, 40vh);
	margin: 0;
	padding: 0;
	overflow-y: auto;
	list-style: none;
}

.mention-form__item {
	display: flex;
	align-items: baseline;
	justify-content: space-between;
	gap: var(--s-3);
	padding: var(--s-2) var(--s-3);
	border-radius: var(--border-radius-small);
	color: var(--color-text-secondary);
	cursor: pointer;
}

.mention-form__item.is-active {
	background: var(--color-accent-subtle);
	color: var(--color-accent);
}

.mention-form__slug {
	color: var(--color-text-muted);
}
</style>
