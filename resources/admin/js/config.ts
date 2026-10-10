/**
 * What the admin needs to start, from the JSON block the shell page
 * prints (`ShellController`). Everything else comes from the API.
 */

export interface AdminConfig {
	base: string;
	api: string;
	site: { name: string; url: string };
	// The URL path the media library is served at (`MediaConfig`).
	media: { url: string };
	// The signed-in account's, or `null` when no one is (D-235).
	colorScheme: 'system' | 'light' | 'dark' | null;
	// The signed-in account's admin theme (D-317), or `null`.
	adminTheme: 'neutral' | 'editorial' | null;
	// Whether `@name` links to a profile (`MarkdownConfig`, D-493).
	mentions: boolean;
	// The site's language (a BCP 47 tag) and its date and time formats,
	// each a style or an ICU pattern (`AppConfig`, D-693).
	dates: { locale: string; date: string; time: string };
	// What raw HTML each level may add (`HtmlRules`, D-495).
	html: HtmlRules;
}

export interface HtmlRules {
	// The allowed list: tags, each with the attributes it may have
	// beyond the global ones.
	allowed: Record<string, string[]>;
	global: string[];
	refused: string[];
	refusedAttributes: string[];
	urlAttributes: string[];
}

function read(): AdminConfig {
	const element = document.getElementById('blush-admin-config');

	if (!element?.textContent) {
		throw new Error('The admin page has no start-up config.');
	}

	return JSON.parse(element.textContent) as AdminConfig;
}

export const config: AdminConfig = read();
