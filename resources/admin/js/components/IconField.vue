<script setup lang="ts">
/**
 * An icon setting (a type's, D-311; a menu item's), as the menus sketch
 * draws it. Unset, it's a dashed tile and **Insert an Icon**; set, it's
 * the icon in a tile, pressed to change it, and **Reset**. Both open the
 * site's icons (`IconPicker`). A name no icon has (one written in a file
 * for an icon that's gone) shows a warning in the tile.
 */

import { computed, ref } from 'vue';
import { iconMask, loadIcons, type SiteIcon } from '../site-icons';
import AdminIcon from './AdminIcon.vue';
import IconPicker from './IconPicker.vue';

defineProps<{
	id: string;
	describedBy?: string;
	// What the choice writes, for the picker's footer: `icon: house`.
	preview: (icon: SiteIcon) => string;
}>();

const name = defineModel<string>({ required: true });

const picking = ref(false);
const icons   = ref<SiteIcon[] | null>(null);

loadIcons().then((list) => {
	icons.value = list;
}, () => {
	icons.value = [];
});

const chosen = computed(() => icons.value?.find((icon) => icon.name === name.value) ?? null);
const known  = computed(() => icons.value === null || chosen.value !== null);

function choose(icon: SiteIcon): void {
	name.value    = icon.name;
	picking.value = false;
}
</script>

<template>
	<div class="icon-field">
		<template v-if="name">
			<button :id="id" type="button" class="icon-field__tile is-set" aria-haspopup="dialog" :aria-describedby="describedBy" :title="known ? chosen?.label ?? name : `No icon is named ${name}`" @click="picking = true">
				<span v-if="chosen && chosen.svg" class="icon icon-field__mask" :style="{ maskImage: iconMask(chosen) }" aria-hidden="true" />
				<AdminIcon v-else-if="!known" name="triangle-alert" />
				<span class="visually-hidden">Change the icon, {{ chosen?.label ?? name }}</span>
			</button>
			<button type="button" class="button button--ghost button--small" @click="name = ''">Reset</button>
		</template>
		<template v-else>
			<button type="button" class="icon-field__tile" tabindex="-1" aria-hidden="true" @click="picking = true"><AdminIcon name="plus" /></button>
			<button :id="id" type="button" class="button button--ghost button--small" aria-haspopup="dialog" :aria-describedby="describedBy" @click="picking = true">Insert an Icon</button>
		</template>
	</div>
	<IconPicker v-if="picking" :preview="preview" @choose="choose" @close="picking = false" />
</template>

<style scoped>
.icon-field {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	min-height: var(--ctl);
}

.icon-field__tile {
	display: grid;
	flex: none;
	place-items: center;
	width: var(--ctl);
	height: var(--ctl);
	padding: 0;
	border: 1px dashed var(--border-strong);
	border-radius: var(--r-1);
	background: transparent;
	color: var(--fg-3);
}

.icon-field__tile:hover {
	border-color: var(--fg-3);
	color: var(--fg);
}

.icon-field__tile.is-set {
	border-style: solid;
	border-color: var(--border);
	background: var(--surface);
	color: var(--fg);
}

.icon-field__tile.is-set:hover {
	border-color: var(--accent);
	color: var(--accent);
}

.icon-field__tile .icon {
	width: 18px;
	height: 18px;
}

.icon-field__mask {
	display: inline-block;
	background: currentColor;
	mask-position: center;
	mask-repeat: no-repeat;
	mask-size: contain;
}
</style>
