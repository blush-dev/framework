<script setup lang="ts">
/**
 * An account's author (D-259, D-312): the slug of the author entry that's
 * its public name, typed or picked from the site's authors. Empty is no
 * author. An author without an entry yet is fine: its page can be made
 * from Your profile.
 */

import { ref, watch } from 'vue';
import { loadReferences, type ReferenceItem } from '../references';
import { authorType, loadTypes } from '../types';

defineProps<{
	id: string;
	describedBy?: string;
	invalid?: boolean;
}>();

const model   = defineModel<string>({ required: true });
const authors = ref<ReferenceItem[]>([]);

loadTypes().catch(() => undefined);

watch(authorType, async (type) => {
	if (type === null) {
		return;
	}

	try {
		authors.value = (await loadReferences(type, { limit: 100 })).items;
	} catch {
		// Suggestions are a help; typing still works.
		authors.value = [];
	}
}, { immediate: true });
</script>

<template>
	<input :id="id" v-model.trim="model" class="mono" :list="`${id}-authors`" autocomplete="off" spellcheck="false" placeholder="None" :aria-describedby="describedBy" :aria-invalid="invalid ? 'true' : undefined">
	<datalist :id="`${id}-authors`">
		<option v-for="author in authors" :key="author.slug" :value="author.slug">{{ author.title }}</option>
	</datalist>
</template>
