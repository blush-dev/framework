<script setup lang="ts">
/**
 * Redirects (D-686; from the redirects sketch), under Settings
 * after Addresses and Search: the `redirects` table's rows, built like
 * the admin's other lists. Tabs with counts (All, Permanent, Temporary,
 * and Problems while there are some), a search (`/`) and a filter for
 * where they go, compact rows, a page at a time, checkboxes for the bulk
 * bar, and a menu on each row. The tab, search, filter, page, and page
 * size live in the URL. Each change saves on its own, with Undo.
 *
 * The search is also the test: an address typed or pasted (a path, or a
 * whole link) finds the rows that handle it, and a line at the top of
 * the table follows it as a visitor would, an arrow with the status for
 * each redirect, to a page, another site, or Not Found, which offers to
 * add a redirect from there.
 *
 * A row's problem (the server works them out on each load) is a chip
 * beside its old address naming the kind; pressing it opens the row's
 * notice, with its one fix, on a row of its own under it. Rows with
 * problems keep working. To says what kind of place it is: an entry by
 * its title with its address under it (followed wherever it moves), a
 * path, or another site, its host in full ink. The site's code's
 * redirects come first; they're listed, read-only, under the table.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, type LocationQueryRaw, type RouteLocationRaw } from 'vue-router';
import { errorMessage } from '../api';
import { debounced, latest, useListAction } from '../action';
import { confirmAction } from '../confirm';
import AdminIcon from '../components/AdminIcon.vue';
import AdminSelect, { type SelectOption } from '../components/AdminSelect.vue';
import BulkBar from '../components/BulkBar.vue';
import DensityToggle from '../components/DensityToggle.vue';
import EmptyState from '../components/EmptyState.vue';
import ListPager from '../components/ListPager.vue';
import MenuButton from '../components/MenuButton.vue';
import RedirectAdded from '../components/RedirectAdded.vue';
import RedirectDialog from '../components/RedirectDialog.vue';
import RedirectMessage from '../components/RedirectMessage.vue';
import RedirectPath from '../components/RedirectPath.vue';
import SkeletonTable from '../components/SkeletonTable.vue';
import { compact } from '../density';
import { formatDate, plural } from '../format';
import { useQueryState } from '../query';
import { deleteRedirects, isPermanent, loadRedirects, restoreRedirects, retypeRedirects, saveRedirect, TYPES, type RedirectList, type RedirectMessage as Message, type RedirectRow, type RedirectStatus, type StoredRedirect, type TraceHop } from '../redirects';
import { useSearchKey } from '../search-key';
import { canType } from '../session';
import { config } from '../config';
import { copyText, toast } from '../toast';
import { useSelectAll } from '../select';

type Tab = 'all' | 'permanent' | 'temporary' | 'problems';

const TABS: { key: Tab; label: string }[] = [
	{ key: 'all', label: 'All' },
	{ key: 'permanent', label: 'Permanent' },
	{ key: 'temporary', label: 'Temporary' },
	{ key: 'problems', label: 'Problems' }
];

const PER_PAGE    = 20;
const PER_OPTIONS = [10, 20, 50, 100];

const route = useRoute();
const { text, set: go } = useQueryState();

const list    = ref<RedirectList | null>(null);
const error   = ref('');
const loading = ref(false);

// The rows chosen for the bulk bar, by old path.
const selected = ref<string[]>([]);

// The rows whose problem notice is open, by old path.
const opened = ref(new Set<string>());

// Whether the site's code's redirects are listed.
const codeOpen = ref(false);

// The form: a row to change, or `true` for a new one.
const editing = ref<{ row: RedirectRow | null; from?: string; repick?: boolean } | null>(null);

const tab    = computed<Tab>(() => TABS.some((item) => item.key === route.query.tab) ? route.query.tab as Tab : 'all');
const search = computed(() => text('search'));
const goes   = computed(() => (text('goes') === 'here' || text('goes') === 'away' ? text('goes') : ''));
const page   = computed(() => Math.max(1, Number(route.query.page) || 1));
const per    = computed(() => PER_OPTIONS.includes(Number(route.query.per)) ? Number(route.query.per) : PER_PAGE);

const filtered = computed(() => search.value !== '' || goes.value !== '');

// Nothing yet, as opposed to nothing matching.
const nothingYet = computed(() => list.value !== null && list.value.counts.all === 0 && !filtered.value);

// Tabs for the kinds the list has; Problems only while there are some.
const tabs = computed(() => TABS.filter((item) => item.key === 'all' || item.key === tab.value || list.value === null || list.value.counts[item.key] > 0));

const goesOptions: SelectOption[] = [
	{ value: '', label: 'Goes anywhere' },
	{ value: 'here', label: 'Goes to this site' },
	{ value: 'away', label: 'Goes to another site' }
];

const goesValue = computed({
	get: () => goes.value,
	set: (value: string) => go({ goes: value || undefined, page: undefined })
});

const perValue = computed({
	get: () => String(per.value),
	set: (value: string) => go({ per: Number(value) === PER_PAGE ? undefined : value, page: undefined })
});

// The search updates the URL a moment after typing stops.
const query = ref(search.value);

watch(query, debounced((value: string) => go({ search: value.trim() || undefined, page: undefined }), 300));

watch(search, (value) => {
	if (value !== query.value.trim()) {
		query.value = value;
	}
});

const searchField = useSearchKey();

function clear(): void {
	query.value = '';
	go({ search: undefined, goes: undefined, page: undefined });
}

function tabQuery(key: Tab): LocationQueryRaw {
	const next: LocationQueryRaw = { ...route.query, tab: key === 'all' ? undefined : key };

	delete next.page;

	return next;
}

function pageLink(to: number): RouteLocationRaw {
	return { query: { ...route.query, page: to === 1 ? undefined : to } };
}

// Only the latest load's answer is shown.
const ask = latest();

async function load(): Promise<void> {
	const current = ask();
	const params  = new URLSearchParams({ tab: tab.value, page: String(page.value), per: String(per.value) });

	if (search.value !== '') {
		params.set('search', search.value);
	}

	if (goes.value !== '') {
		params.set('goes', goes.value);
	}

	loading.value = true;

	try {
		const answer = await loadRedirects(params);

		if (current()) {
			list.value  = answer;
			error.value = '';
		}
	} catch (caught) {
		if (current()) {
			error.value = errorMessage(caught, 'The redirects couldn\'t be loaded.');
		}
	} finally {
		if (current()) {
			loading.value = false;
		}
	}
}

watch(() => [tab.value, search.value, goes.value, page.value, per.value], () => {
	selected.value = [];
	void load();
}, { immediate: true });

const rows = computed(() => list.value?.redirects ?? []);

const caption = computed(() => {
	if (list.value === null) {
		return '';
	}

	const all = list.value.counts[tab.value];

	return filtered.value ? `${list.value.total.toLocaleString()} of ${plural(all, 'redirect')} match` : plural(list.value.total, 'redirect');
});

/*
 * Selecting rows: each on the page, or the page's all at once.
 */
