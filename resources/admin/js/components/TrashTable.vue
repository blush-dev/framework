<script setup lang="ts">
/**
 * Trashed entries of one type (D-237): the title, when it was trashed,
 * and a menu (D-254) with two actions kept apart: **Restore as a draft**
 * and, after a divider, **Delete permanently**.
 */

import type { TrashedSummary } from '../api';
import { formatDate } from '../format';
import AdminIcon from './AdminIcon.vue';
import MenuButton from './MenuButton.vue';

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
	return item.title || 'Untitled';
}
</script>

<template>
	<div class="table-wrap">
		<table class="table" :aria-labelledby="labelledby">
			<thead>
				<tr>
					<th scope="col">Title</th>
					<th scope="col">Trashed</th>
					<th scope="col" class="table__actions"><span class="visually-hidden">Actions</span></th>
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
						</span>
					</th>
					<td class="table__meta"><time :datetime="item.trashed">{{ formatDate(item.trashed) }}</time></td>
					<td class="table__actions">
						<MenuButton button-class="row-more" :label="`Actions for ${name(item)}`" floating>
							<template #button>
								<AdminIcon name="ellipsis" />
							</template>
							<button type="button" class="menu-item" :disabled="busy !== null" @click="$emit('restore', item)">
								<AdminIcon name="refresh-cw" />Restore as a draft
							</button>
							<div class="menu-divider" />
							<button type="button" class="menu-item menu-item--danger" :disabled="busy !== null" @click="$emit('purge', item)">
								<AdminIcon name="trash-2" />Delete permanently
							</button>
						</MenuButton>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>
