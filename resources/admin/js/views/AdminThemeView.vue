<script setup lang="ts">
/**
 * An admin theme's details (D-827), at `/admin-themes/{vendor}/{name}`:
 * its label, with Site default or Can't turn on beside it, **Make
 * Default** and its switch; the admin in its colors in both color
 * schemes (`AdminThemeSketch`, each pinned to one); what it changes, by
 * group of tokens, and the fonts it ships; whether its text is easy to
 * read, from its contrast warnings; then its Details (`ExtensionFacts`)
 * beside its Dependencies (`ExtensionDependencies`, D-431, D-434 to
 * D-440), its earlier version (D-393), and **Delete Admin Theme** for a
 * folder theme that isn't the default. A Composer theme says how it's
 * removed instead; the framework's can't be.
 */

import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AbandonedNotice from '../components/AbandonedNotice.vue';
import AdminIcon from '../components/AdminIcon.vue';
import AdminThemeSketch from '../components/AdminThemeSketch.vue';
import DangerZone from '../components/DangerZone.vue';
import ExtensionDependencies from '../components/ExtensionDependencies.vue';
import ExtensionFacts from '../components/ExtensionFacts.vue';
import PreviousVersion from '../components/PreviousVersion.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';
import { ApiError, errorMessage, request, type AdminThemeSummary } from '../api';
import { useAdminThemes } from '../admin-themes';
import { screenTitle } from '../screen';
import { can } from '../session';

const route  = useRoute();
const router = useRouter();

const { busy, error: failure, toggle: toggleTheme, makeDefault, remove: removeTheme } = useAdminThemes();

// What the account may do here (D-389).
const canActivate = can('extensions.admin-themes.activate');
const canDelete   = can('extensions.admin-themes.delete');

const theme   = ref<AdminThemeSummary | null>(null);
const missing = ref(false);
const error   = ref('');

const name = computed(() => `${String(route.params.vendor)}/${String(route.params.name)}`);

// What it changes, by the group each token is in.
const GROUPS: [string, string][] = [['color.', 'Colors'], ['border.', 'Borders and corners'], ['boxShadow.', 'Shadows'], ['typography.', 'Type'], ['padding.', 'Spacing']];

const changes = computed(() => GROUPS
	.map(([prefix, label]) => ({ label, tokens: (theme.value?.changes ?? []).filter((path) => path.startsWith(prefix)).map((path) => path.slice(prefix.length).replace(/\.default$/, '')) }))
	.filter((group) => group.tokens.length > 0));

async function load(): Promise<void> {
	theme.value   = null;
	missing.value = false;
	error.value   = '';

	try {
		theme.value = (await request<{ theme: AdminThemeSummary }>('GET', `/admin-themes/${name.value}`)).theme;
	} catch (caught) {
		if (caught instanceof ApiError && caught.status === 404) {
			missing.value = true;
		} else {
			error.value = errorMessage(caught, 'The admin theme couldn\'t be loaded.');
		}
	}
}

// Another theme's address reuses the screen, so it loads again.
watch(() => route.fullPath, () => void load(), { immediate: true });

watch(() => theme.value?.label ?? null, (value) => {
	screenTitle.value = value;
}, { immediate: true });

// Fetches the theme again after a change, without clearing the screen, if
// it's still the theme on screen.
async function refresh(): Promise<void> {
	const current = theme.value?.name;

	try {
		const fresh = (await request<{ theme: AdminThemeSummary }>('GET', `/admin-themes/${current ?? ''}`)).theme;

		if (theme.value?.name === fresh.name) {
			theme.value = fresh;
		}
	} catch {
		// The change saved; the screen shows it on the next load.
	}
}

async function toggle(on: boolean): Promise<void> {
	if (theme.value && await toggleTheme(theme.value, on, () => void refresh())) {
		await refresh();
	}
}

async function choose(): Promise<void> {
	if (theme.value && await makeDefault(theme.value)) {
		await refresh();
	}
}

// Why it can't be turned on, when it isn't running, or `null` (D-431).
const blocked = computed(() => theme.value && !theme.value.running ? theme.value.blocked : null);

// Why its switch is locked, or `null`.
const locked = computed(() => blocked.value
	?? (theme.value?.base ? 'Neutral is the admin\'s own look, so it\'s always on.' : null)
	?? (theme.value?.default ? 'It\'s the site\'s default. Make another theme the default to turn it off.' : null)
	?? (canActivate ? null : 'Your role can\'t turn admin themes on and off.'));

