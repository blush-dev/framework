<script setup lang="ts">
/**
 * One box for where a link goes (D-686's **To**, shared with menu items):
 * a path, a whole address, or part of a page's name, whose matches open
 * in the admin's floating list under it (arrows move, Enter picks). A
 * picked option sits in the box as a token; its × or Backspace emits
 * `clear` and puts the focus back in the box, for the screen to put the
 * typing back. Escape closes the list first.
 *
 * Whoever uses it searches (`search`, asked as typing stops), may offer
 * what's typed as an option of its own (`typed`, such as "Link to
 * https://…"), and may say why nothing matched (`empty`); without it the
 * list stays closed when there's nothing to show.
 */

import { nextTick, ref, watch } from 'vue';
import { debounced, latest } from '../action';
import { listMove } from '../grid';
import type { IconName } from '../icons';
import { usePopover } from '../popover';
import AdminIcon from './AdminIcon.vue';

export interface LinkOption {
	key: string;
	icon: IconName;
	title: string;
	// Where it is: an address, in code type.
	hint: string;
	pill?: { label: string; kind: 'warn' | 'danger' } | null;
	// Shown as "Link to …": what was typed.
	typed?: boolean;
}

const props = defineProps<{
	id: string;
	// The picked option, drawn as a token, or `null` for the box.
	chosen: LinkOption | null;
	// What matches, and a line for the list's foot (how many more there are).
	search: (text: string) => Promise<LinkOption[] | { options: LinkOption[]; note: string }>;
	typed?: (text: string) => LinkOption | null;
	empty?: string;
	placeholder?: string;
	invalid?: boolean;
	describedBy?: string;
	autofocus?: boolean;
	label?: string;
}>();

const text = defineModel<string>({ required: true });

const emit = defineEmits<{
	choose: [option: LinkOption];
	clear: [];
	blur: [];
}>();

const field   = ref<HTMLInputElement | null>(null);
const list    = ref<HTMLElement | null>(null);
const options = ref<LinkOption[]>([]);
const note    = ref('');
const active  = ref(-1);
const ask     = latest();
const popover = usePopover(field, list, { gap: 4, sameWidth: true });
const { open, place, layer } = popover;
const listId  = `${props.id}-options`;

const find = debounced(async () => {
	const current = ask();
	const typed   = props.typed?.(text.value.trim()) ?? null;

	try {
		const found = await props.search(text.value.trim());

		if (!current()) {
			return;
		}

		const list = Array.isArray(found) ? found : found.options;

		note.value    = Array.isArray(found) ? '' : found.note;
		options.value = typed === null ? list : [typed, ...list];
		active.value  = options.value.length ? 0 : -1;

		if ((options.value.length || props.empty) && document.activeElement === field.value) {
			await popover.show();
		} else {
			popover.close(false);
		}
	} catch {
		if (current()) {
			options.value = typed === null ? [] : [typed];
			note.value    = '';
			popover.close(false);
		}
	}
}, 200);

watch(text, () => {
	if (props.chosen === null) {
		find();
	}
});

function choose(option: LinkOption | undefined): void {
	if (option !== undefined) {
		popover.close(false);
		emit('choose', option);
	}
}

async function clear(): Promise<void> {
	emit('clear');
	await nextTick();
	field.value?.focus();
}

function key(event: KeyboardEvent): void {
	if (event.key === 'Escape' && open.value) {
		event.preventDefault();
		event.stopPropagation();
		popover.close(false);

		return;
	}

	if (!open.value || !options.value.length) {
		return;
	}

	const next = listMove(event.key, active.value, options.value.length);

	if (next !== null) {
		event.preventDefault();
		active.value = next;
	} else if (event.key === 'Enter' && active.value >= 0) {
		event.preventDefault();
		choose(options.value[active.value]);
	}
}

function tokenKey(event: KeyboardEvent): void {
	if (event.key === 'Backspace' || event.key === 'Delete') {
		event.preventDefault();
		void clear();
	}
}

