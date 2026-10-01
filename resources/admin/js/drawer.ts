/**
 * Whether the editor's settings drawer was left open (D-299), so a
 * refresh or the next entry opens it the same way. It's kept in this
 * browser, and only where the drawer pushes the column aside: at 980px
 * and narrower it lies over the column, so it starts shut there, and
 * opening or closing it there isn't remembered. Storage may be off, so
 * reading and writing are allowed to fail, and the drawer then starts
 * shut.
 */

const KEY  = 'blush-admin-drawer-open';
const wide = window.matchMedia('(width > 980px)');

/**
 * Returns whether the drawer should start open.
 */
export function drawerOpen(): boolean {
	try {
		return wide.matches && localStorage.getItem(KEY) === '1';
	} catch {
		return false;
	}
}

/**
 * Remembers whether the drawer is open.
 */
export function keepDrawer(open: boolean): void {
	if (!wide.matches) {
		return;
	}

	try {
		if (open) {
			localStorage.setItem(KEY, '1');
		} else {
			localStorage.removeItem(KEY);
		}
	} catch {
		// Not remembered; the drawer starts shut next time.
	}
}
