<script setup lang="ts">
/**
 * What a relation picker's search offers (D-607): a heading for what's
 * offered before typing (Most Used, Recently Credited), the results,
 * each drawn by the `item` slot (a name with the typed part in bold and
 * its count, by default), the line saying how many more there are, and
 * Create under a rule when what was typed can be added. Never a
 * scrollbar: the list is capped.
 */

import type { ReferenceItem } from '../references';
import { marked } from '../references';
import AdminIcon from './AdminIcon.vue';

defineProps<{
	id: string;
	items: ReferenceItem[];
	active: number;
	query: string;
	heading?: string;
	more?: string;
	// Create's label (`Create “pec”`), when what was typed can be added.
	create?: string | null;
	// A quiet line when there's nothing to choose.
	empty?: string;
	// Results drawn as cards.
	cards?: boolean;
}>();

defineEmits<{ choose: [item: ReferenceItem | null] }>();
</script>

<template>
	<div class="reference__results" :class="{ 'reference__results--cards': cards }">
		<p v-if="heading && items.length" class="reference__group">{{ heading }}</p>
		<ul v-if="items.length || create" :id="id" class="reference__options" role="listbox">
			<li v-for="(item, index) in items" :key="item.slug" role="option" :aria-selected="index === active">
				<button type="button" :class="{ 'is-active': index === active }" @mousedown.prevent @click="$emit('choose', item)">
					<slot name="item" :item="item">
						<span class="reference__option-name">{{ marked(item.title, query)[0] }}<b>{{ marked(item.title, query)[1] }}</b>{{ marked(item.title, query)[2] }}</span>
						<span v-if="item.path" class="reference__path">{{ item.path }}</span>
						<span v-if="item.uses !== null" class="reference__count mono">{{ item.uses }}</span>
					</slot>
				</button>
			</li>
			<li v-if="create" role="option" :aria-selected="active === items.length" class="reference__create">
				<button type="button" :class="{ 'is-active': active === items.length }" @mousedown.prevent @click="$emit('choose', null)">
					<AdminIcon name="plus" /><span class="reference__option-name">{{ create }}</span>
				</button>
			</li>
		</ul>
		<p v-if="more" class="reference__more">{{ more }}</p>
		<p v-if="!items.length && !create && empty" class="reference__quiet">{{ empty }}</p>
	</div>
</template>
