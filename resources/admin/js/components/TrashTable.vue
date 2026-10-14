<script setup lang="ts">
/**
 * Entries in the trash, of one type (D-237, D-484): the title (a link
 * to look at it, D-276), when it was trashed, and a menu (D-254):
 * **Restore as a draft**, **Preview**, and, after a divider, **Delete
 * permanently**. A file without an id (D-481) links to Site Health's Entry IDs
 * and has no menu.
 */

import { RouterLink } from 'vue-router';
import type { EntrySummary } from '../api';
import { formatDate } from '../format';
import AdminIcon from './AdminIcon.vue';
import MenuButton from './MenuButton.vue';
import { t } from '../messages';

defineProps<{
	items: EntrySummary[];
	labelledby: string;
	busy: string | null;
}>();

defineEmits<{
	restore: [item: EntrySummary];
	purge: [item: EntrySummary];
}>();

function name(item: EntrySummary): string {
	return item.title || t('trash_table.untitled', 'Untitled');
}
</script>

<template>
	<div class="table-wrap">
		<table class="table" :aria-labelledby="labelledby">
			<thead>
				<tr>
					<th scope="col">{{ t('trash_table.title', 'Title') }}</th>
					<th scope="col">{{ t('trash_table.trashed', 'Trashed') }}</th>
					<th scope="col" class="table__actions"><span class="visually-hidden">{{ t('trash_table.actions', 'Actions') }}</span></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="item in items" :key="item.id ?? item.path">
					<th scope="row">
						<span class="entry-title">
							<span class="entry-title__text">
								<RouterLink class="entry-title__link" :to="item.id ? { name: 'trashed', params: { id: item.id } } : { name: 'health-check', params: { area: 'content', check: 'ids' } }">
									<template v-if="item.title">{{ item.title }}</template>
									<span v-else class="untitled">{{ t('trash_table.untitled', 'Untitled') }}</span>
								</RouterLink>
							</span>
						</span>
					</th>
					<td class="table__meta"><time v-if="item.trashed" :datetime="item.trashed">{{ formatDate(item.trashed) }}</time></td>
					<td class="table__actions">
						<MenuButton v-if="item.id" button-class="row-more" :label="t('trash_table.actions_for', 'Actions for {name}', { name: name(item) })" floating>
							<template #button>
								<AdminIcon name="ellipsis" />
							</template>
							<button type="button" class="menu-item" :disabled="busy !== null" @click="$emit('restore', item)">
								<AdminIcon name="refresh-cw" />{{ t('trash_table.restore', 'Restore as a draft') }}
							</button>
							<RouterLink class="menu-item" :to="{ name: 'trashed', params: { id: item.id } }"><AdminIcon name="eye" />{{ t('trash_table.preview', 'Preview') }}</RouterLink>
							<div class="menu-divider" />
							<button type="button" class="menu-item menu-item--danger" :disabled="busy !== null" @click="$emit('purge', item)">
								<AdminIcon name="trash-2" />{{ t('trash_table.delete_permanently', 'Delete permanently') }}
							</button>
						</MenuButton>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>
