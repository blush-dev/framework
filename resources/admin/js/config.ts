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
}

function read(): AdminConfig {
	const element = document.getElementById('blush-admin-config');

	if (!element?.textContent) {
		throw new Error('The admin page has no start-up config.');
	}

	return JSON.parse(element.textContent) as AdminConfig;
}

export const config: AdminConfig = read();
