<script setup lang="ts">
/**
 * The entries of one content type the account may edit (D-230,
 * `/content/{type}`, D-234): status tabs with counts (with **Mine**
 * after **All** for a type that credits authors, D-475), then one row of
 * filters (D-300): a search (`/` focuses it), an author, each term type
 * the type uses, and how recently it was updated, with **Clear filters**
 * while any is on and a toggle for compact rows (roomy by default,
 * D-265). Headers sort the table by their column, and the pager chooses
 * how many rows a page holds. The filters, sort, and page size live in
 * the URL, so the back button and a shared link restore them. A nesting
 * type's tree is flattened by a tab, a filter, or a sort, and a bar says
 * which and how to get it back.
 *
 * Checkboxes choose rows for the bulk bar (D-301), a pill fixed to the
 * bottom that appears with a selection: the count, **Publish** (for
 * accounts that may) and **Move to draft**, then **Move to trash**, then
 * **Clear**. The selection is the page's: changing the type, tab,
 * filters, sort, or page clears it.
 *
 * What an action did is a toast, in the past tense (admin.md §7, D-302);
 * what it couldn't do is a notice above the list: an error, or the
 * entries a bulk change skipped, each with why. Titles open the editor; the type's own words name
 * the screen and its "New" button. There's no list of every type
 * together: each type has its own.
 *
 * The header, tabs, and search show at once; the table is a skeleton
 * until its entries arrive. A type with no entries at all drops the tabs
 * and search, says what the type is for, and offers its first entry.
 */

import { computed, ref, watch } from 'vue';
import { confirmAction } from '../confirm';
import { confirmLeaving, confirmLeavingMany, confirmPurge } from '../referrers';
import { RouterLink, useRoute, type LocationQueryRaw, type RouteLocationRaw } from 'vue-router';
import { entryPath, errorMessage, request, trashEntry, type ContentTypeSummary, type EntryDetail, type EntryList, type EntrySort, type EntryStatus, type EntrySummary } from '../api';
import { debounced, latest, useListAction } from '../action';
import AdminIcon from '../components/AdminIcon.vue';
import AdminSelect, { type SelectOption } from '../components/AdminSelect.vue';
import BulkBar from '../components/BulkBar.vue';
import DensityToggle from '../components/DensityToggle.vue';
import EmptyState from '../components/EmptyState.vue';
import EntryTable from '../components/EntryTable.vue';
import ListPager from '../components/ListPager.vue';
import { compact } from '../density';
import { useSearchKey } from '../search-key';
import { makeHomepage } from '../homepage';
import SkeletonTable from '../components/SkeletonTable.vue';
import TrashTable from '../components/TrashTable.vue';
import { t } from '../messages';
import MessageText from '../components/MessageText.vue';
import { screenTitle } from '../screen';
import { statusOf } from '../statuses';
import { can, canType, session } from '../session';
import { loadReferences } from '../references';
import { useQueryState } from '../query';
import { profileType, currentType, findType, labelsOf, loadTypes, types } from '../types';

type Tab = EntryStatus | 'any' | 'mine' | 'trash';

// A tab for each status of the type's workflow (D-851), live ones
// first, then Scheduled.
const statusTabs = computed<{ status: EntryStatus | 'any'; label: string }[]>(() => {
	const names = info.value?.statuses ?? ['draft', 'published'];

	return [
		{ status: 'any', label: t('entries_view.tab_all', 'All') },
		...[...names.filter((name) => statusOf(name).live), ...names.filter((name) => !statusOf(name).live)].map((name) => ({ status: name, label: statusOf(name).plural })),
		{ status: 'scheduled', label: statusOf('scheduled').plural },
		// A type with expiry's expired entries (D-852).
		...(info.value?.expires === true ? [{ status: 'expired', label: statusOf('expired').plural }] : [])
	];
});

// Pages of entries hold this many unless the URL says otherwise, and
// these are the sizes the pager offers.
const PER_PAGE    = 20;
const PER_OPTIONS = [10, 20, 50, 100];

// How far back the Updated filter reaches, in days.
const DAYS = [7, 30, 90];

const SORTS: EntrySort[] = ['title', 'status', 'author', 'date', 'updated'];

// The most terms or authors a filter offers.
const OPTION_LIMIT = 100;

// Trash is a tab like the statuses (D-237), and a status (D-484), for
// accounts that can delete.
const canTrash = computed(() => canType(type.value, 'delete'));
// Mine sits after All (D-475): the entries crediting the account's
// profile, for a type that credits authors and an account with a profile.
const mineTab  = computed<{ status: Tab; label: string }[]>(() => mine.value === '' ? [] : [{ status: 'mine', label: t('entries_view.tab_mine', 'Mine') }]);
const tabs     = computed<{ status: Tab; label: string }[]>(() => [
	statusTabs.value[0]!,
	...mineTab.value,
	...statusTabs.value.slice(1),
	...(canTrash.value ? [{ status: 'trash' as const, label: t('entries_view.tab_trash', 'Trash') }] : [])
]);

const route = useRoute();

