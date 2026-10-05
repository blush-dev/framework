<script setup lang="ts">
/**
 * A trashed entry, to look at before restoring it (D-276): its body as
 * the editor shows it, but read-only, and its front matter. It isn't on
 * the site and can't be edited here; **Restore as a draft** brings it
 * back into the editor, and **Delete permanently** removes it for good.
 * Opened from the Trash tab's titles and their **Preview** item.
 */

import { computed, ref, watch } from 'vue';
import { confirmAction } from '../confirm';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { ApiError, entryRoute, request, type TrashedDetail } from '../api';
import AdminIcon from '../components/AdminIcon.vue';
import MarkdownEditor from '../components/MarkdownEditor.vue';
import { formatDate } from '../format';
import { screenTitle } from '../screen';
import { toast } from '../toast';
import { restoreFromTrash } from '../trash';
import { currentType, labelsOf, loadTypes } from '../types';

const route  = useRoute();
const router = useRouter();

const item  = ref<TrashedDetail | null>(null);
const body  = ref('');
const error = ref('');
const busy  = ref(false);

const trashName = computed(() => (Array.isArray(route.params.name) ? route.params.name : [route.params.name ?? '']).join('/'));
const labels   = computed(() => labelsOf(item.value?.type || 'entry'));
const noun     = computed(() => labels.value.item);
const name     = computed(() => item.value?.title || 'Untitled');
const back     = computed(() => item.value?.type ? { name: 'type', params: { type: item.value.type }, query: { status: 'trash' } } : { name: 'dashboard' });
const keys     = computed(() => Object.entries(item.value?.frontMatter ?? {}).filter(([key]) => key !== 'title'));

loadTypes().catch(() => undefined);

watch(trashName, async (value) => {
	item.value  = null;
	error.value = '';

	try {
		item.value = await request<TrashedDetail>('GET', `/trash/${value.split('/').map(encodeURIComponent).join('/')}`);
		// Shown only, so the blank lines after the front matter can go.
		body.value = item.value.body.replace(/^\n+/, '');
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : 'That trashed entry couldn\'t be loaded.';
	}
}, { immediate: true });

watch(item, (value) => {
	currentType.value = value?.type ?? null;
	screenTitle.value = value === null ? null : (value.title || 'Untitled');
});

function show(value: unknown): string {
	return typeof value === 'string' ? value : JSON.stringify(value);
}

async function restore(): Promise<void> {
	const detail = item.value;

	if (detail === null) {
		return;
	}

	busy.value  = true;
	error.value = '';

	try {
		const restored = await restoreFromTrash(detail.name);

		if (restored === null) {
			busy.value = false;

			return;
		}

		toast(`Restored “${name.value}” as a draft`);
		await router.push(detail.type === null ? back.value : entryRoute({ id: restored, type: detail.type }));
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : `The ${noun.value} couldn't be restored.`;
		busy.value  = false;
	}
}

async function purge(): Promise<void> {
	const detail = item.value;

	if (detail === null || !await confirmAction({ title: `Delete “${name.value}” Permanently?`, body: 'This can\'t be undone.', confirm: 'Delete permanently', danger: true })) {
		return;
	}

	busy.value  = true;
	error.value = '';

	try {
		await request<void>('POST', '/trash/delete', { name: detail.name });

		toast(`Deleted “${name.value}” permanently`, { kind: 'danger' });
		await router.push(back.value);
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : `The ${noun.value} couldn't be deleted.`;
		busy.value  = false;
	}
}
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="back"><AdminIcon name="chevron-left" />Trash</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">
				<template v-if="item">{{ name }}</template>
				<template v-else>In the Trash</template>
			</h1>
			<p v-if="item" class="page-header__hint">
				{{ labels.singular }} · Moved to the trash <time :datetime="item.trashed">{{ formatDate(item.trashed) }}</time>
			</p>
		</div>
		<div class="page-header__actions">
			<template v-if="item">
				<button type="button" class="button button--danger" :disabled="busy" @click="purge"><AdminIcon name="trash-2" />Delete permanently</button>
				<button type="button" class="button button--primary" :disabled="busy" @click="restore"><AdminIcon name="refresh-cw" />Restore as a draft</button>
			</template>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<template v-if="item">
		<p class="notice notice--warn">This {{ noun }} is in the trash, so it isn't on your site and can't be edited. Restore it as a draft to change it.</p>

		<div class="trashed">
			<section class="panel" aria-labelledby="trashed-body-heading">
				<header class="panel__header">
					<h2 id="trashed-body-heading">Body</h2>
				</header>
				<div class="panel__body">
					<MarkdownEditor v-if="body.trim() !== ''" id="trashed-body" v-model="body" label="Body (read-only)" readonly />
					<p v-else class="field__help">No body.</p>
				</div>
			</section>

			<section class="panel" aria-labelledby="trashed-front-matter-heading">
				<header class="panel__header">
					<h2 id="trashed-front-matter-heading">Front Matter</h2>
				</header>
				<dl v-if="keys.length" class="panel__body trashed__keys">
					<div v-for="[key, value] in keys" :key="key">
						<dt class="mono">{{ key }}</dt>
						<dd class="mono">{{ show(value) }}</dd>
					</div>
				</dl>
				<p v-else class="panel__body field__help">Nothing but its title.</p>
			</section>
		</div>
	</template>
</template>

<style scoped>
.trashed {
	display: grid;
	grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
	align-items: start;
	gap: 16px;
}

.trashed__keys {
	display: grid;
	gap: 10px;
	margin: 0;
}

.trashed__keys > * + * {
	margin-top: 0;
}

.trashed__keys dt {
	color: var(--fg-2);
	font-size: var(--text-xs);
}

.trashed__keys dd {
	margin: 2px 0 0;
	overflow-wrap: anywhere;
	font-size: var(--text-sm);
}

@media (width <= 900px) {
	.trashed {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
