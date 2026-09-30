<script setup lang="ts">
/**
 * A table of one type's entries: the title (a link to the editor) with
 * its address on the site beneath, the status, the authors (or, for a
 * taxonomy's terms, how many published entries use each), a date, and a
 * menu of what to do with each (D-254): **Edit**, **View** and **Copy
 * link** once it's live, and **Move to trash** when the account may.
 * Files and folders aren't shown. Labeled by the heading whose id it's
 * given.
 *
 * The type's index page, when there is one, is `pinned` in a body of its
 * own above the rest (D-255): the same row, tinted, with a pin and an
 * **Index** tag, and never with **Move to trash**.
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
}>();

// The pinned index page's body, then the entries'.
const groups = computed(() => [
	...(pinned === null ? [] : [{ key: 'pinned', entries: [pinned] }]),
	{ key: 'entries', entries }
]);

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
				<tr v-for="entry in group.entries" :key="entry.id">
					<th scope="row">
						<span class="entry-title">
							<span class="entry-title__text">
								<span v-if="entry.index" class="entry-title__pin" title="Pinned: the index page for this type"><AdminIcon name="pin" /></span>
								<RouterLink class="entry-title__link" :to="entryRoute(entry)">
									<template v-if="entry.title">{{ entry.title }}</template>
									<span v-else class="untitled">Untitled</span>
								</RouterLink>
								<template v-if="entry.index">{{ ' ' }}<span class="index-mark">Index</span></template>
								{{ ' ' }}<span v-if="entry.own" class="tag">Yours</span>
							</span>
							<span v-if="entry.url" class="entry-title__path">{{ entry.url }}</span>
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
