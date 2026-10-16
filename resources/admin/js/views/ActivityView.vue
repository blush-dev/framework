<script setup lang="ts">
/**
 * Activity (D-758; a Tools screen, D-900), with `site.activity`: every
 * change to entries and media, by whom and how (the admin, an app, the
 * command line, the API), filtered in the address
 * (`/activity?client=Claude`), with Undo.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import ActivityRows from '../components/ActivityRows.vue';
import AdminIcon from '../components/AdminIcon.vue';
import AdminSelect, { type SelectOption } from '../components/AdminSelect.vue';
import EmptyState from '../components/EmptyState.vue';
import { t } from '../messages';
import { loadActivity, type ActivityPage } from '../activity';
import { errorMessage } from '../api';

const route  = useRoute();
const router = useRouter();

const activity = ref<ActivityPage | null>(null);
const error    = ref('');
const loading  = ref(false);

// The filters, from the address.
const query = (key: string): string => typeof route.query[key] === 'string' ? route.query[key] : '';

const activityQuery = computed(() => ({
	account: query('account'),
	via: query('via'),
	client: query('client'),
	action: query('action'),
	page: Number(query('page')) || 1
}));

// How a change came: a way, or an app by its name.
const howValue = computed(() => query('client') !== '' ? `client:${query('client')}` : query('via'));

const accountOptions = computed<SelectOption[]>(() => [
	{ value: '', label: t('activity_view.filters.anyone', 'Anyone') },
	...(activity.value?.accounts ?? []).map((account) => ({ value: account.username, label: account.name }))
]);

const howOptions = computed<SelectOption[]>(() => [
	{ value: '', label: t('activity_view.filters.any_way', 'Any way') },
	...(activity.value?.via ?? []).map((via) => ({ value: via.value, label: via.label })),
	...(activity.value?.clients ?? []).map((client) => ({ value: `client:${client}`, label: client }))
]);

const actionOptions = computed<SelectOption[]>(() => [
	{ value: '', label: t('activity_view.filters.any_change', 'Any change') },
	...(activity.value?.actions ?? [])
]);

function filter(changes: Record<string, string>): void {
	const next: Record<string, string> = {};

	for (const [key, value] of Object.entries({ account: query('account'), via: query('via'), client: query('client'), action: query('action'), ...changes })) {
		if (value !== '') {
			next[key] = value;
		}
	}

	void router.replace({ query: next });
}

function chooseHow(value: string): void {
	filter(value.startsWith('client:') ? { via: '', client: value.slice(7) } : { via: value, client: '' });
}

async function load(): Promise<void> {
	loading.value = true;
	error.value   = '';

	try {
		activity.value = await loadActivity(activityQuery.value);
	} catch (caught) {
		error.value = errorMessage(caught, t('activity_view.errors.activity', 'The activity couldn\'t be loaded.'));
	} finally {
		loading.value = false;
	}
}

watch(activityQuery, () => {
	void load();
}, { immediate: true });
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ t('activity_view.title', 'Activity') }}</h1>
			<p class="page-header__hint">{{ t('activity_view.hint', 'What changed, by whom, and how') }}</p>
		</div>
	</header>


	<div class="toolbar">
		<div class="toolbar__filter">
			<label class="visually-hidden" for="activity-account">{{ t('activity_view.filters.who', 'Who') }}</label>
			<AdminSelect id="activity-account" :model-value="query('account')" :options="accountOptions" @update:model-value="filter({ account: $event, page: '' })" />
		</div>
		<div class="toolbar__filter">
			<label class="visually-hidden" for="activity-how">{{ t('activity_view.filters.how', 'How') }}</label>
			<AdminSelect id="activity-how" :model-value="howValue" :options="howOptions" @update:model-value="chooseHow($event)" />
		</div>
		<div class="toolbar__filter">
			<label class="visually-hidden" for="activity-action">{{ t('activity_view.filters.change', 'Change') }}</label>
			<AdminSelect id="activity-action" :model-value="query('action')" :options="actionOptions" @update:model-value="filter({ action: $event, page: '' })" />
		</div>
		<span class="toolbar__count" aria-live="polite">{{ activity ? t('activity_view.changes', '{count, plural, one {# change} other {# changes} }', { count: activity.total }) : t('common.loading', 'Loading…') }}</span>
	</div>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<section class="panel" aria-labelledby="activity-heading" :aria-busy="loading">
		<header class="panel__header">
			<h2 id="activity-heading">{{ t('activity_view.activity', 'Activity') }}</h2>
			<p class="panel__hint">{{ t('activity_view.newest_first', 'Newest first') }}</p>
			<div class="panel__actions">
				<button type="button" class="button button--small" :disabled="loading" @click="load"><AdminIcon name="refresh-cw" />{{ t('activity_view.refresh', 'Refresh') }}</button>
			</div>
		</header>
		<p v-if="!activity" class="panel__body loading">{{ t('activity_view.loading', 'Loading the activity…') }}</p>
		<EmptyState v-else-if="!activity.changes.length" icon="clock" :heading="t('activity_view.nothing_changed', 'Nothing Changed')" :text="activityQuery.account || activityQuery.via || activityQuery.client || activityQuery.action ? t('activity_view.no_change_fits', 'No change fits the filters.') : t('activity_view.no_changes', 'Changes to entries and media are listed here as they\'re made.')" />
		<ActivityRows v-else :changes="activity.changes" @changed="load" />
		<p v-if="activity && activity.pages > 1" class="panel__foot activity__pages">
			<RouterLink v-if="activity.page > 1" class="lnk" :to="{ query: { ...route.query, page: String(activity.page - 1) } }">{{ t('activity_view.newer', 'Newer') }}</RouterLink>
			<span class="muted">{{ t('activity_view.page', 'Page {page} of {pages}', { page: activity.page, pages: activity.pages }) }}</span>
			<RouterLink v-if="activity.page < activity.pages" class="lnk" :to="{ query: { ...route.query, page: String(activity.page + 1) } }">{{ t('activity_view.older', 'Older') }}</RouterLink>
		</p>
	</section>
</template>

<style scoped>
/* Newer and older pages of the activity. */
.activity__pages {
	display: flex;
	gap: var(--s-3);
	align-items: center;
}
</style>
