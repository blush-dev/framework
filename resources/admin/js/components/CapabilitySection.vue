<script setup lang="ts" generic="Preset extends { key: string; label: string; text: string }">
/**
 * One section of a role's capabilities (D-359, D-509): a head that opens
 * it, with a triangle, the `icon` slot, its title and any `marks`, a
 * pill when it holds an unsaved change, its key, and a sentence saying
 * what the role can do; then, with a `menuLabel`, a ⋮ that sets the
 * whole section (`presets`, each a name and what it does, then the
 * `menu` slot); then, open, its checkboxes (the default slot) in a grid,
 * and a note.
 */

import AdminIcon from './AdminIcon.vue';
import MenuButton from './MenuButton.vue';

defineProps<{
	title: string;
	open: boolean;
	// The id of the body, which the head controls.
	bodyId: string;
	sentence: { text: string; none: boolean };
	changed: boolean;
	keyText?: string;
	note?: string;
	// Every Type, set apart above the types it fixes.
	every?: boolean;
	menuLabel?: string;
	menuHeading?: string;
	presets?: Preset[];
}>();

const emit = defineEmits<{
	toggle: [];
	preset: [item: Preset];
}>();
</script>

<template>
	<div class="section" :class="{ 'section--every': every }">
		<button type="button" class="section__head" :aria-expanded="open" :aria-controls="bodyId" @click="emit('toggle')">
			<AdminIcon name="chevron-right" class="section__twisty" />
			<slot name="icon" />
			<span class="section__main">
				<span class="section__name">
					<span class="section__title">{{ title }}</span>
					<slot name="marks" />
					<span v-if="changed" class="pill pill--warn section__pill">Changes</span>
				</span>
				<code v-if="keyText" class="section__key">{{ keyText }}</code>
				<span class="section__sentence" :class="{ 'is-none': sentence.none }">{{ sentence.text }}</span>
			</span>
		</button>
		<span v-if="menuLabel" class="section__end">
			<MenuButton button-class="button button--ghost button--small button--icon section__menu" :label="menuLabel" floating>
				<template #button><AdminIcon name="ellipsis-vertical" /></template>
				<p class="menu-heading">{{ menuHeading }}</p>
				<button v-for="item in presets" :key="item.key" type="button" class="menu-item menu-item--described" @click="emit('preset', item)">
					<span>
						<span class="menu-item__name">{{ item.label }}</span>
						<span class="menu-item__text">{{ item.text }}</span>
					</span>
				</button>
				<slot name="menu" />
			</MenuButton>
		</span>
		<div v-if="open" :id="bodyId" class="section__body">
			<div class="section__grid">
				<slot />
			</div>
			<p v-if="note" class="section__note">{{ note }}</p>
		</div>
	</div>
</template>

<style scoped>
/* A section: a name, a sentence, and a triangle. */
.section {
	display: flex;
	flex-wrap: wrap;
	align-items: stretch;
	border-bottom: 1px solid var(--border);
}

.section:last-child {
	border-bottom: 0;
	border-radius: 0 0 var(--r-3) var(--r-3);
}

.section__head {
	display: flex;
	flex: 1;
	align-items: center;
	gap: var(--s-3);
	min-width: 0;
	padding: var(--pad-row) var(--s-2) var(--pad-row) var(--pad-x);
	border: 0;
	background: none;
	color: inherit;
	font: inherit;
	text-align: left;
	cursor: pointer;
}

.section__head:hover,
.section:has(.section__head:hover) .section__end {
	background: var(--surface-2);
}

.section__twisty {
	width: 13px;
	height: 13px;
	color: var(--fg-3);
}

.section__head[aria-expanded="true"] .section__twisty {
	transform: rotate(90deg);
}

@media (prefers-reduced-motion: no-preference) {
	.section__twisty {
		transition: transform .12s;
	}
}

.section__main {
	flex: 1;
	min-width: 0;
}

.section__name {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
	min-width: 0;
}

.section__title {
	overflow: hidden;
	font-family: var(--font-title);
	font-size: var(--title-size);
	font-weight: var(--title-weight);
	letter-spacing: var(--title-track);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.section__pill {
	height: 19px;
	padding: 0 8px 0 7px;
	font-size: var(--text-2xs);
}

.section__key {
	display: none;
	margin-top: 3px;
	color: var(--fg-3);
	font-family: var(--font-mono);
	font-size: var(--text-2xs);
}

.sections--keys .section__key {
	display: block;
}

.section__sentence {
	display: block;
	max-width: 76ch;
	margin-top: 4px;
	color: var(--fg-3);
	font-size: var(--text-sm);
	text-wrap: pretty;
}

.section__sentence.is-none {
	font-style: italic;
}

.section__end {
	display: flex;
	flex: none;
	align-items: center;
	padding-right: var(--pad-x);
}

/* The ⋮ shows on hover, and always to the keyboard. */
.section__end :deep(.section__menu) {
	opacity: 0;
}

.section:hover .section__end :deep(.section__menu),
.section__end :deep(.section__menu:focus-visible),
.section__end :deep(.section__menu[aria-expanded="true"]) {
	opacity: 1;
}

@media (hover: none) {
	.section__end :deep(.section__menu) {
		opacity: 1;
	}
}

/* Every Type, first, since it decides what's fixed in the types below. */
.section--every {
	border-bottom-color: var(--border-strong);
	background: var(--surface-2);
}

.section--every .section__head:hover,
.section--every:has(.section__head:hover) .section__end {
	background: var(--surface-3);
}

.section__body {
	flex: 0 0 100%;
	box-sizing: border-box;
	min-width: 0;
	padding: var(--s-3) var(--pad-x) var(--s-3) calc(var(--pad-x) + 25px);
	border-top: 1px solid var(--border);
	background: var(--surface-2);
}

/* Three columns, in every section, so the boxes line up down the page. */
.section__grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 0 var(--s-5);
	max-width: 880px;
}

.section__note {
	max-width: 60ch;
	padding-top: var(--s-2);
	color: var(--fg-3);
	font-size: var(--text-sm);
}

/* Two where three would cut the labels short. */
@media (width <= 1100px) {
	.section__grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}

@media (width <= 640px) {
	.section__head {
		padding-left: var(--s-4);
	}

	.section__end {
		padding-right: var(--s-4);
	}

	.section__body {
		padding-right: var(--s-4);
		padding-left: calc(var(--s-4) + 23px);
	}

	.section__grid {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
