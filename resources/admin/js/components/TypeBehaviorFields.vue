<script setup lang="ts">
/**
 * How a content type behaves (D-311), for the type editor and the
 * new-type wizard: its URL prefix, whether it's public, whether its
 * entries choose a visibility (D-855), in the sitemap
 * and `llms.txt` (D-398, D-401), and has a feed; a collection's date archives, featured image (an
 * `image` media field), nesting by a `parent`, and order (D-593); file
 * names, for every kind (D-511, D-514), and folders, for every kind but a
 * tree (D-629), both only when content is kept in files (D-683); and the
 * index page (D-255), which a type gets once and keeps.
 * A tree (D-386) has no feed or author archives: its entries are at their
 * folder paths under its prefix (D-683).
 * In the new-type wizard, when the site has profiles: whether entries
 * credit authors (joining the `authors` credit relation, D-602), and
 * whether its authors list has a page introducing it. The relation's
 * archive word and the rest are the Relationships section's.
 */

import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { entryRoute } from '../api';
import AdminSelect from './AdminSelect.vue';
import { DATE_ARCHIVES, FEATURED, FILENAMES, FOLDERS, ORDERS, hasFeatured, type TypeForm, type TypeKind } from '../type-form';
import { typeFiles } from '../types';
import { t } from '../messages';
import MessageText from './MessageText.vue';

const props = defineProps<{
	idPrefix: string;
	kind: TypeKind;
	// The prefix its URLs have without one of its own, its key (D-683),
	// without slashes.
	defaultPrefix: string;
	// Whether types in user/data/types may set their URLs.
	urls: boolean;
	// The index page it has, or `null`; `indexWanted` is the wizard's or
	// the editor's choice to add one.
	indexPage: { id: string | null; type: string; path: string; title: string } | null;
	// The site's profiles type's plural name, in the new-type wizard, or
	// `null` (the type editor, or a site without profiles).
	authorsLabel: string | null;
}>();

const form        = defineModel<TypeForm>({ required: true });
const indexWanted = defineModel<boolean>('index', { default: false });
const pageWanted  = defineModel<boolean>('pageWanted', { default: false });

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
	: [...FILENAMES, { value: form.value.filename, label: form.value.filename, hint: t('type_behavior_fields.from_config', 'from config') }]);

// The folder patterns offered, the same way.
const folders = computed(() => FOLDERS.some((option) => option.value === form.value.folders)
	? FOLDERS
	: [...FOLDERS, { value: form.value.folders, label: form.value.folders, hint: t('type_behavior_fields.from_config', 'from config') }]);
</script>

