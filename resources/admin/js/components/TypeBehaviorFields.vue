<script setup lang="ts">
/**
 * How a content type behaves (D-311), for the type editor and the
 * new-type wizard: its URL prefix, whether it's public, in the sitemap
 * and `llms.txt` (D-398, D-401), and has a feed; a collection's date archives and featured image (an
 * `image` media field); file names, for every kind (D-511, D-514); a taxonomy's nesting and the types its terms
 * group; and the index page (D-255), which a type gets once and keeps.
 * A tree (D-386) has no prefix, feed, or author archives: its entries
 * are at their folder paths.
 * In the new-type wizard, when the site has profiles (D-329): whether
 * entries credit authors, and whether a type with URLs has author
 * archives, at which word, with its authors page, which, like the index
 * page, a type gets once and keeps. The type editor edits every people
 * field in a panel of its own instead (`TypePeopleFields`, D-353).
 */

import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { entryRoute, type ContentTypeSummary } from '../api';
import AdminSelect from './AdminSelect.vue';
import { authorsWordOf, DATE_ARCHIVES, FEATURED, FILENAMES, hasFeatured, AUTHORS, type TypeForm, type TypeKind } from '../type-form';

const props = defineProps<{
	idPrefix: string;
	kind: TypeKind;
	// The folder its URLs default to, without slashes.
	folderPrefix: string;
	// Whether types in user/data/types may set their URLs.
	urls: boolean;
	// The types a taxonomy can group.
	types: ContentTypeSummary[];
	// The index page it has, or `null`; `indexWanted` is the wizard's or
	// the editor's choice to add one.
	indexPage: { id: string | null; type: string; path: string; title: string } | null;
	// The site's authors type's plural name, or `null` without one; the
	// type's authors page, or `null`.
	authorsLabel: string | null;
	authorsPage: { id: string | null; type: string; path: string; title: string } | null;
}>();

const form        = defineModel<TypeForm>({ required: true });
const indexWanted = defineModel<boolean>('index', { default: false });
const pageWanted  = defineModel<boolean>('pageWanted', { default: false });

// Where the author archives would be, for the hint.
const archiveBase = computed(() => {
	const word = authorsWordOf(form.value);

	return word === false ? '' : `/${(form.value.prefix || props.folderPrefix).replace(/^\/+|\/+$/g, '')}/${word}`;
});

const featured = computed({
	get: () => hasFeatured(form.value),
	set: (on: boolean) => {
		form.value.fields = on
			? [...form.value.fields, { ...FEATURED }]
			: form.value.fields.filter((field) => !(field.name === FEATURED.name && field.type === FEATURED.type));
	}
});

// The patterns offered, with a pattern from config that isn't one of
// them as itself.
const filenames = computed(() => FILENAMES.some((option) => option.value === form.value.filename)
	? FILENAMES
	: [...FILENAMES, { value: form.value.filename, label: form.value.filename, hint: 'from config' }]);

const groupable = computed(() => props.types.filter((type) => type.kind !== 'taxonomy'));

function grouped(name: string, on: boolean): void {
	form.value.types = on ? [...form.value.types, name] : form.value.types.filter((type) => type !== name);
}
</script>

