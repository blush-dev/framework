<script lang="ts">
import { ref } from 'vue';

// Which branches are collapsed, by entry ID: held client-side (the design
// direction's Hierarchy), for as long as the admin is open.
const collapsed = ref(new Set<string>());
</script>

<script setup lang="ts">
/**
 * A table of one type's entries: the title (a link to the editor),
 * indented 18px a level under its parent with a disclosure triangle when
 * the list is a tree (D-262), or else after the titles of the entries
 * above it, with its address on the site beneath, the status, the authors (or, for a
 * taxonomy's terms, how many published entries use each), a date, and a
 * menu of what to do with each (D-254): **Edit**, **View** and **Copy
 * link** once it's live, **Duplicate** (not for terms, D-275) and **Move
 * to trash** when the account may.
 * Files and folders aren't shown. Labeled by the heading whose id it's
 * given.
 *
 * The type's index page, when there is one, is `pinned` in a body of its
 * own above the rest (D-255): the same row, tinted, with a pin and an
 * **Index** tag, and never with **Duplicate** or **Move to trash**. Its
 * people field's list page (D-329, D-353) is pinned under it the same
 * way, tagged with the field's name (**Authors**, **Cooks**), and may be
 * trashed. Pages' error pages are pinned the same way (D-411), tagged
 * with their status (**Error 404**), never duplicated.
 *
 * A tree page that starts inside a branch begins with the entries above
 * it, marked **Continued** (D-263). Collapsing a branch hides the rows
 * under it on this page.
 *
 * When `selectable`, a first column of checkboxes chooses rows for the
 * bulk bar (`selected`, by entry ID; D-301), and its header selects every
 * row shown on the page, or none, and is mixed when some are. The index
 * page isn't selectable: its pin takes the checkbox's place. Rows marked
 * **Continued** aren't either, since they're another page's.
 *
 * When `sortable`, the Title, Status, Authors, and Updated headers are
 * buttons that ask to sort by their column (`sort`), and the column it's
 * sorted by is marked with `aria-sort` and an arrow (D-300). A term's
 * Entries column doesn't sort.
 */

import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { entryRoute, type EntrySort, type EntrySummary } from '../api';
import { listRoute } from '../types';
import { config } from '../config';
import { formatDate } from '../format';
import { initials } from '../people';
import { toast } from '../toast';
import AdminIcon from './AdminIcon.vue';
import MenuButton from './MenuButton.vue';
import StatusPill from './StatusPill.vue';

const { terms = false, profiles = false, pinned = [], entries, sortable = false, sort = null, dir = null, dateKey, dateLabel, selectable = false } = defineProps<{
	entries: EntrySummary[];
	pinned?: EntrySummary[];
	labelledby: string;
	dateLabel: string;
	dateKey: 'updated' | 'published';
	terms?: boolean;
	// Profiles (D-353, D-369): named, beside an avatar (dashed for a
	// guest), with the account each is linked to (a guest without one)
	// and how many published entries credit them. A name opens the
	// profile's screen, and the menu adds **Edit profile**.
	profiles?: boolean;
	sortable?: boolean;
	sort?: EntrySort | null;
	dir?: 'asc' | 'desc' | null;
	selectable?: boolean;
}>();

const selected = defineModel<string[]>('selected', { default: () => [] });

defineEmits<{
	trash: [entry: EntrySummary];
	duplicate: [entry: EntrySummary];
	sort: [column: EntrySort];
}>();

// The headers, and what each sorts by (`null` for none).
const columns = computed<{ label: string; sort: EntrySort | null; class?: string }[]>(() => profiles ? [
	{ label: 'Name', sort: 'title' },
	{ label: 'Status', sort: 'status' },
	{ label: 'Account', sort: null },
	{ label: 'Bylines', sort: null, class: 'table__count' },
	{ label: dateLabel, sort: dateKey }
] : [
	{ label: 'Title', sort: 'title' },
	{ label: 'Status', sort: 'status' },
	terms ? { label: 'Entries', sort: null, class: 'table__count' } : { label: 'Authors', sort: 'author' },
	{ label: dateLabel, sort: dateKey }
]);

function ariaSort(column: EntrySort | null): 'ascending' | 'descending' | undefined {
	return column !== null && column === sort ? (dir === 'desc' ? 'descending' : 'ascending') : undefined;
}

// The rows a collapsed branch hides: those under it, by depth.
const hidden = computed(() => {
	const ids   = new Set<string>();
	const above: { depth: number; closed: boolean }[] = [];

	for (const entry of entries) {
		if (entry.depth === null) {
			continue;
		}

		while (above.length && above[above.length - 1]!.depth >= entry.depth) {
			above.pop();
		}

		const inClosed = above.some((item) => item.closed);

		if (inClosed) {
			ids.add(entry.id);
		}

		above.push({ depth: entry.depth, closed: inClosed || collapsed.value.has(entry.id) });
	}

	return ids;
});

