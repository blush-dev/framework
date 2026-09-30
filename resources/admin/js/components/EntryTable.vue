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
 * **Index** tag, and never with **Duplicate** or **Move to trash**.
 *
 * A tree page that starts inside a branch begins with the entries above
 * it, marked **Continued** (D-263). Collapsing a branch hides the rows
 * under it on this page.
 */

import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { entryRoute, type EntrySummary } from '../api';
import { config } from '../config';
import { formatDate } from '../format';
import { toast } from '../toast';
import AdminIcon from './AdminIcon.vue';
import MenuButton from './MenuButton.vue';
import StatusPill from './StatusPill.vue';

const { terms = false, pinned = null, entries } = defineProps<{
	entries: EntrySummary[];
	pinned?: EntrySummary | null;
	labelledby: string;
	dateLabel: string;
	dateKey: 'updated' | 'published';
	terms?: boolean;
}>();

defineEmits<{
	trash: [entry: EntrySummary];
	duplicate: [entry: EntrySummary];
}>();

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
	...(pinned === null ? [] : [{ key: 'pinned', entries: [pinned] }]),
	{ key: 'entries', entries: entries.filter((entry) => !hidden.value.has(entry.id)) }
]);

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
		toast("The link couldn't be copied");
	}
}
</script>

<template>
	<div class="table-wrap">
		<table class="table" :aria-labelledby="labelledby">
			<thead>
				<tr>
					<th scope="col">Title</th>
					<th scope="col">Status</th>
					<th v-if="terms" scope="col" class="table__count">Entries</th>
					<th v-else scope="col">Authors</th>
					<th scope="col">{{ dateLabel }}</th>
					<th scope="col" class="table__actions"><span class="visually-hidden">Actions</span></th>
				</tr>
			</thead>
			<tbody v-for="group in groups" :key="group.key" :class="{ 'table__pinned': group.key === 'pinned' }">
				<tr v-for="entry in group.entries" :key="`${entry.id}${entry.continued ? ':continued' : ''}`">
					<th scope="row">
						<span class="title-cell" :style="entry.depth ? { '--depth': entry.depth } : undefined">
						<button v-if="entry.children" type="button" class="twist" :aria-expanded="!collapsed.has(entry.id)" :aria-label="`${collapsed.has(entry.id) ? 'Expand' : 'Collapse'} ${entry.title || 'Untitled'}`" @click="toggle(entry)"><AdminIcon name="chevron-right" /></button>
						<span v-else-if="entry.depth !== null || (tree && group.key === 'pinned')" class="twist twist--leaf" aria-hidden="true" />
						<span class="entry-title">
							<span class="entry-title__text">
								<span v-if="entry.index" class="entry-title__pin" title="Pinned: the index page for this type"><AdminIcon name="pin" /></span>
								<span v-if="entry.depth === null && entry.ancestors.length" class="entry-title__ancestors">{{ entry.ancestors.join(' › ') }} ›{{ ' ' }}</span>
								<RouterLink class="entry-title__link" :to="entryRoute(entry)">
									<template v-if="entry.title">{{ entry.title }}</template>
									<span v-else class="untitled">Untitled</span>
								</RouterLink>
								<template v-if="entry.index">{{ ' ' }}<span class="index-mark">Index</span></template>
								{{ ' ' }}<span v-if="entry.own" class="tag">Yours</span>
								{{ ' ' }}<span v-if="entry.continued" class="tag" title="Listed on an earlier page; shown again above the entries under it">Continued</span>
							</span>
							<span v-if="entry.url" class="entry-title__path">{{ entry.url }}</span>
						</span>
						</span>
					</th>
					<td><StatusPill :status="entry.status" /></td>
					<td v-if="terms" class="table__meta table__count">{{ entry.uses?.toLocaleString() ?? '—' }}</td>
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
							<RouterLink class="menu-item" :to="entryRoute(entry)"><AdminIcon name="pen-line" />Edit</RouterLink>
							<template v-if="liveUrl(entry)">
								<a class="menu-item" :href="liveUrl(entry) ?? undefined" target="_blank" rel="noopener">
									<AdminIcon name="external-link" />{{ terms ? 'View archive' : 'View' }}<span class="visually-hidden"> (new tab)</span>
								</a>
								<button type="button" class="menu-item" @click="copyLink(entry)"><AdminIcon name="link" />Copy link</button>
							</template>
							<button v-if="entry.can.duplicate && !terms" type="button" class="menu-item" @click="$emit('duplicate', entry)"><AdminIcon name="copy" />Duplicate</button>
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
