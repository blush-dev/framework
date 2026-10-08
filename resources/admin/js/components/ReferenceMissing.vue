<script setup lang="ts">
/**
 * A line for each value a relation picker holds that names nothing
 * (D-607): the value as written, and the closest candidate, with
 * **Replace**, when there's one near enough to be a typo.
 */

import type { ReferenceItem } from '../references';
import { withArticle } from '../format';

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
		<span><span class="mono">{{ value }}</span> doesn't match {{ withArticle(noun) }}.<template v-if="item.closest"> Did you mean <b>{{ item.closest.title }}</b>?</template></span>
		<button v-if="item.closest" type="button" class="lnk" @click="$emit('replace', value, item.closest.slug)">Replace</button>
	</p>
</template>
