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
 * unchanged. A theme that falls back to one that isn't installed, or
 * whose chain has a requirement that isn't met (D-431), can't be
 * activated, and says why; an active theme whose requirements stopped
 * being met says it isn't running, and that the default theme shows in
 * its place. **Delete** removes a folder theme from
 * `extensions/`, unless the active theme uses it; Composer themes and the
 * default theme can't be deleted here. Broken themes are cards too, with
 * no preview.
 *
 * Installing from the admin is planned (D-378): **Install Theme** says
 * how to install one for now. A theme's name, and **Theme details** in
 * its menu, open its details screen (`ThemeView`, D-383); broken themes
 * have none, since they have no name to go by.
 */

import { computed, nextTick } from 'vue';
import AdminIcon from '../components/AdminIcon.vue';
import ExtensionCard from '../components/ExtensionCard.vue';
import ExtensionMenu from '../components/ExtensionMenu.vue';
import InstallModal from '../components/InstallModal.vue';
import ThemeSketch from '../components/ThemeSketch.vue';
import type { ThemeSummary } from '../api';
import { config } from '../config';
import { extensionRoute, folderName } from '../extensions';
import { useInstall } from '../install';
import { can } from '../session';
import { previewUrl, useThemes } from '../themes';
import { copyText } from '../toast';

const { answer, error, busy, failed, themes, active, load, label, installed, dependents, blockedMessage, fallbackMessage, activate: activateTheme, useConfig, remove: removeTheme, find } = useThemes();

// What the account may do here (D-389).
const canActivate = can('extensions.themes.activate');
const canDelete   = can('extensions.themes.delete');

// What a theme the Install modal installed is, and whether it can be activated.
const { installing, canInstall, afterInstall, hop, installState, next } = useInstall('theme', load, {
	find,
	state: (theme, name) => ({
		words: theme?.active ? 'the active theme' : 'inactive',
		next: theme !== null && !theme.active && theme.blocked === null && canActivate,
		live: answer.value?.chain.includes(name) ?? false
	}),
	start: (theme) => activate(theme)
});

void load();

