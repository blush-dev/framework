<script setup lang="ts">
/**
 * The editor's conflict bar (D-509): the entry's file changed while it
 * was being edited, so saving stopped. It says when, and offers **Keep
 * theirs**, **Compare**, and **Keep mine**; while the saved version
 * loads, or when it couldn't be, it offers **Try again** instead.
 * **Compare** opens the comparison under it: the fields that differ, as
 * a table, and the body's changed lines with two on each side. The
 * editor owns the conflict and works out what differs (`compare`), which
 * is only asked for while the comparison is open.
 */

import { computed, ref } from 'vue';
import AdminIcon from './AdminIcon.vue';
import type { EntryDetail } from '../api';
import type { Differences } from '../diff';
import { siteDateTime } from '../dates';
import { plural } from '../format';

const props = defineProps<{
	noun: string;
	// "Your changes are kept in this browser", or "…still here".
	safe: string;
	theirs: EntryDetail | null;
	loading: boolean;
	compare: () => Differences | null;
}>();

const emit = defineEmits<{
	keepTheirs: [];
	keepMine: [];
	retry: [];
}>();

const comparing   = ref(false);
const differences = computed(() => comparing.value ? props.compare() : null);
</script>

<template>
	<section class="conflict" aria-labelledby="conflict-heading">
		<div class="conflict__bar">
			<AdminIcon name="triangle-alert" />
			<div class="conflict__text">
				<h2 id="conflict-heading">This {{ noun }} changed while you were editing</h2>
				<p>
					<template v-if="theirs?.modified">Its file was saved at {{ siteDateTime(theirs.modified) }}, </template>
					<template v-else>Its file was saved again, </template>
					from the admin or by editing the file itself. {{ safe }}; nothing has been saved over. Keep theirs throws away your changes here; Keep mine saves your version of every field shown here over theirs.
				</p>
				<p v-if="!loading && theirs === null" class="field__error">The saved version couldn't be loaded. Check your connection, then try again.</p>
			</div>
			<p class="conflict__buttons">
				<template v-if="theirs">
					<button type="button" class="button button--small" @click="emit('keepTheirs')">Keep Theirs</button>
					<button type="button" class="button button--small" :aria-expanded="comparing" aria-controls="conflict-compare" @click="comparing = !comparing">Compare</button>
					<button type="button" class="button button--small button--primary" @click="emit('keepMine')">Keep Mine</button>
				</template>
				<button v-else type="button" class="button button--small" :disabled="loading" @click="emit('retry')">{{ loading ? 'Loading the saved version…' : 'Try Again' }}</button>
			</p>
		</div>

		<div v-if="differences" id="conflict-compare" class="compare">
			<table v-if="differences.rows.length" class="table compare__fields">
				<thead>
					<tr>
						<th scope="col">Field</th>
						<th scope="col">Theirs</th>
						<th scope="col">Mine</th>
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
				<p class="compare__label">Body <span class="compare__key"><span class="compare__mark compare__mark--theirs">−</span> theirs <span class="compare__mark compare__mark--mine">+</span> mine</span></p>
				<pre class="compare__lines"><template v-for="(line, index) in differences.body" :key="index"><span v-if="line.kind === 'skip'" class="compare__skip">{{ plural(line.count, 'unchanged line') }}</span><span v-else class="compare__line" :class="`compare__line--${line.kind}`"><span class="compare__sign" aria-hidden="true">{{ line.kind === 'theirs' ? '−' : (line.kind === 'mine' ? '+' : ' ') }}</span><span v-if="line.kind !== 'same'" class="visually-hidden">{{ line.kind === 'theirs' ? 'Theirs: ' : 'Mine: ' }}</span>{{ line.text }}</span></template></pre>
			</div>
			<p v-if="!differences.rows.length && !differences.body" class="compare__same">Your changes and theirs match in everything the editor shows.</p>
		</div>
	</section>
</template>

<style scoped>
/* A bar under the header, as the editor's notices are, on a quiet ground. */
.conflict {
	flex: none;
}

.conflict__bar {
	display: flex;
	align-items: flex-start;
	gap: var(--s-3);
	padding: 15px var(--s-5);
	border-bottom: 1px solid var(--border-strong);
	background: var(--surface-2);
	color: var(--fg);
	font-size: var(--text-sm);
}

.conflict__bar > svg {
	flex: none;
	width: 16px;
	height: 16px;
	margin-top: 2px;
	color: var(--warn);
}

.conflict__text {
	display: grid;
	flex: 1;
	gap: 3px;
	min-width: 0;
}

.conflict__text h2 {
	font-size: var(--text-sm);
	font-weight: 500;
}

.conflict__text p {
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.conflict__buttons {
	display: flex;
	flex: none;
	flex-wrap: wrap;
	gap: 6px;
}

/* The comparison, under the bar. */

.compare {
	max-height: 40vh;
	overflow: auto;
	border-bottom: 1px solid var(--border-strong);
	background: var(--surface);
}

.compare__fields td {
	white-space: pre-wrap;
	overflow-wrap: anywhere;
}

.compare__body {
	display: grid;
	gap: 8px;
	padding: 12px var(--pad-x) 16px;
}

.compare__same {
	padding: 12px var(--pad-x);
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.compare__label {
	display: flex;
	flex-wrap: wrap;
	align-items: baseline;
	justify-content: space-between;
	gap: 8px;
	color: var(--fg-2);
	font-size: var(--text-xs);
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
	color: var(--danger);
}

.compare__mark--mine {
	color: var(--good);
}

.compare__lines {
	display: grid;
	margin: 0;
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface-2);
	font-family: var(--font-mono);
	font-size: var(--text-sm);
	line-height: 1.6;
}

.compare__line {
	display: block;
	padding: 0 10px;
	white-space: pre-wrap;
	overflow-wrap: anywhere;
}

.compare__line--theirs {
	background: var(--danger-soft);
	color: var(--danger);
}

.compare__line--mine {
	background: var(--good-soft);
	color: var(--good);
}

.compare__sign {
	display: inline-block;
	width: 1.5ch;
	user-select: none;
}

.compare__skip {
	display: block;
	padding: 2px 10px;
	border-block: 1px dashed var(--border);
	color: var(--fg-3);
	font-family: var(--font-ui);
	font-size: var(--text-xs);
}
</style>
