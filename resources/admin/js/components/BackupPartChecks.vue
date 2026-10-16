<script setup lang="ts">
/**
 * A backup's parts to pick, a table a group (D-893): Content, Site,
 * Users, and Plugins each head their own, with a box in the header that
 * picks or clears the group, and a row a part with its name and, when
 * `measure` is on, how many records and bytes it holds now, each table
 * in a panel inside the modal's padding. There's no
 * box for every part. The boxes are the list tables' (`.check`, D-301),
 * with the dash when some are picked, and picked rows take their tint.
 * Back Up Now picks with it, and so does Restore.
 */

import { computed } from 'vue';
import AdminIcon from './AdminIcon.vue';
import { formatNumber, formatSize } from '../format';
import { t } from '../messages';
import type { BackupGroup, BackupPart } from '../backups';

const props = defineProps<{
	parts: BackupPart[];
	groups: BackupGroup[];
	measure?: boolean;
	disabled?: boolean;
}>();

const model = defineModel<string[]>({ required: true });

const shown = computed(() => props.groups
	.map((group) => ({ ...group, parts: props.parts.filter((part) => part.group === group.key) }))
	.filter((group) => group.parts.length > 0));

// Picks or clears parts, keeping the model in the order parts are listed.
function set(names: string[], on: boolean): void {
	model.value = props.parts
		.map((part) => part.name)
		.filter((part) => names.includes(part) ? on : model.value.includes(part));
}

// Whether all of some parts are picked, some, or none, as `aria-checked`.
function state(parts: BackupPart[]): 'true' | 'mixed' | 'false' {
	const picked = parts.filter((part) => model.value.includes(part.name)).length;

	return picked === 0 ? 'false' : (picked === parts.length ? 'true' : 'mixed');
}

// A box picks its parts unless they're all picked, when it clears them.
function toggle(parts: BackupPart[]): void {
	set(parts.map((part) => part.name), state(parts) !== 'true');
}
</script>

<template>
	<div class="backup-parts">
		<div v-for="group in shown" :key="group.key" class="panel">
			<div class="table-wrap">
				<table class="table">
					<thead>
						<tr>
							<th scope="col" class="table__check">
								<button type="button" class="check" role="checkbox" :aria-checked="state(group.parts)" :aria-label="t('backup_part_checks.group', 'Every part in {group}', { group: group.label })" :disabled="disabled" @click="toggle(group.parts)"><AdminIcon :name="state(group.parts) === 'mixed' ? 'minus' : 'check'" /></button>
							</th>
							<th scope="col">{{ group.label }}</th>
							<th v-if="measure" scope="col" class="table__count">{{ t('backup_part_checks.records', 'Records') }}</th>
							<th v-if="measure" scope="col" class="table__count">{{ t('backup_part_checks.size', 'Size') }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="part in group.parts" :key="part.name" :class="{ 'is-selected': model.includes(part.name) }">
							<td class="table__check">
								<button type="button" class="check" role="checkbox" :aria-checked="model.includes(part.name) ? 'true' : 'false'" :aria-label="part.label" :disabled="disabled" @click="set([part.name], !model.includes(part.name))"><AdminIcon name="check" /></button>
							</td>
							<th scope="row" class="backup-parts__name">{{ part.label }}</th>
							<td v-if="measure" class="table__meta table__count">{{ formatNumber(part.count) }}</td>
							<td v-if="measure" class="table__meta table__count">{{ formatSize(part.bytes) }}</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</template>

<style scoped>
/* A table a group, each in a panel inside the modal's padding. */
.backup-parts {
	display: grid;
	gap: var(--s-4);
}

.backup-parts__name {
	color: var(--color-text);
	font-weight: 500;
}
</style>
