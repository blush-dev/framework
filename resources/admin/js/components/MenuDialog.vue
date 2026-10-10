<script setup lang="ts">
/**
 * New Menu and Duplicate, one dialog (from the menus sketch): a label,
 * and a name that follows it until it's typed in. Either way the result
 * is a draft (`draft`) that opens on its own screen at once, written
 * only when it's published there. A copy has the same items, in no
 * location.
 */

import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { errorMessage } from '../api';
import { plural } from '../format';
import { draft, freeName, loadMenu, menuSlug, MENU_NAME, type MenuNodeData } from '../menus';
import AdminIcon from './AdminIcon.vue';
import AdminModal from './AdminModal.vue';

const props = defineProps<{
	// The menus there are, by name.
	names: string[];
	// The menu to copy, with its items when they're at hand.
	from?: { name: string; label: string; items: number; nodes?: MenuNodeData[] } | null;
}>();

const emit = defineEmits<{ close: [] }>();

const router = useRouter();

const label   = ref(props.from ? `${props.from.label} (Copy)` : '');
const name    = ref(props.from ? freeName(label.value, props.names) : '');
const typed   = ref(false);
const busy    = ref(false);
const failure = ref('');

watch(label, (text) => {
	if (!typed.value) {
		name.value = freeName(text, props.names);
	}
});

function typeName(event: Event): void {
	typed.value = true;
	name.value  = menuSlug((event.target as HTMLInputElement).value);
}

const taken   = computed(() => props.names.includes(name.value));
const problem = computed(() => {
	if (taken.value) {
		return `There's already a menu named ${name.value}. Pick another name.`;
	}

	return name.value !== '' && !MENU_NAME.test(name.value) ? 'Start the name with a letter or digit.' : '';
});
const ready = computed(() => label.value.trim() !== '' && name.value !== '' && problem.value === '');

async function create(): Promise<void> {
	if (!ready.value || busy.value) {
		return;
	}

	busy.value    = true;
	failure.value = '';

	try {
		const items = props.from ? props.from.nodes ?? (await loadMenu(props.from.name)).menu.items : [];

		draft.value = { name: name.value, label: label.value.trim(), items };
		emit('close');
		await router.push({ name: 'menu', params: { name: name.value } });
	} catch (caught) {
		failure.value = errorMessage(caught, `${props.from?.label ?? 'The menu'} couldn't be copied.`);
	} finally {
		busy.value = false;
	}
}
</script>

<template>
	<AdminModal open :title="from ? `Duplicate ${from.label}` : 'New Menu'" @close="emit('close')">
		<form id="menu-dialog" class="form-stack" novalidate @submit.prevent="create">
			<p v-if="from" class="menu-dialog__lead">The copy has the same {{ plural(from.items, 'item') }} and isn't in any location until you choose one.</p>
			<div class="field">
				<label for="menu-dialog-label">Label</label>
				<input id="menu-dialog-label" v-model="label" autocomplete="off" autofocus aria-describedby="menu-dialog-label-help">
				<p id="menu-dialog-label-help" class="field__help">Shown here, and read out by screen readers as the name of the navigation.</p>
			</div>
			<div class="field">
				<label for="menu-dialog-name">Name</label>
				<input id="menu-dialog-name" :value="name" class="mono" autocomplete="off" spellcheck="false" :aria-invalid="problem !== ''" aria-describedby="menu-dialog-name-help" @input="typeName">
				<p v-if="problem" id="menu-dialog-name-help" class="field__error"><AdminIcon name="triangle-alert" />{{ problem }}</p>
				<p v-else id="menu-dialog-name-help" class="field__help">Themes and <code>::menu{name=…}</code> find the menu by it. You can change it later in Settings.</p>
			</div>
			<p v-if="failure" class="field__error" role="alert">{{ failure }}</p>
		</form>

		<template #footer>
			<button type="button" class="button" @click="emit('close')">Cancel</button>
			<button type="submit" form="menu-dialog" class="button button--primary" :disabled="!ready || busy">{{ from ? 'Create Copy' : 'Create Menu' }}</button>
		</template>
	</AdminModal>
</template>

<style scoped>
.menu-dialog__lead {
	margin: 0;
	font-size: var(--text-sm);
}
</style>