// Whether the list is a tree, so every title leaves the triangle's space,
// the pinned index page's too (D-264).
const tree = computed(() => entries.some((entry) => entry.depth !== null));

// The pinned index page's body, then the entries'.
const groups = computed(() => [
	...(pinned.length === 0 ? [] : [{ key: 'pinned', entries: pinned }]),
	{ key: 'entries', entries: entries.filter((entry) => !hidden.value.has(entry.id)) }
]);

// The rows shown that can be selected, and how many of them are.
const choosable = computed(() => groups.value.flatMap((group) => group.entries).filter(canSelect).map((entry) => entry.id));
const chosen    = computed(() => choosable.value.filter((id) => selected.value.includes(id)).length);
const allState  = computed<'true' | 'false' | 'mixed'>(() => chosen.value === 0 ? 'false' : (chosen.value === choosable.value.length ? 'true' : 'mixed'));

function pinTitle(entry: EntrySummary): string {
	if (entry.errorPage !== null) {
		return `Pinned: the page the site shows for error ${entry.errorPage}`;
	}

	return entry.index ? 'Pinned: the index page for this type' : `Pinned: the page introducing this type's ${(entry.peopleLabel ?? 'people').toLowerCase()}`;
}

function isPinned(entry: EntrySummary): boolean {
	return entry.index || entry.authorsPage || entry.errorPage !== null;
}

function canSelect(entry: EntrySummary): boolean {
	return !isPinned(entry) && !entry.continued;
}

function isSelected(entry: EntrySummary): boolean {
	return selected.value.includes(entry.id);
}

function choose(entry: EntrySummary): void {
	selected.value = isSelected(entry) ? selected.value.filter((id) => id !== entry.id) : [...selected.value, entry.id];
}

// All of the page's rows, or none once all are.
function chooseAll(): void {
	const page = new Set(choosable.value);

	selected.value = allState.value === 'true'
		? selected.value.filter((id) => !page.has(id))
		: [...new Set([...selected.value, ...choosable.value])];
}

function toggle(entry: EntrySummary): void {
	const next = new Set(collapsed.value);

	if (!next.delete(entry.id)) {
		next.add(entry.id);
	}

	collapsed.value = next;
}

// A live entry's full address, to visit or share.
function liveUrl(entry: EntrySummary): string | null {
	return entry.status === 'published' && entry.url !== null ? new URL(entry.url, config.site.url).href : null;
}

async function copyLink(entry: EntrySummary): Promise<void> {
	const url = liveUrl(entry);

	if (url === null) {
		return;
	}

	try {
		await navigator.clipboard.writeText(url);
		toast('Link copied');
	} catch {
		toast("The link couldn't be copied", { kind: 'warn' });
	}
}
</script>

