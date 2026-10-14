<script setup lang="ts">
/**
 * The card picker (D-599, D-607): other entries, where a picture and a
 * date tell them apart. What's chosen sits above the search as rows (a
 * still, the title, the type and date), the way people do, so the
 * search field is never crowded by what's picked; results open as cards
 * under it, a chosen one ticked (choosing it again takes it out). Focus
 * offers the recently edited; two skeleton cards hold the place while
 * the server answers. The entry being edited is never offered.
 *
 * At the limit the search goes, and a line says how to get it back. A
 * draft or trashed entry gets its pill and a faded still; a slug that
 * names nothing, a dashed frame. Stills are square (D-731), and an entry
 * with no image gets its type's icon in the frame, never initials,
 * which mean a person everywhere in the admin.
 */

import { computed, ref } from 'vue';
import { CARD_CAP, marked, type ReferenceItem } from '../references';
import { formatDay } from '../format';
import { t } from '../messages';
import { usePicker, type PickerProps } from '../picker';
import { types } from '../types';
import AdminIcon from './AdminIcon.vue';
import ReferenceInherited from './ReferenceInherited.vue';
import ReferenceMissing from './ReferenceMissing.vue';
import ReferenceResults from './ReferenceResults.vue';
import StatusPill from './StatusPill.vue';
import TypeIcon from './TypeIcon.vue';

const props = defineProps<PickerProps>();
const model = defineModel<string>({ required: true });

const picker = usePicker(props, model, { cap: CARD_CAP, suggest: 'edited', suggestions: 3, keepChosen: true });
const { type, names, source, values, has, itemOf, isMissing, full, max, ordered, reorder, query, results, before, active, more, searching, missing, inheritedShown, error } = picker;

// The type's icon, for a still without an image.
// The help under a full list: how many it takes.
const limitHelp = computed(() => t('reference_cards.limit', '{max, plural, one {Takes one {item} at most.} other {Takes # {items} at most.}} Remove one to add another.', { max: max.value ?? 0, item: names.value.item, items: names.value.items }));

// The help when some aren't live.
const hiddenHelp = computed(() => t('reference_cards.hidden', '{count, plural, one {One isn\'t live, so the site doesn\'t show it} other {# {items} aren\'t live, so the site doesn\'t show them}} on this {source}\'s page.', { count: hidden.value, items: names.value.items, source: source.value.item }));

const targetType = computed(() => types.value.find((item) => item.name === type.value));

const rows    = computed(() => values.value.map((value) => ({ value, item: itemOf(value) })));
const focused = ref(false);
const typing  = computed(() => query.value.trim() !== '');
const offered = computed(() => typing.value ? results.value : before.value);

// What isn't live, which the site doesn't show on this entry's page.
const hidden = computed(() => rows.value.filter(({ item }) => item.status === 'draft' || item.status === 'trash').length);

function meta(item: ReferenceItem): string {
	return [names.value.singular, item.date ? formatDay(item.date) : null].filter((part) => part !== null).join(' · ');
}
</script>

