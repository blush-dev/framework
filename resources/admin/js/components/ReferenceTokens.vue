<script setup lang="ts">
/**
 * The token picker (D-281, D-607): flat terms or short targets, several
 * at once, often typed in new. Chips and a bare input in one box; the
 * × is the only control in a chip. Focus offers the most used; typing
 * shows matches with the typed part in bold and each one's count, then
 * Create under a rule where the relation allows it. Enter takes the
 * highlighted match, creating only when Create is all there is;
 * Backspace in an empty input removes the last chip.
 *
 * A chip typed in new is the one accent chip, with a plus, and a line
 * says Updating creates it; a value that names nothing is shown as
 * written, dashed, until it's replaced. Past 12 chips the rest fold into
 * one, which opens in place. An ordered relation's chips move by
 * dragging, or with ⌥ and the arrow keys.
 */

import { computed, ref } from 'vue';
import { FOLD, CAP } from '../references';
import { usePicker, type PickerProps } from '../picker';
import AdminIcon from './AdminIcon.vue';
import ReferenceInherited from './ReferenceInherited.vue';
import ReferenceMissing from './ReferenceMissing.vue';
import ReferenceResults from './ReferenceResults.vue';

const props = defineProps<PickerProps>();
const model = defineModel<string>({ required: true });

const picker = usePicker(props, model, { cap: CAP, suggest: 'uses', suggestions: 5 });
const { names, values, itemOf, isNew, isMissing, full, ordered, reorder, query, results, before, active, more, creatable, searching, whole, total, missing, added, inheritedShown, error } = picker;

const input   = ref<HTMLInputElement | null>(null);
const focused = ref(false);
const unfold  = ref(false);

// Past 12 chips, the rest fold into one.
const shown  = computed(() => unfold.value || values.value.length <= FOLD ? values.value : values.value.slice(0, FOLD));
const folded = computed(() => values.value.length - shown.value.length);

const typing  = computed(() => query.value.trim() !== '');
const offered = computed(() => typing.value ? results.value : before.value);

function key(event: KeyboardEvent): void {
	if (event.key === 'Backspace' && query.value === '' && values.value.length > 0) {
		const last = values.value.at(-1);

		if (last !== undefined) {
			picker.remove(itemOf(last).slug);
		}

		return;
	}

	picker.searchKey(event, offered.value);
}

function focusInput(event: MouseEvent): void {
	if ((event.target as HTMLElement).closest('button, input') === null) {
		input.value?.focus();
	}
}

const placeholder = computed(() => {
	if (full.value) {
		return 'That\'s the most it takes';
	}

	if (whole.value === null && total.value > 0) {
		return `Search ${total.value.toLocaleString()} ${names.value.items}…`;
	}

	return values.value.length ? 'Add another…' : 'Type to add…';
});
</script>

<template>
	<div class="reference">
		<div class="reference__tokens" :class="{ 'is-invalid': invalid }" @click="focusInput">
			<span
				v-for="(value, index) in shown"
				:key="value"
				class="reference__token"
				:class="{ 'is-new': isNew(value), 'is-missing': isMissing(value), 'is-draft': itemOf(value).status === 'draft', 'is-trash': itemOf(value).status === 'trash' }"
				v-bind="ordered && values.length > 1 ? reorder.item(index) : {}"
				:tabindex="ordered && values.length > 1 ? 0 : undefined"
			>
				<AdminIcon v-if="ordered && values.length > 1" name="grip-vertical" class="reference__grip" />
				<AdminIcon v-if="isNew(value)" name="plus" />
				<span class="reference__token-name">{{ isMissing(value) ? value : itemOf(value).title }}</span>
				<button type="button" @click="picker.remove(itemOf(value).slug)"><AdminIcon name="x" /><span class="visually-hidden">Remove {{ itemOf(value).title }}</span></button>
			</span>
			<button v-if="folded > 0" type="button" class="reference__token is-more" @click="unfold = true">+{{ folded }} more</button>
			<input
				:id="id"
				ref="input"
				v-model="query"
				type="text"
				autocomplete="off"
				:disabled="full"
				:placeholder="placeholder"
				role="combobox"
				:aria-expanded="focused && (offered.length > 0 || creatable)"
				:aria-controls="`${id}-results`"
				:aria-describedby="describedBy"
				@focus="focused = true"
				@blur="focused = false"
				@keydown="key"
			>
		</div>
		<ReferenceResults
			v-if="focused && (typing || before.length) && !searching"
			:id="`${id}-results`"
			:items="offered"
			:active="active"
			:query="query"
			:heading="typing ? undefined : (before.some((item) => item.uses !== null) ? 'Most Used' : 'Recently Edited')"
			:more="more"
			:create="creatable ? `Create “${query.trim()}”` : null"
			:empty="typing ? `No ${names.item} matches “${query.trim()}”.` : undefined"
			@choose="picker.choose"
		/>

		<ReferenceInherited v-if="inheritedShown.length && inherited" :inherited="inherited">
			<div class="reference__tokens reference__tokens--quiet">
				<span v-for="value in inheritedShown" :key="`inherited-${value}`" class="reference__token is-inherited">{{ itemOf(value).title }}</span>
			</div>
		</ReferenceInherited>
		<p v-for="value in added" :key="`new-${value}`" class="field__help"><b>{{ value }}</b> is new. Updating creates it as a published {{ names.item }}.</p>
		<ReferenceMissing :missing="missing" :noun="names.item" @replace="picker.replace" />
		<p v-if="error" class="field__error">{{ error }}</p>
	</div>
</template>