// The filters live in the URL: `text()` reads one, `go()` changes some.
const { text, set: go } = useQueryState();

const list    = ref<EntryList | null>(null);
const counts  = ref<Partial<Record<Tab, number>>>({});
const error   = ref('');
const loading = ref(false);
const skipped = ref<{ text: string; items: { title: string; reason: string }[] } | null>(null);

// The rows chosen for the bulk bar, by entry ID.
const selected = ref<string[]>([]);

// What the entries on screen were loaded for: the type and tab. Until
// they match what's asked for, the table is a skeleton.
const loaded = ref('');

const status = computed<Tab>(() => {
	const value = route.query.status;

	return tabs.value.some((tab) => tab.status === value) ? value as Tab : 'any';
});

const inTrash = computed(() => status.value === 'trash');

// The profiles type's list, drawn as the profiles sketch has it (D-370).
const profilesList = computed(() => info.value?.kind === 'profiles');

const type   = computed(() => String(route.params.type ?? ''));
const info   = computed(() => findType(type.value));
const search = computed(() => text('search'));
const page   = computed(() => Math.max(1, Number(route.query.page) || 1));
const key    = computed(() => `${type.value}|${status.value}`);
const ready  = computed(() => loaded.value === key.value);

// The filters beyond the search, which the trash doesn't take.
const author = computed(() => inTrash.value || status.value === 'mine' ? '' : text('author'));
const days   = computed(() => inTrash.value || !DAYS.includes(Number(route.query.days)) ? '' : text('days'));
// Profiles' own filter (D-369): linked to an account, or a guest.
const linkedTo = computed(() => !inTrash.value && info.value?.kind === 'profiles' && (route.query.account === 'linked' || route.query.account === 'guest') ? route.query.account : '');
// What links to an entry (Linked From's View All), or only what's linked
// (a bulk warning's Show Only the Linked), D-608.
const linking = computed(() => inTrash.value ? '' : text('linking'));
const via     = computed(() => linking.value === '' ? '' : text('via'));
const linked  = computed(() => !inTrash.value && route.query.linked === '1');
// The entries with more values in a relation than a number: the ones in
// the way of a relationship change (D-610).
const over    = computed(() => inTrash.value ? '' : text('over'));
const above   = computed(() => over.value === '' || !/^\d+$/.test(text('above')) ? '' : text('above'));
const chosen = computed<Record<string, string>>(() => inTrash.value ? {} : Object.fromEntries(
	text('terms').split(',').map((pair) => pair.split(':')).filter((parts) => parts.length === 2 && parts[0] !== '' && parts[1] !== '')
));

// The column it's sorted by and which way; `''` for the usual order.
const sort = computed<EntrySort | ''>(() => SORTS.includes(route.query.sort as EntrySort) && !inTrash.value ? route.query.sort as EntrySort : '');
const dir  = computed<'asc' | 'desc' | ''>(() => sort.value === '' ? '' : (route.query.dir === 'asc' || route.query.dir === 'desc' ? route.query.dir : (sort.value === 'updated' ? 'desc' : 'asc')));
const per  = computed(() => PER_OPTIONS.includes(Number(route.query.per)) ? Number(route.query.per) : PER_PAGE);

const filtered = computed(() => search.value !== '' || author.value !== '' || days.value !== '' || linkedTo.value !== '' || linking.value !== '' || linked.value || over.value !== '' || Object.keys(chosen.value).length > 0);

// A taxonomy's entries (D-593, D-856), and the profiles type's
// (D-329): they're counted by use, not credited.
const terms = computed(() => info.value?.taxonomy === true || info.value?.kind === 'profiles');

// A nesting type lists as a tree on All with no search (D-261); a tab or
// a search flattens it, and a bar says so and how to get it back (the
// design direction's Hierarchy).
const nests     = computed(() => info.value?.kind === 'tree' || info.value?.hierarchical === true);
const flattened = computed(() => {
	if (!nests.value || inTrash.value) {
		return '';
	}

	if (sort.value !== '') {
		return t('entries_view.flat_sorted', 'Sorted by a column, so the tree is flattened. Clear the sort to see the hierarchy.');
	}

	if (search.value !== '') {
		return t('entries_view.flat_search', 'Searching, so the tree is flattened. Clear the search to see the hierarchy.');
	}

	if (filtered.value) {
		return t('entries_view.flat_filtered', 'Filtered, so the tree is flattened. Clear the filters to see the hierarchy.');
	}

	if (status.value === 'mine') {
		return t('entries_view.flat_mine', 'Only yours, so the tree is flattened. Choose All to see the hierarchy.');
	}

	return status.value === 'any' ? '' : t('entries_view.flat_status', 'Filtered by status, so the tree is flattened. Choose All to see the hierarchy.');
});

const labels  = computed(() => labelsOf(type.value));

// A count of the type's entries, in its own words: "3 recipes".
function counted(count: number): string {
	return t('entries_view.count', '{count, plural, one {# {item}} other {# {items}}}', { count, item: labels.value.item, items: labels.value.items });
}
const heading = computed(() => labels.value.plural);

