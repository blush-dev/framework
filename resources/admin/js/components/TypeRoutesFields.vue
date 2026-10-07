<script setup lang="ts">
/**
 * A content type's addresses (D-350): each route key it answers at (its
 * listing and date archives, entries or terms, feeds, and each people
 * field's archives), with the path after its prefix, edited in place. An empty
 * path is the key's default, shown as the placeholder. Each row says
 * which {placeholders} it needs and may hold, and shows the whole
 * address; the server checks them on save.
 */

import { computed } from 'vue';
import type { PeopleFieldInfo, TypeRoute } from '../api';
import { pathOf, peopleWordOf, type TypeForm } from '../type-form';

const props = defineProps<{
	idPrefix: string;
	routes: TypeRoute[];
	// The prefix the addresses sit under, without slashes.
	prefix: string;
	// Whether its entries are terms, with pages and feeds of their own (D-593).
	terms: boolean;
	// The people fields as loaded, whose route keys' defaults follow the
	// words the form gives them now (D-353).
	people: PeopleFieldInfo[];
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
		const person = props.people.find((item) => feed[1] === `${item.field}.single`);
		const what   = person ? `${person.singular} feed` : (({ collection: 'Feed', single: props.terms ? 'Term feed' : 'Feed' } as Record<string, string>)[feed[1] ?? ''] ?? 'Feed');

		return `${what} (${FEEDS[feed[2] ?? ''] ?? 'RSS'})`;
	}

	const level = /^collection\.(\w+)$/.exec(base);

	if (base === 'collection') {
		label = 'Listing';
	} else if (level && LEVELS[level[1] ?? '']) {
		label = `${LEVELS[level[1] ?? '']} archive`;
	} else if (base === 'single') {
		label = props.terms ? 'Term' : 'Entry';
	} else if (fieldOf(base)?.[1] === 'collection') {
		label = fieldOf(base)?.[0].plural ?? key;
	} else if (fieldOf(base)?.[1] === 'single') {
		label = `${fieldOf(base)?.[0].singular ?? key} archive`;
	} else {
		label = key;
	}

	return paged ? `${label}, later pages` : label;
}

// The people field a route key belongs to, and the rest of the key
// (`collection`, `single`, …), or `null`.
function fieldOf(key: string): [PeopleFieldInfo, string] | null {
	const field = props.people.find((item) => key.startsWith(`${item.field}.`));

	return field ? [field, key.slice(field.field.length + 1)] : null;
}

// The word the form gives a loaded people field now: `false` once its
// archives are off or it's removed.
function formWord(field: PeopleFieldInfo): string | false {
	const now = form.value.people?.find((item) => item.field === field.field && !item.added);

	return now ? peopleWordOf(now) : false;
}

// A key's default with its field's word from the form in place of the
// loaded one.
function defaultOf(route: TypeRoute): string {
	const field = fieldOf(route.key)?.[0];
	const was   = field?.archive;
	const now   = field ? formWord(field) : false;

	if (field === undefined || typeof was !== 'string' || typeof now !== 'string' || was === now) {
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

// A people field's archives show only while the form has them.
const shown = computed(() => props.routes.filter((route) => {
	const field = fieldOf(route.key)?.[0];

	return field === undefined || formWord(field) !== false;
}));
</script>

<template>
	<div class="form-stack">
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
