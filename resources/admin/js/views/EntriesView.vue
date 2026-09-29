<script setup lang="ts">
/**
 * The entries the account may edit (D-230), of one content type
 * (`/content/{type}`, D-234) or of all of them (`/entries`, with a type
 * menu): status tabs with counts, a search, and pages. The filters live
 * in the URL, so the back button and a shared link restore them. Titles
 * open the editor; the type's own words name the screen and its "New"
 * button.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter, type LocationQueryRaw } from 'vue-router';
import { ApiError, request, type EntryList, type EntryStatus, type TrashedSummary } from '../api';
import AdminIcon from '../components/AdminIcon.vue';
import EntryTable from '../components/EntryTable.vue';
import TrashTable from '../components/TrashTable.vue';
import { humanize, inSentence } from '../fields';
import { plural } from '../format';
import { screenTitle } from '../screen';
import { can } from '../session';
import { currentType, findType, loadTypes, types } from '../types';

type Tab = EntryStatus | 'any' | 'trash';

const statusTabs: { status: EntryStatus | 'any'; label: string }[] = [
	{ status: 'any', label: 'All' },
	{ status: 'published', label: 'Published' },
	{ status: 'draft', label: 'Drafts' },
	{ status: 'scheduled', label: 'Scheduled' }
];

// Trash is a tab like the statuses (D-237), for accounts that can delete.
const canTrash = computed(() => can('content.delete'));
const tabs     = computed<{ status: Tab; label: string }[]>(() => canTrash.value ? [...statusTabs, { status: 'trash', label: 'Trash' }] : statusTabs);

const route  = useRoute();
const router = useRouter();

const list    = ref<EntryList | null>(null);
const trash   = ref<TrashedSummary[] | null>(null);
const counts  = ref<Partial<Record<Tab, number>>>({});
const error   = ref('');
const loading = ref(false);
const busy    = ref<string | null>(null);
const done    = ref<{ text: string; entry?: string } | null>(null);

const status = computed<Tab>(() => {
	const value = route.query.status;

	return tabs.value.some((tab) => tab.status === value) ? value as Tab : 'any';
});

const inTrash = computed(() => status.value === 'trash');

// A type's own screen, or the menu on the screen for all of them.
const fixed  = computed(() => route.name === 'type');
const type   = computed(() => fixed.value ? String(route.params.type ?? '') : (typeof route.query.type === 'string' ? route.query.type : ''));
const info   = computed(() => findType(type.value));
const search = computed(() => typeof route.query.search === 'string' ? route.query.search : '');
const page   = computed(() => Math.max(1, Number(route.query.page) || 1));

const filtered = computed(() => search.value !== '' || (!fixed.value && type.value !== ''));

// A taxonomy's entries are terms: they're counted by use, not credited.
const terms = computed(() => fixed.value && info.value?.kind === 'taxonomy');

const heading  = computed(() => fixed.value ? (info.value?.label ?? humanize(type.value)) : 'All entries');
const singular = computed(() => inSentence(info.value?.singular ?? (type.value === '' ? 'entry' : humanize(type.value))));

watch([fixed, type, info], () => {
	currentType.value = fixed.value ? type.value : null;
	screenTitle.value = fixed.value ? heading.value : null;
}, { immediate: true });

// The search box updates the URL a moment after typing stops.
const query = ref(search.value);
let typing: ReturnType<typeof setTimeout> | undefined;

watch(query, (value) => {
	clearTimeout(typing);
	typing = setTimeout(() => go({ search: value.trim() || undefined, page: undefined }), 300);
});

watch(search, (value) => {
	if (value !== query.value.trim()) {
		query.value = value;
	}
});

/**
 * Changes the filters in the URL, keeping the rest.
 */
function go(changes: LocationQueryRaw): void {
	const next: LocationQueryRaw = { ...route.query, ...changes };

	for (const key of Object.keys(next)) {
		if (next[key] === undefined || next[key] === '' || (key === 'status' && next[key] === 'any')) {
			delete next[key];
		}
	}

	void router.replace({ query: next });
}

function clear(): void {
	query.value = '';
	go({ type: undefined, search: undefined, page: undefined });
}

function tabQuery(tab: Tab): LocationQueryRaw {
	const next: LocationQueryRaw = { ...route.query, status: tab === 'any' ? undefined : tab };

	delete next.page;

	return next;
}

function params(extra: Record<string, string>): string {
	const values = new URLSearchParams(extra);

	if (type.value !== '') {
		values.set('type', type.value);
	}

	if (search.value !== '') {
		values.set('search', search.value);
	}

	return values.toString();
}

