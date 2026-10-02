/**
 * Minilytics Smooth Multi-Series Canvas Area Chart Engine
 * Superimposes Views, Visitors, and Visits on the same time-series chart.
 * Features:
 * - Smooth Bézier curves for all active series
 * - Cohesive shared Y-axis scaling
 * - Multi-series synchronized crosshair and anchor dots
 * - Rich unified hover tooltip with color badges
 * - Toggleable series visibility
 */

class MinilyticsChart {
    constructor(canvasId, tooltipId, options = {}) {
        this.canvas = document.getElementById(canvasId);
        this.tooltip = document.getElementById(tooltipId);
        if (!this.canvas) return;

        this.ctx = this.canvas.getContext('2d');
        this.data = [];
        this.hoveredIndex = -1;

        const defaultSeriesConfig = {
            pageviews: {
                key: 'pageviews',
                label: 'Views',
                singular: 'view',
                color: '#2563eb', // Blue
                gradientStart: 'rgba(37, 99, 235, 0.18)',
                gradientEnd: 'rgba(37, 99, 235, 0.00)',
                lineWidth: 2.5
            },
            visitors: {
                key: 'visitors',
                label: 'Visitors',
                singular: 'visitor',
                color: '#8b5cf6', // Violet / Purple
                gradientStart: 'rgba(139, 92, 246, 0.14)',
                gradientEnd: 'rgba(139, 92, 246, 0.00)',
                lineWidth: 2.2
            },
            sessions: {
                key: 'sessions',
                label: 'Visits',
                singular: 'visit',
                color: '#06b6d4', // Cyan
                gradientStart: 'rgba(6, 182, 212, 0.10)',
                gradientEnd: 'rgba(6, 182, 212, 0.00)',
                lineWidth: 2.0
            },
            events: {
                key: 'events',
                label: 'Events',
                singular: 'event',
                color: '#f59e0b', // Amber / Orange
                gradientStart: 'rgba(245, 158, 11, 0.20)',
                gradientEnd: 'rgba(245, 158, 11, 0.00)',
                lineWidth: 2.5
            }
        };

        this.seriesConfig = Object.assign({}, defaultSeriesConfig, options.seriesConfig || {});
        const initialSeries = options.activeSeries || (options.seriesConfig ? Object.keys(options.seriesConfig) : ['pageviews', 'visitors', 'sessions']);
        this.activeSeries = new Set(initialSeries.filter(k => this.seriesConfig[k]));

        this.padding = { top: 25, right: 20, bottom: 32, left: 36 };

        this.initEvents();
        window.addEventListener('resize', () => this.resize());
    }

    setData(data, activeSeries = null) {
        this.data = data || [];
        if (activeSeries) {
            this.activeSeries = new Set(Array.isArray(activeSeries) ? activeSeries : [activeSeries]);
        }
        this.hoveredIndex = -1;
        if (this.tooltip) {
            this.tooltip.classList.remove('visible');
        }
        this.resize();
    }

    toggleSeries(seriesKey) {
        if (!this.seriesConfig[seriesKey]) return;

        // If it is the only active one, don't uncheck (keep at least 1)
        if (this.activeSeries.has(seriesKey) && this.activeSeries.size === 1) {
            return;
        }

        if (this.activeSeries.has(seriesKey)) {
            this.activeSeries.delete(seriesKey);
        } else {
            this.activeSeries.add(seriesKey);
        }

        this.hoveredIndex = -1;
        if (this.tooltip) {
            this.tooltip.classList.remove('visible');
        }
        this.draw();
    }

    isSeriesActive(seriesKey) {
        return this.activeSeries.has(seriesKey);
    }

    setMetric(metric) {
        // Fallback for single metric selection or all
        if (metric === 'all') {
            this.activeSeries = new Set(['pageviews', 'visitors', 'sessions']);
        } else if (this.seriesConfig[metric]) {
            this.activeSeries = new Set([metric]);
        }
        this.draw();
    }

    resize() {
        if (!this.canvas) return;
        const rect = this.canvas.getBoundingClientRect();
        this.dpr = window.devicePixelRatio || 1;
        
        this.width = rect.width;
        this.height = rect.height;

        this.canvas.width = Math.round(this.width * this.dpr);
        this.canvas.height = Math.round(this.height * this.dpr);

        this.ctx.resetTransform();
        this.ctx.scale(this.dpr, this.dpr);

        this.draw();
    }

    initEvents() {
        if (!this.canvas) return;

        this.canvas.addEventListener('mousemove', (e) => {
            const rect = this.canvas.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;

            this.handleHover(mouseX, mouseY);
        });

        this.canvas.addEventListener('mouseleave', () => {
            this.hoveredIndex = -1;
            if (this.tooltip) {
                this.tooltip.classList.remove('visible');
            }
            this.draw();
        });
    }

