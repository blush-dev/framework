<script setup lang="ts">
/**
 * The editor's Component tab (admin.md §8, D-245): the options of the
 * component the caret is in, as a form from its props (`GET components`),
 * written back into its directive as they change. Only the attribute
 * that changed is rewritten; the rest of the author's text stays as it
 * is. An option set back to its default is removed, so the default
 * applies; a required one left empty stays, empty. A component that
 * takes a line of text has it here too, as its `[label]`.
 *
 * A media option has **Choose**, which opens the media picker (D-247).
 *
 * Attributes the component doesn't declare (a class, an id, anything
 * else) are listed as written. A component that isn't in the list (not
 * registered, or without a class) can only be edited in the text.
 */

import { computed } from 'vue';
import AdminIcon from './AdminIcon.vue';
import FieldControl from './FieldControl.vue';
import type { ComponentDescription, ComponentProp } from '../components';
import type { FormValue } from '../fields';
import { attributesOf, directiveHead, withAttribute, withLabel, type Directive, type Edit } from '../markdown';

const props = defineProps<{
	source: string;
	directive: Directive;
	component: ComponentDescription | undefined;
}>();

const emit = defineEmits<{
	edit: [edit: Edit];
	jump: [];
	remove: [];
	// A media option's Choose button: the editor opens the media picker.
	pick: [prop: ComponentProp];
}>();

const head       = computed(() => directiveHead(props.source, props.directive));
const attributes = computed(() => attributesOf(props.source, props.directive));

// The `label` prop is the directive's `[label]`, shown as its text.
const options = computed(() => (props.component?.props ?? []).filter((prop) => prop.name !== 'label'));
const takesText = computed(() => props.component?.content === 'text' || head.value.label !== null);

const others = computed(() => {
	const known = new Set(options.value.map((prop) => prop.name));

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

	return { label: 'Remove component', note: props.directive.kind === 'container' ? 'The text inside it stays.' : 'Its text stays in the sentence.' };
});
</script>

<template>
	<div class="options">
		<div class="options__group">
			<p v-if="component?.description" class="options__note">{{ component.description }}</p>
			<p v-else-if="!component" class="options__note">
				<code>{{ directive.name }}</code> isn't in the list of components this site offers, so its options can't be shown here. Edit it in the text.
			</p>
			<div>
				<button type="button" class="button button--small" @click="emit('jump')">
					<AdminIcon name="arrow-down" />Go to it in the text
				</button>
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

		<div v-if="others.length" class="options__group">
			<p class="options__heading">Other attributes</p>
			<dl class="options__others">
				<div v-for="[name, value] in others" :key="name">
					<dt class="mono">{{ name }}</dt>
					<dd class="mono">{{ value }}</dd>
				</div>
			</dl>
			<p class="field__help">Not options of this component; edit them in the text.</p>
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
.options__group {
	display: grid;
	gap: 11px;
	padding: 13px 14px;
	border-bottom: 1px solid var(--border);
}

.options__heading {
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-weight: 600;
	letter-spacing: .07em;
	text-transform: uppercase;
}

.options__note {
	color: var(--fg-2);
	font-size: var(--text-sm);
	line-height: 1.5;
}

.options__source {
	margin: 0;
	padding: 7px 8px;
	overflow-x: auto;
	border: 1px solid var(--border);
	border-radius: var(--r-1);
	background: var(--surface-2);
	font-family: var(--font-mono);
	font-size: var(--text-sm);
	white-space: pre;
}

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
