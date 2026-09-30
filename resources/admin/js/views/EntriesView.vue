<script setup lang="ts">
/**
 * The entries of one content type the account may edit (D-230,
 * `/content/{type}`, D-234): status tabs with counts, a search, and
 * pages. The filters live in the URL, so the back button and a shared
 * link restore them. Titles open the editor; the type's own words name
 * the screen and its "New" button. There's no list of every type
 * together: each type has its own.
 *
 * The header, tabs, and search show at once; the table is a skeleton
 * until its entries arrive. A type with no entries at all drops the tabs
 * and search, says what the type is for, and offers its first entry.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter, type LocationQueryRaw } from 'vue-router';
import { ApiError, entryPath, request, type ContentTypeSummary, type EntryDetail, type EntryList, type EntryStatus, type EntrySummary, type TrashedSummary } from '../api';
import AdminIcon from '../components/AdminIcon.vue';
import EntryTable from '../components/EntryTable.vue';
import SkeletonTable from '../components/SkeletonTable.vue';
import TrashTable from '../components/TrashTable.vue';
import { humanize, inSentence } from '../fields';
import { plural } from '../format';
import { screenTitle } from '../screen';
import { can } from '../session';
import { currentType, findType, loadTypes } from '../types';

type Tab = EntryStatus | 'any' | 'trash';

const statusTabs: { status: EntryStatus | 'any'; label: string }[] = [
	{ status: 'any', label: 'All' },
	{ status: 'published', label: 'Published' },
	{ status: 'draft', label: 'Drafts' },
	{ status: 'scheduled', label: 'Scheduled' }
];

// Pages of entries hold this many unless the server says otherwise.
const PER_PAGE = 20;

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

// What the entries on screen were loaded for: the type and tab. Until
// they match what's asked for, the table is a skeleton.
const loaded = ref('');

const status = computed<Tab>(() => {
	const value = route.query.status;

	return tabs.value.some((tab) => tab.status === value) ? value as Tab : 'any';
});

const inTrash = computed(() => status.value === 'trash');

const type   = computed(() => String(route.params.type ?? ''));
const info   = computed(() => findType(type.value));
const search = computed(() => typeof route.query.search === 'string' ? route.query.search : '');
const page   = computed(() => Math.max(1, Number(route.query.page) || 1));
const key    = computed(() => `${type.value}|${status.value}`);
const ready  = computed(() => loaded.value === key.value);

const filtered = computed(() => search.value !== '');

// A taxonomy's entries are terms: they're counted by use, not credited.
const terms = computed(() => info.value?.kind === 'taxonomy');

// A nesting type lists as a tree on All with no search (D-261); a tab or
// a search flattens it, and a bar says so and how to get it back (the
// design direction's Hierarchy).
const nests     = computed(() => info.value?.kind === 'pages' || info.value?.hierarchical === true);
const flattened = computed(() => {
	if (!nests.value || inTrash.value) {
		return '';
	}

	if (search.value !== '') {
		return 'Searching, so the tree is flattened. Clear the search to see the hierarchy.';
	}

	return status.value === 'any' ? '' : 'Filtered by status, so the tree is flattened. Choose All to see the hierarchy.';
});

const heading  = computed(() => info.value?.label ?? humanize(type.value));
const singular = computed(() => inSentence(info.value?.singular ?? humanize(type.value)));

// Nothing yet, as opposed to nothing matching: no entries in any status,
// none in the trash, and no search.
const nothingYet = computed(() => ready.value && !filtered.value && counts.value.any === 0 && (counts.value.trash ?? 0) === 0);

watch([type, info], () => {
	currentType.value = type.value;
	screenTitle.value = heading.value;
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

	for (const name of Object.keys(next)) {
		if (next[name] === undefined || next[name] === '' || (name === 'status' && next[name] === 'any')) {
			delete next[name];
		}
	}

	void router.replace({ query: next });
}

function clear(): void {
	query.value = '';
	go({ search: undefined, page: undefined });
}

function tabQuery(tab: Tab): LocationQueryRaw {
	const next: LocationQueryRaw = { ...route.query, status: tab === 'any' ? undefined : tab };

	delete next.page;

	return next;
}

function params(extra: Record<string, string>): string {
	const values = new URLSearchParams({ ...extra, type: type.value });

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

// A skeleton guesses at the rows to come: the tab's last count, up to a
// page, or a handful before anything is known.
const skeletonRows = computed(() => {
	const count = counts.value[status.value];

	return count === undefined ? 6 : Math.max(1, Math.min(count, list.value?.per ?? PER_PAGE));
});

const skeletonColumns = computed(() => inTrash.value ? ['Title', 'Trashed', ''] : ['Title', 'Status', terms.value ? 'Entries' : 'Authors', 'Updated', '']);

// Each load's number; only the latest one's answer is shown.
let latest = 0;

async function load(): Promise<void> {
	const asked  = ++latest;
	const wanted = key.value;

	// Another type's counts don't guess this one's.
	if (!loaded.value.startsWith(`${type.value}|`)) {
		counts.value = {};
	}

	loading.value = true;
	error.value   = '';

	try {
		const [current, trashed, ...totals] = await Promise.all([
			inTrash.value ? Promise.resolve(null) : request<EntryList>('GET', `/entries?${params({ status: status.value, page: String(page.value) })}`),
			canTrash.value ? request<{ trash: TrashedSummary[] }>('GET', `/trash?type=${encodeURIComponent(type.value)}`) : Promise.resolve(null),
			...statusTabs.map((tab) => request<EntryList>('GET', `/entries?${params({ status: tab.status, per: '1' })}`))
		]);

		// A later load has taken over.
		if (asked !== latest) {
			return;
		}

		list.value   = current;
		trash.value  = trashed?.trash ?? null;
		counts.value = {
			...Object.fromEntries(statusTabs.map((tab, index) => [tab.status, totals[index]?.total ?? 0])),
			...(trashed === null ? {} : { trash: trashed.trash.length })
		};
		loaded.value = wanted;
	} catch (caught) {
		if (asked === latest) {
			error.value = caught instanceof ApiError ? caught.message : 'The entries couldn\'t be loaded.';
		}
	} finally {
		if (asked === latest) {
			loading.value = false;
		}
	}
}

watch(() => [status.value, type.value, search.value, page.value], load, { immediate: true });

function nameOf(item: { title: string }): string {
	return item.title === '' ? `the untitled ${singular.value}` : `“${item.title}”`;
}

/**
 * Runs a trash action, then reloads the list and says what happened.
 */
