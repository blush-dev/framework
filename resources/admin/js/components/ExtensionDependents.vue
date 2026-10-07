<script setup lang="ts">
/**
 * Extensions on the other side of one's package links (D-431, D-440), as
 * rows of its Dependencies panel (D-565): those that require it,
 * conflict with it, replace it, or provide it, each linked by its label
 * with its name beside it, under its kind's glyph (`KIND_ICONS`).
 */

import AdminIcon from './AdminIcon.vue';
import type { ExtensionDependent } from '../api';
import { extensionRoute, KIND_ICONS } from '../extensions';

defineProps<{ dependents: ExtensionDependent[] }>();
</script>

<template>
	<ul class="dependencies">
		<li v-for="other in dependents" :key="other.name">
			<AdminIcon :name="KIND_ICONS[other.kind]" />
			<div class="dependencies__ref">
				<RouterLink :to="extensionRoute(other.kind, other.name)">{{ other.label }}</RouterLink>
				<span class="mono dependencies__name">{{ other.name }}</span>
			</div>
		</li>
	</ul>
</template>