const { state: allState, isChosen, toggle, toggleAll: chooseAll } = useSelectAll(() => rows.value.map((row) => row.from), selected);

function toggleNote(row: RedirectRow): void {
	const next = new Set(opened.value);

	if (!next.delete(row.from)) {
		next.add(row.from);
	}

	opened.value = next;
}

// A change, toasted with its Undo, then the list again.
const { busy, act } = useListAction(load, { error });

// Undo puts rows back as they were kept, as its own action.
function undoing(task: () => Promise<unknown>): () => void {
	return () => void act('undo', async () => {
		await task();

		return 'Put back as it was';
	});
}

function remove(froms: string[]): void {
	void act('change', async () => {
		const answer = await deleteRedirects(froms);

		selected.value = selected.value.filter((from) => !froms.includes(from));

		return {
			message: answer.deleted.length === 1 ? `Deleted the redirect from ${answer.deleted[0]?.from}` : `Deleted ${plural(answer.deleted.length, 'redirect')}`,
			kind: 'danger' as const,
			undo: undoing(() => restoreRedirects(answer.deleted))
		};
	});
}

function retype(froms: string[], status: RedirectStatus): void {
	const word = isPermanent(status) ? 'permanent' : 'temporary';

	// Already the kind asked for (a 308 made permanent) stays as it is.
	const change = rows.value.filter((row) => froms.includes(row.from) && isPermanent(row.status) !== isPermanent(status)).map((row) => row.from);

	if (change.length === 0) {
		toast(`Every selected redirect was already ${word}`, { kind: 'info' });

		return;
	}

	void act('change', async () => {
		const answer = await retypeRedirects(change, status);

		return {
			message: answer.changed.length === 1 ? `Made the redirect from ${answer.changed[0]?.from} ${word}` : `Made ${plural(answer.changed.length, 'redirect')} ${word}`,
			undo: undoing(() => restoreRedirects(answer.changed))
		};
	});
}

