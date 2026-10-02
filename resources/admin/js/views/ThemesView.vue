<script setup lang="ts">
/**
 * Themes (the design direction's Appearance, D-306, named Themes in
 * D-327, drawn as the themes sketch in D-381): every installed theme as a
 * card, the active one first, each with a preview sketched from the
 * palette its `theme.json` declares (`ThemeSketch`).
 *
 * **Activate** asks first, then saves the theme in
 * `user/data/settings.json` (`PATCH settings`, `theme.active`), over
 * `config/theme.php`, and has the server compile and reindex for it; a
 * failure is said on the card, leading with the fact that the site is
 * unchanged. A theme that falls back to one that isn't installed can't be
 * activated, and says why. **Delete** removes a folder theme from
 * `user/themes`, unless the active theme uses it; Composer themes and the
 * default theme can't be deleted here. Broken themes are cards too, with
 * no preview.
 *
 * Installing from the admin is planned (D-378): **Install Theme** says
 * how to install one for now. A theme's name, and **Theme details** in
 * its menu, open its details screen (`ThemeView`, D-383); broken themes
 * have none, since they have no name to go by.
 */

import { computed, nextTick, ref } from 'vue';
import AdminIcon from '../components/AdminIcon.vue';
import AdminModal from '../components/AdminModal.vue';
import MenuButton from '../components/MenuButton.vue';
import ThemeSketch from '../components/ThemeSketch.vue';
import type { ThemeSummary } from '../api';
import { config } from '../config';
import { copy, folderName, previewUrl, themeRoute, useThemes } from '../themes';

const { appearance, error, busy, failed, themes, active, load, label, installed, dependents, blockedMessage, activate: activateTheme, useConfig, remove: removeTheme } = useThemes();
const installing = ref(false);

void load();

const count = computed(() => themes.value.length + (appearance.value?.invalid.length ?? 0));

async function activate(theme: ThemeSummary): Promise<void> {
	if (await activateTheme(theme)) {
		// The active theme comes first, so its card moved.
		await nextTick();
		document.querySelector('.theme.is-active')?.scrollIntoView({ block: 'nearest' });
	}
}

