<script setup lang="ts">
/**
 * A table of entries: the title (a link to the editor) with its file
 * beneath, the status, optionally the type, the authors (or, for a
 * taxonomy's terms, how many published entries use each), and a date. Labeled by the heading whose id it's given.
 */

import { RouterLink } from 'vue-router';
import type { EntrySummary } from '../api';
import { formatDate } from '../format';
import StatusPill from './StatusPill.vue';

const { showType = true, terms = false } = defineProps<{
	entries: EntrySummary[];
	labelledby: string;
	dateLabel: string;
	dateKey: 'updated' | 'published';
	showType?: boolean;
	terms?: boolean;
}>();

// The editor's route takes the id's segments.
function editor(entry: EntrySummary): { name: string; params: { id: string[] } } {
	return { name: 'entry', params: { id: entry.id.split('/') } };
}
</script>

<template>
	<div class="table-wrap">
		<table class="table" :aria-labelledby="labelledby">
			<thead>
				<tr>
					<th scope="col">Title</th>
					<th scope="col">Status</th>
					<th v-if="showType" scope="col">Type</th>
					<th v-if="terms" scope="col" class="table__count">Entries</th>
					<th v-else scope="col">Authors</th>
					<th scope="col">{{ dateLabel }}</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="entry in entries" :key="entry.id">
					<th scope="row">
						<span class="entry-title">
							<span class="entry-title__text">
								<RouterLink class="entry-title__link" :to="editor(entry)">
									<template v-if="entry.title">{{ entry.title }}</template>
									<span v-else class="untitled">Untitled</span>
								</RouterLink>
								{{ ' ' }}<span v-if="entry.own" class="tag">Yours</span>
							</span>
							<span v-if="entry.path" class="entry-title__path">{{ entry.path }}</span>
						</span>
					</th>
					<td><StatusPill :status="entry.status" /></td>
					<td v-if="showType" class="table__meta">{{ entry.type }}</td>
					<td v-if="terms" class="table__meta table__count">{{ entry.uses?.toLocaleString() ?? '—' }}</td>
					<td v-else class="table__meta">{{ entry.authors.join(', ') || '—' }}</td>
					<td class="table__meta">
						<time v-if="entry[dateKey]" :datetime="entry[dateKey] ?? undefined">{{ formatDate(entry[dateKey] ?? '') }}</time>
						<template v-else>—</template>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>
