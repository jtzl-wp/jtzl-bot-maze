/**
 * JTZL's Bot Maze Admin Analytics Dashboard
 *
 * Renders an SVG bar chart and handles AJAX refresh for the analytics page.
 */

import './styles/admin.css';
import { initUaCells, type UaCellMessages } from './ua-cell';

declare const jtzlBbgAdmin: {
	ajaxUrl: string;
	nonce: string;
	i18n?: {
		copied?: string;
		copyFailed?: string;
		copyLabel?: string;
	};
};

/**
 * Resolve localised UA-cell feedback strings, with English fallbacks.
 */
function getUaCellMessages(): UaCellMessages {
	const i18n = (typeof jtzlBbgAdmin !== 'undefined' && jtzlBbgAdmin.i18n) || {};
	return {
		copied: i18n.copied ?? 'Copied!',
		copyFailed: i18n.copyFailed ?? 'Copy failed',
	};
}

interface ChartData {
	labels: string[];
	values: number[];
}

interface SummaryStats {
	total_trap_pages: number;
	total_visits: number;
	visits_last_7_days: number;
	all_visits_last_7_days: number;
	total_bots: number;
	blocked_bots: number;
}

interface BotEntry {
	ip_address: string;
	user_agent: string;
	score: number;
	trap_visits_count: number;
	max_depth_reached: number;
	last_seen_at: string;
	is_blocked: boolean;
	country_name: string;
}

/**
 * Render an SVG bar chart into the container using safe DOM methods.
 * @param container
 * @param data
 */
function renderChart(container: HTMLElement, data: ChartData): void {
	const { labels, values } = data;

	// Clear existing content.
	container.replaceChildren();

	if (!labels.length) {
		const noData = document.createElement('div');
		noData.className = 'jtzl-bm-no-data';
		noData.textContent = 'No visit data available yet.';
		container.appendChild(noData);
		return;
	}

	const width = container.clientWidth || 800;
	const height = 250;
	const padding = { top: 20, right: 10, bottom: 40, left: 10 };
	const chartWidth = width - padding.left - padding.right;
	const chartHeight = height - padding.top - padding.bottom;

	const maxValue = Math.max(...values, 1);
	const barWidth = Math.max(2, chartWidth / labels.length - 2);
	const barGap = 2;

	const svgNS = 'http://www.w3.org/2000/svg';
	const svg = document.createElementNS(svgNS, 'svg');
	svg.setAttribute('viewBox', `0 0 ${width} ${height}`);

	// Grid lines.
	const gridLines = 4;
	for (let i = 0; i <= gridLines; i++) {
		const y = padding.top + (chartHeight / gridLines) * i;
		const line = document.createElementNS(svgNS, 'line');
		line.setAttribute('x1', String(padding.left));
		line.setAttribute('y1', String(y));
		line.setAttribute('x2', String(width - padding.right));
		line.setAttribute('y2', String(y));
		line.setAttribute('class', 'jtzl-bm-chart-grid');
		svg.appendChild(line);
	}

	// Bars and labels.
	const showEvery = Math.max(1, Math.floor(labels.length / 10));

	labels.forEach((label, i) => {
		const barHeight = (values[i] / maxValue) * chartHeight;
		const x = padding.left + i * (barWidth + barGap);
		const y = padding.top + chartHeight - barHeight;

		const rect = document.createElementNS(svgNS, 'rect');
		rect.setAttribute('x', String(x));
		rect.setAttribute('y', String(y));
		rect.setAttribute('width', String(barWidth));
		rect.setAttribute('height', String(barHeight));
		rect.setAttribute('class', 'jtzl-bm-chart-bar');
		svg.appendChild(rect);

		// Value label on top (only if value > 0 and there's space).
		if (values[i] > 0 && barWidth >= 8) {
			const text = document.createElementNS(svgNS, 'text');
			text.setAttribute('x', String(x + barWidth / 2));
			text.setAttribute('y', String(y - 4));
			text.setAttribute('class', 'jtzl-bm-chart-value');
			text.textContent = String(values[i]);
			svg.appendChild(text);
		}

		// Date label at bottom (show every Nth to avoid overlap).
		if (i % showEvery === 0) {
			const text = document.createElementNS(svgNS, 'text');
			text.setAttribute('x', String(x + barWidth / 2));
			text.setAttribute('y', String(height - 5));
			text.setAttribute('class', 'jtzl-bm-chart-label');
			text.textContent = label.slice(5); // MM-DD
			svg.appendChild(text);
		}
	});

	container.appendChild(svg);
}

/**
 * Create a text node safely (no HTML interpretation).
 * @param tag
 * @param text
 * @param className
 */
function createCell(tag: string, text: string): HTMLElement {
	const el = document.createElement(tag);
	el.textContent = text;
	return el;
}

/**
 * Create a cell containing a <code> element.
 * @param tag
 * @param text
 */
function createCodeCell(tag: string, text: string): HTMLElement {
	const td = document.createElement(tag);
	const code = document.createElement('code');
	code.textContent = text;
	td.appendChild(code);
	return td;
}

/**
 * Build a User-Agent cell matching the server-rendered partial, so AJAX-refreshed
 * rows keep the copy button and click-to-expand behaviour.
 * @param ua Full user-agent string.
 */