async function remove(name: string, folder: string, falling: ThemeSummary[] = []): Promise<void> {
	if (await removeTheme(name, folder, falling)) {
		await load();
	}
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Themes</h1>
			<p class="page-header__hint">The theme visitors see. How the admin looks is yours alone, and is set on <RouterLink :to="{ name: 'profile' }">Your Account</RouterLink>.</p>
		</div>
		<div class="page-header__actions">
			<button type="button" class="button button--primary" @click="installing = true"><AdminIcon name="upload" />Install Theme</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-if="appearance?.problem" class="notice notice--warn" role="alert">
		<span>The active theme, <span class="mono">{{ appearance.active }}</span>, can't be used: {{ appearance.problem }} Activate another theme to fix the site.</span>
	</p>

	<div class="count-row">
		<span>{{ appearance ? `${count} ${count === 1 ? 'theme' : 'themes'} · 1 active` : 'Loading themes' }}</span>
		<span class="count-row__rule" />
	</div>

	<div v-if="appearance" class="themes">
		<article v-for="theme in themes" :key="theme.name" class="theme" :class="{ 'is-active': theme.active, 'is-busy': busy === theme.name }">
			<ThemeSketch :preview="theme.preview" />
			<div class="theme__body">
				<p class="theme__name">
					<RouterLink class="theme__label" :to="themeRoute(theme.name)">{{ theme.label }}</RouterLink>
					<span v-if="theme.active" class="pill pill--good">Active</span>
					<span v-else-if="theme.blocked" class="pill pill--warn">Can't activate</span>
					<span v-else-if="theme.source === 'framework'" class="pill">Built in</span>
					<span v-if="theme.version" class="theme__version mono">{{ theme.version }}</span>
				</p>
				<p v-if="theme.description" class="theme__description">{{ theme.description }}</p>
				<ul class="theme__facts">
					<li v-if="theme.source === 'framework'"><AdminIcon name="package" />Ships with Blush</li>
					<li v-else-if="theme.source === 'composer'"><AdminIcon name="package" /><span>Composer · <span class="mono">{{ theme.name }}</span></span></li>
					<li v-else><AdminIcon name="folder" /><span class="mono">{{ theme.folder }}</span></li>
					<li v-if="theme.preview?.type"><AdminIcon name="type" />{{ theme.preview.type }}</li>
					<li v-if="theme.source === 'framework'"><AdminIcon name="corner-down-right" />Every theme falls back to this one</li>
					<li v-else-if="theme.parent && !installed(theme.parent)" class="is-warn"><AdminIcon name="triangle-alert" /><span>Falls back to <span class="mono">{{ theme.parent }}</span>, which isn't installed</span></li>
					<li v-else><AdminIcon name="corner-down-right" /><span>Falls back to <RouterLink class="theme__link" :to="themeRoute(theme.parent ?? 'blush/default')">{{ label(theme.parent ?? 'blush/default') }}</RouterLink></span></li>
				</ul>
				<p v-if="failed?.name === theme.name" class="theme__message theme__message--danger" role="alert">
					<AdminIcon name="triangle-alert" /><span>Your site is still showing {{ active?.label ?? appearance.active }}; nothing changed. {{ failed.reason }}</span>
				</p>
				<p v-else-if="theme.blocked && !theme.active" class="theme__message theme__message--warn">
					<AdminIcon name="triangle-alert" /><span>{{ blockedMessage(theme) }}</span>
				</p>
			</div>
			<div class="theme__foot">
				<a v-if="theme.active" class="button button--small" :href="config.site.url" target="_blank" rel="noopener"><AdminIcon name="external-link" />View site<span class="visually-hidden"> (new tab)</span></a>
				<button v-else-if="busy === theme.name" type="button" class="button button--small" disabled><span class="spin" aria-hidden="true" />Activating…</button>
				<button v-else type="button" class="button button--small" :class="{ 'button--danger': failed?.name === theme.name }" :disabled="theme.blocked !== null || busy !== null" @click="activate(theme)">
					{{ failed?.name === theme.name ? 'Try again' : 'Activate' }}<span class="visually-hidden"> {{ theme.label }}</span>
				</button>
				<MenuButton class="theme__more" button-class="button button--ghost button--small button--icon" :label="`More actions for ${theme.label}`" floating>
					<template #button>
						<AdminIcon name="ellipsis" />
					</template>
					<RouterLink class="menu-item" :to="themeRoute(theme.name)"><AdminIcon name="info" />Theme details</RouterLink>
					<a v-if="appearance.preview && !theme.active && !theme.blocked" class="menu-item" :href="previewUrl(theme.name)" target="_blank" rel="noopener"><AdminIcon name="eye" />Preview on the site</a>
					<button v-if="theme.folder" type="button" class="menu-item" @click="copy(theme.folder, 'the folder path')"><AdminIcon name="copy" />Copy folder path</button>
					<button v-if="!theme.active && !theme.blocked" type="button" class="menu-item" @click="copy(`bin/blush theme:activate ${theme.name}`, 'the command')"><AdminIcon name="terminal" />Copy activate command</button>
					<template v-if="theme.deletable && theme.folder">
						<hr class="menu-rule">
						<button type="button" class="menu-item menu-item--danger" @click="remove(theme.label, theme.folder, dependents(theme))"><AdminIcon name="trash-2" />Delete theme</button>
					</template>
				</MenuButton>
			</div>
		</article>

		<article v-for="theme in appearance.invalid" :key="theme.where" class="theme">
			<ThemeSketch :preview="null" broken />
			<div class="theme__body">
				<p class="theme__name">
					<span class="theme__label mono">{{ theme.where }}</span>
					<span class="pill pill--warn">Can't activate</span>
				</p>
				<p class="theme__message theme__message--warn">
					<AdminIcon name="triangle-alert" /><span>{{ theme.reason }} It can't be activated until that's fixed.</span>
				</p>
			</div>
			<div class="theme__foot">
				<button type="button" class="button button--small" disabled>Activate</button>
				<MenuButton v-if="theme.deletable" class="theme__more" button-class="button button--ghost button--small button--icon" :label="`More actions for ${theme.where}`" floating>
					<template #button>
						<AdminIcon name="ellipsis" />
					</template>
					<button type="button" class="menu-item" @click="copy(theme.where, 'the folder path')"><AdminIcon name="copy" />Copy folder path</button>
					<hr class="menu-rule">
					<button type="button" class="menu-item menu-item--danger" @click="remove(folderName(theme.where), theme.where)"><AdminIcon name="trash-2" />Delete theme</button>
				</MenuButton>
			</div>
		</article>
	</div>

	<div v-else-if="!error" class="themes" aria-hidden="true">
		<div v-for="card in 3" :key="card" class="theme">
			<div class="theme__placeholder" />
			<div class="theme__body">
				<span class="skeleton skeleton--title" />
				<span class="skeleton skeleton--wide" />
				<span class="skeleton skeleton--half" />
			</div>
		</div>
	</div>

	<p v-if="appearance" class="notice themes__note">
		<span>
			<template v-if="appearance.saved">
				The active theme was set here, and is saved in <code>user/data/settings.json</code> over <code>config/theme.php</code>.
				<button type="button" class="link-button" @click="useConfig">Use <code>config/theme.php</code>'s theme</button>
			</template>
			<template v-else>The active theme is set in <code>config/theme.php</code><template v-if="!appearance.config"> (the default theme until it exists)</template>; activating one here saves it in <code>user/data/settings.json</code>, over that file.</template>
			A deploy usually activates a theme from the command line instead: <code>bin/blush theme:activate {name}</code>.
		</span>
	</p>

	<AdminModal :open="installing" title="Install Theme" @close="installing = false">
		<p>A theme is a folder in <code>user/themes/</code>. Put one there and it shows up in this list; there's no install step and nothing to register.</p>
		<p>A theme published as a package is installed with <code>composer require vendor/theme</code> instead. Composer keeps it up to date, and it can't be deleted from this screen.</p>
		<div class="install-drop">
			<AdminIcon name="upload" />
			<span>Uploading a theme's <strong>.zip</strong> is coming.</span>
			<span class="install-drop__hint">It will be unpacked into <span class="mono">user/themes/</span>, and nothing activated.</span>
		</div>
		<template #footer>
			<button type="button" class="button" autofocus @click="installing = false">Close</button>
			<button type="button" class="button button--primary" disabled>Upload</button>
		</template>
	</AdminModal>
</template>

<style scoped>
.count-row {
	display: flex;
	align-items: center;
	gap: var(--s-3);
	margin-bottom: var(--s-4);
	color: var(--fg-3);
	font-size: var(--text-2xs);
	font-weight: 600;
	letter-spacing: .06em;
	text-transform: uppercase;
}

.count-row__rule {
	flex: 1;
	height: 1px;
	background: var(--border);
}

.themes {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(min(340px, 100%), 1fr));
	gap: var(--s-4);
}

