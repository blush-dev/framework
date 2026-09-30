<script setup lang="ts">
/**
 * The editor's Component tab (admin.md §8, D-245): the options of the
 * component the caret is in, as a form from its props (`GET components`),
 * written back into its directive as they change. Only the attribute
 * that changed is rewritten; the rest of the author's text stays as it
 * is. An option set back to its default is removed, so the default
 * applies; a required one left empty stays, empty. Removing it takes a
 * container's contents too (D-272). A component that
 * takes a line of text has it here too, as its `[label]`.
 *
 * A component with variants (D-266) has a Variant select first, since
 * the variant usually changes what the options mean. Default writes no
 * attribute; any other writes `variant=name`. A variant the component
 * doesn't have (another theme's, say) is kept and shown as such.
 *
 * A media option has **Choose**, which opens the media picker (D-247).
 * There's no button to go to the component in the text: the caret is
 * already in it (D-265).
 *
 * Every component takes classes and an id (D-268), so it has the same
 * **Classes** and **ID** fields every object on the tab has. Other
 * attributes the component doesn't declare are listed as written. A
 * component that isn't in the list (not registered, or without a class)
 * can only be edited in the text, but for those two fields.
 */

import { computed } from 'vue';
import AdminIcon from './AdminIcon.vue';
import AttributeFields from './AttributeFields.vue';
import FieldControl from './FieldControl.vue';
import type { ComponentDescription, ComponentProp } from '../components';
import type { FormValue } from '../fields';
import { attributeParts, attributesOf, directiveHead, withAttribute, withDirectiveParts, withLabel, type Directive, type Edit } from '../markdown';

const props = defineProps<{
	source: string;
	directive: Directive;
	component: ComponentDescription | undefined;
}>();

const emit = defineEmits<{
	edit: [edit: Edit];
	remove: [];
	// A media option's Choose button: the editor opens the media picker.
	pick: [prop: ComponentProp];
}>();

const head       = computed(() => directiveHead(props.source, props.directive));
const attributes = computed(() => attributesOf(props.source, props.directive));

// The `label` prop is the directive's `[label]`, shown as its text.
const options = computed(() => (props.component?.props ?? []).filter((prop) => prop.name !== 'label'));
const takesText = computed(() => props.component?.content === 'text' || head.value.label !== null);

const variants = computed(() => props.component?.variants ?? []);
const variant  = computed(() => attributes.value.variant ?? '');
const chosen   = computed(() => variants.value.find((item) => item.name === variant.value));

function changeVariant(event: Event): void {
	const value = (event.target as HTMLSelectElement).value;
	const edit  = withAttribute(props.source, props.directive, 'variant', value === '' || value === 'default' ? null : value);

	if (edit !== null) {
		emit('edit', edit);
	}
}

const parts = computed(() => attributeParts(head.value.attributes?.text ?? ''));

function changeParts(classes: string[], id: string): void {
	const edit = withDirectiveParts(props.source, props.directive, classes, id);

	if (edit !== null) {
		emit('edit', edit);
	}
}

const others = computed(() => {
	const known = new Set([...options.value.map((prop) => prop.name), 'class', 'id', ...(variants.value.length ? ['variant'] : [])]);

	return Object.entries(attributes.value).filter(([name]) => !known.has(name));
});

function isBool(prop: ComponentProp): boolean {
	return prop.type === 'bool';
}

function valueOf(prop: ComponentProp): FormValue {
	const written = attributes.value[prop.name];

	if (isBool(prop)) {
		return written === undefined ? prop.default === true : written !== 'false';
	}

	return written ?? (prop.default === undefined || prop.default === null ? '' : String(prop.default as string | number));
}

function change(prop: ComponentProp, value: FormValue): void {
	let written: string | null;

	if (typeof value === 'boolean') {
		written = value === (prop.default === true) ? null : String(value);
	} else if (value === '') {
		written = prop.required === true ? '' : null;
	} else {
		written = prop.default !== undefined && prop.default !== null && value === String(prop.default as string | number) ? null : value;
	}

	const edit = withAttribute(props.source, props.directive, prop.name, written);

	if (edit !== null) {
		emit('edit', edit);
	}
}