<template>
	<div class="form-stack">
		<div class="field">
			<label :for="`${idPrefix}prefix`">{{ t('type_behavior_fields.prefix', 'URL prefix') }}</label>
			<input :id="`${idPrefix}prefix`" v-model="form.prefix" class="mono" :placeholder="defaultPrefix" :disabled="!urls" autocomplete="off" spellcheck="false" :aria-describedby="`${idPrefix}prefix-help`">
			<p :id="`${idPrefix}prefix-help`" class="field__help">
				<MessageText v-if="urls && kind === 'tree'" :text="t('type_behavior_fields.prefix_tree', 'An entry lives at its path under it: {pattern}, so {example} is at {address}. Empty uses its key.')">
					<template #pattern><code>/{{ (form.prefix || defaultPrefix).replace(/^\/+|\/+$/g, '') }}/{path}</code></template>
					<template #example><code>install/requirements</code></template>
					<template #address><code>/{{ (form.prefix || defaultPrefix).replace(/^\/+|\/+$/g, '') }}/install/requirements</code></template>
				</MessageText>
				<MessageText v-else-if="urls" :text="t('type_behavior_fields.prefix_entry', 'An entry lives at {pattern}. Empty uses its key.')"><template #pattern><code>/{{ (form.prefix || defaultPrefix).replace(/^\/+|\/+$/g, '') }}/{slug}</code></template></MessageText>
				<MessageText v-else :text="t('type_behavior_fields.prefix_locked', '{config} doesn\'t let these types set their URLs ({setting}).')">
					<template #config><code>config/content.php</code></template>
					<template #setting><code>dataTypeUrls</code></template>
				</MessageText>
			</p>
		</div>

		<fieldset class="fieldset">
			<legend>{{ t('type_behavior_fields.options', 'Options') }}</legend>
			<label class="checkbox"><input v-model="form.public" type="checkbox"> {{ t('type_behavior_fields.public', 'Visible on the site') }}</label>
			<label class="checkbox"><input v-model="form.visibility" type="checkbox" :disabled="!form.public" :aria-describedby="`${idPrefix}visibility-help`"> {{ t('type_behavior_fields.visibility', 'Entries choose a visibility') }}</label>
			<p :id="`${idPrefix}visibility-help`" class="field__help">{{ t('type_behavior_fields.visibility_help', 'Public, unlisted (an address, but in no lists), or hidden (no address). Off, every entry is public.') }}</p>
			<label class="checkbox"><input v-model="form.sitemap" type="checkbox" :disabled="!form.public"> {{ t('type_behavior_fields.sitemap', 'In the sitemap') }}</label>
			<label class="checkbox"><input v-model="form.llms" type="checkbox" :disabled="!form.public"> <MessageText :text="t('type_behavior_fields.llms', 'Listed in {file}')"><template #file><code>llms.txt</code></template></MessageText></label>
			<label v-if="kind !== 'tree'" class="checkbox"><input v-model="form.feed" type="checkbox"> {{ t('type_behavior_fields.feed', 'Has a feed (RSS, Atom, and JSON)') }}</label>
			<label v-if="kind === 'collection'" class="checkbox"><input v-model="form.hierarchical" type="checkbox"> {{ t('type_behavior_fields.hierarchical', 'Entries can nest under a parent, as categories do') }}</label>
			<label v-if="kind === 'collection'" class="checkbox"><input v-model="featured" type="checkbox"> {{ t('type_behavior_fields.featured', 'Has a featured image') }}</label>
			<template v-if="indexPage">
				<p class="field__help"><MessageText :text="t('type_behavior_fields.index_page', 'Its index page: {link}, the landing page at its prefix. It\'s an entry, edited like one.')"><template #link><RouterLink :to="entryRoute(indexPage)">{{ indexPage.title }}</RouterLink></template></MessageText></p>
			</template>
			<label v-else class="checkbox"><input v-model="indexWanted" type="checkbox"> {{ t('type_behavior_fields.index_wanted', 'Has an index page') }}</label>
			<p v-if="!indexPage && indexWanted" class="field__help"><MessageText :text="t('type_behavior_fields.index_created', 'An entry is created for the landing page at {address}, titled with the plural name, and pinned at the top of its list.')"><template #address><code>/{{ (form.prefix || defaultPrefix).replace(/^\/+|\/+$/g, '') }}</code></template></MessageText></p>
		</fieldset>

		<fieldset v-if="authorsLabel !== null" class="fieldset">
			<legend>{{ authorsLabel }}</legend>
			<label class="checkbox"><input v-model="form.authors" type="checkbox"> {{ t('type_behavior_fields.authors', 'Entries credit authors') }}</label>
			<template v-if="form.authors && kind !== 'tree'">
				<label class="checkbox"><input v-model="pageWanted" type="checkbox"> {{ t('type_behavior_fields.authors_page', 'Has a page introducing its list of authors') }}</label>
				<p class="field__help"><MessageText :text="t('type_behavior_fields.authors_help', 'They\'re credited through the {relation} relationship, whose archives and words are set under Relationships.')"><template #relation><code>authors</code></template></MessageText><template v-if="pageWanted"> <MessageText :text="t('type_behavior_fields.authors_page_help', 'An entry is created at {file} in the folder, titled Authors, and pinned in its list.')"><template #file><code>_authors</code></template></MessageText></template></p>
			</template>
		</fieldset>

		<div v-if="kind === 'collection'" class="field">
			<label :for="`${idPrefix}order`">{{ t('type_behavior_fields.order', 'Order') }}</label>
			<AdminSelect :id="`${idPrefix}order`" v-model="form.order" :options="ORDERS" :described-by="`${idPrefix}order-help`" />
			<p :id="`${idPrefix}order-help`" class="field__help">{{ t('type_behavior_fields.order_help', 'How its lists are ordered unless a list says otherwise. Taxonomies are usually by position: those without one follow, by title.') }}</p>
		</div>

		<div v-if="kind === 'collection'" class="field">
			<label :for="`${idPrefix}archives`">{{ t('type_behavior_fields.archives', 'Date archives') }}</label>
			<AdminSelect :id="`${idPrefix}archives`" v-model="form.dateArchives" :options="DATE_ARCHIVES" :described-by="`${idPrefix}archives-help`" />
			<p :id="`${idPrefix}archives-help`" class="field__help">{{ t('type_behavior_fields.archives_help', 'With archives, entries are dated: new ones get a publish date, and listings by year (and finer) appear.') }}</p>
		</div>

		<div v-if="typeFiles" class="field">
			<label :for="`${idPrefix}filename`">{{ t('type_behavior_fields.filenames', 'File names') }}</label>
			<AdminSelect :id="`${idPrefix}filename`" v-model="form.filename" :options="filenames" :described-by="`${idPrefix}filename-help`" />
			<p :id="`${idPrefix}filename-help`" class="field__help">{{ t('type_behavior_fields.filenames_help', 'How new entries\' files are named; the default is the slug alone. Changing it renames nothing (Site Health can rename older files to a pattern chosen here, but never to the default): a file\'s address comes from its slug, after the last dot, so older names keep working.') }}<template v-if="kind === 'tree'"> {{ t('type_behavior_fields.filenames_tree', 'Folders keep their pages\' slugs.') }}</template></p>
		</div>

		<div v-if="typeFiles && kind !== 'tree'" class="field">
			<label :for="`${idPrefix}folders`">{{ t('type_behavior_fields.folders', 'Folders') }}</label>
			<AdminSelect :id="`${idPrefix}folders`" v-model="form.folders" :options="folders" :described-by="`${idPrefix}folders-help`" />
			<p :id="`${idPrefix}folders-help`" class="field__help">{{ t('type_behavior_fields.folders_help', 'Where entries\' files are kept inside the type\'s folder, for a type with thousands of them; by year and month go by the publish date, and a new date moves the file. Folders are never part of an address. Changing it moves nothing; Site Health moves older files.') }}</p>
		</div>

	</div>
</template>

<style scoped>
.type-behavior__word {
	max-width: 24rem;
}
</style>
