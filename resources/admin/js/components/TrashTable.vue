<script setup lang="ts">
/**
 * Trashed entries of one type (D-237): the title (a link to look at it,
 * D-276), when it was trashed, and a menu (D-254): **Restore as a
 * draft**, **Preview**, and, after a divider, **Delete permanently**.
 */

import { RouterLink } from 'vue-router';
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
				<tr v-for="item in items" :key="item.name">
					<th scope="row">
						<span class="entry-title">
							<span class="entry-title__text">
								<RouterLink class="entry-title__link" :to="{ name: 'trashed', params: { name: item.name.split('/') } }">
									<template v-if="item.title">{{ item.title }}</template>
									<span v-else class="untitled">Untitled</span>
								</RouterLink>
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
							<RouterLink class="menu-item" :to="{ name: 'trashed', params: { name: item.name.split('/') } }"><AdminIcon name="eye" />Preview</RouterLink>
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
