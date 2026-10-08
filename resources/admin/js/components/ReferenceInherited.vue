<script setup lang="ts">
/**
 * What a translation shows from its original in a relation (D-599,
 * D-607), in a dashed band that names the original: beside its own
 * (`add`), or in place of its own until it sets some (`fallback`). The
 * values are the slot's, drawn as the picker draws them, without ×.
 */

import type { InheritedValues } from '../api';
import AdminIcon from './AdminIcon.vue';

defineProps<{ inherited: InheritedValues }>();
</script>

<template>
	<div class="reference__inherited">
		<p class="reference__inherited-head">
			<AdminIcon name="languages" />
			<span v-if="inherited.rule === 'add'">Also shown, from {{ inherited.title }} ({{ inherited.language }})</span>
			<span v-else>Shown from {{ inherited.title }} ({{ inherited.language }}) until this translation sets its own</span>
		</p>
		<slot />
	</div>
</template>
