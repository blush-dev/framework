<script setup lang="ts">
/**
 * Themes (the design direction's Appearance, D-306, named Themes in
 * D-327): the
 * installed themes, with the active one and the themes it builds on.
 * How the admin looks is set per account, on Your Account.
 *
 * The active theme is developer configuration (`config/theme.php`,
 * D-039), so it's shown, not changed: `theme:activate` changes it, and
 * in development another theme can be previewed with `?theme=`. Theme
 * settings wait for their own API. Installing from the admin is planned
 * (D-378); its button is a placeholder.
 */

import { computed, ref } from 'vue';
import AdminIcon from '../components/AdminIcon.vue';
import { ApiError, request, type Appearance, type ThemeSummary } from '../api';
import { config } from '../config';
import { toast } from '../toast';

const appearance = ref<Appearance | null>(null);
const error      = ref('');

const active = computed(() => appearance.value?.themes.find((theme) => theme.active) ?? null);

request<Appearance>('GET', '/appearance').then((item) => {
	appearance.value = item;
}).catch((caught: unknown) => {
	error.value = caught instanceof ApiError ? caught.message : 'The themes couldn\'t be loaded.';
});

const SOURCES: Record<ThemeSummary['source'], string> = {
	framework: 'Built in',
	local: 'user/themes',
	composer: 'Composer'
};

function themeLabel(name: string): string {
	return appearance.value?.themes.find((theme) => theme.name === name)?.label ?? name;
}

// How a theme relates to the active one: it is it, or the active one
// builds on it.
function role(theme: ThemeSummary): 'active' | 'ancestor' | null {
	if (theme.active) {
		return 'active';
	}

	return appearance.value?.chain.includes(theme.name) === true ? 'ancestor' : null;
}

function previewUrl(name: string): string {
	const url = new URL(config.site.url);

	url.searchParams.set('theme', name);

	return url.toString();
}

async function copy(text: string): Promise<void> {
	try {
		await navigator.clipboard.writeText(text);
		toast('Copied the command');
	} catch {
		toast('The command couldn\'t be copied');
	}
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Themes</h1>
			<p class="page-header__hint">The theme visitors see. How the admin looks is set per account, on Your account.</p>
		</div>
		<div class="page-header__actions">
			<button type="button" class="button" disabled aria-describedby="install-note"><AdminIcon name="upload" />Install Theme</button>
		</div>
	</header>

	<p id="install-note" class="notice"><span>Installing from here is coming. For now, put themes in <code>user/themes</code> or install them with Composer.</span></p>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<div v-if="appearance" class="stack">
		<section class="panel" aria-labelledby="themes-heading">
			<header class="panel__header">
				<h2 id="themes-heading">Site Theme</h2>
				<p class="panel__hint">One active theme per site</p>
			</header>
			<ul class="packages">
				<li v-for="theme in appearance.themes" :key="theme.name" class="package" :class="{ 'package--off': role(theme) === null }">
					<span class="package__mark"><AdminIcon name="paintbrush" /></span>
					<div class="package__main">
						<p class="package__title">
							<span class="package__name">{{ theme.label }}</span>
							<span class="package__fact mono">{{ theme.name }}</span>
							<span v-if="theme.version" class="package__fact mono">{{ theme.version }}</span>
							<span class="package__fact" :class="{ mono: theme.source === 'local' }">{{ SOURCES[theme.source] }}</span>
						</p>
						<p v-if="theme.description" class="package__description">{{ theme.description }}</p>
						<p v-if="theme.parent" class="package__description">Builds on {{ themeLabel(theme.parent) }}.</p>
					</div>
					<div class="package__end">
						<span v-if="role(theme) === 'active'" class="pill pill--good">Active</span>
						<span v-else-if="role(theme) === 'ancestor'" class="pill">In use by {{ active?.label ?? appearance.active }}</span>
						<template v-else>
							<a v-if="appearance.preview" class="button button--small" :href="previewUrl(theme.name)" target="_blank" rel="noopener"><AdminIcon name="eye" />Preview<span class="visually-hidden"> {{ theme.label }} (new tab)</span></a>
							<button type="button" class="button button--ghost button--small" :title="`bin/blush theme:activate ${theme.name}`" @click="copy(`bin/blush theme:activate ${theme.name}`)"><AdminIcon name="terminal" />Copy command<span class="visually-hidden"> to activate {{ theme.label }}</span></button>
						</template>
					</div>
				</li>
			</ul>
			<p class="panel__body field__help appearance__note">
				The active theme is set in <code>config/theme.php</code><template v-if="!appearance.config"> (the default theme until it exists)</template>.
				To switch, run <code>bin/blush theme:activate {name}</code>.
			</p>
		</section>


		<section v-if="appearance.invalid.length" class="panel" aria-labelledby="invalid-heading">
			<header class="panel__header">
				<h2 id="invalid-heading">Can't Be Used</h2>
				<p class="panel__hint">Installed, with a broken manifest</p>
			</header>
			<ul class="packages">
				<li v-for="theme in appearance.invalid" :key="theme.where" class="package">
					<span class="package__mark"><AdminIcon name="triangle-alert" /></span>
					<div class="package__main">
						<p class="package__title"><span class="package__name mono">{{ theme.where }}</span></p>
						<p class="package__description">{{ theme.reason }}</p>
					</div>
				</li>
			</ul>
		</section>
	</div>

	<div v-else-if="!error" class="stack" aria-hidden="true">
		<div class="panel"><div class="panel__body"><span class="skeleton skeleton--heading" /><span class="skeleton" /><span class="skeleton" /></div></div>
		<div class="panel"><div class="panel__body"><span class="skeleton skeleton--heading" /><span class="skeleton" /></div></div>
	</div>
</template>

<style scoped>
/* Widths as classes: the admin's CSP blocks inline style attributes. */
.skeleton--heading {
	width: 40%;
}

.stack {
	display: grid;
	gap: var(--s-4);
}

.appearance__note {
	margin: 0;
	border-top: 1px solid var(--border);
}
</style>
