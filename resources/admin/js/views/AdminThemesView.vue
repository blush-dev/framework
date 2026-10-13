<script setup lang="ts">
/**
 * Admin Themes (D-827): every installed admin theme as a card, Neutral
 * first, then by label, then broken ones. Each card is a sketch of the
 * admin in the theme's own colors (`AdminThemeSketch`), so nothing of the
 * theme is loaded to show it.
 *
 * A theme's switch turns it on or off (`useAdminThemes()`), saved over
 * `config/admin-themes.php`, as icon packs' are (D-390, D-391): the
 * framework's and Composer's are on, and a local one only when config
 * names it, until a list is saved here. Neutral is always on. A theme
 * that's on can be the site's default (**Make Default**), which anyone
 * who hasn't chosen a theme sees, and the sign-in screen; the default is
 * marked **Site default**, and can't be turned off or deleted until
 * another is chosen. A theme whose requirements aren't met can't be
 * turned on, and says why (D-431). **Delete** removes a folder theme (or
 * a broken one) from `extensions/`. A theme's name, and **Admin theme
 * details** in its menu, open its details screen (`AdminThemeView`).
 *
 * Filters over the list (D-565): words matching a theme's label, name,
 * description, or keywords, its status (on, off, or needing attention:
 * can't turn on, broken, or abandoned), and its source; and Cards or a
 * Compact list of rows (`extensionView`), each marked with its accent.
 */

import { computed } from 'vue';
import AdminIcon from '../components/AdminIcon.vue';
import AdminThemeSketch from '../components/AdminThemeSketch.vue';
import EmptyState from '../components/EmptyState.vue';
import ExtensionCard from '../components/ExtensionCard.vue';
import ExtensionFilters from '../components/ExtensionFilters.vue';
import ExtensionMenu from '../components/ExtensionMenu.vue';
import ExtensionRow from '../components/ExtensionRow.vue';
import InstallModal from '../components/InstallModal.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';
import type { AdminThemeSummary } from '../api';
import { useAdminThemes } from '../admin-themes';
import { extensionView } from '../density';
import { extensionRoute, folderName, useExtensionFilter } from '../extensions';
import { useInstall } from '../install';
import { can } from '../session';

const { answer, error, busy, load, toggle: toggleTheme, makeDefault, useConfig, useConfigDefault, remove: removeTheme } = useAdminThemes();

// What the account may do here (D-389).
const canActivate = can('extensions.admin-themes.activate');
const canDelete   = can('extensions.admin-themes.delete');

// What a theme the Install modal installed is, and whether it can be turned on.
const { installing, canInstall, afterInstall, hop, installState, next } = useInstall('admin-theme', load, {
	find: (name) => themes.value.find((item) => item.name === name) ?? null,
	state: (theme) => ({
		words: theme?.enabled ? 'turned on' : 'turned off',
		next: theme !== null && !theme.enabled && blocked(theme) === null && canActivate,
		live: theme?.enabled ?? false
	}),
	start: (theme) => toggle(theme, true)
});

void load();

const themes = computed(() => answer.value?.themes ?? []);
const count  = computed(() => themes.value.length + (answer.value?.invalid.length ?? 0));
const on     = computed(() => themes.value.filter((theme) => theme.offered).length);

const { query, status, source, filtered, matches, clear } = useExtensionFilter();

const shownThemes = computed(() => themes.value.filter((theme) => matches({
	on: theme.running,
	attention: blocked(theme) !== null || theme.abandoned !== false,
	source: theme.source,
	words: [theme.label, theme.name, theme.description, ...theme.keywords]
})));
const shownBroken = computed(() => (answer.value?.invalid ?? []).filter((theme) => matches({
	on: false,
	attention: true,
	source: theme.where.startsWith('extensions/') ? 'local' : 'composer',
	words: [theme.where]
})));
const shown = computed(() => shownThemes.value.length + shownBroken.value.length);

// Rows, not cards, and the slot each draws its controls in.
const list = computed(() => extensionView.value === 'list');
const foot = computed(() => list.value ? 'end' : 'foot');