<template>
	<div class="table-wrap">
		<table class="table" :aria-labelledby="labelledby">
			<thead>
				<tr>
					<th v-if="selectable" scope="col" class="table__check">
						<button type="button" class="check" role="checkbox" :aria-checked="allState" aria-label="Select every row on this page" :disabled="!choosable.length" @click="chooseAll"><AdminIcon :name="allState === 'mixed' ? 'minus' : 'check'" /></button>
					</th>
					<th v-for="column in columns" :key="column.label" scope="col" :class="column.class" :aria-sort="sortable ? ariaSort(column.sort) : undefined">
						<button v-if="sortable && column.sort !== null" type="button" class="table__sort" @click="$emit('sort', column.sort)">
							{{ column.label }}<AdminIcon :name="ariaSort(column.sort) === 'ascending' ? 'arrow-up' : 'arrow-down'" class="table__sort-icon" />
						</button>
						<template v-else>{{ column.label }}</template>
					</th>
					<th scope="col" class="table__actions"><span class="visually-hidden">Actions</span></th>
				</tr>
			</thead>
			<tbody v-for="group in groups" :key="group.key" :class="{ 'table__pinned': group.key === 'pinned' }">
				<tr v-for="entry in group.entries" :key="`${entry.id}${entry.continued ? ':continued' : ''}`" :class="{ 'is-selected': selectable && isSelected(entry) }">
					<td v-if="selectable" class="table__check">
						<span v-if="isPinned(entry)" class="table__pin" :title="pinTitle(entry)"><AdminIcon name="pin" /><span class="visually-hidden">Pinned</span></span>
						<button v-else-if="canSelect(entry)" type="button" class="check" role="checkbox" :aria-checked="isSelected(entry) ? 'true' : 'false'" :aria-label="`Select ${entry.title || 'Untitled'}`" @click="choose(entry)"><AdminIcon name="check" /></button>
					</td>
					<th scope="row">
						<span class="title-cell" :style="entry.depth ? { '--depth': entry.depth } : undefined">
						<button v-if="entry.children" type="button" class="twist" :aria-expanded="!collapsed.has(entry.id)" :aria-label="`${collapsed.has(entry.id) ? 'Expand' : 'Collapse'} ${entry.title || 'Untitled'}`" @click="toggle(entry)"><AdminIcon name="chevron-right" /></button>
						<span v-else-if="entry.depth !== null || (tree && group.key === 'pinned')" class="twist twist--leaf" aria-hidden="true" />
						<span v-if="profiles" class="avatar" :class="{ 'avatar--guest': !entry.linked }" aria-hidden="true">{{ initials(entry.title || '?') }}</span>
						<span class="entry-title">
							<span class="entry-title__text">
								<span v-if="isPinned(entry) && !selectable" class="entry-title__pin" :title="pinTitle(entry)"><AdminIcon name="pin" /></span>
								<span v-if="entry.depth === null && entry.ancestors.length" class="entry-title__ancestors">{{ entry.ancestors.join(' › ') }} ›{{ ' ' }}</span>
								<RouterLink class="entry-title__link" :to="listRoute(entry)">
									<template v-if="entry.title">{{ entry.title }}</template>
									<span v-else class="untitled">Untitled</span>
								</RouterLink>
								<template v-if="entry.index">{{ ' ' }}<span class="index-mark">Index</span></template>
								<template v-if="entry.authorsPage">{{ ' ' }}<span class="index-mark">{{ entry.peopleLabel ?? 'People' }}</span></template>
								<template v-if="entry.errorPage !== null">{{ ' ' }}<span class="index-mark">Error {{ entry.errorPage }}</span></template>
								{{ ' ' }}<span v-if="entry.own && profiles" class="tag--you">You</span><span v-else-if="entry.own" class="tag">Yours</span>
								{{ ' ' }}<span v-if="entry.continued" class="tag" title="Listed on an earlier page; shown again above the entries under it">Continued</span>
							</span>
							<span v-if="entry.url" class="entry-title__path">{{ entry.url }}</span>
						</span>
						</span>
					</th>
					<td><StatusPill :status="entry.status" /></td>
					<td v-if="profiles">
						<template v-if="entry.account"><RouterLink class="lnk" :to="{ name: 'account', params: { username: entry.account.username } }">{{ entry.account.displayName }}</RouterLink>{{ ' ' }}<span class="entry-title__path mono">{{ entry.account.username }}</span></template>
						<template v-else-if="entry.linked">Linked</template>
						<span v-else class="tag" title="No account is linked to this profile">Guest</span>
					</td>
					<td v-if="terms || profiles" class="table__meta table__count">{{ entry.uses?.toLocaleString() ?? '—' }}</td>
					<td v-else class="table__meta">{{ entry.authors.join(', ') || '—' }}</td>
					<td class="table__meta">
						<time v-if="entry[dateKey]" :datetime="entry[dateKey] ?? undefined">{{ formatDate(entry[dateKey] ?? '') }}</time>
						<template v-else>—</template>
					</td>
					<td class="table__actions">
						<MenuButton button-class="row-more" :label="`Actions for ${entry.title || 'Untitled'}`" floating>
							<template #button>
								<AdminIcon name="ellipsis" />
							</template>
							<RouterLink v-if="profiles" class="menu-item" :to="listRoute(entry)"><AdminIcon name="user-round" />Open</RouterLink>
							<RouterLink class="menu-item" :to="entryRoute(entry)"><AdminIcon name="pen-line" />{{ profiles ? 'Edit profile' : 'Edit' }}</RouterLink>
							<template v-if="liveUrl(entry)">
								<a class="menu-item" :href="liveUrl(entry) ?? undefined" target="_blank" rel="noopener">
									<AdminIcon name="external-link" />{{ terms ? 'View archive' : 'View' }}<span class="visually-hidden"> (new tab)</span>
								</a>
								<button type="button" class="menu-item" @click="copyLink(entry)"><AdminIcon name="link" />Copy link</button>
							</template>
							<button v-if="entry.can.duplicate && !terms && !profiles" type="button" class="menu-item" @click="$emit('duplicate', entry)"><AdminIcon name="copy" />Duplicate</button>
							<template v-if="entry.can.delete">
								<div class="menu-divider" />
								<button type="button" class="menu-item menu-item--danger" @click="$emit('trash', entry)"><AdminIcon name="trash-2" />Move to trash</button>
							</template>
						</MenuButton>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<style scoped>
/* A profile's avatar, beside its name and address. */
.title-cell > .avatar {
	align-self: center;
	margin-right: var(--s-2);
}
</style>
