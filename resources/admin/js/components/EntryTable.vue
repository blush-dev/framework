<script setup lang="ts">
/**
 * A table of entries: title, type, authors, a date, and the file.
 * Labeled by the heading whose id it's given.
 */

import type { EntrySummary } from '../api';
import { formatDate } from '../format';
import PreviewLinkControl from './PreviewLinkControl.vue';

defineProps<{
	entries: EntrySummary[];
	labelledby: string;
	dateLabel: string;
	dateKey: 'updated' | 'published';
}>();
</script>

<template>
	<div class="table-wrap">
		<table class="table" :aria-labelledby="labelledby">
			<thead>
				<tr>
					<th scope="col">Title</th>
					<th scope="col">Type</th>
					<th scope="col">Authors</th>
					<th scope="col">{{ dateLabel }}</th>
					<th scope="col">File</th>
					<th scope="col">Preview</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="entry in entries" :key="entry.id">
					<th scope="row">
						<template v-if="entry.title">{{ entry.title }}</template>
						<span v-else class="untitled">(Untitled)</span>
						<span v-if="entry.own" class="tag">Yours</span>
					</th>
					<td>{{ entry.type }}</td>
					<td>{{ entry.authors.join(', ') || '—' }}</td>
					<td>
						<time v-if="entry[dateKey]" :datetime="entry[dateKey] ?? undefined">{{ formatDate(entry[dateKey] ?? '') }}</time>
						<template v-else>—</template>
					</td>
					<td><code>{{ entry.path ?? '—' }}</code></td>
					<td><PreviewLinkControl :entry="entry" /></td>
				</tr>
			</tbody>
		</table>
	</div>
</template>