async function remove(): Promise<void> {
	const value = theme.value;

	if (value?.folder && await removeTheme(value.label, value.folder, value)) {
		await router.push({ name: 'admin-themes' });
	}
}
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="{ name: 'admin-themes' }"><AdminIcon name="chevron-left" />All admin themes</RouterLink>
		<div class="page-header__text">
			<div class="page-header__title">
				<h1 tabindex="-1">{{ theme?.label ?? 'Admin Theme' }}</h1>
				<span v-if="theme?.default" class="pill pill--good">Site default</span>
				<span v-if="blocked" class="pill pill--warn">Can't turn on</span>
				<span v-else-if="theme?.source === 'framework'" class="pill">Built in</span>
			</div>
			<p v-if="theme" class="page-header__hint">{{ theme.description || 'This admin theme has no description.' }}</p>
		</div>
		<div v-if="theme" class="page-header__actions">
			<button v-if="canActivate && theme.offered && !theme.default" type="button" class="button" :disabled="busy !== null" @click="choose">Make Default</button>
			<ToggleSwitch :checked="theme.running" :label="theme.label" :locked="locked !== null" :busy="busy === theme.name" :reason="locked" @change="toggle" />
		</div>
	</header>

	<p v-if="error || failure" class="notice notice--error" role="alert">{{ error || failure }}</p>

	<div v-if="theme" class="extension-detail">
		<AbandonedNotice noun="admin theme" :abandoned="theme.abandoned" :replacement="theme.replacement" />
		<p v-if="blocked" class="notice notice--warn">
			<span>{{ blocked }} {{ theme.enabled ? 'It\'s turned on, but no one can choose it until that\'s fixed, and anyone who chose it sees the site\'s default.' : 'It can\'t be turned on until that\'s fixed.' }}</span>
		</p>
		<p v-else-if="!theme.enabled" class="notice notice--warn">
			<span>This admin theme is off, so no one can choose it.</span>
		</p>

		<section class="panel" aria-labelledby="look-heading">
			<header class="panel__header">
				<h2 id="look-heading">How It Looks</h2>
				<p class="panel__hint">Drawn from its colors</p>
			</header>
			<div class="panel__body">
				<div class="looks">
					<figure v-for="scheme in (['light', 'dark'] as const)" :key="scheme" class="looks__scheme">
						<AdminThemeSketch class="looks__sketch" :style="{ colorScheme: scheme }" :preview="theme.preview" />
						<figcaption>{{ scheme === 'light' ? 'Light' : 'Dark' }}</figcaption>
					</figure>
				</div>
			</div>
		</section>

		<section class="panel" aria-labelledby="changes-heading">
			<header class="panel__header">
				<h2 id="changes-heading">What It Changes</h2>
				<p class="panel__hint">The rest is Neutral's</p>
			</header>
			<div v-if="changes.length || theme.fonts.length" class="panel__section">
				<dl class="facts facts--grid">
					<template v-for="group in changes" :key="group.label">
						<dt>{{ group.label }}</dt>
						<dd>
							<ul class="chips">
								<li v-for="token in group.tokens" :key="token"><span class="chips__item mono">{{ token }}</span></li>
							</ul>
						</dd>
					</template>
					<template v-if="theme.fonts.length">
						<dt>Fonts it brings</dt>
						<dd>{{ theme.fonts.join(', ') }}</dd>
					</template>
				</dl>
			</div>
			<div v-else class="panel__body">
				<p class="field__help">{{ theme.base ? 'Neutral is the admin\'s own look: every other theme changes it.' : 'Nothing: it looks just like Neutral.' }}</p>
			</div>
			<div class="panel__section">
				<p v-if="theme.contrast.length === 0" class="notice notice--small notice--success"><AdminIcon name="check" /><span>Its text is easy to read, in both color schemes.</span></p>
				<template v-else>
					<p class="notice notice--small notice--warn"><AdminIcon name="triangle-alert" /><span>Some of its text may be hard to read. It still works, but the colors could use more contrast:</span></p>
					<ul class="contrast">
						<li v-for="warning in theme.contrast" :key="warning">{{ warning }}</li>
					</ul>
				</template>
			</div>
		</section>

		<div class="extension-detail__columns">
			<ExtensionFacts :extension="theme" :installed-by="theme.source === 'composer' ? 'Composer' : (theme.source === 'framework' ? 'Blush' : 'A folder in extensions/')" :folder="theme.source === 'framework' ? null : theme.path" />
			<ExtensionDependencies :extension="theme" noun="admin theme" replaces-hint="No one can choose it" />
		</div>

		<PreviousVersion kind="admin-theme" :extension="theme" :live="theme.enabled" @changed="load" />
		<p v-if="theme.source === 'composer'" class="notice">
			<span>Composer manages this admin theme, so it can't be deleted here. Remove it from the project with <code>composer remove {{ theme.name }}</code>, and it leaves this list.</span>
		</p>
		<p v-else-if="theme.deletable && theme.default" class="notice">
			<span>It's the site's default, so it can't be deleted. Make another theme the default first.</span>
		</p>
		<DangerZone v-else-if="canDelete && theme.deletable">
			Deleting removes the folder from the server, and anyone who chose it sees the site's default.
			<template #action><button type="button" class="button button--danger" @click="remove"><AdminIcon name="trash-2" />Delete Admin Theme</button></template>
		</DangerZone>
	</div>

	<p v-else-if="missing" class="notice notice--warn" role="alert">
		<span>No admin theme named <span class="mono">{{ name }}</span> is installed.</span>
	</p>

	<div v-else-if="!error" class="extension-detail" aria-hidden="true">
		<div class="panel"><div class="panel__body"><span class="skeleton skeleton--title" /><span class="skeleton" /><span class="skeleton skeleton--half" /></div></div>
	</div>
</template>

<style scoped>
/* The theme in each color scheme, side by side. */
.looks {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
	gap: var(--s-4);
	max-width: 760px;
}

.looks__scheme {
	display: grid;
	gap: var(--s-2);
	margin: 0;
}

.looks__sketch {
	aspect-ratio: 16 / 9;
	border: 1px solid var(--border-color);
	border-radius: var(--border-radius-medium);
}

.looks__scheme figcaption {
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}

.contrast {
	margin: var(--s-2) 0 0;
	padding-left: var(--s-5);
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}

.skeleton--title {
	width: 40%;
	height: 14px;
}

.skeleton--half {
	width: 64%;
}
</style>
