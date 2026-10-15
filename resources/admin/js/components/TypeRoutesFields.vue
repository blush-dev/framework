<script setup lang="ts">
/**
 * A content type's routes (D-350, drawn from the content types sketch,
 * D-871): a setting row for each route key it answers at, its control
 * lined up with the type page's other rows, grouped on bands as the code
 * groups them (its listing and feeds, its entries, date archives, and
 * each relation's archive, D-596, D-602), with its path after the
 * prefix. A path is a `PatternField`, as Media's upload paths are: an
 * empty one is the key's default, shown as the placeholder, and while
 * it has focus it shows the address it makes and the placeholders it
 * may hold, a required one marked. A path that leaves out a required
 * placeholder, or holds one the route doesn't give, says so under it;
 * the server checks them again on save.
 */

import { computed } from 'vue';
import type { TypeRoute } from '../api';
import { pathOf, type TypeForm } from '../type-form';
import { t } from '../messages';
import PatternField from './PatternField.vue';
import SettingRow from './SettingRow.vue';

const props = withDefaults(defineProps<{
	idPrefix: string;
	routes: TypeRoute[];
	// The prefix the addresses sit under, without slashes.
	prefix: string;
	// Whether it's a taxonomy, whose entries have pages and feeds of
	// their own (D-593, D-856).
	taxonomy: boolean;
	// Whether the type may set its URLs (`dataTypeUrls`).
	editable: boolean;
	// The saved paths, to mark a path not saved yet; `null` for a new type.
	saved?: Record<string, string> | null;
}>(), { saved: null });

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
		const what = ({ collection: t('type_routes_fields.feed', 'Feed'), single: props.taxonomy ? t('type_routes_fields.term_feed', 'Term feed') : t('type_routes_fields.feed', 'Feed') } as Record<string, string>)[feed[1] ?? ''] ?? t('type_routes_fields.feed', 'Feed');

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
		label = props.taxonomy ? t('type_routes_fields.term', 'Term') : t('type_routes_fields.entry', 'Entry');
	} else {
		label = key;
	}

	return paged ? t('type_routes_fields.later_pages', '{label}, later pages', { label }) : label;
}

// What each placeholder stands for in an example address.
const EXAMPLES: Record<string, string> = { name: 'hello-world', year: '2026', month: '10', day: '15', hour: '13', minute: '28', second: '00', page: '2', target: 'jane-doe' };

function groupOf(route: TypeRoute): string {
	if (route.relation !== null) {
		return t('type_routes_fields.relation_group', 'Relationship Archive: {relation}', { relation: route.relation });
	}

	if (route.key.startsWith('single')) {
		return t('type_routes_fields.single', 'Single');
	}

	return /^collection\.(year|month|day|hour|minute|second)\b/.test(route.key) ? t('type_routes_fields.date_archives', 'Date Archives') : t('type_routes_fields.collection', 'Collection');
}

const groups = computed(() => {
	const all: { name: string; routes: TypeRoute[] }[] = [];

	for (const route of props.routes) {
		const name = groupOf(route);
		const last = all.at(-1);

		if (last?.name === name) {
			last.routes.push(route);
		} else {
			all.push({ name, routes: [route] });
		}
	}

	return all;
});

function leadOf(route: TypeRoute): string {
	return route.root || props.prefix === '' ? '/' : `/${props.prefix}/`;
}

function exampleOf(route: TypeRoute): string {
	const path = (pathOf(form.value.paths[route.key] ?? '') || route.default).replace(/\{([a-z_]+)\}/g, (match, token: string) => EXAMPLES[token] ?? match);

	return `/${[route.root ? '' : props.prefix, path].filter((part) => part !== '').join('/')}`;
}

function problemOf(route: TypeRoute): string {
	const path = pathOf(form.value.paths[route.key] ?? '');

	if (path === '') {
		return '';
	}

	if (!/^[A-Za-z0-9{}/_.-]*$/.test(path)) {
		return t('type_routes_fields.characters', 'Letters, digits, - _ . / and placeholders only.');
	}

	const held    = [...path.matchAll(/\{([a-z_]+)\}/g)].map((match) => match[1] ?? '');
	const missing = route.requires.filter((token) => !held.includes(token));

	if (missing.length) {
		return t('type_routes_fields.missing', 'Missing required {tokens}: without it, two pages would share this path.', { tokens: missing.map((token) => `{${token}}`).join(' ') });
	}

	const foreign = held.find((token) => !route.requires.includes(token) && !route.allows.includes(token));

	return foreign === undefined ? '' : t('type_routes_fields.foreign', '{token} isn\'t a placeholder this route gives.', { token: `{${foreign}}` });
}

function unsaved(route: TypeRoute): boolean {
	return props.saved !== null && pathOf(form.value.paths[route.key] ?? '') !== pathOf(props.saved[route.key] ?? '');
}

// A path that can't be changed, whole, with its placeholders.
function fixedOf(route: TypeRoute): string {
	const whole = `${leadOf(route)}${pathOf(form.value.paths[route.key] ?? '') || route.default}`;

	return whole === '/' ? whole : whole.replace(/\/+$/, '');
}

function marked(path: string): string[] {
	return path.split(/(\{[a-z_]+\})/).filter((part) => part !== '');
}
</script>

<template>
	<template v-for="group in groups" :key="group.name">
		<h3 class="setting-panels__group">{{ group.name }}</h3>
		<div class="setting-panels__rows">
			<SettingRow v-for="route in group.routes" :key="route.key" :label="labelOf(route)" :control-id="editable ? `${idPrefix}${route.key}` : undefined" :changed="unsaved(route)" wide>
				<PatternField
					v-if="editable"
					:id="`${idPrefix}${route.key}`"
					:model-value="form.paths[route.key] ?? ''"
					:lead="leadOf(route)"
					:placeholder="route.default"
					:tokens="[...route.requires.map((name) => ({ name, required: true })), ...route.allows.map((name) => ({ name }))]"
					:example="exampleOf(route)"
					:label="t('type_routes_fields.path_label', '{route} path', { route: route.key })"
					:problem="problemOf(route)"
					@update:model-value="form.paths[route.key] = $event"
				/>
				<p v-else class="setting__value"><code class="type-routes__path"><template v-for="(part, index) in marked(fixedOf(route))" :key="index"><span :class="{ 'type-routes__token': part.startsWith('{') }">{{ part }}</span></template></code></p>
			</SettingRow>
		</div>
	</template>
</template>

<style scoped>
.type-routes__path {
	color: var(--color-text-secondary);
	overflow-wrap: anywhere;
}

.type-routes__token {
	color: var(--color-accent);
}
</style>
