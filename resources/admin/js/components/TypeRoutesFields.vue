<script setup lang="ts">
/**
 * A content type's addresses (D-350): each route key it answers at (its
 * listing and date archives, entries or terms, feeds, and each
 * relation's archives, D-596, D-602), with the path after its prefix, edited in place. An empty
 * path is the key's default, shown as the placeholder. Each row says
 * which {placeholders} it needs and may hold, and shows the whole
 * address; the server checks them on save.
 */

import { computed } from 'vue';
import type { TypeRoute } from '../api';
import { pathOf, type TypeForm } from '../type-form';
import { t } from '../messages';
import MessageText from './MessageText.vue';

const props = defineProps<{
	idPrefix: string;
	routes: TypeRoute[];
	// The prefix the addresses sit under, without slashes.
	prefix: string;
	// Whether its entries are terms, with pages and feeds of their own (D-593).
	terms: boolean;
	// Whether the type may set its URLs (`dataTypeUrls`).
	editable: boolean;
}>();

const form = defineModel<TypeForm>({ required: true });

const LEVELS: Record<string, string> = { year: t('type_routes_fields.levels.year', 'Year'), month: t('type_routes_fields.levels.month', 'Month'), day: t('type_routes_fields.levels.day', 'Day'), hour: t('type_routes_fields.levels.hour', 'Hour'), minute: t('type_routes_fields.levels.minute', 'Minute'), second: t('type_routes_fields.levels.second', 'Second') };
const FEEDS: Record<string, string> = { '': 'RSS', '.atom': 'Atom', '.json': 'JSON' };

// What a route key is, for people.
function labelOf(route: TypeRoute): string {
	const key   = route.key;
	const paged = key.endsWith('.paged');
	const base  = paged ? key.slice(0, -'.paged'.length) : key;
	const feed  = /^(.*)\.feed(\.atom|\.json)?$/.exec(base);
	let label: string;

	if (feed && route.relation !== null) {
		return t('type_routes_fields.relation_feed', '{relation} archive feed ({format})', { relation: route.relation, format: FEEDS[feed[2] ?? ''] ?? 'RSS' });
	}

	if (feed) {
		const what = ({ collection: t('type_routes_fields.feed', 'Feed'), single: props.terms ? t('type_routes_fields.term_feed', 'Term feed') : t('type_routes_fields.feed', 'Feed') } as Record<string, string>)[feed[1] ?? ''] ?? t('type_routes_fields.feed', 'Feed');

		return t('type_routes_fields.feed_format', '{what} ({format})', { what, format: FEEDS[feed[2] ?? ''] ?? 'RSS' });
	}

	const level = /^collection\.(\w+)$/.exec(base);

	if (route.relation !== null) {
		label = base.endsWith('.collection') ? route.relation : t('type_routes_fields.relation_archive', '{relation} archive', { relation: route.relation });
	} else if (base === 'collection') {
		label = t('type_routes_fields.listing', 'Listing');
	} else if (level && LEVELS[level[1] ?? '']) {
		label = t('type_routes_fields.level_archive', '{level} archive', { level: LEVELS[level[1] ?? ''] });
	} else if (base === 'single') {
		label = props.terms ? t('type_routes_fields.term', 'Term') : t('type_routes_fields.entry', 'Entry');
	} else {
		label = key;
	}

	return paged ? t('type_routes_fields.later_pages', '{label}, later pages', { label }) : label;
}


function addressOf(route: TypeRoute): string {
	const path = pathOf(form.value.paths[route.key] ?? '') || route.default;

	return `/${[route.root ? '' : props.prefix, path].filter((part) => part !== '').join('/')}`;
}

function braced(names: string[]): string {
	return names.map((name) => `{${name}}`).join(' ');
}

const shown = computed(() => props.routes);
</script>

<template>
	<div class="form-stack">
		<p v-if="!editable" class="field__help">
			<MessageText :text="t('type_routes_fields.not_editable', '{config} doesn\'t let types in {folder} set their URLs ({setting}), so these are as the site has them.')">
				<template #config><code>config/content.php</code></template>
				<template #folder><code>user/data/types</code></template>
				<template #setting><code>dataTypeUrls</code></template>
			</MessageText>
		</p>
		<div class="type-routes__grid">
			<div v-for="route in shown" :key="route.key" class="field">
				<label :for="`${idPrefix}${route.key}`">{{ labelOf(route) }}</label>
				<input :id="`${idPrefix}${route.key}`" v-model="form.paths[route.key]" class="mono" :placeholder="route.default || t('type_routes_fields.prefix_itself', '(the prefix itself)')" :disabled="!editable" autocomplete="off" spellcheck="false" :aria-describedby="`${idPrefix}${route.key}-help`">
				<p :id="`${idPrefix}${route.key}-help`" class="field__help">
					<MessageText :text="t('type_routes_fields.at', 'At {address}.')"><template #address><code>{{ addressOf(route) }}</code></template></MessageText>
					<MessageText v-if="route.requires.length && route.allows.length" :text="t('type_routes_fields.needs_may_hold', 'Needs {requires}; may hold {allows}.')">
						<template #requires><code>{{ braced(route.requires) }}</code></template>
						<template #allows><code>{{ braced(route.allows) }}</code></template>
					</MessageText>
					<MessageText v-else-if="route.requires.length" :text="t('type_routes_fields.needs', 'Needs {requires}.')"><template #requires><code>{{ braced(route.requires) }}</code></template></MessageText>
					<MessageText v-else-if="route.allows.length" :text="t('type_routes_fields.may_hold', 'May hold {allows}.')"><template #allows><code>{{ braced(route.allows) }}</code></template></MessageText>
				</p>
			</div>
		</div>
		<p class="field__help">
			<MessageText :text="t('type_routes_fields.paths_help', 'Paths follow the prefix. Leave one empty for its default. Changing an address moves those pages; add redirects for the old ones in {folder}.')"><template #folder><code>user/data/redirects</code></template></MessageText>
		</p>
	</div>
</template>

<style scoped>
.type-routes__grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(20rem, 1fr));
	align-items: start;
	gap: var(--s-4);
}

.type-routes__grid code {
	overflow-wrap: anywhere;
}
</style>
