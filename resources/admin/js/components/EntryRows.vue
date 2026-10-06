<script setup lang="ts">
/**
 * Entries as rows that open them (D-538), as the dashboard lists them:
 * the title, the type's singular name with a note on what happens next,
 * the status, and when (a draft's last change, else its publish date).
 * `credits` adds who each one credits, "You" for the account's own.
 */

import { RouterLink } from 'vue-router';
import AdminIcon from './AdminIcon.vue';
import StatusPill from './StatusPill.vue';
import { formatWhen, plural } from '../format';
import { labelsOf, listRoute } from '../types';
import type { DashboardEntry } from '../api';

defineProps<{
	entries: DashboardEntry[];
	credits?: boolean;
}>();

// A draft untouched this long says so.
const STALE_DAYS = 7;

// After the type, each part starts with a capital.
function sub(entry: DashboardEntry, credits: boolean): string {
	const parts = [];

	if (credits && (entry.yours || entry.authors.length > 0)) {
		parts.push(entry.yours ? 'You' : entry.authors.join(', '));
	}

	const idle = Math.floor((Date.now() - new Date(entry.updated).getTime()) / 86_400_000);

	if (entry.status === 'draft' && idle >= STALE_DAYS) {
		parts.push(`Untouched for ${plural(idle, 'day')}`);
	} else if (entry.status === 'scheduled') {
		parts.push('Goes live on its own');
	}

	return [labelsOf(entry.type).singular, ...parts].join(' · ');
}

function when(entry: DashboardEntry): string {
	return formatWhen(entry.status === 'draft' ? entry.updated : (entry.published ?? entry.updated));
}
</script>

<template>
	<ul class="rows">
		<li v-for="entry in entries" :key="entry.path">
			<RouterLink class="rows__link" :to="listRoute(entry)">
				<span class="rows__main">
					<span class="rows__title" :class="{ untitled: !entry.title }">{{ entry.title || 'Untitled' }}</span>
					<span class="rows__sub">{{ sub(entry, credits) }}</span>
				</span>
				<span class="rows__side">
					<StatusPill :status="entry.status" />
					<span class="rows__when">{{ when(entry) }}</span>
					<AdminIcon name="chevron-right" class="rows__chevron" />
				</span>
			</RouterLink>
		</li>
	</ul>
</template>
