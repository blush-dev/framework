<script setup lang="ts">
/**
 * A list's pager (D-509; the entries list's, shared with Redirects,
 * D-686): where you are, how many rows a page holds (`per`), and
 * **Previous** and **Next**, links to the pages `link()` gives. With a
 * single page it says how many there are. Shown once a list holds more
 * than the smallest page size.
 */

import { computed, useId } from 'vue';
import type { RouteLocationRaw } from 'vue-router';
import { RouterLink } from 'vue-router';
import { plural } from '../format';
import AdminIcon from './AdminIcon.vue';
import AdminSelect, { type SelectOption } from './AdminSelect.vue';

const props = defineProps<{
	page: number;
	pages: number;
	total: number;
	// What's counted: one and many.
	noun: [string, string];
	options: number[];
	link: (page: number) => RouteLocationRaw;
}>();

const per = defineModel<string>('per', { required: true });

const id = useId();

const perOptions = computed<SelectOption[]>(() => props.options.map((count) => ({ value: String(count), label: `${count} per page` })));
</script>

<template>
	<nav v-if="total > (options[0] ?? 0)" class="pager" aria-label="Pages">
		<span class="pager__status">{{ pages > 1 ? `Page ${page} of ${pages}` : `All ${plural(total, noun[0], noun[1])}` }}</span>
		<div class="pager__end">
			<label class="visually-hidden" :for="id">Rows per page</label>
			<div class="toolbar__filter">
				<AdminSelect :id="id" v-model="per" :options="perOptions" />
			</div>
			<template v-if="pages > 1">
				<RouterLink v-if="page > 1" class="button button--small" :to="link(page - 1)"><AdminIcon name="chevron-left" />Previous</RouterLink>
				<RouterLink v-if="page < pages" class="button button--small" :to="link(page + 1)">Next<AdminIcon name="chevron-right" /></RouterLink>
			</template>
		</div>
	</nav>
</template>

<style scoped>
.pager {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-3);
	padding: var(--s-4) var(--pad-x);
	border-top: 1px solid var(--border);
}

.pager__status {
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.pager__end {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	margin-left: auto;
}
</style>