<template>
	<div class="reference">
		<ul v-if="rows.length" class="reference__rows">
			<li
				v-for="({ value, item }, index) in rows"
				:key="value"
				class="reference__row"
				:class="{ 'is-missing': isMissing(value), 'is-draft': item.status === 'draft', 'is-trash': item.status === 'trash' }"
				v-bind="ordered && rows.length > 1 ? reorder.item(index) : {}"
				:tabindex="ordered && rows.length > 1 ? 0 : undefined"
			>
				<span v-if="ordered && rows.length > 1" class="reference__grip" :title="t('reference_cards.drag', 'Drag, or move with ⌥↑ and ⌥↓')"><AdminIcon name="grip-vertical" /></span>
				<span class="reference__still reference__still--row" :class="{ 'is-missing': isMissing(value) }" aria-hidden="true">
					<img v-if="item.image && !isMissing(value)" :src="item.image" alt="" loading="lazy">
					<template v-else-if="isMissing(value)">?</template>
					<TypeIcon v-else-if="targetType" :type="targetType" />
				</span>
				<span class="reference__who">
					<span class="reference__name">
						<span :class="{ mono: isMissing(value) }">{{ isMissing(value) ? value : item.title || t('reference_cards.untitled', 'Untitled') }}</span>
						<StatusPill v-if="item.status === 'draft' || item.status === 'trash'" :status="item.status" />
					</span>
					<span class="reference__meta">{{ isMissing(value) ? t('reference_cards.missing', 'No {item} has this slug', { item: names.item }) : meta(item) }}</span>
				</span>
				<button type="button" class="reference__remove" @click="picker.remove(item.slug)">
					<AdminIcon name="x" /><span class="visually-hidden">{{ t('reference_cards.remove', 'Remove {title}', { title: item.title }) }}</span>
				</button>
			</li>
		</ul>

		<p v-if="full && max !== null" class="field__help">{{ limitHelp }}</p>
		<div v-else class="reference__search" :class="{ 'is-invalid': invalid }">
			<AdminIcon name="search" />
			<input
				:id="id"
				v-model="query"
				type="text"
				autocomplete="off"
				:placeholder="t('reference_cards.search', 'Search {items}…', { items: names.items })"
				role="combobox"
				:aria-expanded="focused && offered.length > 0"
				:aria-controls="`${id}-results`"
				:aria-describedby="describedBy"
				@focus="focused = true"
				@blur="focused = false"
				@keydown="picker.searchKey($event, offered)"
			>
		</div>

		<div v-if="focused && searching" class="reference__results reference__results--cards" aria-hidden="true">
			<div v-for="n in 2" :key="n" class="reference__card-skeleton">
				<span class="skeleton reference__still" />
				<span class="reference__who"><span class="skeleton skeleton--label" /><span class="skeleton skeleton--small" /></span>
			</div>
		</div>
		<ReferenceResults
			v-else-if="focused && (typing || before.length)"
			:id="`${id}-results`"
			:items="offered"
			:active="active"
			:query="query"
			:heading="typing ? undefined : t('reference_cards.recently_edited', 'Recently Edited')"
			:more="more"
			:empty="typing ? t('reference_cards.no_match', 'No {item} matches “{query}”. Drafts are included; the trash is not.', { item: names.item, query: query.trim() }) : undefined"
			cards
			@choose="picker.choose"
		>
			<template #item="{ item }">
				<span class="reference__still" :class="{ 'is-faded': item.status === 'draft' }" aria-hidden="true"><img v-if="item.image" :src="item.image" alt="" loading="lazy"><TypeIcon v-else-if="targetType" :type="targetType" /></span>
				<span class="reference__who">
					<span class="reference__card-title">{{ marked(item.title || t('reference_cards.untitled', 'Untitled'), query)[0] }}<b>{{ marked(item.title || t('reference_cards.untitled', 'Untitled'), query)[1] }}</b>{{ marked(item.title || t('reference_cards.untitled', 'Untitled'), query)[2] }}</span>
					<span class="reference__meta">{{ meta(item) }}<StatusPill v-if="item.status === 'draft' || item.status === 'scheduled'" :status="item.status" /></span>
				</span>
				<span class="reference__tick" :class="{ 'is-on': has(item.slug) }" aria-hidden="true"><AdminIcon name="check" /></span>
			</template>
		</ReferenceResults>

		<ReferenceInherited v-if="inheritedShown.length && inherited" :inherited="inherited">
			<ul class="reference__rows">
				<li v-for="value in inheritedShown" :key="`inherited-${value}`" class="reference__row is-inherited">
					<span class="reference__still reference__still--row" aria-hidden="true"><img v-if="itemOf(value).image" :src="itemOf(value).image ?? ''" alt="" loading="lazy"><TypeIcon v-else-if="targetType" :type="targetType" /></span>
					<span class="reference__who">
						<span class="reference__name">{{ itemOf(value).title }}</span>
						<span class="reference__meta">{{ meta(itemOf(value)) }}</span>
					</span>
				</li>
			</ul>
		</ReferenceInherited>
		<p v-if="hidden" class="field__help">{{ hiddenHelp }}</p>
		<ReferenceMissing :missing="missing" :noun="names.item" @replace="picker.replace" />
		<p v-if="error" class="field__error">{{ error }}</p>
	</div>
</template>
