<script setup lang="ts">
/**
 * An extension's ⋯ menu in a list (D-509): its details, then copying
 * where it's installed, then, under a rule, deleting it. The `lead` slot
 * adds items after the details, and the default slot after the copy.
 */

import type { RouteLocationRaw } from 'vue-router';
import AdminIcon from './AdminIcon.vue';
import MenuButton from './MenuButton.vue';
import { copyText } from '../toast';

withDefaults(defineProps<{
	// What the menu is for, in its button's name.
	label: string;
	details?: RouteLocationRaw;
	detailsLabel?: string;
	// What Copy copies, and what it's called.
	copy?: string;
	copyWhat?: string;
	copyLabel?: string;
	// Delete's words, when it's offered.
	deleteLabel?: string;
}>(), { copyWhat: 'the folder path', copyLabel: 'Copy folder path' });

const emit = defineEmits<{ delete: [] }>();
</script>

<template>
	<MenuButton class="extension__menu" button-class="button button--ghost button--small button--icon" :label="`More actions for ${label}`" floating>
		<template #button>
			<AdminIcon name="ellipsis" />
		</template>
		<RouterLink v-if="details" class="menu-item" :to="details"><AdminIcon name="info" />{{ detailsLabel }}</RouterLink>
		<slot name="lead" />
		<button v-if="copy" type="button" class="menu-item" @click="copyText(copy, copyWhat)"><AdminIcon name="copy" />{{ copyLabel }}</button>
		<slot />
		<template v-if="deleteLabel">
			<hr class="menu-rule">
			<button type="button" class="menu-item menu-item--danger" @click="emit('delete')"><AdminIcon name="trash-2" />{{ deleteLabel }}</button>
		</template>
	</MenuButton>
</template>
