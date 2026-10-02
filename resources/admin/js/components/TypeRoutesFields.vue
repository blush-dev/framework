<script setup lang="ts">
/**
 * A content type's addresses (D-350): each route key it answers at (its
 * listing and date archives, entries or terms, feeds, and author
 * archives), with the path after its prefix, edited in place. An empty
 * path is the key's default, shown as the placeholder. Each row says
 * which {placeholders} it needs and may hold, and shows the whole
 * address; the server checks them on save.
 */

import { computed } from 'vue';
import type { TypeRoute } from '../api';
import { pathOf, type TypeForm } from '../type-form';

const props = defineProps<{
	idPrefix: string;
	routes: TypeRoute[];
	// The prefix the addresses sit under, without slashes.
	prefix: string;
	taxonomy: boolean;
	// The word author archives sat under when the type was loaded, and
	// the one the form has now, so their defaults follow it.
	authorsWord: string | false | null;
	formAuthorsWord: string | false;
	// Whether the type may set its URLs (`dataTypeUrls`).
	editable: boolean;
}>();

const form = defineModel<TypeForm>({ required: true });

const LEVELS: Record<string, string> = { year: 'Year', month: 'Month', day: 'Day', hour: 'Hour', minute: 'Minute', second: 'Second' };
const FEEDS: Record<string, string> = { '': 'RSS', '.atom': 'Atom', '.json': 'JSON' };

// What a route key is, for people.
function labelOf(key: string): string {
	const paged = key.endsWith('.paged');
	const base  = paged ? key.slice(0, -'.paged'.length) : key;
	const feed  = /^(.*)\.feed(\.atom|\.json)?$/.exec(base);
	let label: string;

	if (feed) {
		const what = ({ collection: 'Feed', single: props.taxonomy ? 'Term feed' : 'Feed', 'authors.single': 'Author feed' } as Record<string, string>)[feed[1] ?? ''] ?? 'Feed';

		return `${what} (${FEEDS[feed[2] ?? ''] ?? 'RSS'})`;
	}

	const level = /^collection\.(\w+)$/.exec(base);

	if (base === 'collection') {
		label = 'Listing';
	} else if (level && LEVELS[level[1] ?? '']) {
		label = `${LEVELS[level[1] ?? '']} archive`;
	} else if (base === 'single') {
		label = props.taxonomy ? 'Term' : 'Entry';
	} else if (base === 'authors.collection') {
		label = 'Authors';
	} else if (base === 'authors.single') {
		label = 'Author archive';
	} else {
		label = key;
	}

	return paged ? `${label}, later pages` : label;
}

// A key's default with the form's author word in place of the loaded one.
function defaultOf(route: TypeRoute): string {
	const was = props.authorsWord;
	const now = props.formAuthorsWord;

	if (!route.key.startsWith('authors.') || typeof was !== 'string' || typeof now !== 'string' || was === now) {
		return route.default;
	}

	return route.default.startsWith(was) ? now + route.default.slice(was.length) : route.default;
}

function addressOf(route: TypeRoute): string {
	const path = pathOf(form.value.paths[route.key] ?? '') || defaultOf(route);

	return `/${[route.root ? '' : props.prefix, path].filter((part) => part !== '').join('/')}`;
}

function braced(names: string[]): string {
	return names.map((name) => `{${name}}`).join(' ');
}

// Author archives show only while the form has them.
const shown = computed(() => props.routes.filter((route) => !route.key.startsWith('authors.') || props.formAuthorsWord !== false));
</script>

<template>
	<div class="type-routes">
		<p v-if="!editable" class="field__help"><code>config/content.php</code> doesn't let types in <code>user/data/types</code> set their URLs (<code>dataTypeUrls</code>), so these are as the site has them.</p>
		<div class="type-routes__grid">
			<div v-for="route in shown" :key="route.key" class="field">
				<label :for="`${idPrefix}${route.key}`">{{ labelOf(route.key) }}</label>
				<input :id="`${idPrefix}${route.key}`" v-model="form.paths[route.key]" class="mono" :placeholder="defaultOf(route) || '(the prefix itself)'" :disabled="!editable" autocomplete="off" spellcheck="false" :aria-describedby="`${idPrefix}${route.key}-help`">
				<p :id="`${idPrefix}${route.key}-help`" class="field__help">
					At <code>{{ addressOf(route) }}</code>.
					<template v-if="route.requires.length">Needs <code>{{ braced(route.requires) }}</code>{{ route.allows.length ? '' : '.' }}</template>
					<template v-if="route.allows.length">{{ route.requires.length ? '; may' : 'May' }} hold <code>{{ braced(route.allows) }}</code>.</template>
				</p>
			</div>
		</div>
		<p class="field__help">Paths follow the prefix. Leave one empty for its default. Changing an address moves those pages; add redirects for the old ones in <code>user/data/redirects</code>.</p>
	</div>
</template>

<style scoped>
.type-routes {
	display: grid;
	gap: var(--s-4);
}

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
