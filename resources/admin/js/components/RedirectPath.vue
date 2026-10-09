<script setup lang="ts">
/**
 * A redirect's path, in code type, with each `{placeholder}` marked so
 * a pattern reads as one at a glance (D-686).
 */

import { computed } from 'vue';

const props = defineProps<{ path: string }>();

const parts = computed(() => props.path.split(/(\{[^}]*\})/).filter((part) => part !== '').map((text) => ({ text, placeholder: text.startsWith('{') })));
</script>

<template>
	<span class="mono redirect-path"><template v-for="(part, index) in parts" :key="index"><span v-if="part.placeholder" class="redirect-path__placeholder">{{ part.text }}</span><template v-else>{{ part.text }}</template></template></span>
</template>

<style scoped>
.redirect-path {
	overflow-wrap: anywhere;
}

.redirect-path__placeholder {
	color: var(--accent);
}
</style>