function createUaCell(ua: string): HTMLElement {
	const td = document.createElement('td');
	td.className = 'jtzl-bm-ua-cell';

	const text = document.createElement('span');
	text.className = 'jtzl-bm-ua-text';
	text.setAttribute('role', 'button');
	text.setAttribute('tabindex', '0');
	text.setAttribute('aria-expanded', 'false');
	text.setAttribute('title', ua);
	text.textContent = ua;
	td.appendChild(text);

	const copyLabel =
		(typeof jtzlBbgAdmin !== 'undefined' && jtzlBbgAdmin.i18n?.copyLabel) ||
		'Copy user agent to clipboard';

	const button = document.createElement('button');
	button.type = 'button';
	button.className = 'jtzl-bm-ua-copy';
	button.setAttribute('data-clipboard-text', ua);
	button.setAttribute('title', copyLabel);

	const icon = document.createElement('span');
	icon.className = 'dashicons dashicons-clipboard';
	icon.setAttribute('aria-hidden', 'true');
	button.appendChild(icon);

	const label = document.createElement('span');
	label.className = 'screen-reader-text';
	label.textContent = copyLabel;
	button.appendChild(label);

	td.appendChild(button);
	return td;
}

/**
 * Update the dashboard with fresh AJAX data.
 */
async function refreshDashboard(): Promise<void> {
	const btn = document.getElementById(
		'jtzl-bm-refresh-btn'
	) as HTMLButtonElement | null;
	if (btn) {
		btn.disabled = true;
		btn.textContent = 'Refreshing...';
	}

	try {
		const formData = new FormData();
		formData.append('action', 'jtzl_bm_refresh_analytics');
		formData.append('nonce', jtzlBbgAdmin.nonce);

		const response = await fetch(jtzlBbgAdmin.ajaxUrl, {
			method: 'POST',
			body: formData,
		});

		const result = await response.json();

		if (!result.success) {
			return;
		}

		const {
			summary,
			chart_data: chartData,
			top_bots: topBots,
		} = result.data;

		// Update summary cards.
		updateSummary(summary);

		// Update chart.
		const chartEl = document.getElementById('jtzl-bm-chart');
		if (chartEl && chartData) {
			renderChart(chartEl, chartData);
		}

		// Update top bots table.
		updateTopBots(topBots);
	} catch {
		// Silent fail on network errors.
	} finally {
		if (btn) {
			btn.disabled = false;
			btn.textContent = 'Refresh';
		}
	}
}

function updateSummary(summary: SummaryStats): void {
	const map: Record<string, keyof SummaryStats> = {
		'jtzl-bm-stat-trap-pages': 'total_trap_pages',
		'jtzl-bm-stat-recent-visits': 'all_visits_last_7_days',
		'jtzl-bm-stat-total-bots': 'total_bots',
		'jtzl-bm-stat-blocked-bots': 'blocked_bots',
	};

	for (const [id, key] of Object.entries(map)) {
		const el = document.getElementById(id);
		if (el) {
			el.textContent = String(summary[key] ?? 0);
		}
	}

	// Update alert styling: remove alert if no bots blocked.
	const ratioCard = document
		.getElementById('jtzl-bm-stat-bot-ratio')
		?.closest('.jtzl-bm-card');
	if (ratioCard) {
		ratioCard.classList.toggle(
			'jtzl-bm-card-alert',
			(summary.blocked_bots ?? 0) > 0
		);
	}
}

function updateTopBots(bots: BotEntry[]): void {
	const tbody = document.querySelector('#jtzl-bm-top-bots-table tbody');
	if (!tbody) return;

	// Clear existing rows.
	tbody.replaceChildren();

	if (!bots.length) {
		const tr = document.createElement('tr');
		const td = createCell('td', 'No bot activity detected yet.');
		td.setAttribute('colspan', '8');
		tr.appendChild(td);
		tbody.appendChild(tr);
		return;
	}

	for (const bot of bots) {
		const tr = document.createElement('tr');

		tr.appendChild(createCodeCell('td', bot.ip_address));
		tr.appendChild(createCell('td', bot.country_name || ''));
		tr.appendChild(createUaCell(bot.user_agent));

		const scoreTd = document.createElement('td');
		const strong = document.createElement('strong');
		strong.textContent = Number(bot.score).toFixed(1);
		scoreTd.appendChild(strong);
		tr.appendChild(scoreTd);

		tr.appendChild(createCell('td', String(bot.trap_visits_count)));
		tr.appendChild(createCell('td', String(bot.max_depth_reached)));
		tr.appendChild(createCell('td', bot.last_seen_at));

		const statusTd = document.createElement('td');
		const badge = document.createElement('span');
		badge.className = bot.is_blocked
			? 'jtzl-bm-badge jtzl-bm-badge-blocked'
			: 'jtzl-bm-badge jtzl-bm-badge-tracking';
		badge.textContent = bot.is_blocked ? 'Blocked' : 'Tracking';
		statusTd.appendChild(badge);
		tr.appendChild(statusTd);

		tbody.appendChild(tr);
	}
}

/**
 * Initialize the analytics dashboard.
 */
function init(): void {
	// UA cells appear on all admin listing pages, not just the dashboard.
	initUaCells(document, getUaCellMessages());

	const app = document.getElementById('jtzl-bm-analytics-app');
	if (!app) return;

	// Render initial chart from embedded data.
	const chartEl = document.getElementById('jtzl-bm-chart');
	const chartDataAttr = app.getAttribute('data-chart');

	if (chartEl && chartDataAttr) {
		try {
			const chartData: ChartData = JSON.parse(chartDataAttr);
			renderChart(chartEl, chartData);
		} catch {
			// Invalid chart data.
		}
	}

	// Bind refresh button.
	const refreshBtn = document.getElementById('jtzl-bm-refresh-btn');
	if (refreshBtn) {
		refreshBtn.addEventListener('click', () => {
			refreshDashboard();
		});
	}
}

// Initialize when DOM is ready.
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}
