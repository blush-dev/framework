<script setup lang="ts">
/**
 * A theme's details (D-383; drawn as the extensions sketch's in D-565),
 * at `/themes/{vendor}/{name}`: its label with Active or Can't activate
 * beside it, its Appearance (its preview in both halves of its palette,
 * each with the six colors listed under it), then its Details
 * (`ExtensionFacts`, with its type) beside its Dependencies
 * (`ExtensionDependencies`: the theme it falls back to, its
 * requirements, checked as if it were active, D-431, its conflicts,
 * replaces, provides, and suggests, D-434 to D-439, and those on the
 * other side, with the themes that fall back to it, D-440), and, for a
 * folder theme the active one doesn't use, **Delete theme**. An active
 * theme whose requirements aren't met says it isn't running. A Composer
 * theme says how it's removed instead.
 *
 * **Activate** asks, as on the Themes screen (`useThemes()`), and a
 * failure is said under the header, leading with the site being
 * unchanged. A theme without a palette is sketched in the admin's own
 * colors, and says how to give it one.
 */

import { computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AbandonedNotice from '../components/AbandonedNotice.vue';
import AdminIcon from '../components/AdminIcon.vue';
import DangerZone from '../components/DangerZone.vue';
import ExtensionDependencies from '../components/ExtensionDependencies.vue';
import ExtensionDependents from '../components/ExtensionDependents.vue';
import ExtensionFacts from '../components/ExtensionFacts.vue';
import MessageText from '../components/MessageText.vue';
import PaletteLegend from '../components/PaletteLegend.vue';
import PreviousVersion from '../components/PreviousVersion.vue';
import ThemeSketch from '../components/ThemeSketch.vue';
import { config } from '../config';
import { extensionRoute } from '../extensions';
import { t } from '../messages';
import { can } from '../session';
import { screenTitle } from '../screen';
import { useThemes } from '../themes';

const route  = useRoute();
const router = useRouter();

const { answer, error, busy, failed, active, load, find, label, installed, dependents, blockedMessage, fallbackMessage, activate, remove: removeTheme } = useThemes();

// What the account may do here (D-389).
const canActivate = can('extensions.themes.activate');
const canDelete   = can('extensions.themes.delete');

void load();

const name  = computed(() => `${String(route.params.vendor)}/${String(route.params.name)}`);
const theme = computed(() => find(name.value));
const deps  = computed(() => theme.value ? dependents(theme.value) : []);

watch(theme, (value) => {
	screenTitle.value = value?.label ?? null;
}, { immediate: true });

const installedBy = computed(() => {
	switch (theme.value?.source) {
		case 'framework':
			return 'Blush';
		case 'composer':
			return 'Composer';
		default:
			return t('theme_view.installed_by_folder', 'A folder in extensions/');
	}
});

// The themes that fall back to it, as the Dependencies panel lists them.
const fallingBack = computed(() => deps.value.map((dep) => ({ name: dep.name, label: dep.label, kind: 'theme' as const })));

// The theme it falls back to, by name, if any (D-632), and whether it's missing.
const parentName    = computed(() => theme.value?.parent ?? '');
const missingParent = computed(() => parentName.value !== '' && !installed(parentName.value));

// What deleting does, and how many themes fall back to this one.
const deleteNote = computed(() => {
	return t('theme_view.delete_note', 'Deleting removes the folder from the server.{count, plural, =0 {} one { # theme falls back to this one.} other { # themes fall back to this one.}}', { count: deps.value.length });
});

async function remove(): Promise<void> {
	const value = theme.value;

	if (value?.folder && await removeTheme(value.label, value.folder, deps.value)) {
		await router.push({ name: 'themes' });
	}
}
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="{ name: 'themes' }"><AdminIcon name="chevron-left" />{{ t('theme_view.all_themes', 'All themes') }}</RouterLink>
		<div class="page-header__text">
			<div class="page-header__title">
				<h1 tabindex="-1">{{ theme?.label ?? t('theme_view.theme', 'Theme') }}</h1>
				<template v-if="theme">
					<span v-if="theme.active" class="pill pill--good">{{ t('theme_view.active', 'Active') }}</span>
					<span v-if="fallbackMessage(theme)" class="pill pill--warn">{{ t('theme_view.not_running', 'Not running') }}</span>
					<span v-else-if="theme.blocked && !theme.active" class="pill pill--warn">{{ t('theme_view.cant_activate', 'Can\'t activate') }}</span>
				</template>
			</div>
			<p v-if="theme" class="page-header__hint">{{ theme.description || t('theme_view.no_description', 'This theme has no description.') }}</p>
		</div>
		<div v-if="theme" class="page-header__actions">
			<a v-if="theme.active" class="button" :href="config.site.url" target="_blank" rel="noopener"><AdminIcon name="external-link" />{{ t('theme_view.view_site', 'View Site') }}<span class="visually-hidden"> {{ t('theme_view.new_tab', '(new tab)') }}</span></a>
			<button v-else-if="theme.blocked" type="button" class="button" disabled>{{ t('theme_view.activate', 'Activate') }}</button>
			<button v-else-if="busy === theme.name" type="button" class="button" disabled><span class="spin" aria-hidden="true" />{{ t('theme_view.activating', 'Activating…') }}</button>
			<button v-else-if="canActivate" type="button" class="button" :class="failed?.name === theme.name ? 'button--danger' : 'button--primary'" :disabled="busy !== null" @click="activate(theme)">
				{{ failed?.name === theme.name ? t('common.retry', 'Try Again') : t('theme_view.activate_named', 'Activate {label}', { label: theme.label }) }}
			</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<div v-if="theme && answer" class="extension-detail">
		<p v-if="fallbackMessage(theme)" class="notice notice--small notice--warn">
			<AdminIcon name="triangle-alert" /><span>{{ fallbackMessage(theme) }}</span>
		</p>
		<p v-if="failed?.name === theme.name" class="notice notice--small notice--error" role="alert">
			<AdminIcon name="triangle-alert" /><span>{{ t('theme_view.still_showing', 'Your site is still showing {theme}; nothing changed. {reason}', { theme: active?.label ?? answer.active, reason: failed.reason }) }}</span>
		</p>
		<p v-else-if="theme.blocked && !theme.active" class="notice notice--small notice--warn">
			<AdminIcon name="triangle-alert" /><span>{{ blockedMessage(theme) }}</span>
		</p>
		<AbandonedNotice noun="theme" :abandoned="theme.abandoned" :replacement="theme.replacement" />

		<section class="panel" aria-labelledby="appearance-heading">
			<header class="panel__header">
				<h2 id="appearance-heading">{{ t('theme_view.appearance', 'Appearance') }}</h2>
				<p class="panel__hint">{{ theme.preview?.palette ? t('theme_view.palette_declared', 'The six colors it declares, drawn and listed') : t('theme_view.no_palette', 'No palette declared') }}</p>
			</header>
			<div class="panel__body">
				<div class="extension-appearance">
					<figure v-for="half in ([0, 1] as const)" :key="half">
						<ThemeSketch :preview="theme.preview" :scheme="half === 0 ? 'light' : 'dark'" />
						<figcaption class="eyebrow">{{ half === 0 ? t('theme_view.light', 'Light') : t('theme_view.dark', 'Dark') }}</figcaption>
						<PaletteLegend v-if="theme.preview?.palette" :palette="theme.preview.palette" :half="half" />
					</figure>
				</div>
				<p v-if="!theme.preview?.palette" class="field__help">
					<MessageText :text="t('theme_view.palette_help', 'These use the admin\'s colors. A theme draws its own preview with a {preview} palette in its {file}.')"><template #preview><code>preview</code></template><template #file><code>theme.json</code></template></MessageText>
				</p>
			</div>
		</section>

		<div class="extension-detail__columns">
			<ExtensionFacts :extension="theme" :installed-by="installedBy" :folder="theme.folder">
				<template #kind>
					<dt>{{ t('theme_view.type', 'Type') }}</dt>
					<dd>{{ theme.preview?.type || '—' }}</dd>
				</template>
			</ExtensionFacts>

			<ExtensionDependencies :extension="theme" noun="theme" :replaces-hint="t('theme_view.replaces_hint', 'It can\'t run')" :as-if-active="!theme.active">
				<template v-if="parentName !== ''" #own>
					<section class="panel__section" aria-labelledby="fallback-heading">
						<div class="panel__section-head"><h3 id="fallback-heading" class="eyebrow">{{ t('theme_view.falls_back_to', 'Falls back to') }}</h3><span>{{ t('theme_view.falls_back_hint', 'Where what it doesn\'t define comes from') }}</span></div>
						<ul class="dependencies">
							<li>
								<AdminIcon :name="missingParent ? 'circle-x' : 'circle-check'" :class="missingParent ? 'is-unmet' : 'is-met'" />
								<div class="dependencies__ref">
									<template v-if="missingParent">
										<span class="mono">{{ parentName }}</span>
										<span class="dependencies__note is-unmet">{{ t('theme_view.not_installed', 'isn\'t installed') }}</span>
									</template>
									<template v-else>
										<RouterLink :to="extensionRoute('theme', parentName)">{{ label(parentName) }}</RouterLink>
										<span class="mono dependencies__name">{{ parentName }}</span>
									</template>
								</div>
							</li>
						</ul>
					</section>
				</template>
				<template v-if="deps.length" #others>
					<section class="panel__section" aria-labelledby="fallback-by-heading">
						<div class="panel__section-head"><h3 id="fallback-by-heading" class="eyebrow">{{ t('theme_view.fallback_by', 'Used as fallback by') }}</h3></div>
						<ExtensionDependents :dependents="fallingBack" />
					</section>
				</template>
			</ExtensionDependencies>
		</div>

		<PreviousVersion kind="theme" :extension="theme" :live="answer.chain.includes(theme.name)" @changed="load" />
		<p v-if="theme.source === 'composer'" class="notice">
			<span><MessageText :text="t('theme_view.composer_note', 'Composer manages this theme, so it can\'t be deleted here. Remove it from the project with {command}, and it leaves this list.')"><template #command><code>composer remove {{ theme.name }}</code></template></MessageText></span>
		</p>
		<p v-else-if="theme.source === 'local' && !theme.deletable" class="notice">
			<span>{{ t('theme_view.not_deletable', '{active, select, yes {This is the active theme, so it can\'t be deleted. Activate another theme first.} other {The active theme, {theme}, falls back to it, so it can\'t be deleted. Activate another theme first.} }', { active: theme.active ? 'yes' : 'no', theme: active?.label ?? answer.active }) }}</span>
		</p>
		<DangerZone v-else-if="canDelete && theme.deletable">
			{{ deleteNote }}
			<template #action><button type="button" class="button button--danger" @click="remove"><AdminIcon name="trash-2" />{{ t('theme_view.delete', 'Delete Theme') }}</button></template>
		</DangerZone>
	</div>

	<p v-else-if="answer" class="notice notice--warn" role="alert">
		<span><MessageText :text="t('theme_view.not_found', 'No theme named {name} is installed.')"><template #name><span class="mono">{{ name }}</span></template></MessageText></span>
	</p>

	<div v-else-if="!error" class="extension-detail" aria-hidden="true">
		<div class="panel"><div class="panel__body"><span class="skeleton skeleton--title" /><span class="skeleton" /><span class="skeleton skeleton--half" /></div></div>
	</div>
</template>

<style scoped>
.skeleton--title {
	width: 40%;
	height: 14px;
}

.skeleton--half {
	width: 64%;
}
</style>