// The trash isn't paged; the search narrows it here.
const trashShown = computed(() => {
	const needle = search.value.toLowerCase();

	return (trash.value ?? []).filter((item) => needle === '' || item.title.toLowerCase().includes(needle) || item.entry.toLowerCase().includes(needle));
});

async function load(): Promise<void> {
	loading.value = true;
	error.value   = '';

	try {
		const [current, trashed, ...totals] = await Promise.all([
			inTrash.value ? Promise.resolve(null) : request<EntryList>('GET', `/entries?${params({ status: status.value, page: String(page.value) })}`),
			canTrash.value ? request<{ trash: TrashedSummary[] }>('GET', `/trash${type.value === '' ? '' : `?type=${encodeURIComponent(type.value)}`}`) : Promise.resolve(null),
			...statusTabs.map((tab) => request<EntryList>('GET', `/entries?${params({ status: tab.status, per: '1' })}`))
		]);

		list.value   = current;
		trash.value  = trashed?.trash ?? null;
		counts.value = {
			...Object.fromEntries(statusTabs.map((tab, index) => [tab.status, totals[index]?.total ?? 0])),
			...(trashed === null ? {} : { trash: trashed.trash.length })
		};
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : 'The entries couldn\'t be loaded.';
	} finally {
		loading.value = false;
	}
}

watch(() => [status.value, type.value, search.value, page.value], load, { immediate: true });

function nameOf(item: TrashedSummary): string {
	return item.title === '' ? item.entry : `“${item.title}”`;
}

/**
 * Runs a trash action, then reloads the list and says what happened.
 */
async function act(key: string, action: () => Promise<{ text: string; entry?: string }>): Promise<void> {
	busy.value  = key;
	done.value  = null;
	error.value = '';

	try {
		done.value = await action();
		await load();
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : 'That didn\'t work. Reload the page and try again.';
	} finally {
		busy.value = null;
	}
}

function restore(item: TrashedSummary): void {
	void act(item.id, async () => {
		const restored = await request<{ id: string }>('POST', '/trash/restore', { id: item.id });

		return { text: `Restored ${nameOf(item)} as a draft.`, entry: restored.id };
	});
}

function purge(item: TrashedSummary): void {
	if (!window.confirm(`Delete ${nameOf(item)} permanently? This can't be undone.`)) {
		return;
	}

	void act(item.id, async () => {
		await request<void>('POST', '/trash/delete', { id: item.id });

		return { text: `Deleted ${nameOf(item)} permanently.` };
	});
}

function emptyTrash(): void {
	const count = trash.value?.length ?? 0;

	if (!window.confirm(`Delete ${plural(count, 'entry', 'entries')} in the trash permanently? This can't be undone.`)) {
		return;
	}

	void act('empty', async () => {
		const answer = await request<{ deleted: number }>('POST', '/trash/empty', type.value === '' ? {} : { type: type.value });

		return { text: `Deleted ${plural(answer.deleted, 'entry', 'entries')} permanently.` };
	});
}

// Without types the menu and labels are left out; the list still works.
loadTypes().catch(() => undefined);

