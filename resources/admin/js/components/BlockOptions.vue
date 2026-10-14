<script setup lang="ts">
/**
 * The editor's element tab for a block (admin.md §8, D-245): the options of the
 * block the caret is in, as a form from its props (`GET blocks`),
 * written back into its block as they change. Only the attribute
 * that changed is rewritten; the rest of the author's text stays as it
 * is. An option set back to its default is removed, so the default
 * applies; a required one left empty stays, empty. Removing it takes a
 * container's contents too (D-272). A block that
 * takes a line of text has it here too, as its `[label]`.
 *
 * A block with variants (D-266) has a Variant select first, since
 * the variant usually changes what the options mean. Default writes no
 * attribute; any other writes `variant=name`. A variant the block
 * doesn't have (another theme's, say) is kept and shown as such.
 *
 * A media option has **Choose**, which opens the media picker (D-247).
 * There's no button to go to the block in the text: the caret is
 * already in it (D-265).
 *
 * Every block takes classes and an id (D-268), so it has the same
 * **Classes** and **ID** fields every object on the tab has. Other
 * attributes the block doesn't declare are listed as written. A
 * block that isn't in the list (not registered, or without a class)
 * can only be edited in the text, but for those two fields.
 */

import { computed } from 'vue';
import AdminIcon from './AdminIcon.vue';
import AttributeFields from './AttributeFields.vue';
import FieldControl from './FieldControl.vue';
import OptionSelect from './OptionSelect.vue';
import OptionsGroup from './OptionsGroup.vue';
import RawValues from './RawValues.vue';
import type { BlockDescription, BlockProp } from '../blocks';
import type { FormValue } from '../fields';
import { t } from '../messages';
import MessageText from './MessageText.vue';
import { attributeParts, attributesOf, blockHead, withAttribute, withBlockParts, withLabel, type Block, type Edit } from '../markdown';

const props = defineProps<{
	source: string;
	block: Block;
	described: BlockDescription | undefined;
}>();

const emit = defineEmits<{
	edit: [edit: Edit];
	remove: [];
	// A media option's Choose button: the editor opens the media picker.
	pick: [prop: BlockProp];
}>();

const head       = computed(() => blockHead(props.source, props.block));
const attributes = computed(() => attributesOf(props.source, props.block));

// The `label` prop is the block's `[label]`, shown as its text.
const options = computed(() => (props.described?.props ?? []).filter((prop) => prop.name !== 'label'));
const takesText = computed(() => props.described?.content === 'text' || head.value.label !== null);

const variants = computed(() => props.described?.variants ?? []);
const variant  = computed(() => attributes.value.variant ?? '');
const chosen   = computed(() => variants.value.find((item) => item.name === variant.value));

function changeVariant(value: string): void {
	const edit  = withAttribute(props.source, props.block, 'variant', value === '' || value === 'default' ? null : value);

	if (edit !== null) {
		emit('edit', edit);
	}
}

// Default first, then the block's variants here, and one it names
// that isn't here, kept.
const variantOptions = computed(() => [
	{ value: '', label: t('block_options.default', 'Default') },
	...variants.value.map((item) => ({ value: item.name, label: item.source ? t('block_options.variant_from', '{label} ({source})', { label: item.label, source: item.source.label }) : item.label })),
	...(variant.value !== '' && variant.value !== 'default' && !chosen.value ? [{ value: variant.value, label: t('block_options.variant_missing', '{variant} (not available here)', { variant: variant.value }) }] : [])
]);

const parts = computed(() => attributeParts(head.value.attributes?.text ?? ''));

function changeParts(classes: string[], id: string): void {
	const edit = withBlockParts(props.source, props.block, classes, id);

	if (edit !== null) {
		emit('edit', edit);
	}
}

const others = computed(() => {
	const known = new Set([...options.value.map((prop) => prop.name), 'class', 'id', ...(variants.value.length ? ['variant'] : [])]);

	return Object.entries(attributes.value).filter(([name]) => !known.has(name));
});

function isBool(prop: BlockProp): boolean {
	return prop.type === 'bool';
}

