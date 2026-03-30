/**
 * Labyrinth Admin Analytics Dashboard
 *
 * Renders an SVG bar chart and handles AJAX refresh for the analytics page.
 */

import './styles/admin.css';

declare const lbyAdmin: {
	ajaxUrl: string;
	nonce: string;
};

interface ChartData {
	labels: string[];
	values: number[];
}

interface SummaryStats {
	total_trap_pages: number;
	total_visits: number;
	visits_last_7_days: number;
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
}

interface ActivityEntry {
	visited_at: string;
	ip_address: string;
	user_agent: string;
	depth_reached: number;
	request_type: string;
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
		noData.className = 'lby-no-data';
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
		line.setAttribute('class', 'lby-chart-grid');
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
		rect.setAttribute('class', 'lby-chart-bar');
		svg.appendChild(rect);

		// Value label on top (only if value > 0 and there's space).
		if (values[i] > 0 && barWidth >= 8) {
			const text = document.createElementNS(svgNS, 'text');
			text.setAttribute('x', String(x + barWidth / 2));
			text.setAttribute('y', String(y - 4));
			text.setAttribute('class', 'lby-chart-value');
			text.textContent = String(values[i]);
			svg.appendChild(text);
		}

		// Date label at bottom (show every Nth to avoid overlap).
		if (i % showEvery === 0) {
			const text = document.createElementNS(svgNS, 'text');
			text.setAttribute('x', String(x + barWidth / 2));
			text.setAttribute('y', String(height - 5));
			text.setAttribute('class', 'lby-chart-label');
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
function createCell(
	tag: string,
	text: string,
	className?: string
): HTMLElement {
	const el = document.createElement(tag);
	el.textContent = text;
	if (className) {
		el.className = className;
	}
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
 * Update the dashboard with fresh AJAX data.
 */
async function refreshDashboard(): Promise<void> {
	const btn = document.getElementById(
		'lby-refresh-btn'
	) as HTMLButtonElement | null;
	if (btn) {
		btn.disabled = true;
		btn.textContent = 'Refreshing...';
	}

	try {
		const formData = new FormData();
		formData.append('action', 'lby_refresh_analytics');
		formData.append('nonce', lbyAdmin.nonce);

		const response = await fetch(lbyAdmin.ajaxUrl, {
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
			recent_activity: recentActivity,
		} = result.data;

		// Update summary cards.
		updateSummary(summary);

		// Update chart.
		const chartEl = document.getElementById('lby-chart');
		if (chartEl && chartData) {
			renderChart(chartEl, chartData);
		}

		// Update top bots table.
		updateTopBots(topBots);

		// Update recent activity table.
		updateActivity(recentActivity);
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
		'lby-stat-trap-pages': 'total_trap_pages',
		'lby-stat-recent-visits': 'visits_last_7_days',
		'lby-stat-total-bots': 'total_bots',
		'lby-stat-blocked-bots': 'blocked_bots',
	};

	for (const [id, key] of Object.entries(map)) {
		const el = document.getElementById(id);
		if (el) {
			el.textContent = String(summary[key] ?? 0);
		}
	}

	// Update alert styling: remove alert if no bots blocked.
	const ratioCard = document
		.getElementById('lby-stat-bot-ratio')
		?.closest('.lby-card');
	if (ratioCard) {
		ratioCard.classList.toggle(
			'lby-card-alert',
			(summary.blocked_bots ?? 0) > 0
		);
	}
}

function updateTopBots(bots: BotEntry[]): void {
	const tbody = document.querySelector('#lby-top-bots-table tbody');
	if (!tbody) return;

	// Clear existing rows.
	tbody.replaceChildren();

	if (!bots.length) {
		const tr = document.createElement('tr');
		const td = createCell('td', 'No bot activity detected yet.');
		td.setAttribute('colspan', '7');
		tr.appendChild(td);
		tbody.appendChild(tr);
		return;
	}

	for (const bot of bots) {
		const tr = document.createElement('tr');

		tr.appendChild(createCodeCell('td', bot.ip_address));
		tr.appendChild(createCell('td', bot.user_agent));

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
			? 'lby-badge lby-badge-blocked'
			: 'lby-badge lby-badge-tracking';
		badge.textContent = bot.is_blocked ? 'Blocked' : 'Tracking';
		statusTd.appendChild(badge);
		tr.appendChild(statusTd);

		tbody.appendChild(tr);
	}
}

function updateActivity(activity: ActivityEntry[]): void {
	const tbody = document.querySelector('#lby-activity-table tbody');
	if (!tbody) return;

	// Clear existing rows.
	tbody.replaceChildren();

	if (!activity.length) {
		const tr = document.createElement('tr');
		const td = createCell('td', 'No recent activity.');
		td.setAttribute('colspan', '5');
		tr.appendChild(td);
		tbody.appendChild(tr);
		return;
	}

	for (const visit of activity) {
		const tr = document.createElement('tr');
		tr.appendChild(createCell('td', visit.visited_at));
		tr.appendChild(createCodeCell('td', visit.ip_address));
		tr.appendChild(createCell('td', visit.user_agent, 'lby-ua-cell'));
		tr.appendChild(createCell('td', String(visit.depth_reached)));

		const typeTd = document.createElement('td');
		const badge = document.createElement('span');
		if (visit.request_type === 'blocked_visit') {
			badge.className = 'lby-badge lby-badge-blocked';
			badge.textContent = 'Blocked';
		} else if (visit.request_type === 'crawler_exempted') {
			badge.className = 'lby-badge lby-badge-exempted';
			badge.textContent = 'Exempted';
		} else {
			badge.className = 'lby-badge lby-badge-tracking';
			badge.textContent = 'Trap Visit';
		}
		typeTd.appendChild(badge);
		tr.appendChild(typeTd);

		tbody.appendChild(tr);
	}
}

/**
 * Initialize the analytics dashboard.
 */
function init(): void {
	const app = document.getElementById('lby-analytics-app');
	if (!app) return;

	// Render initial chart from embedded data.
	const chartEl = document.getElementById('lby-chart');
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
	const refreshBtn = document.getElementById('lby-refresh-btn');
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