// Nothing yet, as opposed to nothing matching: no entries in any status,
// none in the trash, and no search.
const nothingYet = computed(() => ready.value && !filtered.value && counts.value.any === 0 && (counts.value.trash ?? 0) === 0);

watch([type, info], () => {
	currentType.value = type.value;
	screenTitle.value = heading.value;
}, { immediate: true });

// The search box updates the URL a moment after typing stops.
const query = ref(search.value);

watch(query, debounced((value: string) => go({ search: value.trim() || undefined, page: undefined }), 300));

watch(search, (value) => {
	if (value !== query.value.trim()) {
		query.value = value;
	}
});

function clear(): void {
	query.value = '';
	go({ search: undefined, author: undefined, terms: undefined, days: undefined, account: undefined, linking: undefined, via: undefined, linked: undefined, over: undefined, above: undefined, page: undefined });
}

// The filters' selects write the URL; the first page shows what they find.
const authorValue = computed({
	get: () => author.value,
	set: (value: string) => go({ author: value || undefined, page: undefined })
});

const daysValue = computed({
	get: () => days.value,
	set: (value: string) => go({ days: value || undefined, page: undefined })
});

const linkedValue = computed({
	get: () => linkedTo.value,
	set: (value: string) => go({ account: value || undefined, page: undefined })
});

const linkedOptions: SelectOption[] = [
	{ value: '', label: t('entries_view.any_account', 'Any account') },
	{ value: 'linked', label: t('entries_view.linked_account', 'Linked to an account') },
	{ value: 'guest', label: t('entries_view.guest', 'Guest') }
];

function chooseTerm(termType: string, slug: string): void {
	const next = { ...chosen.value, [termType]: slug };
	const list = Object.entries(next).filter(([, value]) => value !== '').map(([name, value]) => `${name}:${value}`);

	go({ terms: list.join(',') || undefined, page: undefined });
}

const perValue = computed({
	get: () => String(per.value),
	set: (value: string) => go({ per: Number(value) === PER_PAGE ? undefined : value, page: undefined })
});

/**
 * Sorts by a column: again by the same one turns it around; a new one
 * starts A to Z, or newest first for Updated (the prototype's way).
 */
function sortBy(column: EntrySort): void {
	const next = sort.value === column ? (dir.value === 'asc' ? 'desc' : 'asc') : (column === 'updated' ? 'desc' : 'asc');

	go({ sort: column, dir: next === (column === 'updated' ? 'desc' : 'asc') ? undefined : next, page: undefined });
}

function clearSort(): void {
	go({ sort: undefined, dir: undefined, page: undefined });
}

// What each filter offers: the authors and terms the type's entries use,
// loaded with the type. A filter that couldn't load, or has nothing to
// offer, isn't shown.
const authorOptions = ref<SelectOption[]>([]);
const termFilters   = ref<{ termType: string; label: string; options: SelectOption[] }[]>([]);

const dayOptions: SelectOption[] = [
	{ value: '', label: t('entries_view.any_time', 'Any time') },
	...DAYS.map((count) => ({ value: String(count), label: t('entries_view.updated_in', '{count, plural, one {Updated in the last # day} other {Updated in the last # days}}', { count }) }))
];

// A page of the list, keeping the rest of the address.
function pageLink(to: number): RouteLocationRaw {
	return { query: { ...route.query, page: to === 1 ? undefined : to } };
}

// The date the list shows: the one it's in order of (D-413), else when
// each was last changed.
const dateColumn = computed<{ key: 'date' | 'updated'; label: string }>(() => list.value?.by === 'date'
	? { key: 'date', label: t('entries_view.date', 'Date') }
	: { key: 'updated', label: t('entries_view.updated', 'Updated') });

// The rows pinned above the entries: the index page (or Pages' root
// page, D-420), then the authors
// page (D-255, D-329), then Pages' error pages (D-411).
function pinnedOf(answer: EntryList): EntrySummary[] {
	return [answer.index, answer.archivePage, ...(answer.errorPages ?? [])].filter((entry): entry is EntrySummary => entry !== null && entry !== undefined);
}

// The term types that file this type (D-593).
const termTypes = computed(() => types.value.filter((item) => item.taxonomy
	&& item.name !== type.value
	&& (!item.types?.length || item.types.includes(type.value))));

// Only the types that credit authors have an author filter (D-329).
const authored = computed(() => profileType.value !== null && info.value?.authors === true);

// The account's profile's slug, for the Mine tab; `''` for none.
const mine = computed(() => authored.value ? session.account?.author ?? '' : '');

let optionsFor = '';

