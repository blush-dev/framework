<script setup lang="ts">
/**
 * The people picker (D-353, D-607): credits, several at once, usually
 * in order. Rows of avatar, name, and slug, the first marked Lead when
 * there are two or more and the order is kept; grips show on hover and
 * focus, only on an ordered relation, so their presence says the order
 * means something, and ⌥↑ ⌥↓ move the focused row. Adding is a search,
 * whose results are the same rows minus the grip, so a pick reads as
 * moving into the list; focus offers those most recently credited.
 *
 * When the last one must stay, its × goes and a line says why; short of
 * the relation's minimum, a line says what it blocks, in danger ink once
 * publishing was tried. A slug no profile has gets a dashed avatar.
 */

import { computed, ref } from 'vue';
import { CAP, marked } from '../references';
import { initials } from '../people';
import { capitalized, plural, withArticle } from '../format';
import { usePicker, type PickerProps } from '../picker';
import AdminIcon from './AdminIcon.vue';
import ReferenceInherited from './ReferenceInherited.vue';
import ReferenceMissing from './ReferenceMissing.vue';
import ReferenceResults from './ReferenceResults.vue';

const props = defineProps<PickerProps>();
const model = defineModel<string>({ required: true });

const picker = usePicker(props, model, { cap: CAP, suggest: 'recent', suggestions: 5 });
const { names, source, relation, values, itemOf, isMissing, full, minimum, ordered, reorder, lastStays, query, results, before, active, more, searching, missing, inheritedShown, error } = picker;

// Without a relation (an older people field), the order is the byline's.
const kept    = computed(() => relation.value === undefined || ordered.value);
const persons = computed(() => values.value.map((value) => ({ value, item: itemOf(value) })));
const focused = ref(false);
const typing  = computed(() => query.value.trim() !== '');
const offered = computed(() => typing.value ? results.value : before.value);

// Below the minimum: how many more publishing needs.
const short = computed(() => Math.max(0, minimum.value - values.value.length));
</script>

<template>
	<div class="reference">
		<ul v-if="persons.length" class="reference__rows">
			<li
				v-for="({ value, item }, index) in persons"
				:key="value"
				class="reference__row"
				:class="{ 'is-missing': isMissing(value), 'is-draft': item.status === 'draft' }"
				v-bind="kept && persons.length > 1 ? reorder.item(index) : {}"
				:tabindex="kept && persons.length > 1 ? 0 : undefined"
			>
				<span v-if="kept && persons.length > 1" class="reference__grip" title="Drag, or move with ⌥↑ and ⌥↓"><AdminIcon name="grip-vertical" /></span>
				<span class="avatar reference__avatar" :class="{ 'is-missing': isMissing(value) }" aria-hidden="true">{{ isMissing(value) ? '?' : initials(item.title) }}</span>
				<span class="reference__who">
					<span class="reference__name">
						<span>{{ isMissing(value) ? value : item.title }}</span>
						<span v-if="kept && index === 0 && persons.length > 1" class="reference__lead">Lead</span>
					</span>
					<span class="reference__meta" :class="{ mono: !isMissing(value) }">{{ isMissing(value) ? 'No profile has this slug' : item.slug }}</span>
				</span>
				<button v-if="!lastStays" type="button" class="reference__remove" @click="picker.remove(item.slug)">
					<AdminIcon name="x" /><span class="visually-hidden">Remove {{ item.title }}</span>
				</button>
			</li>
		</ul>
		<div v-if="!full" class="reference__search" :class="{ 'is-invalid': invalid }">
			<AdminIcon name="search" />
			<input
				:id="id"
				v-model="query"
				type="text"
				autocomplete="off"
				:placeholder="`Add ${withArticle(names.item)}…`"
				role="combobox"
				:aria-expanded="focused && offered.length > 0"
				:aria-controls="`${id}-results`"
				:aria-describedby="describedBy"
				@focus="focused = true"
				@blur="focused = false"
				@keydown="picker.searchKey($event, offered)"
			>
		</div>
		<ReferenceResults
			v-if="focused && (typing || before.length) && !searching"
			:id="`${id}-results`"
			:items="offered"
			:active="active"
			:query="query"
			:heading="typing ? undefined : 'Recently Credited'"
			:more="more"
			:empty="typing ? `Nobody matches “${query.trim()}”.` : undefined"
			@choose="picker.choose"
		>
			<template #item="{ item }">
				<span class="avatar reference__avatar reference__avatar--small" aria-hidden="true">{{ initials(item.title) }}</span>
				<span class="reference__option-name">{{ marked(item.title, query)[0] }}<b>{{ marked(item.title, query)[1] }}</b>{{ marked(item.title, query)[2] }}</span>
				<span class="reference__count mono">{{ item.slug }}</span>
			</template>
		</ReferenceResults>

		<ReferenceInherited v-if="inheritedShown.length && inherited" :inherited="inherited">
			<ul class="reference__rows">
				<li v-for="value in inheritedShown" :key="`inherited-${value}`" class="reference__row is-inherited">
					<span class="avatar reference__avatar" aria-hidden="true">{{ initials(itemOf(value).title) }}</span>
					<span class="reference__who">
						<span class="reference__name">{{ itemOf(value).title }}</span>
						<span class="reference__meta mono">{{ itemOf(value).slug }}</span>
					</span>
				</li>
			</ul>
		</ReferenceInherited>
		<p v-if="lastStays && persons.length === 1" class="field__help">{{ capitalized(withArticle(source.item)) }} always has {{ withArticle(names.item) }}, so this one stays until another is added.</p>
		<p v-else-if="short > 0 && attempted" class="field__error">Add {{ plural(short, `more ${names.item}`, `more ${names.items}`) }} to publish.</p>
		<p v-else-if="short > 0" class="field__help">Publishing needs at least {{ plural(minimum, names.item, names.items) }}. A draft saves without {{ minimum === 1 ? 'one' : 'them' }}.</p>
		<ReferenceMissing :missing="missing" :noun="names.item" @replace="picker.replace" />
		<p v-if="error" class="field__error">{{ error }}</p>
	</div>
</template>