defineExpose({ focus: () => field.value?.focus() });
</script>

<template>
	<div v-if="chosen" class="input link-picker__chosen" :class="{ 'is-invalid': invalid }" tabindex="0" role="group" :aria-label="`${label ?? 'Link'}: ${chosen.title}`" :aria-describedby="describedBy" @keydown="tokenKey">
		<AdminIcon :name="chosen.icon" />
		<strong>{{ chosen.title }}</strong>
		<span v-if="chosen.pill" class="pill" :class="`pill--${chosen.pill.kind}`">{{ chosen.pill.label }}</span>
		<span v-if="chosen.hint" class="link-picker__at mono">{{ chosen.hint }}</span>
		<button type="button" class="button button--ghost button--icon button--small" @click="clear"><AdminIcon name="x" /><span class="visually-hidden">Clear {{ chosen.title }}</span></button>
	</div>
	<input
		v-else
		:id="id"
		ref="field"
		v-model="text"
		class="mono"
		type="text"
		:placeholder="placeholder ?? 'A path, a page\'s name, or https://…'"
		autocomplete="off"
		autocapitalize="none"
		spellcheck="false"
		role="combobox"
		aria-autocomplete="list"
		:autofocus="autofocus"
		:aria-expanded="open"
		:aria-controls="listId"
		:aria-activedescendant="open && active >= 0 ? `${listId}-${active}` : undefined"
		:aria-invalid="invalid"
		:aria-describedby="describedBy"
		@keydown="key"
		@focus="find()"
		@blur="emit('blur'); popover.close(false)"
	>
	<Teleport :to="layer">
		<div v-if="open" ref="list" class="select-list link-picker__list" :style="place ?? { visibility: 'hidden' }">
			<div :id="listId" class="select-list__options" role="listbox" :aria-label="label ?? 'Pages'">
				<button
					v-for="(option, index) in options"
					:id="`${listId}-${index}`"
					:key="option.key"
					type="button"
					role="option"
					tabindex="-1"
					class="select-list__option"
					:class="{ 'is-active': index === active }"
					:aria-selected="index === active"
					@mousedown.prevent
					@click="choose(option)"
				>
					<AdminIcon :name="option.icon" class="select-list__icon" />
					<span class="select-list__label link-picker__title"><template v-if="option.typed">Link to <b>{{ option.title }}</b></template><template v-else>{{ option.title }}</template></span>
					<span v-if="option.pill" class="pill" :class="`pill--${option.pill.kind}`">{{ option.pill.label }}</span>
					<span v-if="option.hint && !option.typed" class="select-list__hint mono link-picker__at">{{ option.hint }}</span>
				</button>
				<p v-if="!options.length && empty" class="select-list__empty">{{ empty }}</p>
			</div>
			<p v-if="note && options.length" class="select-list__note">{{ note }}</p>
		</div>
	</Teleport>
</template>

<style scoped>
/* The results fit the box's width, each shortened to fit, never
   scrolled sideways. */
.link-picker__list .select-list__options {
	overflow-x: hidden;
}

.link-picker__list .select-list__option {
	min-width: 0;
}

/* A picked option, in the box the input was. */
.link-picker__chosen {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	width: 100%;
	min-width: 0;
	padding-right: 4px;
}

.link-picker__chosen.is-invalid {
	border-color: var(--danger-dot);
}

.link-picker__chosen > .icon {
	flex: none;
	width: 15px;
	height: 15px;
	color: var(--fg-3);
}

.link-picker__chosen strong {
	overflow: hidden;
	font-weight: 500;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.link-picker__chosen .pill {
	flex: none;
}

.link-picker__chosen .link-picker__at {
	flex: 1;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.link-picker__chosen .button {
	flex: none;
	margin-left: auto;
}

.link-picker__title {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.link-picker__at {
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.select-list__option .link-picker__at {
	max-width: 45%;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
</style>