<template>
	<div class="form-stack">
		<div v-if="kind === 'tree'" class="field">
			<span class="field__label">Addresses</span>
			<p class="field__help">An entry lives at its path in the folder: <code>/{{ folderPrefix }}/{path}</code>, so <code>install/requirements.md</code> is at <code>/{{ folderPrefix }}/install/requirements</code>.</p>
		</div>
		<div v-else class="field">
			<label :for="`${idPrefix}prefix`">URL prefix</label>
			<input :id="`${idPrefix}prefix`" v-model="form.prefix" class="mono" :placeholder="folderPrefix" :disabled="!urls" autocomplete="off" spellcheck="false" :aria-describedby="`${idPrefix}prefix-help`">
			<p :id="`${idPrefix}prefix-help`" class="field__help">
				<template v-if="urls">An entry lives at <code>/{{ (form.prefix || folderPrefix).replace(/^\/+|\/+$/g, '') }}/{slug}</code>. Empty uses the folder's.</template>
				<template v-else><code>config/content.php</code> doesn't let these types set their URLs (<code>dataTypeUrls</code>).</template>
			</p>
		</div>

		<fieldset class="fieldset">
			<legend>Options</legend>
			<label class="checkbox"><input v-model="form.public" type="checkbox"> Visible on the site</label>
			<label class="checkbox"><input v-model="form.sitemap" type="checkbox" :disabled="!form.public"> In the sitemap</label>
			<label class="checkbox"><input v-model="form.llms" type="checkbox" :disabled="!form.public"> Listed in <code>llms.txt</code></label>
			<label v-if="kind !== 'tree'" class="checkbox"><input v-model="form.feed" type="checkbox"> Has a feed (RSS, Atom, and JSON)</label>
			<label v-if="kind === 'taxonomy'" class="checkbox"><input v-model="form.hierarchical" type="checkbox"> Terms can nest under a parent</label>
			<label v-if="kind === 'collection'" class="checkbox"><input v-model="featured" type="checkbox"> Has a featured image</label>
			<template v-if="indexPage">
				<p class="field__help">Its index page: <RouterLink :to="entryRoute(indexPage)">{{ indexPage.title }}</RouterLink>, the landing page at its prefix. It's an entry, edited like one.</p>
			</template>
			<label v-else class="checkbox"><input v-model="indexWanted" type="checkbox"> Has an index page</label>
			<p v-if="!indexPage && indexWanted" class="field__help">An entry is created for the landing page at <code>/{{ (form.prefix || folderPrefix).replace(/^\/+|\/+$/g, '') }}</code>, titled with the plural name, and pinned at the top of its list.</p>
		</fieldset>

		<fieldset v-if="authorsLabel !== null && form.people === null" class="fieldset">
			<legend>{{ authorsLabel }}</legend>
			<label class="checkbox"><input v-model="form.authors" type="checkbox"> Entries credit {{ authorsLabel.toLowerCase() }}</label>
			<template v-if="form.authors && kind !== 'tree'">
				<label class="checkbox"><input v-model="form.authorArchives" type="checkbox" :disabled="!urls"> Each one has an archive here</label>
				<div v-if="form.authorArchives" class="field type-behavior__word">
					<label :for="`${idPrefix}authors-word`">Word in the address</label>
					<input :id="`${idPrefix}authors-word`" v-model="form.authorsWord" class="mono" :placeholder="AUTHORS" :disabled="!urls" autocomplete="off" spellcheck="false" :aria-describedby="`${idPrefix}authors-word-help`">
					<p :id="`${idPrefix}authors-word-help`" class="field__help">The list is at <code>{{ archiveBase }}</code> and each archive at <code>{{ archiveBase }}/{slug}</code>. Bylines link there.</p>
				</div>
				<p v-else class="field__help">{{ urls ? 'Bylines name the authors without linking anywhere.' : 'These types can\'t set their URLs, so their author archives are as the site has them.' }}</p>
				<template v-if="form.authorArchives">
					<p v-if="authorsPage" class="field__help">Its {{ authorsLabel.toLowerCase() }} page: <RouterLink :to="entryRoute(authorsPage)">{{ authorsPage.title }}</RouterLink>, which introduces the list. It's an entry, edited like one.</p>
					<template v-else>
						<label class="checkbox"><input v-model="pageWanted" type="checkbox"> Has a page introducing the list</label>
						<p v-if="pageWanted" class="field__help">An entry is created at <code>_authors</code> in the folder, titled {{ authorsLabel }}, and pinned in its list. It has no address of its own.</p>
					</template>
				</template>
			</template>
		</fieldset>

		<div v-if="kind === 'collection'" class="field">
			<label :for="`${idPrefix}archives`">Date archives</label>
			<AdminSelect :id="`${idPrefix}archives`" v-model="form.dateArchives" :options="DATE_ARCHIVES" :described-by="`${idPrefix}archives-help`" />
			<p :id="`${idPrefix}archives-help`" class="field__help">With archives, entries are dated: new ones get a publish date, and listings by year (and finer) appear.</p>
		</div>

		<div class="field">
			<label :for="`${idPrefix}filename`">File names</label>
			<AdminSelect :id="`${idPrefix}filename`" v-model="form.filename" :options="filenames" :described-by="`${idPrefix}filename-help`" />
			<p :id="`${idPrefix}filename-help`" class="field__help">How new entries' files are named; the default is the slug alone. Changing it renames nothing (Content Health can rename older files to a pattern chosen here, but never to the default): a file's address comes from its slug, after the last dot, so older names keep working.<template v-if="kind === 'tree'"> Folders keep their pages' slugs.</template></p>
		</div>

		<fieldset v-if="kind === 'taxonomy'" class="fieldset">
			<legend>Groups</legend>
			<label v-for="type in groupable" :key="type.name" class="checkbox"><input type="checkbox" :checked="form.types.includes(type.name)" @change="grouped(type.name, ($event.target as HTMLInputElement).checked)"> {{ type.labels.plural }}</label>
			<p class="field__help">{{ form.types.length === 0 ? 'None chosen, so its terms group every type.' : 'A term\'s page lists entries of these types.' }}</p>
		</fieldset>
	</div>
</template>

<style scoped>
.type-behavior__word {
	max-width: 24rem;
}
</style>