// Why a theme can't be turned on, when it isn't running, or `null` (D-431).
function blocked(theme: AdminThemeSummary): string | null {
	return theme.running ? null : theme.blocked;
}

// Why its switch is locked, or `null`.
function locked(theme: AdminThemeSummary): string | null {
	return blocked(theme)
		?? (theme.base ? 'Neutral is the admin\'s own look, so it\'s always on.' : null)
		?? (theme.default ? 'It\'s the site\'s default. Make another theme the default to turn it off.' : null)
		?? (canActivate ? null : 'Your role can\'t turn admin themes on and off.');
}

async function toggle(theme: AdminThemeSummary, value: boolean): Promise<void> {
	if (await toggleTheme(theme, value, () => void load())) {
		await load();
	}
}

async function choose(theme: AdminThemeSummary): Promise<void> {
	if (await makeDefault(theme)) {
		await load();
	}
}

async function remove(label: string, folder: string, theme: AdminThemeSummary | null = null): Promise<void> {
	if (await removeTheme(label, folder, theme)) {
		await load();
	}
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Admin Themes</h1>
			<p class="page-header__hint">How the admin looks: its colors, fonts, and sizes. Each account chooses its own on Your Account.</p>
		</div>
		<div class="page-header__actions">
			<button v-if="canInstall" type="button" class="button button--primary" @click="installing = true"><AdminIcon name="upload" />Install Admin Theme</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<template v-if="answer">
		<ExtensionFilters v-model:query="query" v-model:status="status" v-model:source="source" noun="admin themes" on="On" off="Off" :sources="['composer', 'local', 'framework']" :shown="shown" :total="count" :summary="`${on} to choose from`" :filtered="filtered" views @clear="clear" />

		<div v-if="shown === 0" class="panel">
			<EmptyState icon="search" heading="No Admin Theme Matches" text="Nothing here fits the filters in force. Clearing them brings the other admin themes back.">
				<template #actions><button type="button" class="button" @click="clear">Clear Filters</button></template>
			</EmptyState>
		</div>

		<component :is="list ? 'ul' : 'div'" v-else :class="list ? 'extension-rows' : 'extension-cards'">
			<component :is="list ? ExtensionRow : ExtensionCard" v-for="theme in shownThemes" :key="theme.name" :class="{ 'is-off': !theme.running, 'is-on': theme.running, 'is-busy': busy === theme.name }" :label="theme.label" :to="extensionRoute('admin-theme', theme.name)" :version="theme.version" :description="list ? undefined : theme.description">
				<template v-if="list" #mark><span class="theme-mark" :style="{ background: theme.preview.accent }" /></template>
				<template v-else #media><AdminThemeSketch class="theme-sketch" :preview="theme.preview" /></template>
				<template #pills>
					<span v-if="theme.default" class="pill pill--good">Site default</span>
					<span v-else-if="theme.source === 'framework'" class="pill">Built in</span>
					<span v-if="blocked(theme)" class="pill pill--warn">Can't turn on</span>
					<span v-if="theme.abandoned !== false" class="pill pill--warn">Abandoned</span>
				</template>
				<p v-if="blocked(theme)" class="notice notice--small notice--warn extension__message">
					<AdminIcon name="triangle-alert" /><span>{{ blocked(theme) }} {{ theme.enabled ? 'It\'s turned on, but no one can choose it until that\'s fixed.' : 'It can\'t be turned on until that\'s fixed.' }}</span>
				</p>
				<p v-else-if="theme.source === 'composer' && !theme.enabled" class="extension__description">Installed by Composer. It's off because the list of admin themes turned on here doesn't name it.</p>
				<ul class="extension__facts" :class="{ 'extension__facts--inline': list }">
					<li v-if="theme.source === 'framework'"><AdminIcon name="package" />Ships with Blush</li>
					<li v-else-if="theme.source === 'composer'"><AdminIcon name="package" /><span>Composer · <span class="mono">{{ theme.name }}</span></span></li>
					<li v-else><AdminIcon name="folder" /><span class="mono">{{ theme.path }}</span></li>
					<li v-if="theme.contrast.length" class="is-warn"><AdminIcon name="triangle-alert" /><span>Some text is hard to read</span></li>
				</ul>
				<template #[foot]>
					<ToggleSwitch :checked="theme.running" :label="theme.label" :locked="locked(theme) !== null" :busy="busy === theme.name" :reason="locked(theme)" @change="toggle(theme, $event)" />
					<button v-if="canActivate && theme.offered && !theme.default" type="button" class="button button--small" :disabled="busy !== null" @click="choose(theme)">Make Default<span class="visually-hidden"> {{ theme.label }}</span></button>
					<ExtensionMenu :label="theme.label" :details="extensionRoute('admin-theme', theme.name)" details-label="Admin theme details" :copy="theme.path" :delete-label="canDelete && theme.deletable && theme.folder && !theme.default ? 'Delete admin theme' : undefined" @delete="remove(theme.label, theme.folder ?? '', theme)" />
				</template>
			</component>

			<component :is="list ? ExtensionRow : ExtensionCard" v-for="theme in shownBroken" :key="theme.where" class="is-off" :label="theme.where">
				<template v-if="list" #mark><AdminIcon name="triangle-alert" /></template>
				<template v-else #media><AdminThemeSketch class="theme-sketch" :preview="null" /></template>
				<template #pills><span class="pill pill--warn">Can't be used</span></template>
				<p class="notice notice--small notice--warn extension__message">
					<AdminIcon name="triangle-alert" /><span>{{ theme.reason }} It can't be chosen until that's fixed.</span>
				</p>
				<template v-if="canDelete && theme.deletable" #[foot]>
					<ExtensionMenu :label="theme.where" :copy="theme.where" delete-label="Delete admin theme" @delete="remove(folderName(theme.where), theme.where)" />
				</template>
			</component>
		</component>
	</template>

	<ul v-else-if="!error && list" class="extension-rows" aria-hidden="true">
		<li v-for="row in 3" :key="row" class="extension-row">
			<span class="extension-row__mark" />
			<div class="extension-row__main">
				<span class="skeleton skeleton--title" />
				<span class="skeleton skeleton--wide" />
			</div>
		</li>
	</ul>

	<div v-else-if="!error" class="extension-cards" aria-hidden="true">
		<div v-for="card in 3" :key="card" class="extension-card">
			<AdminThemeSketch class="theme-sketch" :preview="null" />
			<div class="extension-card__body">
				<span class="skeleton skeleton--title" />
				<span class="skeleton skeleton--wide" />
			</div>
		</div>
	</div>

	<p v-if="answer" class="notice extension__note">
		<span>
			Admin themes live in <code>extensions/</code> or come from Composer, and change only how the admin looks. Each account chooses one on Your Account; the site's default is what everyone else sees, and the sign-in screen.
			<template v-if="answer.saved">
				Which are on was set here, and is saved in <code>user/data/settings.json</code> over <code>config/admin-themes.php</code>.
				<button v-if="canActivate" type="button" class="link-button" @click="useConfig">Use <code>config/admin-themes.php</code>'s list</button>
			</template>
			<template v-else>A theme in <code>extensions/</code> is off until <code>config/admin-themes.php</code> names it in <code>enabled</code><template v-if="!answer.config"> (there's no such file yet)</template>; the framework's and Composer's are on. Turning one on or off here saves the list of every theme that's on, over that file.</template>
			<template v-if="answer.defaultSaved">
				The default was chosen here.
				<button v-if="canActivate" type="button" class="link-button" @click="useConfigDefault">Use <code>config/admin-themes.php</code>'s default</button>
			</template>
		</span>
	</p>

	<InstallModal kind="admin-theme" :open="installing" :upload="answer?.upload ?? null" :state="installState" @close="installing = false" @installed="afterInstall" @next="next" @hop="hop" />
</template>

<style scoped>
/* The admin in miniature, as wide as a card. */
.theme-sketch {
	aspect-ratio: 16 / 9;
}

/* A row's mark is the theme's accent. */
.theme-mark {
	display: block;
	width: 14px;
	height: 14px;
	border-radius: 50%;
}

.skeleton--title {
	width: 48%;
	height: 14px;
}

.skeleton--wide {
	width: 88%;
}
</style>