.theme {
	display: flex;
	flex-direction: column;
	overflow: hidden;
	border: 1px solid var(--border);
	border-radius: var(--r-3);
	background: var(--surface);
	box-shadow: var(--shadow-1);
	transition: border-color .14s ease-out;
}

.theme:hover {
	border-color: var(--border-strong);
}

.theme.is-active {
	border-color: var(--accent-line);
	box-shadow: 0 0 0 1px var(--accent-line);
}

.theme.is-busy {
	opacity: .72;
}

.theme > :first-child {
	border-bottom: 1px solid var(--border);
}

.theme__placeholder {
	aspect-ratio: 16 / 10;
	background: var(--surface-2);
}

.theme__body {
	display: flex;
	flex: 1;
	flex-direction: column;
	gap: var(--s-2);
	padding: var(--s-4) var(--pad-x) var(--s-3);
}

.theme__name {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
	margin: 0;
}

.theme__label {
	min-width: 0;
	color: var(--fg);
	font-family: var(--font-title);
	font-size: var(--title-size);
	font-weight: 600;
	letter-spacing: var(--title-track);
	overflow-wrap: anywhere;
}

.theme__label:any-link {
	text-decoration: none;
}

.theme__label:any-link:hover {
	color: var(--accent);
}

.theme__facts .theme__link {
	color: var(--fg-2);
	text-decoration: none;
	border-bottom: 1px solid var(--border-strong);
}

