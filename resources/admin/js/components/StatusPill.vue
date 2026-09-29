<script setup lang="ts">
/**
 * An entry's status as a pill: a dot and the word, never color alone.
 */

import { computed } from 'vue';
import type { EntryStatus } from '../api';

const props = defineProps<{ status: EntryStatus }>();

const pills: Record<EntryStatus, { label: string; kind: string }> = {
	published: { label: 'Published', kind: 'pill--good' },
	scheduled: { label: 'Scheduled', kind: 'pill--accent' },
	draft: { label: 'Draft', kind: '' }
};

const pill = computed(() => pills[props.status]);
</script>

<template>
	<span class="pill" :class="pill.kind">{{ pill.label }}</span>
</template>