    handleHover(mouseX, mouseY) {
        if (!this.data || this.data.length === 0) return;

        const plotWidth = this.width - this.padding.left - this.padding.right;
        const stepX = plotWidth / Math.max(1, this.data.length - 1);

        const relativeX = mouseX - this.padding.left;
        let index = Math.round(relativeX / stepX);
        index = Math.max(0, Math.min(this.data.length - 1, index));

        this.hoveredIndex = index;
        this.draw();
        this.updateTooltip(index);
    }

    updateTooltip(index) {
        if (!this.tooltip || index < 0 || !this.data[index]) return;
        const item = this.data[index];
        const dateStr = item.full_label || item.label || '';

        // Build list of metrics for all active series
        const seriesKeys = Object.keys(this.seriesConfig).filter(k => this.activeSeries.has(k));
        
        const metricsHtml = seriesKeys.map(key => {
            const cfg = this.seriesConfig[key];
            const val = Number(item[key]) || 0;
            const label = val <= 1 ? cfg.singular : cfg.label.toLowerCase();
            return `
                <div class="chart-tooltip-metric" style="margin-top: 4px;">
                    <span class="chart-tooltip-dot" style="background: ${cfg.color}; box-shadow: 0 0 6px ${cfg.color}99;"></span>
                    <span class="chart-tooltip-val">${val.toLocaleString()}</span>
                    <span class="chart-tooltip-name">${label}</span>
                </div>
            `;
        }).join('');

        this.tooltip.innerHTML = `
            <div class="chart-tooltip-date">${dateStr}</div>
            <div class="chart-tooltip-metrics-list">${metricsHtml}</div>
        `;

        const plotY = this.padding.top;
        const plotWidth = this.width - this.padding.left - this.padding.right;
        const plotHeight = this.height - this.padding.top - this.padding.bottom;
        const stepX = plotWidth / Math.max(1, this.data.length - 1);
        const pointX = this.padding.left + index * stepX;

        // Find max value across all active series to place tooltip
        const allActiveValues = [];
        this.activeSeries.forEach(k => {
            this.data.forEach(d => allActiveValues.push(Number(d[k]) || 0));
        });
        let maxVal = Math.max(...allActiveValues, 8);
        const roundStep = maxVal > 100 ? 50 : (maxVal > 50 ? 20 : (maxVal > 20 ? 10 : 4));
        maxVal = Math.ceil(maxVal / roundStep) * roundStep;

        // Use highest point among active series at this index
        const currentIndexValues = seriesKeys.map(k => Number(item[k]) || 0);
        const topValAtIndex = Math.max(...currentIndexValues, 0);
        const pointY = plotY + plotHeight - (topValAtIndex / maxVal) * plotHeight;

        // Horizontal positioning
        const ratio = this.data.length > 1 ? index / (this.data.length - 1) : 0.5;
        let translateX = -50;
        if (ratio < 0.12) {
            translateX = -5;
        } else if (ratio > 0.88) {
            translateX = -95;
        }

        // Place tooltip nicely above the highest point
        if (pointY < 80) {
            this.tooltip.style.left = `${pointX}px`;
            this.tooltip.style.top = `${pointY + 14}px`;
            this.tooltip.style.transform = `translate(${translateX}%, 0)`;
        } else {
            this.tooltip.style.left = `${pointX}px`;
            this.tooltip.style.top = `${pointY - 14}px`;
            this.tooltip.style.transform = `translate(${translateX}%, -100%)`;
        }

        this.tooltip.classList.add('visible');
    }

