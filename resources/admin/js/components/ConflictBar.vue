<script setup lang="ts">
/**
 * The editor's conflict bar (D-509): the entry's file changed while it
 * was being edited, so saving stopped. It says when, and offers **Keep
 * theirs**, **Compare**, and **Keep mine**; while the saved version
 * loads, or when it couldn't be, it offers **Try again** instead.
 * **Compare** opens the comparison under it: the fields that differ, as
 * a table, and the body's changed lines with two on each side
 * (`DiffCompare`). The
 * editor owns the conflict and works out what differs (`compare`), which
 * is only asked for while the comparison is open.
 */

import { computed, ref } from 'vue';
import AdminIcon from './AdminIcon.vue';
import DiffCompare from './DiffCompare.vue';
import type { EntryDetail } from '../api';
import type { Differences } from '../diff';
import { siteDateTime } from '../dates';
import { t } from '../messages';

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
				<h2 id="conflict-heading">{{ t('conflict_bar.heading', 'This {noun} changed while you were editing', { noun }) }}</h2>
				<p>
					<template v-if="theirs?.modified">{{ t('conflict_bar.saved_at', 'Its file was saved at {time}, from the admin or by editing the file itself. {safe}; nothing has been saved over. Keep theirs throws away your changes here; Keep mine saves your version of every field shown here over theirs.', { time: siteDateTime(theirs.modified), safe }) }}</template>
					<template v-else>{{ t('conflict_bar.saved_again', 'Its file was saved again, from the admin or by editing the file itself. {safe}; nothing has been saved over. Keep theirs throws away your changes here; Keep mine saves your version of every field shown here over theirs.', { safe }) }}</template>
				</p>
				<p v-if="!loading && theirs === null" class="field__error">{{ t('conflict_bar.load_failed', "The saved version couldn't be loaded. Check your connection, then try again.") }}</p>
			</div>
			<p class="conflict__buttons">
				<template v-if="theirs">
					<button type="button" class="button button--small" @click="emit('keepTheirs')">{{ t('conflict_bar.keep_theirs', 'Keep Theirs') }}</button>
					<button type="button" class="button button--small" :aria-expanded="comparing" aria-controls="conflict-compare" @click="comparing = !comparing">{{ t('conflict_bar.compare', 'Compare') }}</button>
					<button type="button" class="button button--small button--primary" @click="emit('keepMine')">{{ t('conflict_bar.keep_mine', 'Keep Mine') }}</button>
				</template>
				<button v-else type="button" class="button button--small" :disabled="loading" @click="emit('retry')">{{ loading ? t('conflict_bar.loading', 'Loading the saved version…') : t('common.retry', 'Try Again') }}</button>
			</p>
		</div>

		<div v-if="differences" id="conflict-compare" class="compare">
			<DiffCompare :differences="differences" :labels="{ field: t('conflict_bar.field', 'Field'), before: t('conflict_bar.theirs', 'Theirs'), after: t('conflict_bar.mine', 'Mine'), body: t('conflict_bar.body', 'Body'), legend: t('conflict_bar.key', '{before_mark} theirs {after_mark} mine'), beforeLine: t('conflict_bar.theirs_line', 'Theirs: '), afterLine: t('conflict_bar.mine_line', 'Mine: '), same: t('conflict_bar.same', 'Your changes and theirs match in everything the editor shows.') }" />
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
	border-bottom: 1px solid var(--border-color-strong);
	background: var(--color-surface-subtle);
	color: var(--color-text);
	font-size: var(--font-size-small);
}

.conflict__bar > svg {
	flex: none;
	width: 16px;
	height: 16px;
	margin-top: 2px;
	color: var(--color-warning);
}

.conflict__text {
	display: grid;
	flex: 1;
	gap: 3px;
	min-width: 0;
}

.conflict__text h2 {
	font-size: var(--font-size-small);
	font-weight: 500;
}

.conflict__text p {
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
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
	border-bottom: 1px solid var(--border-color-strong);
	background: var(--color-surface);
}
</style>
