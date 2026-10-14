<script setup lang="ts">
/**
 * A translated sentence with elements in it (D-831): a link, a name in
 * bold, a code span. The sentence stays one message, so a translator
 * can move the element where their language puts it, and each
 * `{name}` in it is filled by the slot of that name:
 *
 *     <MessageText :text="t('type_editor.introduced', 'Introduced by {link}, an entry edited like any other.')">
 *         <template #link><RouterLink :to="to">{{ page.title }}</RouterLink></template>
 *     </MessageText>
 *
 * Plain values go through `t()`'s own parameters, so only elements are
 * slots; a `{name}` with no slot is left as written. It renders no
 * element of its own.
 */

import { computed } from 'vue';

const props = defineProps<{ text: string }>();

const parts = computed(() => props.text.split(/\{([A-Za-z_][A-Za-z0-9_]*)\}/));
</script>

<template>
	<template v-for="(part, index) in parts" :key="index">
		<template v-if="index % 2 === 0">{{ part }}</template>
		<slot v-else-if="$slots[part]" :name="part" />
		<template v-else>{{ '{' + part + '}' }}</template>
	</template>
</template>