async function confirmBulkDelete(): Promise<void> {
	const froms = [...selected.value];

	if (froms.length > 1 && !await confirmAction({ title: `Delete ${plural(froms.length, 'Redirect')}?`, body: 'Their old addresses stop redirecting. Undo puts them back for a moment after.', confirm: 'Delete', danger: true })) {
		return;
	}

	remove(froms);
}

// The form saved a row.
function saved(row: RedirectRow, was: StoredRedirect | null): void {
	const isNew = editing.value?.row === null;

	editing.value = null;

	void act('save', async () => ({
		message: isNew ? `Added a redirect from ${row.from}` : `Saved the redirect from ${row.from}`,
		undo: undoing(() => restoreRedirects(was === null ? [] : [was], [row.from]))
	}));
}

// A row's problem's fix.
function fix(row: RedirectRow, action: NonNullable<Message['fix']>): void {
	switch (action.action) {
		case 'code':
			codeOpen.value = true;
			break;
		case 'delete':
			remove([row.from]);
			break;
		case 'repick':
			editing.value = { row, repick: true };
			break;
		case 'edit':
			editing.value = { row };
			break;
		case 'straight':
			straight(row);
			break;
	}
}

// Points a chained row straight at where its chain ends.
function straight(row: RedirectRow): void {
	const final = row.problem?.final;

	if (!final) {
		return;
	}

	void act('change', async () => {
		const answer = await saveRedirect({ from: row.from, to: final.to ?? '', entry: final.entry ?? null, status: row.status, was: row.from });

		return {
			message: `Pointed ${row.from} straight at ${answer.redirect.entry?.title || answer.redirect.to || 'its page'}`,
			undo: undoing(() => restoreRedirects([row.stored], [row.from]))
		};
	});
}

// Puts an address in the search, to follow it.
function test(row: RedirectRow): void {
	const address = row.from.replace(/\{[^}]*\}/g, 'example');

	query.value = address;
	go({ search: address, tab: undefined, goes: undefined, page: undefined });
	searchField.value?.focus();
}

function openOther(from: string): void {
	const row = rows.value.find((item) => item.from === from);

	editing.value = null;

	if (row) {
		editing.value = { row };
	} else {
		query.value = from;
		go({ search: from, tab: undefined, goes: undefined, page: undefined });
	}
}

function deleteFromForm(row: RedirectRow): void {
	editing.value = null;
	remove([row.from]);
}

/*
 * How each cell reads.
 */
function host(url: string): { scheme: string; host: string; rest: string } {
	try {
		const parsed = new URL(url);

		return { scheme: `${parsed.protocol}//`, host: parsed.host, rest: `${parsed.pathname === '/' && !parsed.search ? '' : parsed.pathname}${parsed.search}` };
	} catch {
		return { scheme: '', host: url, rest: '' };
	}
}

const isUrl = (to: string | null): boolean => to !== null && /^https?:\/\//i.test(to);

const STATE_WORDS: Record<string, string> = {
	trash: 'In the trash',
	draft: 'A draft',
	scheduled: 'Not published yet',
	hidden: 'Not on the site',
	deleted: 'Deleted'
};

function entryLink(row: RedirectRow): RouteLocationRaw | null {
	return row.entry?.type && canType(row.entry.type, 'edit') ? { name: 'entry', params: { type: row.entry.type, id: row.entry.id } } : null;
}

function typeWords(status: RedirectStatus): string {
	return compact.value ? TYPES[status].short : TYPES[status].name;
}

/*
 * The trace: each step of an address as a visitor would follow it.
 */
const trace = computed(() => list.value?.trace ?? null);

interface Step {
	kind: 'start' | 'arrow' | 'is' | 'page' | 'path' | 'away' | 'nf' | 'gone';
	text: string;
	title?: string;
	status?: number;
}

