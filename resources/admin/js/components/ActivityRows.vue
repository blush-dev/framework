<script setup lang="ts">
/**
 * Changes from the activity log (D-758), newest first, as rows: what
 * happened, as a sentence, linking to what it changed; who; how, as a
 * quiet tag, so an app's changes stand out among a person's own; when;
 * and Undo where it can be undone, or why it can't, in its place. An
 * undo is a change of its own, so the rows are loaded again after one
 * (`changed`). On Tools → Activity and an entry's Document tab.
 */

import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import { changedHow, undoChange, type ActivityChange } from '../activity';
import { errorMessage } from '../api';
import { formatWhen, formatWhenInline } from '../format';
import { toast } from '../toast';

defineProps<{
	changes: ActivityChange[];
}>();

const emit = defineEmits<{ changed: [] }>();

const busy = ref<string | null>(null);

async function undo(change: ActivityChange): Promise<void> {
	busy.value = change.id;

	try {
		await undoChange(change.id);
		toast(`Undid: ${change.summary}`);
		emit('changed');
	} catch (caught) {
		toast(errorMessage(caught, 'The change couldn\'t be undone.'), { kind: 'warn' });
		emit('changed');
	} finally {
		busy.value = null;
	}
}
</script>

<template>
	<ul class="rows">
		<li v-for="change in changes" :key="change.id" class="rows__item">
			<div class="rows__main">
				<RouterLink v-if="change.link" class="rows__title" :to="change.link">{{ change.summary }}</RouterLink>
				<span v-else class="rows__title">{{ change.summary }}</span>
				<span class="rows__sub">
					{{ change.account.name }}
					<span class="tag">{{ changedHow(change) }}</span>
					· <time :datetime="change.time" :title="formatWhen(change.time)">{{ formatWhenInline(change.time) }}</time>
				</span>
			</div>
			<div class="rows__side">
				<span v-if="change.undone" class="pill">Undone</span>
				<button v-else-if="change.undo.available" type="button" class="button button--small" :disabled="busy !== null" @click="undo(change)">
					<span v-if="busy === change.id" class="spin" aria-hidden="true" />{{ busy === change.id ? 'Undoing…' : 'Undo' }}
				</button>
				<span v-else class="rows__sub">{{ change.undo.reason }}</span>
			</div>
		</li>
	</ul>
</template>