    draw() {
        if (!this.ctx || !this.width || !this.height) return;
        const ctx = this.ctx;
        ctx.clearRect(0, 0, this.width, this.height);

        if (!this.data || this.data.length === 0) {
            ctx.fillStyle = '#94a3b8';
            ctx.font = '13px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('No data available for this period', this.width / 2, this.height / 2);
            return;
        }

        const plotX = this.padding.left;
        const plotY = this.padding.top;
        const plotWidth = this.width - this.padding.left - this.padding.right;
        const plotHeight = this.height - this.padding.top - this.padding.bottom;

        // 1. Determine common Y-axis maximum across all active series
        const allActiveValues = [];
        this.activeSeries.forEach(k => {
            this.data.forEach(d => allActiveValues.push(Number(d[k]) || 0));
        });

        let maxVal = Math.max(...allActiveValues, 8);
        const roundStep = maxVal > 100 ? 50 : (maxVal > 50 ? 20 : (maxVal > 20 ? 10 : 4));
        maxVal = Math.ceil(maxVal / roundStep) * roundStep;

        const getY = (val) => {
            return plotY + plotHeight - (val / maxVal) * plotHeight;
        };

        const stepX = plotWidth / Math.max(1, this.data.length - 1);

        // 2. Subtle horizontal grid lines & Y-axis numbers
        const gridLines = 3;
        ctx.strokeStyle = '#f1f5f9';
        ctx.lineWidth = 1;
        ctx.fillStyle = '#94a3b8';
        ctx.font = '11px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';

        for (let i = 0; i <= gridLines; i++) {
            const val = Math.round((maxVal / gridLines) * i);
            const y = getY(val);

            ctx.beginPath();
            ctx.moveTo(plotX, y);
            ctx.lineTo(plotX + plotWidth, y);
            ctx.stroke();

            ctx.fillText(val.toLocaleString(), plotX - 8, y);
        }

        // 3. Draw dashed vertical crosshair if hovering
        const activeIdx = this.hoveredIndex >= 0 ? this.hoveredIndex : (this.data.length > 3 ? this.data.length - 1 : -1);
        if (activeIdx >= 0 && activeIdx < this.data.length && this.hoveredIndex >= 0) {
            const crosshairX = plotX + activeIdx * stepX;
            ctx.save();
            ctx.beginPath();
            ctx.setLineDash([3, 3]);
            ctx.strokeStyle = '#cbd5e1';
            ctx.lineWidth = 1;
            ctx.moveTo(crosshairX, plotY);
            ctx.lineTo(crosshairX, plotY + plotHeight);
            ctx.stroke();
            ctx.restore();
        }

        // 4. Render each active series (in background-to-foreground order)
        const preferredOrder = ['pageviews', 'events', 'sessions', 'visitors'];
        const renderOrder = preferredOrder.filter(k => this.activeSeries.has(k) && this.seriesConfig[k]);
        Object.keys(this.seriesConfig).forEach(k => {
            if (this.activeSeries.has(k) && !renderOrder.includes(k)) renderOrder.push(k);
        });

        renderOrder.forEach(key => {
            const cfg = this.seriesConfig[key];
            const points = this.data.map((item, i) => ({
                x: plotX + i * stepX,
                y: getY(Number(item[key]) || 0),
                val: Number(item[key]) || 0
            }));

            if (points.length <= 1) return;

            // A. Smooth Area Gradient Fill
            const gradient = ctx.createLinearGradient(0, plotY, 0, plotY + plotHeight);
            gradient.addColorStop(0, cfg.gradientStart);
            gradient.addColorStop(0.7, cfg.gradientEnd);
            gradient.addColorStop(1, 'rgba(255, 255, 255, 0)');

            ctx.beginPath();
            ctx.moveTo(points[0].x, plotY + plotHeight);
            ctx.lineTo(points[0].x, points[0].y);

            const floorY = plotY + plotHeight;
            for (let i = 0; i < points.length - 1; i++) {
                const p0 = points[i === 0 ? 0 : i - 1];
                const p1 = points[i];
                const p2 = points[i + 1];
                const p3 = points[i + 2] || p2;

                const cp1x = p1.x + (p2.x - p0.x) / 6;
                const cp1y = Math.min(p1.y + (p2.y - p0.y) / 6, floorY);
                const cp2x = p2.x - (p3.x - p1.x) / 6;
                const cp2y = Math.min(p2.y - (p3.y - p1.y) / 6, floorY);

                ctx.bezierCurveTo(cp1x, cp1y, cp2x, cp2y, p2.x, p2.y);
            }

            ctx.lineTo(points[points.length - 1].x, plotY + plotHeight);
            ctx.closePath();
            ctx.fillStyle = gradient;
            ctx.fill();

            // B. Smooth Bézier Curve Stroke
            ctx.beginPath();
            ctx.moveTo(points[0].x, points[0].y);

            for (let i = 0; i < points.length - 1; i++) {
                const p0 = points[i === 0 ? 0 : i - 1];
                const p1 = points[i];
                const p2 = points[i + 1];
                const p3 = points[i + 2] || p2;

                const cp1x = p1.x + (p2.x - p0.x) / 6;
                const cp1y = Math.min(p1.y + (p2.y - p0.y) / 6, floorY);
                const cp2x = p2.x - (p3.x - p1.x) / 6;
                const cp2y = Math.min(p2.y - (p3.y - p1.y) / 6, floorY);

                ctx.bezierCurveTo(cp1x, cp1y, cp2x, cp2y, p2.x, p2.y);
            }

            ctx.strokeStyle = cfg.color;
            ctx.lineWidth = cfg.lineWidth;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.stroke();

            // C. Anchor Dot on active point for this series
            if (activeIdx >= 0 && activeIdx < points.length) {
                const pt = points[activeIdx];
                ctx.beginPath();
                ctx.arc(pt.x, pt.y, 4.5, 0, Math.PI * 2);
                ctx.fillStyle = '#ffffff';
                ctx.fill();
                ctx.lineWidth = 2.5;
                ctx.strokeStyle = cfg.color;
                ctx.stroke();
            }
        });

        // 5. X-axis Labels (Dates / Time steps)
        ctx.fillStyle = '#94a3b8';
        ctx.font = '11px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';

        const labelInterval = Math.max(1, Math.floor(this.data.length / 7));
        for (let i = 0; i < this.data.length; i += labelInterval) {
            const ptX = plotX + i * stepX;
            const label = this.data[i].label || '';
            ctx.fillText(label, ptX, plotY + plotHeight + 10);
        }
    }
}

window.MinilyticsChart = MinilyticsChart;