const steps = computed<Step[]>(() => {
	const found = trace.value;

	if (!found || found.path === null) {
		return [];
	}

	const out: Step[] = [{ kind: 'start', text: found.path }];
	let shownEntry = false;

	found.hops.forEach((hop: TraceHop, index: number) => {
		if (hop.t === 'redirect') {
			out.push({ kind: 'arrow', text: String(hop.status), status: hop.status, title: `${TYPES[hop.status].name} ${hop.status}, by ${hop.code ? 'the site\'s code' : `the redirect from ${hop.from}`}` });

			if (hop.target.kind === 'entry') {
				out.push({ kind: 'page', text: hop.target.title ?? 'A page' });
				shownEntry = true;
			} else if (hop.target.kind === 'url') {
				out.push({ kind: 'away', text: host(hop.target.url ?? '').host });
			} else if (hop.target.kind === 'path') {
				out.push({ kind: 'path', text: hop.target.path ?? '' });
				shownEntry = false;
			}
		}

		if (hop.t === 'page' && !(shownEntry && index > 0)) {
			out.push({ kind: 'is', text: 'is' }, { kind: 'page', text: hop.title ?? 'The site\'s own page' });
		}

		if (hop.t === 'missing') {
			out.push({ kind: 'nf', text: 'Not Found' });
		}

		if (hop.t === 'gone') {
			out.push({ kind: 'gone', text: hop.title ? `${hop.title}, not live` : 'Not Found' });
		}

		if (hop.t === 'loop') {
			out.push({ kind: 'nf', text: 'Loops' });
		}
	});

	return out;
});

// Nothing answers the address, and no redirect handles it.
const addFrom = computed(() => trace.value?.hops.length === 1 && trace.value.hops[0]?.t === 'missing' ? trace.value.path : null);

const siteHost = computed(() => {
	try {
		return new URL(config.site.url).host;
	} catch {
		return 'this site';
	}
});
</script>