const emptyText = computed(() => {
	if (filtered.value) {
		return 'Nothing matches these filters.';
	}

	const what = fixed.value ? inSentence(heading.value) : 'entries';

	if (terms.value && status.value === 'any') {
		return `Terms group other entries. Create a ${singular.value} to describe one in its own page.`;
	}

	if (inTrash.value) {
		return `Entries you move to the trash wait here until you restore them or delete them permanently.`;
	}

	return status.value === 'any' ? `There are no ${what} you can edit yet.` : `No ${tabs.value.find((tab) => tab.status === status.value)?.label.toLowerCase()} among the ${what}.`;
});
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ heading }}</h1>
			<p class="page-header__hint">{{ terms ? `Terms that group other entries` : (fixed ? `Every ${singular} you can edit` : 'Every entry you can edit, of any type') }}</p>
		</div>
		<div v-if="can('content.create')" class="page-header__actions">
			<RouterLink class="button button--primary" :to="{ name: 'entry-new', query: type ? { type } : {} }">New {{ singular }}</RouterLink>
		</div>
	</header>

	<nav class="tabs" aria-label="Status">
		<RouterLink v-for="tab in tabs" :key="tab.status" class="tabs__tab" :to="{ query: tabQuery(tab.status) }" :aria-current="status === tab.status ? 'page' : undefined">
			{{ tab.label }}
			<span v-if="counts[tab.status] !== undefined" class="tabs__count">{{ counts[tab.status] }}</span>
		</RouterLink>
	</nav>

	<div class="toolbar" role="search">
		<label class="visually-hidden" for="entries-search">Search entries</label>
		<input id="entries-search" v-model="query" class="input" type="search" :placeholder="`Search ${fixed ? inSentence(heading) : 'titles and files'}`" autocomplete="off">

		<template v-if="!fixed && types.length">
			<label class="visually-hidden" for="entries-type">Type</label>
			<select id="entries-type" class="input" :value="type" @change="go({ type: ($event.target as HTMLSelectElement).value || undefined, page: undefined })">
				<option value="">All types</option>
				<option v-for="item in types" :key="item.name" :value="item.name">{{ item.label }}</option>
			</select>
		</template>

		<button v-if="filtered" type="button" class="button button--ghost" @click="clear">Clear filters</button>
	</div>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<p v-if="done" class="notice notice--success" role="status">
		{{ done.text }}
		<RouterLink v-if="done.entry" :to="{ name: 'entry', params: { id: done.entry.split('/') } }">Open it</RouterLink>
	</p>

	<section v-if="inTrash && trash" class="panel" aria-labelledby="entries-heading" :aria-busy="loading || busy !== null">
		<header class="panel__header">
			<h2 id="entries-heading">Trash</h2>
			<p class="panel__hint" aria-live="polite">{{ plural(trashShown.length, 'entry', 'entries') }} · Restored entries come back as drafts</p>
			<div v-if="trash.length" class="panel__actions">
				<button type="button" class="button button--small button--danger" :disabled="busy !== null" @click="emptyTrash">Empty trash</button>
			</div>
		</header>

		<TrashTable v-if="trashShown.length" :items="trashShown" labelledby="entries-heading" :busy="busy" :show-type="!fixed" @restore="restore" @purge="purge" />

		<div v-else class="empty">
			<AdminIcon name="circle-check" />
			<p class="empty__heading">The trash is empty</p>
			<p class="empty__text">{{ emptyText }}</p>
			<button v-if="filtered" type="button" class="button" @click="clear">Clear filters</button>
		</div>
	</section>

	<section v-else-if="!error && list && !inTrash" class="panel" aria-labelledby="entries-heading" :aria-busy="loading">
		<header class="panel__header">
			<h2 id="entries-heading">{{ tabs.find((tab) => tab.status === status)?.label }}</h2>
			<p class="panel__hint" aria-live="polite">
				{{ fixed ? plural(list.total, singular, inSentence(heading)) : plural(list.total, 'entry', 'entries') }}<template v-if="terms"> · Entries counts the published entries using each</template>
			</p>
		</header>

		<EntryTable v-if="list.entries.length" :entries="list.entries" labelledby="entries-heading" date-label="Updated" date-key="updated" :show-type="!fixed" :terms="terms" />

		<div v-else class="empty">
			<AdminIcon name="files" />
			<p class="empty__heading">{{ fixed ? `No ${inSentence(heading)}` : 'No entries' }}</p>
			<p class="empty__text">{{ emptyText }}</p>
			<button v-if="filtered" type="button" class="button" @click="clear">Clear filters</button>
		</div>

		<nav v-if="list.pages > 1" class="pager" aria-label="Pages">
			<RouterLink v-if="page > 1" class="button button--small" :to="{ query: { ...route.query, page: page - 1 === 1 ? undefined : page - 1 } }">Previous</RouterLink>
			<span class="pager__status">Page {{ list.page }} of {{ list.pages }}</span>
			<RouterLink v-if="page < list.pages" class="button button--small" :to="{ query: { ...route.query, page: page + 1 } }">Next</RouterLink>
		</nav>
	</section>

	<p v-else-if="!error" class="loading" aria-live="polite">Loading…</p>
</template>

<style scoped>
.tabs {
	display: flex;
	gap: 4px;
	margin-top: -8px;
	overflow-x: auto;
	border-bottom: 1px solid var(--border);
}

.tabs__tab {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 8px 10px;
	border-bottom: 2px solid transparent;
	margin-bottom: -1px;
	color: var(--fg-2);
	font-weight: 500;
	text-decoration: none;
	white-space: nowrap;
}

.tabs__tab:hover {
	color: var(--fg);
}

.tabs__tab[aria-current="page"] {
	border-bottom-color: var(--accent);
	color: var(--fg);
}

.tabs__count {
	padding: 0 6px;
	border-radius: 999px;
	background: var(--surface-2);
	color: var(--fg-2);
	font-family: var(--font-mono);
	font-size: var(--text-xs);
}

.toolbar .input[type="search"] {
	flex: 1 1 16rem;
	max-width: 24rem;
}

.pager {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 12px;
	padding: 10px var(--pad-x);
	border-top: 1px solid var(--border);
}

.pager__status {
	color: var(--fg-2);
	font-size: var(--text-sm);
}
</style>