function valueOf(prop: BlockProp): FormValue {
	const written = attributes.value[prop.name];

	if (isBool(prop)) {
		return written === undefined ? prop.default === true : written !== 'false';
	}

	return written ?? (prop.default === undefined || prop.default === null ? '' : String(prop.default as string | number));
}

function change(prop: BlockProp, value: FormValue): void {
	let written: string | null;

	if (typeof value === 'boolean') {
		written = value === (prop.default === true) ? null : String(value);
	} else if (value === '') {
		written = prop.required === true ? '' : null;
	} else {
		written = prop.default !== undefined && prop.default !== null && value === String(prop.default as string | number) ? null : value;
	}

	const edit = withAttribute(props.source, props.block, prop.name, written);

	if (edit !== null) {
		emit('edit', edit);
	}
}

function changeLabel(event: Event): void {
	emit('edit', withLabel(props.source, props.block, (event.target as HTMLInputElement).value));
}

const removal = computed(() => {
	if (props.block.kind === 'leaf') {
		return { label: t('block_options.remove', 'Remove Block'), note: t('block_options.remove_leaf', 'Its line is removed.') };
	}

	return { label: t('block_options.remove', 'Remove Block'), note: props.block.kind === 'container' ? t('block_options.remove_container', 'Everything inside it goes too.') : t('block_options.remove_inline', 'Its text stays in the sentence.') };
});
</script>

<template>
	<div class="options">
		<OptionsGroup v-if="described?.description || !described">
			<p v-if="described?.description" class="options__note">{{ described.description }}</p>
			<p v-else class="options__note">
				<MessageText :text="t('block_options.unknown', '{name} isn\'t in the list of blocks this site offers, so its options can\'t be shown here. Edit it in the text.')"><template #name><code>{{ block.name }}</code></template></MessageText>
			</p>
		</OptionsGroup>

		<OptionsGroup v-if="variants.length" :heading="t('block_options.variant', 'Variant')">
			<OptionSelect id="option-variant" :label="t('block_options.variant', 'Variant')" :model-value="variant === 'default' ? '' : variant" :options="variantOptions" @update:model-value="changeVariant">
				<template #help>
					<template v-if="chosen">{{ chosen.description || t('block_options.variant_help', 'A style the theme provides.') }}</template>
					<template v-else-if="variant !== '' && variant !== 'default'">{{ t('block_options.variant_unavailable', "This site's theme doesn't have it, so it shows as Default.") }}</template>
					<template v-else>{{ t('block_options.variant_default', "The theme's own styling for this block.") }}</template>
				</template>
			</OptionSelect>
		</OptionsGroup>

		<OptionsGroup v-if="takesText || options.length" :heading="t('block_options.options', 'Options')">
			<div v-if="takesText" class="field">
				<label for="option-label">{{ t('block_options.text', 'Text') }}</label>
				<input id="option-label" :value="head.label?.text ?? ''" autocomplete="off" @input="changeLabel">
				<p class="field__help">{{ t('block_options.text_help', 'Written in the brackets after its name.') }}</p>
			</div>
			<FieldControl
				v-for="prop in options"
				:key="`${block.start}-${prop.name}`"
				:field="prop"
				id-prefix="option-"
				:model-value="valueOf(prop)"
				pickable
				@update:model-value="change(prop, $event)"
				@pick="emit('pick', prop)"
			/>
		</OptionsGroup>

		<OptionsGroup :heading="t('block_options.attributes', 'Attributes')">
			<AttributeFields :classes="parts.classes" :id="parts.id" id-prefix="option-" @change="changeParts" />
			<template v-if="others.length">
				<RawValues :entries="others" />
				<p class="field__help">{{ t('block_options.others', 'Not options of this block; edit them in the text.') }}</p>
			</template>
		</OptionsGroup>

		<slot />

		<OptionsGroup :heading="t('block_options.source', 'Source')">
			<pre class="options__source">{{ source.slice(head.start, head.end) }}</pre>
			<div>
				<button type="button" class="button button--small button--danger" @click="emit('remove')">
					<AdminIcon name="trash-2" />{{ removal.label }}
				</button>
			</div>
			<p class="field__help">{{ t('block_options.remove_note', '{note} Undo in the text takes it back.', { note: removal.note }) }}</p>
		</OptionsGroup>
	</div>
</template>
