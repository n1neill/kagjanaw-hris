import { BarController, BarElement, CategoryScale, Chart, LinearScale, Tooltip } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

/**
 * Draws every <canvas data-chart="{json}"> (see the x-chart Blade component). Colours come from CSS custom
 * properties, so the charts follow the light or dark theme and are redrawn
 * when a "System" theme follows the computer into dark mode.
 */

const BAR_THICKNESS = 24;
const RADIUS = 4;
const GAP = 2;

const pesoFormat = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 });
const compactPeso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', notation: 'compact', maximumFractionDigits: 1 });
const numberFormat = new Intl.NumberFormat('en-PH', { maximumFractionDigits: 1 });

const instances = new Map();

function token(element, name) {
    return getComputedStyle(element).getPropertyValue(name).trim();
}

function formatValue(value, unit, compact = false) {
    if (unit === 'peso') {
        return compact ? compactPeso.format(value) : pesoFormat.format(value);
    }

    return numberFormat.format(value);
}

function truncate(label, length = 26) {
    return label.length > length ? `${label.slice(0, length - 1)}…` : label;
}

/**
 * Bar ends are rounded only at the data end; stacked segments are split by
 * a surface-coloured gap instead of an outline.
 */
function datasetStyle(type, index, count, color, surface) {
    const horizontal = type === 'bar' || type === 'bar-stacked';
    const stacked = type === 'column-stacked' || type === 'bar-stacked';
    const isLast = index === count - 1;
    const dataEnd = horizontal ? { topRight: RADIUS, bottomRight: RADIUS } : { topLeft: RADIUS, topRight: RADIUS };

    return {
        backgroundColor: color,
        hoverBackgroundColor: color,
        maxBarThickness: BAR_THICKNESS,
        borderRadius: !stacked || isLast ? dataEnd : 0,
        borderSkipped: stacked ? false : 'start',
        borderColor: surface,
        borderWidth: stacked && index > 0 ? (horizontal ? { left: GAP } : { bottom: GAP }) : 0,
        categoryPercentage: type === 'column' ? 0.7 : 0.8,
        barPercentage: type === 'column' ? 0.9 : 1,
    };
}

function buildConfig(canvas, data) {
    const surface = token(canvas, '--app-surface');
    const ink = token(canvas, '--app-ink');
    const inkMuted = token(canvas, '--app-ink-muted');
    const grid = token(canvas, '--app-line');
    const horizontal = data.type === 'bar' || data.type === 'bar-stacked';
    const stacked = data.type === 'column-stacked' || data.type === 'bar-stacked';
    const isSingleRow = data.type === 'bar-stacked';

    const valueAxis = {
        stacked,
        beginAtZero: true,
        display: !isSingleRow,
        border: { display: false },
        grid: { color: grid, drawTicks: false },
        ticks: {
            color: inkMuted,
            padding: 8,
            maxTicksLimit: 5,
            precision: data.unit === 'peso' ? undefined : 0,
            callback: (value) => formatValue(value, data.unit, true),
        },
    };

    const categoryAxis = {
        stacked,
        display: !isSingleRow,
        border: { color: grid },
        grid: { display: false },
        ticks: {
            color: inkMuted,
            autoSkip: true,
            maxRotation: 0,
            callback(value) {
                const label = this.getLabelForValue(value);

                return horizontal ? truncate(label) : label;
            },
        },
    };

    return {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: data.series.map((series, index) => ({
                label: series.label,
                data: series.values,
                ...datasetStyle(data.type, index, data.series.length, token(canvas, `--chart-${series.color}`), surface),
            })),
        },
        options: {
            indexAxis: horizontal ? 'y' : 'x',
            maintainAspectRatio: false,
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 400 },
            interaction: { mode: stacked ? 'index' : 'nearest', axis: horizontal ? 'y' : 'x', intersect: false },
            layout: { padding: isSingleRow ? 0 : { top: 4, right: 8 } },
            scales: horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: surface,
                    borderColor: grid,
                    borderWidth: 1,
                    titleColor: inkMuted,
                    bodyColor: ink,
                    titleFont: { weight: 'normal' },
                    bodyFont: { weight: '600' },
                    padding: 10,
                    cornerRadius: 8,
                    boxWidth: 10,
                    boxHeight: 2,
                    boxPadding: 6,
                    usePointStyle: false,
                    callbacks: {
                        title: (items) => (isSingleRow ? '' : items[0]?.label ?? ''),
                        label: (item) => `${formatValue(item.raw, data.unit)}  ${item.dataset.label}`,
                        labelColor: (item) => ({ borderColor: item.dataset.backgroundColor, backgroundColor: item.dataset.backgroundColor }),
                    },
                },
            },
        },
    };
}

function render(canvas) {
    instances.get(canvas)?.destroy();

    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 12;

    instances.set(canvas, new Chart(canvas, buildConfig(canvas, JSON.parse(canvas.dataset.chart))));
}

export function renderCharts() {
    document.querySelectorAll('canvas[data-chart]').forEach(render);
}

renderCharts();

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', renderCharts);
