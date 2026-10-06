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
import { entryPath, entryRoute, errorMessage, request, type EntryDetail } from '../api';
import { useAction } from '../action';
import AdminIcon from '../components/AdminIcon.vue';
import MarkdownEditor from '../components/MarkdownEditor.vue';
import RawValues from '../components/RawValues.vue';
import { formatDate } from '../format';
import { screenTitle } from '../screen';
import { toast } from '../toast';
import { currentType, labelsOf, loadTypes } from '../types';

const route  = useRoute();
const router = useRouter();

const item = ref<EntryDetail | null>(null);
const body = ref('');

const { busy, error, run } = useAction();

const id       = computed(() => String(route.params.id ?? ''));
const labels   = computed(() => labelsOf(item.value?.type.name || 'entry'));
const noun     = computed(() => labels.value.item);
const name     = computed(() => item.value?.title || 'Untitled');
const back     = computed(() => item.value ? { name: 'type', params: { type: item.value.type.name }, query: { status: 'trash' } } : { name: 'dashboard' });
// Its front matter, as the editor reads it: field values, then the rest.
const keys     = computed(() => Object.entries({ ...item.value?.values, ...item.value?.extra })
	.filter(([key, value]) => key !== 'title' && value !== null && value !== '' && !(Array.isArray(value) && value.length === 0)));

loadTypes().catch(() => undefined);

watch(id, async (value) => {
	item.value  = null;
	error.value = '';

	try {
		const detail = await request<EntryDetail>('GET', entryPath(value));

		// Restored meanwhile: it's edited, not looked at.
		if (detail.status !== 'trash') {
			await router.replace(entryRoute({ id: detail.id, type: detail.type.name }));

			return;
		}

		item.value = detail;
		// Shown only, so the blank lines after the front matter can go.
		body.value = item.value.body.replace(/^\n+/, '');
	} catch (caught) {
		error.value = errorMessage(caught, 'That trashed entry couldn\'t be loaded.');
	}
}, { immediate: true });

watch(item, (value) => {
	currentType.value = value?.type.name ?? null;
	screenTitle.value = value === null ? null : (value.title || 'Untitled');
});

async function restore(): Promise<void> {
	const detail = item.value;

	if (detail === null) {
		return;
	}

	await run(`The ${noun.value} couldn't be restored.`, async () => {
		await request<{ id: string }>('POST', `${entryPath(detail.id)}/restore`);

		toast(`Restored “${name.value}” as a draft`);
		await router.push(entryRoute({ id: detail.id, type: detail.type.name }));
	});
}

async function purge(): Promise<void> {
	const detail = item.value;

	if (detail === null || !await confirmAction({ title: `Delete “${name.value}” Permanently?`, body: 'This can\'t be undone.', confirm: 'Delete Permanently', danger: true })) {
		return;
	}

	await run(`The ${noun.value} couldn't be deleted.`, async () => {
		await request<{ deleted: string }>('DELETE', `${entryPath(detail.id)}?permanently=1`);

		toast(`Deleted “${name.value}” permanently`, { kind: 'danger' });
		await router.push(back.value);
	});
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
				{{ labels.singular }}<template v-if="item.trashed"> · Moved to the trash <time :datetime="item.trashed">{{ formatDate(item.trashed) }}</time></template>
			</p>
		</div>
		<div class="page-header__actions">
			<template v-if="item">
				<button type="button" class="button button--danger" :disabled="busy" @click="purge"><AdminIcon name="trash-2" />Delete Permanently</button>
				<button type="button" class="button button--primary" :disabled="busy" @click="restore"><AdminIcon name="refresh-cw" />Restore as a Draft</button>
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
				<RawValues v-if="keys.length" class="panel__body trashed__keys" :entries="keys" />
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

/* A panel of them, a size up. */
.trashed__keys {
	gap: 10px;
}

.trashed__keys > * + * {
	margin-top: 0;
}

.trashed__keys :deep(dd) {
	font-size: var(--text-sm);
}

@media (width <= 900px) {
	.trashed {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
