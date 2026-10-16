<script setup lang="ts">
/**
 * Two versions compared (D-902, from the editor's conflict bar): the
 * fields that differ, as a table, and the body's changed lines with two
 * on each side, marked − and +. What the two versions are is the
 * caller's to say (`labels`): theirs and mine in the editor, before and
 * after on a change's screen. With nothing different, `labels.same`.
 */

import type { Differences } from '../diff';
import { t } from '../messages';
import MessageText from './MessageText.vue';

defineProps<{
	differences: Differences;
	labels: {
		field: string;
		before: string;
		after: string;
		body: string;
		// With `{before_mark}` and `{after_mark}` where − and + go.
		legend: string;
		// Read before a changed line by screen readers, as "Theirs: ".
		beforeLine: string;
		afterLine: string;
		same: string;
	};
}>();
</script>

<template>
	<table v-if="differences.rows.length" class="table compare__fields">
		<thead>
			<tr>
				<th scope="col">{{ labels.field }}</th>
				<th scope="col">{{ labels.before }}</th>
				<th scope="col">{{ labels.after }}</th>
			</tr>
		</thead>
		<tbody>
			<tr v-for="row in differences.rows" :key="row.label">
				<th scope="row">{{ row.label }}</th>
				<td>{{ row.theirs }}</td>
				<td>{{ row.mine }}</td>
			</tr>
		</tbody>
	</table>
	<div v-if="differences.body" class="compare__body">
		<p class="compare__label">{{ labels.body }} <span class="compare__key"><MessageText :text="labels.legend"><template #before_mark><span class="compare__mark compare__mark--theirs">−</span></template><template #after_mark><span class="compare__mark compare__mark--mine">+</span></template></MessageText></span></p>
		<pre class="compare__lines"><template v-for="(line, index) in differences.body" :key="index"><span v-if="line.kind === 'skip'" class="compare__skip">{{ t('diff_compare.unchanged_lines', '{count, plural, one {# unchanged line} other {# unchanged lines} }', { count: line.count }) }}</span><span v-else class="compare__line" :class="`compare__line--${line.kind}`"><span class="compare__sign" aria-hidden="true">{{ line.kind === 'theirs' ? '−' : (line.kind === 'mine' ? '+' : ' ') }}</span><span v-if="line.kind !== 'same'" class="visually-hidden">{{ line.kind === 'theirs' ? labels.beforeLine : labels.afterLine }}</span>{{ line.text }}</span></template></pre>
	</div>
	<p v-if="!differences.rows.length && !differences.body" class="compare__same">{{ labels.same }}</p>
</template>

<style scoped>
.compare__fields td {
	white-space: pre-wrap;
	overflow-wrap: anywhere;
}

.compare__body {
	display: grid;
	gap: 8px;
	padding: 12px var(--padding-inline) 16px;
}

.compare__same {
	padding: 12px var(--padding-inline);
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}

.compare__label {
	display: flex;
	flex-wrap: wrap;
	align-items: baseline;
	justify-content: space-between;
	gap: 8px;
	color: var(--color-text-secondary);
	font-size: var(--font-size-xsmall);
	font-weight: 500;
	letter-spacing: .06em;
	text-transform: uppercase;
}

.compare__key {
	letter-spacing: normal;
	text-transform: none;
}

.compare__mark {
	font-family: var(--font-mono);
	font-weight: 600;
}

.compare__mark--theirs {
	color: var(--color-danger);
}

.compare__mark--mine {
	color: var(--color-success);
}

.compare__lines {
	display: grid;
	margin: 0;
	border: 1px solid var(--border-color);
	border-radius: var(--border-radius-medium);
	background: var(--color-surface-subtle);
	font-family: var(--font-mono);
	font-size: var(--font-size-small);
	line-height: 1.6;
}

.compare__line {
	display: block;
	padding: 0 10px;
	white-space: pre-wrap;
	overflow-wrap: anywhere;
}

.compare__line--theirs {
	background: var(--color-danger-subtle);
	color: var(--color-danger);
}

.compare__line--mine {
	background: var(--color-success-subtle);
	color: var(--color-success);
}

.compare__sign {
	display: inline-block;
	width: 1.5ch;
	user-select: none;
}

.compare__skip {
	display: block;
	padding: 2px 10px;
	border-block: 1px dashed var(--border-color);
	color: var(--color-text-muted);
	font-family: var(--font-family-ui);
	font-size: var(--font-size-xsmall);
}
</style>