<template>
	<div class="redirects">
		<header class="page-header">
			<div class="page-header__text">
				<h1 tabindex="-1">Redirects</h1>
				<p class="page-header__hint">Old addresses that send visitors somewhere new</p>
			</div>
			<div v-if="!nothingYet" class="page-header__actions">
				<button type="button" class="button button--primary" @click="editing = { row: null }"><AdminIcon name="plus" />Add Redirect</button>
			</div>
		</header>

		<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

		<section v-if="nothingYet" class="panel">
			<EmptyState icon="corner-down-right" heading="No Redirects Yet" tag="h2" text="A redirect keeps an old address working by sending visitors to a new one. The admin adds one by itself whenever a published entry's address changes, so most sites only come here to add a short link or fix an old one.">
				<template #actions>
					<button type="button" class="button button--primary" @click="editing = { row: null }"><AdminIcon name="plus" />Add Redirect</button>
				</template>
			</EmptyState>
		</section>

		<template v-else>
			<nav class="status-tabs" aria-label="Kinds of redirect">
				<RouterLink v-for="item in tabs" :key="item.key" class="status-tabs__tab" :to="{ query: tabQuery(item.key) }" :aria-current="tab === item.key ? 'page' : undefined">
					{{ item.label }}
					<span v-if="list" class="status-tabs__count">{{ list.counts[item.key].toLocaleString() }}</span>
				</RouterLink>
			</nav>

			<div class="toolbar" role="search">
				<label class="search-field toolbar__search">
					<AdminIcon name="search" />
					<span class="visually-hidden">Search redirects, or test an address</span>
					<input ref="searchField" v-model="query" type="search" placeholder="Search, or paste an address to test" autocomplete="off" spellcheck="false" aria-keyshortcuts="/">
					<kbd class="search-field__key" aria-hidden="true">/</kbd>
				</label>
				<div class="toolbar__filter">
					<label class="visually-hidden" for="redirects-goes">Goes to</label>
					<AdminSelect id="redirects-goes" v-model="goesValue" :options="goesOptions" />
				</div>
				<button v-if="filtered" type="button" class="button button--ghost" @click="clear">Clear Filters</button>
				<DensityToggle />
			</div>

			<section class="panel" :class="{ 'panel--compact': compact }" aria-labelledby="redirects-heading" :aria-busy="loading || busy !== null">
				<header class="panel__header">
					<h2 id="redirects-heading">{{ TABS.find((item) => item.key === tab)?.label }}</h2>
					<p class="panel__hint" aria-live="polite">{{ list ? caption : '\u00a0' }}</p>
				</header>

				<!-- Where an address goes, followed as a visitor would. -->
				<div v-if="trace" class="notebar redirect-trace" aria-live="polite">
					<span class="redirect-trace__label"><AdminIcon name="corner-down-right" />Where it goes</span>
					<template v-if="trace.path === null">
						<span class="redirect-trace__step is-nf"><AdminIcon name="circle-x" />{{ trace.other ? `Another site, ${trace.other}` : 'Not an address' }}</span>
						<span v-if="trace.other" class="redirect-trace__say">Only addresses on {{ siteHost }} can be redirected here.</span>
					</template>
					<span v-else class="redirect-trace__chain">
						<template v-for="(step, index) in steps" :key="index">
							<span v-if="step.kind === 'arrow'" class="redirect-trace__arrow" :title="step.title"><AdminIcon name="arrow-right" /><small class="mono">{{ step.text }}</small><span class="visually-hidden">{{ step.title }}</span></span>
							<span v-else-if="step.kind === 'is'" class="redirect-trace__is">is</span>
							<span v-else class="redirect-trace__step" :class="`is-${step.kind}`">
								<AdminIcon v-if="step.kind === 'page'" name="file-text" />
								<AdminIcon v-else-if="step.kind === 'away'" name="external-link" />
								<AdminIcon v-else-if="step.kind === 'nf' || step.kind === 'gone'" name="circle-x" />
								<span :class="{ mono: step.kind === 'start' || step.kind === 'path' }">{{ step.text }}</span>
							</span>
						</template>
					</span>
					<button v-if="addFrom" type="button" class="lnk" @click="editing = { row: null, from: addFrom }">Add a Redirect From Here</button>
					<span v-if="trace.overruled" class="redirect-trace__say">{{ trace.overruled === 'page' ? 'A page answers this address, so its redirect does nothing.' : 'The site\'s code overrules the row for this address.' }}</span>
				</div>

				<SkeletonTable v-if="list === null" :columns="['', 'From', 'To', 'Type', 'Added', '']" :rows="6" label="Loading redirects…" />

				<div v-else-if="rows.length" class="table-wrap">
					<table class="table redirects-table" aria-labelledby="redirects-heading">
						<thead>
							<tr>
								<th scope="col" class="table__check">
									<button type="button" class="check" role="checkbox" :aria-checked="allState" aria-label="Select every redirect on this page" @click="chooseAll"><AdminIcon :name="allState === 'mixed' ? 'minus' : 'check'" /></button>
								</th>
								<th scope="col">From</th>
								<th scope="col">To</th>
								<th scope="col" class="redirects-table__type">Type</th>
								<th scope="col" class="redirects-table__added">Added</th>
								<th scope="col" class="table__actions"><span class="visually-hidden">Actions</span></th>
							</tr>
						</thead>
						<tbody>
							<template v-for="row in rows" :key="row.from">
								<tr :class="{ 'is-selected': isChosen(row.from), 'has-note': row.problem && opened.has(row.from) }">
									<td class="table__check">
										<button type="button" class="check" role="checkbox" :aria-checked="isChosen(row.from) ? 'true' : 'false'" :aria-label="`Select ${row.from}`" @click="toggle(row.from)"><AdminIcon name="check" /></button>
									</td>
									<th scope="row">
										<span class="redirects-table__from">
											<RedirectPath :path="row.from" />
											<button v-if="row.problem" type="button" class="pill pill--warn redirects-table__chip" :aria-expanded="opened.has(row.from)" :aria-controls="`redirect-note-${row.from}`" @click="toggleNote(row)">{{ row.problem.label }}</button>
										</span>
									</th>
									<td>
										<span v-if="row.entry" class="redirects-table__to">
											<span v-if="row.entry.state !== 'live'" class="redirects-table__gone"><AdminIcon name="file-text" />{{ row.entry.title || 'A page that was deleted' }}</span>
											<RouterLink v-else-if="entryLink(row)" class="lnk" :to="entryLink(row)!"><AdminIcon name="file-text" />{{ row.entry.title || 'Untitled' }}</RouterLink>
											<span v-else><AdminIcon name="file-text" />{{ row.entry.title || 'Untitled' }}</span>
											<span class="entry-title__path">{{ row.entry.state === 'live' ? row.entry.url : STATE_WORDS[row.entry.state] }}</span>
										</span>
										<span v-else-if="isUrl(row.to)" class="mono redirects-table__away">{{ host(row.to ?? '').scheme }}<strong>{{ host(row.to ?? '').host }}</strong>{{ host(row.to ?? '').rest }}<AdminIcon name="external-link" /><span class="visually-hidden"> (another site)</span></span>
										<RedirectPath v-else :path="row.to ?? ''" />
									</td>
									<td class="table__meta redirects-table__type">
										{{ typeWords(row.status) }} <span class="mono redirects-table__code">{{ row.status }}</span>
									</td>
									<td class="table__meta redirects-table__added">
										<template v-if="row.added"><time :datetime="row.added">{{ formatDate(row.added) }}</time><RedirectAdded class="redirects-table__how" :row="row" linked /></template>
										<template v-else>—</template>
									</td>
									<td class="table__actions">
										<MenuButton button-class="row-more" :label="`Actions for ${row.from}`" floating>
											<template #button>
												<AdminIcon name="ellipsis" />
											</template>
											<button type="button" class="menu-item" @click="editing = { row }"><AdminIcon name="pen-line" />Edit</button>
											<button type="button" class="menu-item" @click="test(row)"><AdminIcon name="corner-down-right" />Test this address</button>
											<button type="button" class="menu-item" @click="copyText(row.from, 'the old address', row.from)"><AdminIcon name="copy" />Copy old address</button>
											<div class="menu-divider" />
											<button v-if="isPermanent(row.status)" type="button" class="menu-item" @click="retype([row.from], 302)"><AdminIcon name="clock" />Make temporary</button>
											<button v-else type="button" class="menu-item" @click="retype([row.from], 301)"><AdminIcon name="check" />Make permanent</button>
											<div class="menu-divider" />
											<button type="button" class="menu-item menu-item--danger" @click="remove([row.from])"><AdminIcon name="trash-2" />Delete</button>
										</MenuButton>
									</td>
								</tr>
								<tr v-if="row.problem && opened.has(row.from)" class="redirects-table__note-row">
									<td />
									<td colspan="4">
										<div :id="`redirect-note-${row.from}`" class="notice notice--small notice--warn">
											<AdminIcon name="triangle-alert" />
											<span class="notice__text"><RedirectMessage :message="row.problem.message" @fix="fix(row, $event)" /></span>
										</div>
									</td>
									<td />
								</tr>
							</template>
						</tbody>
					</table>
				</div>

				<EmptyState v-else :icon="filtered ? 'search' : 'circle-check'" :heading="filtered ? 'No Redirects Match' : 'Nothing Here'" :text="filtered ? (search ? `Nothing starts at or goes to ${search}.` : 'No redirect fits these filters.') : 'No redirect is of this kind.'">
					<template #actions>
						<button v-if="filtered" type="button" class="button" @click="clear">Clear Filters</button>
					</template>
				</EmptyState>

				<ListPager v-if="list" v-model:per="perValue" :page="list.page" :pages="list.pages" :total="list.total" :noun="['redirect', 'redirects']" :options="PER_OPTIONS" :link="pageLink" />

				<div v-if="list && list.code.length" class="panel__note redirects-code">
					<button type="button" class="lnk" :aria-expanded="codeOpen" aria-controls="redirects-code-list" @click="codeOpen = !codeOpen">
						<AdminIcon name="code" />The site's code sets {{ plural(list.code.length, 'more redirect') }}. {{ list.code.length === 1 ? 'It comes' : 'They come' }} before this list and can't be changed here.
						<AdminIcon :name="codeOpen ? 'chevron-up' : 'chevron-down'" />
					</button>
					<ul v-if="codeOpen" id="redirects-code-list" class="redirects-code__list">
						<li v-for="item in list.code" :key="item.from">
							<RedirectPath :path="item.from" /><AdminIcon name="arrow-right" /><RedirectPath :path="item.to" />
							<span class="redirects-code__type">{{ TYPES[item.status]?.name ?? item.status }} <span class="mono">{{ item.status }}</span></span>
						</li>
					</ul>
				</div>
			</section>

			<BulkBar v-if="selected.length" :count="selected.length" label="Selected redirects" @clear="selected = []">
				<button type="button" class="button button--ghost button--small" :disabled="busy !== null" @click="retype([...selected], 301)"><AdminIcon name="check" />Make Permanent</button>
				<button type="button" class="button button--ghost button--small" :disabled="busy !== null" @click="retype([...selected], 302)"><AdminIcon name="clock" />Make Temporary</button>
				<span class="bulk-bar__divider" aria-hidden="true" />
				<button type="button" class="button button--ghost button--small button--danger" :disabled="busy !== null" @click="confirmBulkDelete"><AdminIcon name="trash-2" />Delete</button>
			</BulkBar>
		</template>

		<RedirectDialog
			v-if="editing"
			:key="editing.row?.from ?? 'new'"
			:row="editing.row"
			:from="editing.from"
			:repick="editing.repick"
			@close="editing = null"
			@saved="saved"
			@delete="deleteFromForm"
			@other="openOther"
		/>
	</div>
