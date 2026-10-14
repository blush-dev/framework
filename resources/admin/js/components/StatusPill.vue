<script setup lang="ts">
/**
 * An entry's status as a pill: a dot and the word, never color alone.
 */

import { computed } from 'vue';
import type { EntryStatus } from '../api';
import { t } from '../messages';

const props = defineProps<{ status: EntryStatus }>();

const pills: Record<EntryStatus, { label: string; kind: string }> = {
	published: { label: t('status_pill.published', 'Published'), kind: 'pill--good' },
	scheduled: { label: t('status_pill.scheduled', 'Scheduled'), kind: 'pill--accent' },
	draft: { label: t('status_pill.draft', 'Draft'), kind: '' },
	trash: { label: t('status_pill.trash', 'Trash'), kind: 'pill--danger' }
};

const pill = computed(() => pills[props.status]);
</script>

<template>
	<span class="pill" :class="pill.kind">{{ pill.label }}</span>
</template>