async function loadOptions(): Promise<void> {
	const wanted = `${type.value}|${termTypes.value.map((item) => item.name).join(',')}|${authored.value ? profileType.value : ''}`;

	if (wanted === optionsFor) {
		return;
	}

	optionsFor = wanted;

	// Only the terms and authors this type's entries use (D-303).
	const people = authored.value && profileType.value !== null ? loadReferences(profileType.value, { limit: OPTION_LIMIT, for: type.value }).catch(() => null) : Promise.resolve(null);
	const groups = Promise.all(termTypes.value.map((item) => loadReferences(item.name, { limit: OPTION_LIMIT, for: type.value }).then(
		(answer) => ({ item, answer }),
		() => null
	)));

	const [authors, found] = await Promise.all([people, groups]);

	// The type changed meanwhile.
	if (optionsFor !== wanted) {
		return;
	}

	authorOptions.value = authors === null || authors.items.length === 0 ? [] : [
		{ value: '', label: t('entries_view.any_author', 'Any author') },
		...authors.items.map((item) => ({ value: item.slug, label: item.title || item.slug }))
	];

	termFilters.value = found.flatMap((group) => group === null || group.answer.items.length === 0 ? [] : [{
		termType: group.item.name,
		label: group.item.labels.singular,
		options: [
			{ value: '', label: t('entries_view.any_term', 'Any {item}', { item: group.item.labels.item }) },
			...group.answer.items.map((item) => ({ value: item.slug, label: item.title || item.slug, depth: item.depth ?? undefined }))
		]
	}]);
}

watch([type, types, profileType], () => {
	authorOptions.value = [];
	termFilters.value   = [];
	void loadOptions();
}, { immediate: true });

// `/` puts the caret in the search, unless something is being typed.
const searchField = useSearchKey();

function tabQuery(tab: Tab): LocationQueryRaw {
	const next: LocationQueryRaw = { ...route.query, status: tab === 'any' ? undefined : tab };

	delete next.page;

	return next;
}

function params(extra: Record<string, string>, own = status.value === 'mine'): string {
	const values = new URLSearchParams({ ...extra, type: type.value });
	const pairs  = Object.entries(chosen.value).map(([name, slug]) => `${name}:${slug}`);

	if (search.value !== '') {
		values.set('search', search.value);
	}

	// Mine is the account's profile as the author.
	if (own && mine.value !== '') {
		values.set('author', mine.value);
	} else if (author.value !== '') {
		values.set('author', author.value);
	}

	if (pairs.length) {
		values.set('terms', pairs.join(','));
	}

	if (days.value !== '') {
		values.set('days', days.value);
	}

	if (linkedTo.value !== '') {
		values.set('account', linkedTo.value);
	}

	if (linking.value !== '') {
		values.set('linking', linking.value);

		if (via.value !== '') {
			values.set('via', via.value);
		}
	}

	if (linked.value) {
		values.set('linked', '1');
	}

	if (over.value !== '') {
		values.set('over', over.value);
		values.set('above', above.value || '0');
	}

	return values.toString();
}

// The list's own: the page, its size, and the sort.
function listParams(): Record<string, string> {
	return {
		status: status.value === 'mine' ? 'any' : status.value,
		page: String(page.value),
		per: String(per.value),
		...(sort.value === '' ? {} : { sort: sort.value, dir: dir.value })
	};
}

// The trash is the list with `status=trash` (D-484), paged and searched
// like the rest.
const trashShown = computed(() => inTrash.value ? list.value?.entries ?? [] : []);

// A skeleton guesses at the rows to come: the tab's last count, up to a
// page, or a handful before anything is known.
const skeletonRows = computed(() => {
	const count = counts.value[status.value];

	return count === undefined ? 6 : Math.max(1, Math.min(count, per.value));
});

const skeletonColumns = computed(() => inTrash.value ? [t('entries_view.col_title', 'Title'), t('entries_view.col_trashed', 'Trashed'), ''] : [t('entries_view.col_title', 'Title'), t('entries_view.col_status', 'Status'), terms.value ? t('entries_view.col_entries', 'Entries') : t('entries_view.col_authors', 'Authors'), t('entries_view.updated', 'Updated'), '']);

// Only the latest load's answer is shown.
const ask = latest();

async function load(): Promise<void> {
	const current = ask();
	const wanted  = key.value;

	// Another type's counts don't guess this one's.
	if (!loaded.value.startsWith(`${type.value}|`)) {
		counts.value = {};
	}

	loading.value = true;
	error.value   = '';

	try {
		const [answer, trashed, own, ...totals] = await Promise.all([
			request<EntryList>('GET', `/entries?${params(listParams())}`),
			canTrash.value ? request<EntryList>('GET', `/entries?${params({ status: 'trash', per: '1' }, false)}`) : Promise.resolve(null),
			mine.value === '' ? Promise.resolve(null) : request<EntryList>('GET', `/entries?${params({ status: 'any', per: '1' }, true)}`),
			...statusTabs.value.map((tab) => request<EntryList>('GET', `/entries?${params({ status: tab.status, per: '1' }, false)}`))
		]);

		// A later load has taken over.
		if (!current()) {
			return;
		}

		list.value   = answer;
		counts.value = {
			...Object.fromEntries(statusTabs.value.map((tab, index) => [tab.status, totals[index]?.total ?? 0])),
			...(own === null ? {} : { mine: own.total }),
			...(trashed === null ? {} : { trash: trashed.total })
		};
		loaded.value = wanted;
	} catch (caught) {
		if (current()) {
			error.value = errorMessage(caught, t('entries_view.load_failed', 'The entries couldn\'t be loaded.'));
		}
	} finally {
		if (current()) {
			loading.value = false;
		}
	}
}

