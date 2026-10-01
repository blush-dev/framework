<script setup lang="ts">
/**
 * How a content type behaves (D-311), for the type editor and the
 * new-type wizard: its URL prefix, whether it's public, in the sitemap,
 * and has a feed; a collection's date archives and featured image (an
 * `image` media field); a taxonomy's nesting and the types its terms
 * group; and the index page (D-255), which a type gets once and keeps.
 */

import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import type { ContentTypeSummary } from '../api';
import AdminSelect from './AdminSelect.vue';
import { DATE_ARCHIVES, FEATURED, hasFeatured, type TypeForm } from '../type-form';

const props = defineProps<{
	idPrefix: string;
	kind: 'collection' | 'taxonomy';
	// The folder its URLs default to, without slashes.
	folderPrefix: string;
	// Whether types in user/data/types may set their URLs.
	urls: boolean;
	// The types a taxonomy can group.
	types: ContentTypeSummary[];
	// The index page it has, or `null`; `indexWanted` is the wizard's or
	// the editor's choice to add one.
	indexPage: { id: string; title: string } | null;
}>();

const form        = defineModel<TypeForm>({ required: true });
const indexWanted = defineModel<boolean>('index', { default: false });

const featured = computed({
	get: () => hasFeatured(form.value),
	set: (on: boolean) => {
		form.value.fields = on
			? [...form.value.fields, { ...FEATURED }]
			: form.value.fields.filter((field) => !(field.name === FEATURED.name && field.type === FEATURED.type));
	}
});

const groupable = computed(() => props.types.filter((type) => type.kind !== 'taxonomy'));

function grouped(name: string, on: boolean): void {
	form.value.types = on ? [...form.value.types, name] : form.value.types.filter((type) => type !== name);
}
</script>

<template>
	<div class="type-behavior">
		<div class="field">
			<label :for="`${idPrefix}prefix`">URL prefix</label>
			<input :id="`${idPrefix}prefix`" v-model="form.prefix" class="mono" :placeholder="folderPrefix" :disabled="!urls" autocomplete="off" spellcheck="false" :aria-describedby="`${idPrefix}prefix-help`">
			<p :id="`${idPrefix}prefix-help`" class="field__help">
				<template v-if="urls">An entry lives at <code>/{{ (form.prefix || folderPrefix).replace(/^\/+|\/+$/g, '') }}/{slug}</code>. Empty uses the folder's.</template>
				<template v-else><code>config/content.php</code> doesn't let these types set their URLs (<code>dataTypeUrls</code>).</template>
			</p>
		</div>

		<fieldset class="type-behavior__group">
			<legend>Options</legend>
			<label class="checkbox"><input v-model="form.public" type="checkbox"> Visible on the site</label>
			<label class="checkbox"><input v-model="form.sitemap" type="checkbox" :disabled="!form.public"> In the sitemap</label>
			<label class="checkbox"><input v-model="form.feed" type="checkbox"> Has a feed (RSS, Atom, and JSON)</label>
			<label v-if="kind === 'taxonomy'" class="checkbox"><input v-model="form.hierarchical" type="checkbox"> Terms can nest under a parent</label>
			<label v-if="kind === 'collection'" class="checkbox"><input v-model="featured" type="checkbox"> Has a featured image</label>
			<template v-if="indexPage">
				<p class="field__help">Its index page: <RouterLink :to="{ name: 'entry-file', params: { id: indexPage.id.split('/') } }">{{ indexPage.title }}</RouterLink>, the landing page at its prefix. It's an entry, edited like one.</p>
			</template>
			<label v-else class="checkbox"><input v-model="indexWanted" type="checkbox"> Has an index page</label>
			<p v-if="!indexPage && indexWanted" class="field__help">An entry is created for the landing page at <code>/{{ (form.prefix || folderPrefix).replace(/^\/+|\/+$/g, '') }}</code>, titled with the plural name, and pinned at the top of its list.</p>
		</fieldset>

		<div v-if="kind === 'collection'" class="field">
			<label :for="`${idPrefix}archives`">Date archives</label>
			<AdminSelect :id="`${idPrefix}archives`" v-model="form.dateArchives" :options="DATE_ARCHIVES" :described-by="`${idPrefix}archives-help`" />
			<p :id="`${idPrefix}archives-help`" class="field__help">With archives, entries are dated: new ones get a publish date and a date in their file name, and listings by year (and finer) appear.</p>
		</div>

		<fieldset v-if="kind === 'taxonomy'" class="type-behavior__group">
			<legend>Groups</legend>
			<label v-for="type in groupable" :key="type.name" class="checkbox"><input type="checkbox" :checked="form.types.includes(type.name)" @change="grouped(type.name, ($event.target as HTMLInputElement).checked)"> {{ type.labels.plural }}</label>
			<p class="field__help">{{ form.types.length === 0 ? 'None chosen, so its terms group every type.' : 'A term\'s page lists entries of these types.' }}</p>
		</fieldset>
	</div>
</template>

<style scoped>
.type-behavior {
	display: grid;
	gap: var(--s-4);
}

.type-behavior__group {
	display: grid;
	gap: var(--s-2);
	margin: 0;
	padding: 0;
	border: 0;
}

.type-behavior__group legend {
	margin-bottom: 7px;
	padding: 0;
	color: var(--fg-2);
	font-size: var(--text-sm);
	font-weight: 500;
}
</style>
