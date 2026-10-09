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
	<div class="segmented segmented--icons toolbar__end" role="group" aria-label="Rows">
		<button type="button" :aria-pressed="!compact" title="Roomy rows" @click="compact = false">
			<AdminIcon name="rows-3" /><span class="visually-hidden">Roomy</span>
		</button>
		<button type="button" :aria-pressed="compact" title="Compact rows" @click="compact = true">
			<AdminIcon name="rows-4" /><span class="visually-hidden">Compact</span>
		</button>
	</div>
</template>