watch(() => [status.value, type.value, search.value, page.value, author.value, days.value, linkedTo.value, linking.value, via.value, linked.value, over.value, above.value, route.query.terms, sort.value, dir.value, per.value], () => {
	selected.value = [];
	void load();
}, { immediate: true });

function nameOf(item: { title: string }): string {
	return item.title === '' ? t('entries_view.untitled_name', 'the untitled {item}', { item: labels.value.item }) : t('entries_view.quoted', '“{title}”', { title: item.title });
}

// A row's, the trash's, or the bulk bar's action, toasted, then the
// list again; a bulk change's skipped rows clear first.
const { busy, act } = useListAction(load, { error, started: () => {
	skipped.value = null;
} });

/**
 * Moves an entry to the trash, as the editor does: at the revision it's
 * at now, so an edit made meanwhile isn't thrown away unseen.
 */
async function moveToTrash(entry: EntrySummary): Promise<void> {
	const id = entry.id;

	if (id === null || !await confirmLeaving(id, nameOf(entry), 'trash')) {
		return;
	}

	void act(id, async () => {
		const undo = await trashEntry(id);

		return {
			message: t('entries_view.trashed', 'Moved {name} to the trash', { name: nameOf(entry) }),
			undo: () => void act(id, async () => {
				await undo();

				return t('entries_view.restored', 'Restored {name}', { name: nameOf(entry) });
			})
		};
	}, 'danger');
}

/**
 * Copies an entry as a draft beside it (D-275).
 */
function duplicate(entry: EntrySummary): void {
	const id = entry.id;

	if (id === null) {
		return;
	}

	void act(id, async () => {
		const copy = await request<EntryDetail>('POST', `${entryPath(id)}/duplicate`);

		return t('entries_view.duplicated', 'Duplicated as a draft: {name}', { name: nameOf(copy) });
	});
}

/**
 * Makes the root page the homepage (D-420), then reloads the list for
 * its new chip.
 */
async function toHomepage(entry: EntrySummary): Promise<void> {
	if (await makeHomepage(entry.title, entry.homeInstead)) {
		await load();
	}
}

type BulkAction = 'publish' | 'draft' | 'trash';

const canPublish = computed(() => canType(type.value, 'publish'));

/**
 * Publishes, moves to draft, or trashes the selected rows at once
 * (`POST entries/bulk`, D-301), then says how many changed and lists any
 * that couldn't, with why.
 */
async function bulk(action: BulkAction): Promise<void> {
	const ids   = [...selected.value];
	const count = counted(ids.length);

	// Live entries linking to them stop showing them (D-598); a selection
	// too long to list shows the list's linked ones instead (D-608).
	const titles = Object.fromEntries(ids.map((id) => [id, list.value?.entries.find((item) => item.id === id)?.title ?? '']));
	const linked = (): void => {
		selected.value = [];
		go({ linked: '1', page: undefined });
	};

	if (action !== 'publish' && !await confirmLeavingMany(titles, count, action, linked)) {
		return;
	}

	void act('bulk', async () => {
		const answer = await request<{ done: string[]; skipped: { id: string; title: string; reason: string }[] }>('POST', '/entries/bulk', { action, ids });
		const moved  = counted(answer.done.length);

		selected.value = [];

		if (answer.skipped.length) {
			skipped.value = {
				text: t('entries_view.skipped', '{count} couldn\'t be {action, select, publish {published} draft {moved to draft} other {moved to the trash}}:', { count: counted(answer.skipped.length), action }),
				items: answer.skipped.map((item) => ({ title: item.title || t('entries_view.untitled', 'Untitled'), reason: item.reason }))
			};
		}

		return answer.done.length === 0
			? t('entries_view.nothing_changed', 'Nothing changed')
			: { publish: t('entries_view.bulk_published', 'Published {count}', { count: moved }), draft: t('entries_view.bulk_drafted', 'Moved {count} to draft', { count: moved }), trash: t('entries_view.bulk_trashed', 'Moved {count} to the trash', { count: moved }) }[action];
	}, action === 'trash' ? 'danger' : 'good');
}

function restore(item: EntrySummary): void {
	const id = item.id;

	if (id === null) {
		return;
	}

	void act(id, async () => {
		await request<{ id: string }>('POST', `${entryPath(id)}/restore`);

		return t('entries_view.restored_draft', 'Restored {name} as a draft', { name: nameOf(item) });
	});
}

async function purge(item: EntrySummary): Promise<void> {
	const id = item.id;

	const unlink = id === null ? null : await confirmPurge(id, nameOf(item));

	if (id === null || unlink === null) {
		return;
	}

	void act(id, async () => {
		const answer = await request<{ deleted: string; unlinked: number }>('DELETE', `${entryPath(id)}?permanently=1${unlink}`);

		return answer.unlinked > 0
			? t('entries_view.deleted_unlinked', 'Deleted {name} permanently, and took it out of {count, plural, one {# entry} other {# entries}}', { name: nameOf(item), count: answer.unlinked })
			: t('entries_view.deleted', 'Deleted {name} permanently', { name: nameOf(item) });
	}, 'danger');
}

