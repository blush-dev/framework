<script setup lang="ts">
/**
 * Trashed entries of one type (D-237): the title with where it lived,
 * when it was trashed, and two actions, kept apart: **Restore as a
 * draft** and, after a divider, **Delete permanently**.
 */

import type { TrashedSummary } from '../api';
import { formatDate } from '../format';
import AdminIcon from './AdminIcon.vue';

defineProps<{
	items: TrashedSummary[];
	labelledby: string;
	busy: string | null;
}>();

defineEmits<{
	restore: [item: TrashedSummary];
	purge: [item: TrashedSummary];
}>();

function name(item: TrashedSummary): string {
	return item.title || item.entry;
}
</script>

<template>
	<div class="table-wrap">
		<table class="table" :aria-labelledby="labelledby">
			<thead>
				<tr>
					<th scope="col">Title</th>
					<th scope="col">Trashed</th>
					<th scope="col"><span class="visually-hidden">Actions</span></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="item in items" :key="item.id">
					<th scope="row">
						<span class="entry-title">
							<span class="entry-title__text">
								<template v-if="item.title">{{ item.title }}</template>
								<span v-else class="untitled">Untitled</span>
								{{ ' ' }}<span v-if="item.own" class="tag">Yours</span>
							</span>
							<span class="entry-title__path">{{ item.entry }}<template v-if="item.bundle"> (with its folder)</template></span>
						</span>
					</th>
					<td class="table__meta"><time :datetime="item.trashed">{{ formatDate(item.trashed) }}</time></td>
					<td>
						<div class="trash-actions">
							<button type="button" class="button button--small" :disabled="busy !== null" @click="$emit('restore', item)">
								<AdminIcon name="refresh-cw" />
								Restore as a draft<span class="visually-hidden">: {{ name(item) }}</span>
							</button>
							<span class="trash-actions__divider" aria-hidden="true" />
							<button type="button" class="button button--small button--danger" :disabled="busy !== null" @click="$emit('purge', item)">
								Delete permanently<span class="visually-hidden">: {{ name(item) }}</span>
							</button>
						</div>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<style scoped>
.trash-actions {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 8px;
}

.trash-actions__divider {
	align-self: stretch;
	width: 1px;
	background: var(--border);
}
</style>
