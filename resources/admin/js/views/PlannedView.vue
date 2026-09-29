<script setup lang="ts">
/**
 * A screen that's planned but not built (D-241): its heading, what it's
 * for, what it will do, and where that's done until then. The route's
 * `meta.planned` describes it, so the navigation can show the whole admin
 * before every screen exists.
 */

import { computed } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import type { PlannedScreen } from '../screen';

const route   = useRoute();
const title   = computed(() => typeof route.meta.title === 'string' ? route.meta.title : '');
const planned = computed(() => route.meta.planned as PlannedScreen);

// `today` marks commands and paths with backticks, shown as code.
const today = computed(() => planned.value.today.split('`').map((text, index) => ({ text, code: index % 2 === 1 })));
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ title }}</h1>
			<p class="page-header__hint">{{ planned.hint }}</p>
		</div>
	</header>

	<section class="panel" aria-labelledby="planned-heading">
		<div class="empty">
			<AdminIcon :name="planned.icon" />
			<h2 id="planned-heading" class="empty__heading">This screen comes next</h2>
			<p class="empty__text">{{ planned.next }}</p>
			<p class="empty__text planned__today"><template v-for="(part, index) in today" :key="index"><code v-if="part.code">{{ part.text }}</code><template v-else>{{ part.text }}</template></template></p>
			<RouterLink class="button" :to="{ name: 'dashboard' }">Back to the dashboard</RouterLink>
		</div>
	</section>
</template>

<style scoped>
.planned__today {
	margin-bottom: 8px;
	font-size: var(--text-sm);
}
</style>