</template>

<style scoped>
.redirects {
	display: contents;
}

.redirects-table {
	min-width: 760px;
}

.redirects-table__from {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
	font-weight: 400;
}

.redirects-table__chip {
	height: 21px;
	border: 0;
	font-size: var(--text-xs);
	cursor: pointer;
}

.redirects-table__to {
	display: grid;
	gap: 2px;
}

.redirects-table__to .icon,
.redirects-table__gone .icon {
	width: 14px;
	height: 14px;
	margin-right: 6px;
	vertical-align: -2px;
}

.redirects-table__gone {
	color: var(--fg-3);
	text-decoration: line-through;
}

.redirects-table__away {
	color: var(--fg-2);
	overflow-wrap: anywhere;
}

.redirects-table__away strong {
	color: var(--fg);
	font-weight: 500;
}

.redirects-table__away .icon {
	width: 12px;
	height: 12px;
	margin-left: 4px;
	vertical-align: -1px;
}

.redirects-table__type {
	width: 150px;
	white-space: nowrap;
}

.redirects-table__added {
	width: 170px;
}

.redirects-table__code {
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.redirects-table__how {
	display: block;
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.panel--compact .redirects-table__how {
	display: none;
}

.redirects-table__note-row td {
	padding-top: 0;
}

.table tbody tr.has-note > * {
	border-bottom: 0;
}

.redirect-trace {
	flex-wrap: wrap;
	gap: var(--s-2) var(--s-3);
}

.redirect-trace__label {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-weight: 600;
	letter-spacing: .06em;
	text-transform: uppercase;
}

.redirect-trace__chain {
	display: inline-flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 6px;
}

.redirect-trace__step {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	padding: 1px 8px;
	border: 1px solid var(--border);
	border-radius: var(--r-1);
	background: var(--surface);
	color: var(--fg);
	font-size: var(--text-sm);
}

.redirect-trace__step .icon {
	width: 13px;
	height: 13px;
	color: var(--fg-3);
}

.redirect-trace__step.is-nf,
.redirect-trace__step.is-gone {
	border-color: var(--danger-dot);
	background: var(--danger-soft);
	color: var(--danger);
}

.redirect-trace__step.is-nf .icon,
.redirect-trace__step.is-gone .icon {
	color: inherit;
}

.redirect-trace__arrow,
.redirect-trace__is {
	display: inline-flex;
	align-items: center;
	gap: 2px;
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.redirect-trace__arrow .icon {
	width: 14px;
	height: 14px;
}

.redirect-trace__say {
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.redirects-code > .lnk {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	text-align: left;
}

.redirects-code__list {
	display: grid;
	gap: 6px;
	margin: var(--s-3) 0 0;
	padding: 0;
	list-style: none;
}

.redirects-code__list li {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
}

.redirects-code__list .icon {
	width: 14px;
	height: 14px;
	color: var(--fg-3);
}

.redirects-code__type {
	margin-left: auto;
	color: var(--fg-3);
	font-size: var(--text-xs);
}
</style>
