<script setup lang="ts">
/**
 * A line for each value a relation picker holds that names nothing
 * (D-607): the value as written, and the closest candidate, with
 * **Replace**, when there's one near enough to be a typo.
 */

import type { ReferenceItem } from '../references';
import MessageText from './MessageText.vue';

defineProps<{
	// Each value as written, with what's known about it.
	missing: { value: string; item: ReferenceItem }[];
	// The target type's word for one (`ingredient`).
	noun: string;
}>();

defineEmits<{ replace: [value: string, slug: string] }>();
</script>

<template>
	<p v-for="{ value, item } in missing" :key="value" class="field__error reference__missing">
		<span><MessageText :text="t('reference_missing.no_match', '{value} doesn\'t match any {noun}.', { noun })"><template #value><span class="mono">{{ value }}</span></template></MessageText><template v-if="item.closest"> <MessageText :text="t('reference_missing.did_you_mean', 'Did you mean {title}?')"><template #title><b>{{ item.closest.title }}</b></template></MessageText></template></span>
		<button v-if="item.closest" type="button" class="lnk" @click="$emit('replace', value, item.closest.slug)">{{ t('reference_missing.replace', 'Replace') }}</button>
	</p>
</template>
