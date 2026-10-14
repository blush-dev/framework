<script setup lang="ts">
/**
 * Roomy or compact rows for a list (admin.md §6, Space; D-509): the
 * toolbar's icon pair at its end, kept in this browser (`density.ts`).
 * A list draws compact rows with `panel--compact`.
 */

import { compact } from '../density';
import AdminIcon from './AdminIcon.vue';
</script>

<template>
	<div class="segmented segmented--icons toolbar__end" role="group" :aria-label="t('density_toggle.rows', 'Rows')">
		<button type="button" :aria-pressed="!compact" :title="t('density_toggle.roomy_rows', 'Roomy rows')" @click="compact = false">
			<AdminIcon name="rows-3" /><span class="visually-hidden">{{ t('density_toggle.roomy', 'Roomy') }}</span>
		</button>
		<button type="button" :aria-pressed="compact" :title="t('density_toggle.compact_rows', 'Compact rows')" @click="compact = true">
			<AdminIcon name="rows-4" /><span class="visually-hidden">{{ t('density_toggle.compact', 'Compact') }}</span>
		</button>
	</div>
</template>