const count = computed(() => themes.value.length + (answer.value?.invalid.length ?? 0));

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
			<button v-if="canInstall" type="button" class="button button--primary" @click="installing = true"><AdminIcon name="upload" />Install Theme</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-if="answer?.problem" class="notice notice--warn" role="alert">
		<span>The active theme, <span class="mono">{{ answer.active }}</span>, can't be used: {{ answer.problem }} Activate another theme to fix the site.</span>
	</p>

	<div class="count-row">
		<span>{{ answer ? `${count} ${count === 1 ? 'theme' : 'themes'} · 1 active` : 'Loading themes' }}</span>
		<span class="count-row__rule" />
	</div>

	<div v-if="answer" class="extension-cards">
		<ExtensionCard v-for="theme in themes" :key="theme.name" :class="{ 'is-active': theme.active, 'is-busy': busy === theme.name }" :label="theme.label" :to="extensionRoute('theme', theme.name)" :version="theme.version" :description="theme.description">
			<template #media><ThemeSketch :preview="theme.preview" /></template>
			<template #pills>
				<span v-if="theme.active" class="pill pill--good">Active</span>
				<span v-if="fallbackMessage(theme)" class="pill pill--warn">Not running</span>
				<span v-else-if="theme.blocked && !theme.active" class="pill pill--warn">Can't activate</span>
				<span v-else-if="theme.source === 'framework'" class="pill">Built in</span>
				<span v-if="theme.abandoned !== false" class="pill pill--warn">Abandoned</span>
			</template>
			<ul class="extension__facts">
				<li v-if="theme.source === 'framework'"><AdminIcon name="package" />Ships with Blush</li>
				<li v-else-if="theme.source === 'composer'"><AdminIcon name="package" /><span>Composer · <span class="mono">{{ theme.name }}</span></span></li>
				<li v-else><AdminIcon name="folder" /><span class="mono">{{ theme.folder }}</span></li>
				<li v-if="theme.preview?.type"><AdminIcon name="type" />{{ theme.preview.type }}</li>
				<li v-if="theme.source === 'framework'"><AdminIcon name="corner-down-right" />Every theme falls back to this one</li>
				<li v-else-if="theme.parent && !installed(theme.parent)" class="is-warn"><AdminIcon name="triangle-alert" /><span>Falls back to <span class="mono">{{ theme.parent }}</span>, which isn't installed</span></li>
				<li v-else><AdminIcon name="corner-down-right" /><span>Falls back to <RouterLink :to="extensionRoute('theme', theme.parent ?? 'blush/default')">{{ label(theme.parent ?? 'blush/default') }}</RouterLink></span></li>
			</ul>
			<p v-if="fallbackMessage(theme)" class="notice notice--small notice--warn extension__message">
				<AdminIcon name="triangle-alert" /><span>{{ fallbackMessage(theme) }}</span>
			</p>
			<p v-if="failed?.name === theme.name" class="notice notice--small notice--error extension__message" role="alert">
				<AdminIcon name="triangle-alert" /><span>Your site is still showing {{ active?.label ?? answer.active }}; nothing changed. {{ failed.reason }}</span>
			</p>
			<p v-else-if="theme.blocked && !theme.active" class="notice notice--small notice--warn extension__message">
				<AdminIcon name="triangle-alert" /><span>{{ blockedMessage(theme) }}</span>
			</p>
			<template #foot>
				<a v-if="theme.active" class="button button--small" :href="config.site.url" target="_blank" rel="noopener"><AdminIcon name="external-link" />View Site<span class="visually-hidden"> (new tab)</span></a>
				<button v-else-if="busy === theme.name" type="button" class="button button--small" disabled><span class="spin" aria-hidden="true" />Activating…</button>
				<button v-else-if="canActivate" type="button" class="button button--small" :class="{ 'button--danger': failed?.name === theme.name }" :disabled="theme.blocked !== null || busy !== null" @click="activate(theme)">
					{{ failed?.name === theme.name ? 'Try Again' : 'Activate' }}<span class="visually-hidden"> {{ theme.label }}</span>
				</button>
				<ExtensionMenu :label="theme.label" :details="extensionRoute('theme', theme.name)" details-label="Theme details" :copy="theme.folder ?? undefined" :delete-label="canDelete && theme.deletable && theme.folder ? 'Delete theme' : undefined" @delete="remove(theme.label, theme.folder ?? '', dependents(theme))">
					<template #lead>
						<a v-if="answer.preview && !theme.active && !theme.blocked" class="menu-item" :href="previewUrl(theme.name)" target="_blank" rel="noopener"><AdminIcon name="eye" />Preview on the site</a>
					</template>
					<button v-if="!theme.active && !theme.blocked" type="button" class="menu-item" @click="copyText(`bin/blush theme:activate ${theme.name}`, 'the command')"><AdminIcon name="terminal" />Copy activate command</button>
				</ExtensionMenu>
			</template>
		</ExtensionCard>

		<ExtensionCard v-for="theme in answer.invalid" :key="theme.where" :label="theme.where">
			<template #media><ThemeSketch :preview="null" broken /></template>
			<template #pills><span class="pill pill--warn">Can't activate</span></template>
			<p class="notice notice--small notice--warn extension__message">
				<AdminIcon name="triangle-alert" /><span>{{ theme.reason }} It can't be activated until that's fixed.</span>
			</p>
			<template #foot>
				<button type="button" class="button button--small" disabled>Activate</button>
				<ExtensionMenu v-if="canDelete && theme.deletable" :label="theme.where" :copy="theme.where" delete-label="Delete theme" @delete="remove(folderName(theme.where), theme.where)" />
			</template>
		</ExtensionCard>
	</div>

	<div v-else-if="!error" class="extension-cards" aria-hidden="true">
		<div v-for="card in 3" :key="card" class="extension-card">
			<div class="extension-card__placeholder" />
			<div class="extension-card__body">
				<span class="skeleton skeleton--title" />
				<span class="skeleton skeleton--wide" />
				<span class="skeleton skeleton--half" />
			</div>
		</div>
	</div>

	<p v-if="answer" class="notice extension__note">
		<span>
			<template v-if="answer.saved">
				The active theme was set here, and is saved in <code>user/data/settings.json</code> over <code>config/theme.php</code>.
				<button v-if="canActivate" type="button" class="link-button" @click="useConfig">Use <code>config/theme.php</code>'s theme</button>
			</template>
			<template v-else>The active theme is set in <code>config/theme.php</code><template v-if="!answer.config"> (the default theme until it exists)</template>; activating one here saves it in <code>user/data/settings.json</code>, over that file.</template>
			A deploy usually activates a theme from the command line instead: <code>bin/blush theme:activate {name}</code>.
		</span>
	</p>

	<InstallModal kind="theme" :open="installing" :upload="answer?.upload ?? null" :state="installState" @close="installing = false" @installed="afterInstall" @next="next" @hop="hop" />
</template>

<style scoped>
.extension-card__placeholder {
	aspect-ratio: 16 / 10;
	background: var(--surface-2);
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
</style>
