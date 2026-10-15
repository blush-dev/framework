<script setup lang="ts">
/**
 * An entry's status as a pill: a dot and the word, never color alone,
 * in the status's tone (D-850).
 */

import { computed } from 'vue';
import type { EntryStatus } from '../api';
import { pillKind, statusOf } from '../statuses';

const props = defineProps<{ status: EntryStatus }>();

const status = computed(() => statusOf(props.status));
</script>

<template>
	<span class="pill" :class="pillKind(status)">{{ status.label }}</span>
</template>