.theme__facts .theme__link:hover {
	color: var(--accent);
	border-bottom-color: var(--accent-line);
}

.theme__label.mono {
	font-family: var(--font-mono);
	font-size: var(--text-sm);
}

.theme__version {
	margin-left: auto;
	padding-left: var(--s-2);
	color: var(--fg-3);
	font-size: var(--text-2xs);
}

.theme__description {
	margin: 0;
	color: var(--fg-2);
	font-size: var(--text-sm);
	line-height: 1.5;
}

.theme__facts {
	display: grid;
	gap: 5px;
	margin: var(--s-1) 0 0;
	padding: 0;
	color: var(--fg-3);
	font-size: var(--text-xs);
	list-style: none;
}

.theme__facts li {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	min-width: 0;
}

.theme__facts .icon {
	flex: none;
	width: 13px;
	height: 13px;
}

.theme__facts .mono {
	min-width: 0;
	overflow: hidden;
	color: var(--fg-2);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.theme__facts .is-warn {
	color: var(--warn);
}

/* A problem is said where the action is, leading with what's safe. */
.theme__message {
	display: flex;
	align-items: flex-start;
	gap: var(--s-2);
	margin: var(--s-2) 0 0;
	padding: var(--s-3);
	border-radius: var(--r-1);
	font-size: var(--text-xs);
	line-height: 1.45;
}

.theme__message .icon {
	flex: none;
	width: 14px;
	height: 14px;
	margin-top: 1px;
}

.theme__message--danger {
	background: var(--danger-soft);
	color: var(--danger);
}

.theme__message--warn {
	background: var(--warn-soft);
	color: var(--warn);
}

.theme__foot {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	padding: var(--s-3) var(--pad-x);
	border-top: 1px solid var(--border);
}

.theme__more {
	margin-left: auto;
}

.menu-rule {
	margin: 5px -1px;
	border: 0;
	border-top: 1px solid var(--border);
}

/* The spinner of a working button. */
.spin {
	flex: none;
	width: 13px;
	height: 13px;
	border: 2px solid currentColor;
	border-top-color: transparent;
	border-radius: 50%;
	animation: spin .7s linear infinite;
}

@keyframes spin {
	to {
		transform: rotate(360deg);
	}
}

.skeleton--title {
	width: 48%;
	height: 14px;
}

.skeleton--wide {
	width: 88%;
}

.skeleton--half {
	width: 64%;
}

.themes__note {
	margin-top: var(--s-6);
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.link-button {
	padding: 0;
	border: 0;
	background: none;
	color: var(--accent);
	font: inherit;
	text-decoration: underline;
	text-underline-offset: .15em;
	cursor: pointer;
}

.install-drop {
	display: grid;
	justify-items: center;
	gap: var(--s-1);
	margin-top: var(--s-4);
	padding: var(--s-5);
	border: 1px dashed var(--border-strong);
	border-radius: var(--r-2);
	color: var(--fg-3);
	font-size: var(--text-sm);
	text-align: center;
}

.install-drop__hint {
	font-size: var(--text-xs);
}

@media (prefers-reduced-motion: reduce) {
	.spin {
		animation: none;
	}
}
</style>