function changeLabel(event: Event): void {
	emit('edit', withLabel(props.source, props.directive, (event.target as HTMLInputElement).value));
}

const removal = computed(() => {
	if (props.directive.kind === 'leaf') {
		return { label: 'Remove component', note: 'Its line is removed.' };
	}

	return { label: 'Remove component', note: props.directive.kind === 'container' ? 'Everything inside it goes too.' : 'Its text stays in the sentence.' };
});
</script>

<template>
	<div class="options">
		<div v-if="component?.description || !component" class="options__group">
			<p v-if="component?.description" class="options__note">{{ component.description }}</p>
			<p v-else class="options__note">
				<code>{{ directive.name }}</code> isn't in the list of components this site offers, so its options can't be shown here. Edit it in the text.
			</p>
		</div>

		<div v-if="variants.length" class="options__group">
			<p class="options__heading">Variant</p>
			<div class="field">
				<label class="visually-hidden" for="option-variant">Variant</label>
				<select id="option-variant" :value="variant === 'default' ? '' : variant" aria-describedby="option-variant-help" @change="changeVariant">
					<option value="">Default</option>
					<option v-for="item in variants" :key="item.name" :value="item.name">{{ item.label }}<template v-if="item.source && item.source.kind !== 'site'"> ({{ item.source.label }})</template></option>
					<option v-if="variant !== '' && variant !== 'default' && !chosen" :value="variant">{{ variant }} (not available here)</option>
				</select>
				<p id="option-variant-help" class="field__help">
					<template v-if="chosen">{{ chosen.description || 'A style the theme provides.' }}</template>
					<template v-else-if="variant !== '' && variant !== 'default'">This site's theme doesn't have it, so it shows as Default.</template>
					<template v-else>The theme's own styling for this component.</template>
				</p>
			</div>
		</div>

		<div v-if="takesText || options.length" class="options__group">
			<p class="options__heading">Options</p>
			<div v-if="takesText" class="field">
				<label for="option-label">Text</label>
				<input id="option-label" :value="head.label?.text ?? ''" autocomplete="off" @input="changeLabel">
				<p class="field__help">Written in the brackets after its name.</p>
			</div>
			<FieldControl
				v-for="prop in options"
				:key="`${directive.start}-${prop.name}`"
				:field="prop"
				id-prefix="option-"
				:model-value="valueOf(prop)"
				pickable
				@update:model-value="change(prop, $event)"
				@pick="emit('pick', prop)"
			/>
		</div>

		<div class="options__group">
			<p class="options__heading">Attributes</p>
			<AttributeFields :classes="parts.classes" :id="parts.id" id-prefix="option-" @change="changeParts" />
			<template v-if="others.length">
				<dl class="options__others">
					<div v-for="[name, value] in others" :key="name">
						<dt class="mono">{{ name }}</dt>
						<dd class="mono">{{ value }}</dd>
					</div>
				</dl>
				<p class="field__help">Not options of this component; edit them in the text.</p>
			</template>
		</div>

		<div class="options__group">
			<p class="options__heading">Source</p>
			<pre class="options__source">{{ source.slice(head.start, head.end) }}</pre>
			<div>
				<button type="button" class="button button--small button--danger" @click="emit('remove')">
					<AdminIcon name="trash-2" />{{ removal.label }}
				</button>
			</div>
			<p class="field__help">{{ removal.note }} Undo in the text takes it back.</p>
		</div>
	</div>
</template>

<style scoped>
.options__others {
	display: grid;
	gap: 6px;
	margin: 0;
}

.options__others div {
	display: grid;
	gap: 2px;
}

.options__others dt {
	color: var(--fg-2);
	font-size: var(--text-xs);
}

.options__others dd {
	margin: 0;
	font-size: var(--text-xs);
	overflow-wrap: anywhere;
}
</style>