async function emptyTrash(): Promise<void> {
	const count = counts.value.trash ?? 0;

	if (!await confirmAction({ title: t('entries_view.empty_title', 'Empty the Trash?'), body: t('entries_view.empty_body', '{count} in the trash will be deleted permanently. This can\'t be undone.', { count: counted(count) }), confirm: t('entries_view.empty_confirm', 'Empty the Trash'), danger: true })) {
		return;
	}

	void act('empty', async () => {
		const answer = await request<{ deleted: number }>('POST', '/entries/empty-trash', { type: type.value });

		return t('entries_view.emptied', 'Deleted {count} permanently', { count: counted(answer.deleted) });
	}, 'danger');
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

	if (summary?.taxonomy) {
		return t('entries_view.purpose_terms', '{label} file other entries. Each one is an entry of its own, with a page listing what\'s filed under it.', { label });
	}

	switch (summary?.kind) {
		case 'tree':
			return summary.folder === '' ? t('entries_view.purpose_flat_tree', '{label} stand on their own, like an About or a Contact page.', { label }) : t('entries_view.purpose_tree', '{label} nest by folder, each at its own address.', { label });
		case 'collection':
			return summary.dated ? t('entries_view.purpose_dated', '{label} are dated entries the site lists together, newest first.', { label }) : t('entries_view.purpose_collection', '{label} are entries the site lists together.', { label });
		default:
			return t('entries_view.purpose_other', '{label} are entries of their own type.', { label });
	}
}

const emptyText = computed(() => {
	if (filtered.value) {
		return search.value !== '' && !author.value && !days.value && !Object.keys(chosen.value).length ? t('entries_view.no_search_match', 'Nothing matches this search.') : t('entries_view.no_filter_match', 'Nothing matches these filters.');
	}

	if (inTrash.value) {
		return t('entries_view.empty_trash_text', '{label} you move to the trash wait here until you restore them or delete them permanently.', { label: heading.value });
	}

	if (status.value === 'mine') {
		return t('entries_view.empty_mine', 'None of the {items} credit you yet.', { items: labels.value.items });
	}

	return status.value === 'any'
		? t('entries_view.empty_any', 'There are no {items} you can edit.', { items: labels.value.items })
		: t('entries_view.empty_status', 'No {what} among the {items}.', { what: status.value === 'draft' ? t('entries_view.drafts', 'drafts') : tabs.value.find((tab) => tab.status === status.value)?.label.toLowerCase(), items: labels.value.items });
});
</script>

