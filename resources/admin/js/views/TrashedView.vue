<script setup lang="ts">
/**
 * A trashed entry, to look at before restoring it (D-276): its body as
 * the editor shows it, but read-only, and its front matter. It isn't on
 * the site and can't be edited here; **Restore as a draft** brings it
 * back into the editor, and **Delete permanently** removes it for good.
 * Opened from the Trash tab's titles and their **Preview** item.
 */

import { computed, ref, watch } from 'vue';
import { confirmPurge } from '../referrers';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { entryPath, entryRoute, errorMessage, request, type EntryDetail } from '../api';
import { useAction } from '../action';
import AdminIcon from '../components/AdminIcon.vue';
import MarkdownEditor from '../components/MarkdownEditor.vue';
import MessageText from '../components/MessageText.vue';
import RawValues from '../components/RawValues.vue';
import { siteDateTime } from '../dates';
import { t } from '../messages';
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
const name     = computed(() => item.value?.title || t('trashed_view.untitled', 'Untitled'));
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
		error.value = errorMessage(caught, t('trashed_view.load_failed', 'That trashed entry couldn\'t be loaded.'));
	}
}, { immediate: true });

watch(item, (value) => {
	currentType.value = value?.type.name ?? null;
	screenTitle.value = value === null ? null : (value.title || t('trashed_view.untitled', 'Untitled'));
});

async function restore(): Promise<void> {
	const detail = item.value;

	if (detail === null) {
		return;
	}

	await run(t('trashed_view.restore_failed', 'The {noun} couldn\'t be restored.', { noun: noun.value }), async () => {
		await request<{ id: string }>('POST', `${entryPath(detail.id)}/restore`);

		toast(t('trashed_view.restored', 'Restored “{name}” as a draft', { name: name.value }));
		await router.push(entryRoute({ id: detail.id, type: detail.type.name }));
	});
}

async function purge(): Promise<void> {
	const detail = item.value;

	const unlink = detail === null ? null : await confirmPurge(detail.id, t('trashed_view.quoted_name', '“{name}”', { name: name.value }));

	if (detail === null || unlink === null) {
		return;
	}

	await run(t('trashed_view.delete_failed', 'The {noun} couldn\'t be deleted.', { noun: noun.value }), async () => {
		const answer = await request<{ deleted: string; unlinked: number }>('DELETE', `${entryPath(detail.id)}?permanently=1${unlink}`);

		toast(answer.unlinked > 0
			? t('trashed_view.deleted_unlinked', 'Deleted “{name}” permanently, and took it out of {count, plural, one {# entry} other {# entries}}', { name: name.value, count: answer.unlinked })
			: t('trashed_view.deleted', 'Deleted “{name}” permanently', { name: name.value }), { kind: 'danger' });
		await router.push(back.value);
	});
}
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="back"><AdminIcon name="chevron-left" />{{ t('trashed_view.trash', 'Trash') }}</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">
				<template v-if="item">{{ name }}</template>
				<template v-else>{{ t('trashed_view.in_trash', 'In the Trash') }}</template>
			</h1>
			<p v-if="item" class="page-header__hint">
				{{ labels.singular }}<template v-if="item.trashed"> · <MessageText :text="t('trashed_view.moved', 'Moved to the trash {time}')"><template #time><time :datetime="item.trashed">{{ siteDateTime(item.trashed) }}</time></template></MessageText></template>
			</p>
		</div>
		<div class="page-header__actions">
			<template v-if="item">
				<button type="button" class="button button--danger" :disabled="busy" @click="purge"><AdminIcon name="trash-2" />{{ t('trashed_view.delete_permanently', 'Delete Permanently') }}</button>
				<button type="button" class="button button--primary" :disabled="busy" @click="restore"><AdminIcon name="refresh-cw" />{{ t('trashed_view.restore', 'Restore as a Draft') }}</button>
			</template>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<template v-if="item">
		<p class="notice notice--warn">{{ t('trashed_view.notice', 'This {noun} is in the trash, so it isn\'t on your site and can\'t be edited. Restore it as a draft to change it.', { noun }) }}</p>

		<div class="trashed">
			<section class="panel" aria-labelledby="trashed-body-heading">
				<header class="panel__header">
					<h2 id="trashed-body-heading">{{ t('trashed_view.body', 'Body') }}</h2>
				</header>
				<div class="panel__body">
					<MarkdownEditor v-if="body.trim() !== ''" id="trashed-body" v-model="body" :label="t('trashed_view.body_readonly', 'Body (read-only)')" readonly />
					<p v-else class="field__help">{{ t('trashed_view.no_body', 'No body.') }}</p>
				</div>
			</section>

			<section class="panel" aria-labelledby="trashed-front-matter-heading">
				<header class="panel__header">
					<h2 id="trashed-front-matter-heading">{{ t('trashed_view.front_matter', 'Front Matter') }}</h2>
				</header>
				<RawValues v-if="keys.length" class="panel__body trashed__keys" :entries="keys" />
				<p v-else class="panel__body field__help">{{ t('trashed_view.title_only', 'Nothing but its title.') }}</p>
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
	font-size: var(--font-size-small);
}

@media (width <= 900px) {
	.trashed {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
