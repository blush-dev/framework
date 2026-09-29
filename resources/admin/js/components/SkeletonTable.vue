<script setup lang="ts">
/**
 * A table in the shape of the one that's loading: its real column
 * headings, and bars where each row's values will be. Hidden from
 * assistive technology, which hears the label instead.
 */

const props = defineProps<{
	columns: string[];
	rows: number;
	label: string;
}>();

// Bar widths vary a little by row so the shape reads as text.
function width(row: number, column: number): string {
	return column === 0 ? `${45 + (row * 17) % 35}%` : `${50 + (row * 13 + column * 7) % 30}%`;
}
</script>

<template>
	<div class="table-wrap">
		<p class="visually-hidden" role="status">{{ props.label }}</p>
		<table class="table skeleton-table" aria-hidden="true">
			<thead>
				<tr>
					<th v-for="column in columns" :key="column" scope="col">{{ column }}</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="row in rows" :key="row">
					<td v-for="(column, index) in columns" :key="column">
						<span class="skeleton-table__cell">
							<span class="skeleton" :style="{ width: width(row, index) }" />
							<span v-if="index === 0" class="skeleton skeleton--small" :style="{ width: width(row + 3, index) }" />
						</span>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<style scoped>
.skeleton-table td {
	vertical-align: middle;
}

.skeleton-table tbody tr:hover {
	background: none;
}

.skeleton-table__cell {
	display: grid;
	gap: 6px;
	min-width: 4rem;
}
</style>
