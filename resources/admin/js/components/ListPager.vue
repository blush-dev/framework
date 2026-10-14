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
import { t } from '../messages';
import AdminIcon from './AdminIcon.vue';
import AdminSelect, { type SelectOption } from './AdminSelect.vue';

const props = defineProps<{
	page: number;
	pages: number;
	total: number;
	// What's counted, with its count: "3 entries".
	counted: (count: number) => string;
	options: number[];
	link: (page: number) => RouteLocationRaw;
}>();

const per = defineModel<string>('per', { required: true });

const id = useId();

const perOptions = computed<SelectOption[]>(() => props.options.map((count) => ({ value: String(count), label: t('list_pager.per_page', '{count} per page', { count }) })));
</script>

<template>
	<nav v-if="total > (options[0] ?? 0)" class="pager" :aria-label="t('list_pager.pages', 'Pages')">
		<span class="pager__status">{{ pages > 1 ? t('list_pager.page_of', 'Page {page} of {pages}', { page, pages }) : t('list_pager.all', 'All {things}', { things: counted(total) }) }}</span>
		<div class="pager__end">
			<label class="visually-hidden" :for="id">{{ t('list_pager.rows_per_page', 'Rows per page') }}</label>
			<div class="toolbar__filter">
				<AdminSelect :id="id" v-model="per" :options="perOptions" />
			</div>
			<template v-if="pages > 1">
				<RouterLink v-if="page > 1" class="button button--small" :to="link(page - 1)"><AdminIcon name="chevron-left" />{{ t('list_pager.previous', 'Previous') }}</RouterLink>
				<RouterLink v-if="page < pages" class="button button--small" :to="link(page + 1)">{{ t('list_pager.next', 'Next') }}<AdminIcon name="chevron-right" /></RouterLink>
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
	padding: var(--s-4) var(--padding-inline);
	border-top: 1px solid var(--border-color);
}

.pager__status {
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}

.pager__end {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	margin-left: auto;
}
</style>