<template>
	<!-- Profiles are drawn as the profiles sketch has them (D-370); every
	     other type's list keeps the direction's look. -->
	<div :class="profilesList ? 'people' : 'entries-root'">
		<header class="page-header">
			<div class="page-header__text">
				<h1 tabindex="-1">{{ heading }}</h1>
				<p class="page-header__hint">{{ info?.kind === 'profiles' ? t('entries_view.hint_profiles', 'Public identities. Every byline on the site points at one.') : (terms ? t('entries_view.hint_taxonomy', 'Entries that group other entries') : t('entries_view.hint', 'Every {item} you can edit', { item: labels.item })) }}</p>
			</div>
			<div v-if="(canType(type, 'create') && !nothingYet) || can('site.settings')" class="page-header__actions">
				<RouterLink v-if="can('site.settings')" class="button" :to="{ name: 'content-type', params: { name: type } }"><AdminIcon name="layers" />{{ t('entries_view.type_settings', 'Type Settings') }}</RouterLink>
				<RouterLink v-if="canType(type, 'create') && !nothingYet" class="button button--primary" :to="{ name: 'entry-new', query: { type } }">{{ labels.newItem }}</RouterLink>
			</div>
		</header>

		<section v-if="nothingYet" class="panel" aria-labelledby="entries-heading">
			<!-- A type with an index page is never empty: the index page is
			     already there, so the first-run state sits under it (D-255). -->
			<EntryTable v-if="list && pinnedOf(list).length" :entries="[]" :pinned="pinnedOf(list)" labelledby="entries-heading" :date-label="dateColumn.label" :date-key="dateColumn.key" :terms="terms && info?.kind !== 'profiles'" :profiles="info?.kind === 'profiles'" @homepage="toHomepage" />
			<EmptyState :icon="info?.kind === 'profiles' ? 'user-round' : (terms ? 'tag' : 'files')" :heading="t('entries_view.none_yet', 'No {label} Yet', { label: heading })" tag="h2" heading-id="entries-heading">
				{{ purpose(info, heading) }}<template v-if="list?.index?.status === 'published'"> {{ t('entries_view.index_live', 'The index page above is already live: it\'s what readers land on.') }}</template>
				<template #actions>
					<RouterLink v-if="canType(type, 'create')" class="button button--primary" :to="{ name: 'entry-new', query: { type } }">{{ t('entries_view.create_first', 'Create the First {item}', { item: labels.item }) }}</RouterLink>
				</template>
			</EmptyState>
		</section>

		<template v-else>
			<nav class="status-tabs" :aria-label="t('entries_view.status', 'Status')">
				<RouterLink v-for="tab in tabs" :key="tab.status" class="status-tabs__tab" :to="{ query: tabQuery(tab.status) }" :aria-current="status === tab.status ? 'page' : undefined">
					{{ tab.label }}
					<span v-if="counts[tab.status] !== undefined" class="status-tabs__count">{{ counts[tab.status] }}</span>
				</RouterLink>
			</nav>

			<div class="toolbar" role="search">
				<label class="search-field toolbar__search">
					<AdminIcon name="search" />
					<span class="visually-hidden">{{ labels.searchItems }}</span>
					<input id="entries-search" ref="searchField" v-model="query" type="search" :placeholder="labels.searchItems" autocomplete="off" aria-keyshortcuts="/">
					<kbd class="search-field__key" aria-hidden="true">/</kbd>
				</label>
				<template v-if="!inTrash">
					<div v-if="authorOptions.length && status !== 'mine'" class="toolbar__filter">
						<label class="visually-hidden" for="entries-author">{{ t('entries_view.author', 'Author') }}</label>
						<AdminSelect id="entries-author" v-model="authorValue" :options="authorOptions" />
					</div>
					<div v-for="filter in termFilters" :key="filter.termType" class="toolbar__filter">
						<label class="visually-hidden" :for="`entries-${filter.termType}`">{{ filter.label }}</label>
						<AdminSelect :id="`entries-${filter.termType}`" :model-value="chosen[filter.termType] ?? ''" :options="filter.options" @update:model-value="chooseTerm(filter.termType, $event)" />
					</div>
					<div v-if="info?.kind === 'profiles'" class="toolbar__filter">
						<label class="visually-hidden" for="entries-account">{{ t('entries_view.account', 'Account') }}</label>
						<AdminSelect id="entries-account" v-model="linkedValue" :options="linkedOptions" />
					</div>
					<div class="toolbar__filter">
						<label class="visually-hidden" for="entries-days">{{ t('entries_view.updated', 'Updated') }}</label>
						<AdminSelect id="entries-days" v-model="daysValue" :options="dayOptions" />
					</div>
				</template>
				<button v-if="filtered" type="button" class="button button--ghost" @click="clear">{{ t('entries_view.clear_filters', 'Clear Filters') }}</button>
				<span v-if="profilesList" class="toolbar__count" aria-live="polite">{{ !ready ? t('common.loading', 'Loading…') : (list ? counted(list.total) : '') }}</span>
				<DensityToggle v-if="!inTrash && !profilesList" />
			</div>

			<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

			<div v-if="skipped" class="notice notice--warn" role="status">
				{{ skipped.text }}
				<ul>
					<li v-for="(item, index) in skipped.items" :key="index"><strong>{{ item.title }}</strong>: {{ item.reason }}</li>
				</ul>
			</div>

			<section v-if="!error || ready" class="panel" :class="{ 'panel--compact': compact }" aria-labelledby="entries-heading" :aria-busy="loading || busy !== null">
				<h2 v-if="profilesList && !inTrash" id="entries-heading" class="visually-hidden">{{ tabs.find((tab) => tab.status === status)?.label }}</h2>
				<header v-else class="panel__header">
					<h2 id="entries-heading">{{ tabs.find((tab) => tab.status === status)?.label }}</h2>
					<p class="panel__hint" aria-live="polite">
						<template v-if="!ready">&nbsp;</template>
						<template v-else-if="inTrash && list">{{ counted(list.total) }} · {{ t('entries_view.restored_hint', 'Restored entries come back as drafts') }}</template>
						<template v-else-if="list">{{ counted(list.total) }}<template v-if="info?.kind === 'profiles'"> · {{ t('entries_view.bylines_hint', 'Bylines counts the published entries crediting each') }}</template><template v-else-if="terms"> · {{ t('entries_view.entries_hint', 'Entries counts the published entries using each') }}</template></template>
					</p>
					<div v-if="ready && inTrash && list?.total" class="panel__actions">
						<button type="button" class="button button--small button--danger" :disabled="busy !== null" @click="emptyTrash">{{ t('entries_view.empty_trash', 'Empty Trash') }}</button>
					</div>
				</header>

				<SkeletonTable v-if="!ready" :columns="skeletonColumns" :rows="skeletonRows" :label="t('entries_view.loading', 'Loading {items}…', { items: labels.items })" />

				<template v-else-if="inTrash">
					<TrashTable v-if="trashShown.length" :items="trashShown" labelledby="entries-heading" :busy="busy" @restore="restore" @purge="purge" />

					<EmptyState v-else icon="circle-check" :heading="filtered ? t('entries_view.trash_no_match', 'Nothing in the Trash Matches') : t('entries_view.trash_empty', 'The Trash Is Empty')" :text="emptyText">
						<template #actions>
							<button v-if="filtered" type="button" class="button" @click="clear">{{ t('entries_view.clear_filters', 'Clear Filters') }}</button>
						</template>
					</EmptyState>
				</template>

				<template v-else-if="list">
					<p v-if="list.linking || list.linked" class="notebar">
						<AdminIcon name="link" />
						<span v-if="list.linking"><MessageText :text="list.linking.via ? t('entries_view.linking_via', 'Only the {items} linking to {title} through {via}.', { items: labels.items, via: list.linking.via }) : t('entries_view.linking', 'Only the {items} linking to {title}.', { items: labels.items })"><template #title><strong>{{ list.linking.title || t('entries_view.untitled', 'Untitled') }}</strong></template></MessageText></span>
						<span v-else>{{ t('entries_view.linked', 'Only the {items} live entries link to.', { items: labels.items }) }}</span>
						<button type="button" class="button button--ghost button--small notebar__action" @click="go({ linking: undefined, via: undefined, linked: undefined, page: undefined })">{{ t('entries_view.show_all', 'Show All') }}</button>
					</p>
					<p v-if="list.over" class="notebar">
						<AdminIcon name="workflow" />
						<span><MessageText :text="list.over.above === 0 ? t('entries_view.over_any', 'Only the {items} with any in {label}.', { items: labels.items }) : t('entries_view.over_more', 'Only the {items} with more than {above} in {label}.', { items: labels.items, above: list.over.above })"><template #label><strong>{{ list.over.label }}</strong></template></MessageText></span>
						<button type="button" class="button button--ghost button--small notebar__action" @click="go({ over: undefined, above: undefined, page: undefined })">{{ t('entries_view.show_all', 'Show All') }}</button>
					</p>
					<p v-if="flattened && list.entries.length" class="notebar">
						<AdminIcon name="info" />{{ flattened }}
						<button v-if="sort" type="button" class="button button--ghost button--small notebar__action" @click="clearSort">{{ t('entries_view.clear_sort', 'Clear the Sort') }}</button>
					</p>
					<EntryTable
						v-if="list.entries.length || pinnedOf(list).length"
						:entries="list.entries"
						:pinned="pinnedOf(list)"
						labelledby="entries-heading"
						:date-label="dateColumn.label"
						:date-key="dateColumn.key"
						:terms="terms && info?.kind !== 'profiles'"
						:profiles="info?.kind === 'profiles'"
						v-model:selected="selected"
						selectable
						sortable
						:sort="sort || null"
						:dir="dir || null"
						@trash="moveToTrash"
						@duplicate="duplicate"
						@homepage="toHomepage"
						@sort="sortBy"
					/>

					<EmptyState v-if="!list.entries.length" icon="files" :heading="filtered ? t('entries_view.none_match', 'No {label} Match', { label: heading }) : t('entries_view.none', 'No {label}', { label: heading })" :text="emptyText">
						<template #actions>
							<button v-if="filtered" type="button" class="button" @click="clear">{{ t('entries_view.clear_filters', 'Clear Filters') }}</button>
						</template>
					</EmptyState>
				</template>

				<ListPager v-if="ready && list" v-model:per="perValue" :page="page" :pages="list.pages" :total="list.total" :counted="(count) => t('entries_view.counted', '{count, plural, one {# {item}} other {# {items}}}', { count, item: labels.item, items: labels.items })" :options="PER_OPTIONS" :link="pageLink" />

				<p v-if="profilesList && !inTrash && ready" class="panel__note"><MessageText :text="t('entries_view.profiles_note', '{guest} is a profile with no account: it can be credited and has an archive, but can\'t sign in. {bylines} counts every published entry of every type that credits the profile.')"><template #guest><strong>{{ t('entries_view.guest', 'Guest') }}</strong></template><template #bylines><strong>{{ t('entries_view.bylines', 'Bylines') }}</strong></template></MessageText></p>
			</section>

			<BulkBar v-if="selected.length && !inTrash" :count="selected.length" :label="t('entries_view.bulk_actions', 'Bulk actions')" @clear="selected = []">
				<button v-if="canPublish" type="button" class="button button--ghost button--small" :disabled="busy !== null" @click="bulk('publish')"><AdminIcon name="circle-check" />{{ t('entries_view.publish', 'Publish') }}</button>
				<button type="button" class="button button--ghost button--small" :disabled="busy !== null" @click="bulk('draft')"><AdminIcon name="file-text" />{{ t('entries_view.move_draft', 'Move to Draft') }}</button>
				<template v-if="canTrash">
					<span class="bulk-bar__divider" aria-hidden="true" />
					<button type="button" class="button button--ghost button--small button--danger" :disabled="busy !== null" @click="bulk('trash')"><AdminIcon name="trash-2" />{{ t('entries_view.move_trash', 'Move to Trash') }}</button>
				</template>
			</BulkBar>
		</template>
	</div>
</template>

<style scoped>
.entries-root {
	display: contents;
}

.notebar__action {
	margin: -4px 0 -4px auto;
}
</style>