async function act(name: string, action: () => Promise<{ text: string; entry?: string }>): Promise<void> {
	busy.value  = name;
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

/**
 * Moves an entry to the trash, as the editor does: at the revision it's
 * at now, so an edit made meanwhile isn't thrown away unseen.
 */
function moveToTrash(entry: EntrySummary): void {
	if (!window.confirm(`Move ${nameOf(entry)} to the trash? You can restore it from the Trash tab.`)) {
		return;
	}

	void act(entry.id, async () => {
		const detail = await request<EntryDetail>('GET', entryPath(entry.id));

		await request<void>('DELETE', `${entryPath(entry.id)}?revision=${encodeURIComponent(detail.revision)}`);

		return { text: `Moved ${nameOf(entry)} to the trash.` };
	});
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

	if (!window.confirm(`Delete ${plural(count, singular.value, inSentence(heading.value))} in the trash permanently? This can't be undone.`)) {
		return;
	}

	void act('empty', async () => {
		const answer = await request<{ deleted: number }>('POST', '/trash/empty', { type: type.value });

		return { text: `Deleted ${plural(answer.deleted, singular.value, inSentence(heading.value))} permanently.` };
	});
}

// Without types the labels fall back to the type's name; the list still works.
loadTypes().catch(() => undefined);

/**
 * What a type is for, for the screen of a type with no entries yet: its
 * description, or else what its kind is for.
 */
function purpose(summary: ContentTypeSummary | undefined, label: string): string {
	if (summary?.description) {
		return summary.description;
	}

	switch (summary?.kind) {
		case 'taxonomy':
			return `${label} group other entries. Each one is an entry of its own, with a page listing what uses it.`;
		case 'pages':
			return `${label} stand on their own, like an About or a Contact page.`;
		case 'collection':
			return summary.dated ? `${label} are dated entries the site lists together, newest first.` : `${label} are entries the site lists together.`;
		default:
			return `${label} are entries of their own type.`;
	}
}

const emptyText = computed(() => {
	if (filtered.value) {
		return 'Nothing matches this search.';
	}

	if (inTrash.value) {
		return `${heading.value} you move to the trash wait here until you restore them or delete them permanently.`;
	}

	return status.value === 'any' ? `There are no ${inSentence(heading.value)} you can edit.` : `No ${tabs.value.find((tab) => tab.status === status.value)?.label.toLowerCase()} among the ${inSentence(heading.value)}.`;
});
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ heading }}</h1>
			<p class="page-header__hint">{{ terms ? 'Terms that group other entries' : `Every ${singular} you can edit` }}</p>
		</div>
		<div v-if="(can('content.create') && !nothingYet) || can('site.settings')" class="page-header__actions">
			<RouterLink v-if="can('site.settings')" class="button" :to="{ name: 'content-type', params: { name: type } }"><AdminIcon name="layers" />Type settings</RouterLink>
			<RouterLink v-if="can('content.create') && !nothingYet" class="button button--primary" :to="{ name: 'entry-new', query: { type } }">New {{ singular }}</RouterLink>
		</div>
	</header>

	<section v-if="nothingYet" class="panel" aria-labelledby="entries-heading">
		<!-- A type with an index page is never empty: the index page is
		     already there, so the first-run state sits under it (D-255). -->
		<EntryTable v-if="list?.index" :entries="[]" :pinned="list.index" labelledby="entries-heading" date-label="Updated" date-key="updated" :terms="terms" />
		<div class="empty">
			<AdminIcon :name="terms ? 'tag' : 'files'" />
			<h2 id="entries-heading" class="empty__heading">No {{ inSentence(heading) }} yet</h2>
			<p class="empty__text">{{ purpose(info, heading) }}<template v-if="list?.index?.status === 'published'"> The index page above is already live: it's what readers land on.</template></p>
			<RouterLink v-if="can('content.create')" class="button button--primary" :to="{ name: 'entry-new', query: { type } }">Create the first {{ singular }}</RouterLink>
		</div>
	</section>

	<template v-else>
		<nav class="tabs" aria-label="Status">
			<RouterLink v-for="tab in tabs" :key="tab.status" class="tabs__tab" :to="{ query: tabQuery(tab.status) }" :aria-current="status === tab.status ? 'page' : undefined">
				{{ tab.label }}
				<span v-if="counts[tab.status] !== undefined" class="tabs__count">{{ counts[tab.status] }}</span>
			</RouterLink>
		</nav>

		<div class="toolbar" role="search">
			<label class="visually-hidden" for="entries-search">Search {{ inSentence(heading) }}</label>
			<input id="entries-search" v-model="query" class="input" type="search" :placeholder="`Search ${inSentence(heading)}`" autocomplete="off">
			<button v-if="filtered" type="button" class="button button--ghost" @click="clear">Clear filters</button>
		</div>

		<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

		<p v-if="done" class="notice notice--success" role="status">
			{{ done.text }}
			<RouterLink v-if="done.entry" :to="{ name: 'entry-file', params: { id: done.entry.split('/') } }">Open it</RouterLink>
		</p>

		<section v-if="!error || ready" class="panel" aria-labelledby="entries-heading" :aria-busy="loading || busy !== null">
			<header class="panel__header">
				<h2 id="entries-heading">{{ tabs.find((tab) => tab.status === status)?.label }}</h2>
				<p class="panel__hint" aria-live="polite">
					<template v-if="!ready">&nbsp;</template>
					<template v-else-if="inTrash">{{ plural(trashShown.length, singular, inSentence(heading)) }} · Restored entries come back as drafts</template>
					<template v-else-if="list">{{ plural(list.total, singular, inSentence(heading)) }}<template v-if="terms"> · Entries counts the published entries using each</template></template>
				</p>
				<div v-if="ready && inTrash && trash?.length" class="panel__actions">
					<button type="button" class="button button--small button--danger" :disabled="busy !== null" @click="emptyTrash">Empty trash</button>
				</div>
			</header>

			<SkeletonTable v-if="!ready" :columns="skeletonColumns" :rows="skeletonRows" :label="`Loading ${inSentence(heading)}…`" />

			<template v-else-if="inTrash">
				<TrashTable v-if="trashShown.length" :items="trashShown" labelledby="entries-heading" :busy="busy" @restore="restore" @purge="purge" />

				<div v-else class="empty">
					<AdminIcon name="circle-check" />
					<p class="empty__heading">{{ filtered ? 'Nothing in the trash matches' : 'The trash is empty' }}</p>
					<p class="empty__text">{{ emptyText }}</p>
					<button v-if="filtered" type="button" class="button" @click="clear">Clear filters</button>
				</div>
			</template>

			<template v-else-if="list">
				<p v-if="flattened && list.entries.length" class="notebar"><AdminIcon name="info" />{{ flattened }}</p>
				<EntryTable v-if="list.entries.length || list.index" :entries="list.entries" :pinned="list.index" labelledby="entries-heading" date-label="Updated" date-key="updated" :terms="terms" @trash="moveToTrash" />

				<div v-if="!list.entries.length" class="empty">
					<AdminIcon name="files" />
					<p class="empty__heading">{{ filtered ? `No ${inSentence(heading)} match` : `No ${inSentence(heading)}` }}</p>
					<p class="empty__text">{{ emptyText }}</p>
					<button v-if="filtered" type="button" class="button" @click="clear">Clear filters</button>
				</div>

				<nav v-if="list.pages > 1" class="pager" aria-label="Pages">
					<RouterLink v-if="page > 1" class="button button--small" :to="{ query: { ...route.query, page: page - 1 === 1 ? undefined : page - 1 } }">Previous</RouterLink>
					<span class="pager__status">Page {{ list.page }} of {{ list.pages }}</span>
					<RouterLink v-if="page < list.pages" class="button button--small" :to="{ query: { ...route.query, page: page + 1 } }">Next</RouterLink>
				</nav>
			</template>
		</section>
	</template>
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
