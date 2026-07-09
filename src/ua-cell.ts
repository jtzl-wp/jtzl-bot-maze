/**
 * User-Agent table cell interactions.
 *
 * The full user-agent string is rendered into each cell and visually truncated
 * with CSS. These handlers let admins read the full value (click / keyboard to
 * expand inline) or copy it to the clipboard, addressing the truncated-UA
 * limitation of the Blocked Bots, Recent Activity and Top Bots tables.
 */

export interface UaCellMessages {
	copied: string;
	copyFailed: string;
}

/**
 * Copy text to the clipboard, falling back to a hidden textarea when the async
 * Clipboard API is unavailable (e.g. non-secure admin contexts).
 * @param text Text to copy.
 * @returns Whether the copy succeeded.
 */
export async function copyToClipboard(text: string): Promise<boolean> {
	if (navigator.clipboard && window.isSecureContext) {
		try {
			await navigator.clipboard.writeText(text);
			return true;
		} catch {
			// Fall through to the legacy path below.
		}
	}

	try {
		const textarea = document.createElement('textarea');
		textarea.value = text;
		textarea.setAttribute('readonly', '');
		textarea.style.position = 'fixed';
		textarea.style.left = '-9999px';
		document.body.appendChild(textarea);
		textarea.select();
		const ok = document.execCommand('copy');
		document.body.removeChild(textarea);
		return ok;
	} catch {
		return false;
	}
}

/**
 * Toggle a UA text span between truncated and expanded.
 * @param textEl The `.jtzl-bm-ua-text` element.
 */
function toggleExpanded(textEl: HTMLElement): void {
	const expanded = textEl.classList.toggle('is-expanded');
	textEl.setAttribute('aria-expanded', expanded ? 'true' : 'false');
}

/**
 * Briefly reflect the copy result on the button via colour and tooltip.
 * @param button The copy button.
 * @param message Feedback message.
 * @param ok Whether the copy succeeded.
 */
function showCopyFeedback(
	button: HTMLElement,
	message: string,
	ok: boolean
): void {
	button.classList.toggle('is-copied', ok);
	button.setAttribute('title', message);
	window.setTimeout(() => {
		button.classList.remove('is-copied');
		button.removeAttribute('title');
	}, 1500);
}

/**
 * Create a visually-hidden polite live region for announcing copy results to
 * assistive technology.
 */
function createLiveRegion(): HTMLElement {
	const region = document.createElement('div');
	region.className = 'screen-reader-text';
	region.setAttribute('aria-live', 'polite');
	region.setAttribute('role', 'status');
	document.body.appendChild(region);
	return region;
}

/**
 * Announce a message via the live region, clearing first so an identical
 * repeated message is still re-announced.
 * @param region The live region element.
 * @param message Message to announce.
 */
function announce(region: HTMLElement, message: string): void {
	region.textContent = '';
	window.setTimeout(() => {
		region.textContent = message;
	}, 50);
}

/**
 * Bind delegated copy / expand handlers for UA cells within a root.
 * @param root Container to delegate from (document or a table element).
 * @param messages Localised feedback strings.
 */
export function initUaCells(
	root: Document | HTMLElement,
	messages: UaCellMessages
): void {
	const liveRegion = createLiveRegion();

	root.addEventListener('click', (event: Event) => {
		const target = event.target;
		if (!(target instanceof Element)) return;

		const copyBtn = target.closest('.jtzl-bm-ua-copy');
		if (copyBtn instanceof HTMLElement) {
			event.preventDefault();
			const text = copyBtn.getAttribute('data-clipboard-text') ?? '';
			void copyToClipboard(text).then((ok) => {
				const message = ok ? messages.copied : messages.copyFailed;
				showCopyFeedback(copyBtn, message, ok);
				announce(liveRegion, message);
			});
			return;
		}

		const textEl = target.closest('.jtzl-bm-ua-text');
		if (textEl instanceof HTMLElement) {
			toggleExpanded(textEl);
		}
	});

	root.addEventListener('keydown', (event: Event) => {
		const key = (event as KeyboardEvent).key;
		if (key !== 'Enter' && key !== ' ') return;

		const target = event.target;
		if (!(target instanceof Element)) return;

		const textEl = target.closest('.jtzl-bm-ua-text');
		if (textEl instanceof HTMLElement) {
			event.preventDefault();
			toggleExpanded(textEl);
		}
	});
}
