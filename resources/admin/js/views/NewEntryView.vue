<script setup lang="ts">
/**
 * Starts an entry: a type and a title. The server writes it as a draft
 * credited to the account's author (D-229), then the editor opens it.
 */

import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { ApiError, entryRoute, request, type EntryDetail } from '../api';
import AdminSelect from '../components/AdminSelect.vue';
import { titleCase } from '../format';
import { screenTitle } from '../screen';
import { currentType, labelsOf, loadTypes, types } from '../types';

const route  = useRoute();
const router = useRouter();

const ready = ref(false);
const type  = ref(typeof route.query.type === 'string' ? route.query.type : '');
const title = ref('');
const busy  = ref(false);
const error = ref('');

const labels = computed(() => labelsOf(type.value || 'entry'));
const noun   = computed(() => labels.value.item);

// The navigation marks the type; the title follows the choice.
watch(type, (name) => {
	currentType.value = name === '' ? null : name;
}, { immediate: true });

watch(labels, (value) => {
	screenTitle.value = titleCase(value.newItem);
}, { immediate: true });

onMounted(async () => {
	try {
		const all = await loadTypes();

		if (!all.some((item) => item.name === type.value)) {
			type.value = all.find((item) => item.kind === 'collection')?.name ?? all[0]?.name ?? '';
		}

		ready.value = true;
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : 'The content types couldn\'t be loaded.';
	}
});

async function create(): Promise<void> {
	busy.value  = true;
	error.value = '';

	try {
		const entry = await request<EntryDetail>('POST', '/entries', { type: type.value, title: title.value });

		await router.replace({ ...entryRoute(entry), query: { created: '1' } });
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : `The ${noun.value} couldn't be created.`;
		busy.value  = false;
	}
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ titleCase(labels.newItem) }}</h1>
			<p class="page-header__hint">It starts as a draft. You'll write the rest in the editor.</p>
		</div>
	</header>

	<form v-if="ready" class="panel new-entry" :aria-busy="busy" @submit.prevent="create">
		<div class="panel__body">
			<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
			<p class="field">
				<label for="new-type">Type</label>
				<AdminSelect id="new-type" v-model="type" :options="types.map((item) => ({ value: item.name, label: item.labels.singular }))" />
			</p>
			<p class="field">
				<label for="new-title">Title</label>
				<input id="new-title" v-model="title" required autocomplete="off">
			</p>
			<div class="new-entry__actions">
				<button type="submit" class="button button--primary" :disabled="busy || title.trim() === ''">{{ busy ? 'Creating…' : `Create ${noun}` }}</button>
				<RouterLink class="button button--ghost" :to="type ? { name: 'type', params: { type } } : { name: 'dashboard' }">Cancel</RouterLink>
			</div>
		</div>
	</form>

	<p v-else-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-else class="loading" aria-live="polite">Loading…</p>
</template>

<style scoped>
.new-entry {
	max-width: 36rem;
}

.new-entry__actions {
	display: flex;
	gap: 8px;
}
</style>
