/**
 * Advanced Analytics Module
 * Depends on: Chart.js (globally loaded), App helpers
 */

const analyticsModule = {
    charts: {},
    state: { period: 'daily', from: '', to: '' },
    wilayas: [
        { id: '01', name: '01 - Adrar' }, { id: '02', name: '02 - Chlef' }, { id: '03', name: '03 - Laghouat' },
        { id: '04', name: '04 - Oum El Bouaghi' }, { id: '05', name: '05 - Batna' }, { id: '06', name: '06 - Béjaïa' },
        { id: '07', name: '07 - Biskra' }, { id: '08', name: '08 - Béchar' }, { id: '09', name: '09 - Blida' },
        { id: '10', name: '10 - Bouira' }, { id: '11', name: '11 - Tamanrasset' }, { id: '12', name: '12 - Tébessa' },
        { id: '13', name: '13 - Tlemcen' }, { id: '14', name: '14 - Tiaret' }, { id: '15', name: '15 - Tizi Ouzou' },
        { id: '16', name: '16 - Alger' }, { id: '17', name: '17 - Djelfa' }, { id: '18', name: '18 - Jijel' },
        { id: '19', name: '19 - Sétif' }, { id: '20', name: '20 - Saïda' }, { id: '21', name: '21 - Skikda' },
        { id: '22', name: '22 - Sidi Bel Abbès' }, { id: '23', name: '23 - Annaba' }, { id: '24', name: '24 - Guelma' },
        { id: '25', name: '25 - Constantine' }, { id: '26', name: '26 - Médéa' }, { id: '27', name: '27 - Mostaganem' },
        { id: '28', name: '28 - M\'Sila' }, { id: '29', name: '29 - Mascara' }, { id: '30', name: '30 - Ouargla' },
        { id: '31', name: '31 - Oran' }, { id: '32', name: '32 - El Bayadh' }, { id: '33', name: '33 - Illizi' },
        { id: '34', name: '34 - Bordj Bou Arreridj' }, { id: '35', name: '35 - Boumerdès' }, { id: '36', name: '36 - El Tarf' },
        { id: '37', name: '37 - Tindouf' }, { id: '38', name: '38 - Tissemsilt' }, { id: '39', name: '39 - El Oued' },
        { id: '40', name: '40 - Khenchela' }, { id: '41', name: '41 - Souk Ahras' }, { id: '42', name: '42 - Tipaza' },
        { id: '43', name: '43 - Mila' }, { id: '44', name: '44 - Aïn Defla' }, { id: '45', name: '45 - Naâma' },
        { id: '46', name: '46 - Aïn Témouchent' }, { id: '47', name: '47 - Ghardaïa' }, { id: '48', name: '48 - Relizane' },
        { id: '49', name: '49 - El M\'Ghair' }, { id: '50', name: '50 - El Meniaa' }, { id: '51', name: '51 - Ouled Djellal' },
        { id: '52', name: '52 - Bordj Baji Mokhtar' }, { id: '53', name: '53 - Béni Abbès' }, { id: '54', name: '54 - Timimoun' },
        { id: '55', name: '55 - Touggourt' }, { id: '56', name: '56 - Djanet' }, { id: '57', name: '57 - In Salah' },
        { id: '58', name: '58 - In Guezzam' }
    ],

    // ─── Entry Point ────────────────────────────────────────────────────────
    init() {
        this._destroyCharts();
        this._setDefaultDates();
        this._bindEvents();
        this.loadAll();
        console.log('[Analytics] Module initialized');
    },

    // ─── Date Helpers ────────────────────────────────────────────────────────
    _setDefaultDates() {
        const today = new Date().toISOString().split('T')[0];
        const ago30 = new Date(Date.now() - 30 * 86400000).toISOString().split('T')[0];
        const fromEl = document.getElementById('analytics-from');
        const toEl   = document.getElementById('analytics-to');
        if (!fromEl) return;
        if (!fromEl.value) fromEl.value = ago30;
        if (!toEl.value)   toEl.value   = today;
        this.state.from = fromEl.value;
        this.state.to   = toEl.value;
    },

    // ─── Events ──────────────────────────────────────────────────────────────
    _bindEvents() {
        // Period selector
        document.getElementById('analytics-period-group')?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-period]');
            if (!btn) return;
            document.querySelectorAll('#analytics-period-group .btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            this.state.period = btn.dataset.period;
            this.loadAll();
        });

        // Apply date range
        document.getElementById('btn-analytics-apply')?.addEventListener('click', () => {
            const from = document.getElementById('analytics-from')?.value;
            const to   = document.getElementById('analytics-to')?.value;
            if (!from || !to) { App.toast('warning', 'Please select both dates.'); return; }
            if (from > to)    { App.toast('warning', 'Start date must be before end date.'); return; }
            this.state.from = from;
            this.state.to   = to;
            this.loadAll();
        });

        // Export buttons
        document.getElementById('btn-analytics-pdf')?.addEventListener('click', () => this._exportPDF());
        document.getElementById('btn-analytics-excel')?.addEventListener('click', () => this._exportExcel());

        // Dark-mode redraw
        window.addEventListener('themeChanged', () => this._redrawCharts());
    },

    // ─── Load All Data ───────────────────────────────────────────────────────
    async loadAll() {
        const { from, to, period } = this.state;
        const base = `analytics.php?from=${from}&to=${to}&period=${period}`;

        const [summary, trend, products, payments, customers, heatmap, wilaya] = await Promise.all([
            App.api(`${base}&action=summary`),
            App.api(`${base}&action=revenue_trend`),
            App.api(`${base}&action=product_performance`),
            App.api(`${base}&action=payment_methods`),
            App.api(`${base}&action=customer_insights`),
            App.api(`${base}&action=hourly_heatmap`),
            App.api(`${base}&action=sales_by_wilaya`),
        ]);

        if (summary)   this._renderKPI(summary);
        if (trend)     this._renderRevenueTrend(trend.data);
        if (products)  this._renderTopProducts(products.data);
        if (payments)  this._renderPaymentMethods(payments.data);
        if (customers) this._renderCustomerInsights(customers.data);
        if (heatmap)   this._renderHeatmap(heatmap.data);
        if (wilaya)    this._renderWilayaSales(wilaya.data);
    },

    // ─── KPI Cards ───────────────────────────────────────────────────────────
    _renderKPI(d) {
        const margin = d.revenue > 0 ? ((d.profit / d.revenue) * 100).toFixed(1) : 0;
        this._setText('an-kpi-revenue',   App.formatCurrency(d.revenue));
        this._setText('an-kpi-profit',    App.formatCurrency(d.profit));
        this._setText('an-kpi-margin',    `${margin}% margin`);
        this._setText('an-kpi-orders',    d.sales_count.toLocaleString());
        this._setText('an-kpi-avg',       App.formatCurrency(d.avg_order));
        this._setText('an-kpi-customers', d.new_customers.toLocaleString());
        this._setText('an-kpi-topcat',    d.top_category || 'N/A');
    },

    // ─── Revenue & Profit Trend ───────────────────────────────────────────────
    _renderRevenueTrend(rows) {
        const labels  = rows.map(r => r.label);
        const revenue = rows.map(r => parseFloat(r.revenue));
        const profit  = rows.map(r => parseFloat(r.profit));

        const cfg = {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Revenue',
                        data: revenue,
                        borderColor: '#0ea5e9',
                        backgroundColor: 'rgba(14,165,233,0.12)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                    },
                    {
                        label: 'Profit',
                        data: profit,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,0.10)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                    }
                ]
            },
            options: this._commonOptions({ x: false })
        };

        this._destroyChart('chartRevenueTrend');
        this.charts['chartRevenueTrend'] = new Chart(document.getElementById('chartRevenueTrend'), cfg);

        // range label
        const label = document.getElementById('an-trend-range-label');
        if (label) label.textContent = `${this.state.from}  →  ${this.state.to}`;
    },

    // ─── Top Products Bar Chart ───────────────────────────────────────────────
    _renderTopProducts(rows) {
        const labels  = rows.map(r => r.name);
        const revenue = rows.map(r => parseFloat(r.revenue));
        const qty     = rows.map(r => parseInt(r.qty_sold));

        const cfg = {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Revenue',
                        data: revenue,
                        backgroundColor: 'rgba(14,165,233,0.75)',
                        borderRadius: 6,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Qty Sold',
                        data: qty,
                        type: 'line',
                        borderColor: '#f59e0b',
                        backgroundColor: 'transparent',
                        tension: 0.4,
                        pointRadius: 4,
                        yAxisID: 'y2',
                    }
                ]
            },
            options: {
                ...this._commonOptions({ x: true }),
                scales: {
                    y:  { beginAtZero: true, position: 'left',  grid: { color: this._gridColor() }, ticks: { color: this._textColor() } },
                    y2: { beginAtZero: true, position: 'right', grid: { display: false },           ticks: { color: this._textColor() } },
                    x:  { ticks: { color: this._textColor(), maxRotation: 30 }, grid: { color: this._gridColor() } }
                }
            }
        };

        this._destroyChart('chartTopProducts');
        this.charts['chartTopProducts'] = new Chart(document.getElementById('chartTopProducts'), cfg);
    },

    // ─── Payment Methods Doughnut ─────────────────────────────────────────────
    _renderPaymentMethods(rows) {
        const COLORS = ['#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4'];

        const cfg = {
            type: 'doughnut',
            data: {
                labels: rows.map(r => r.method),
                datasets: [{
                    data: rows.map(r => parseFloat(r.total)),
                    backgroundColor: rows.map((_, i) => COLORS[i % COLORS.length]),
                    borderWidth: 2,
                    hoverOffset: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { color: this._textColor(), padding: 12, font: { size: 12 } } },
                    tooltip: {
                        callbacks: {
                            label: ctx => `${ctx.label}: ${App.formatCurrency(ctx.parsed)}`
                        }
                    }
                },
                cutout: '60%',
            }
        };

        this._destroyChart('chartPaymentMethods');
        this.charts['chartPaymentMethods'] = new Chart(document.getElementById('chartPaymentMethods'), cfg);
    },

    // ─── Customer Insights Stacked Bar ────────────────────────────────────────
    _renderCustomerInsights(rows) {
        const labels     = rows.map(r => r.label);
        const newCust    = rows.map(r => parseInt(r.new_customers));
        const returning  = rows.map(r => parseInt(r.returning_customers));

        const cfg = {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'New',
                        data: newCust,
                        backgroundColor: 'rgba(16,185,129,0.75)',
                        borderRadius: 4,
                        stack: 'customers',
                    },
                    {
                        label: 'Returning',
                        data: returning,
                        backgroundColor: 'rgba(14,165,233,0.65)',
                        borderRadius: 4,
                        stack: 'customers',
                    }
                ]
            },
            options: this._commonOptions({ x: true })
        };

        this._destroyChart('chartCustomerInsights');
        this.charts['chartCustomerInsights'] = new Chart(document.getElementById('chartCustomerInsights'), cfg);
    },

    // ─── Hourly Heatmap (pure HTML/CSS grid) ──────────────────────────────────
    _renderHeatmap(rows) {
        const container = document.getElementById('analytics-heatmap');
        if (!container) return;

        const DAYS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        const HOURS = Array.from({ length: 24 }, (_, i) => `${String(i).padStart(2,'0')}h`);

        // Build lookup: dow × hour → count
        const map = {};
        rows.forEach(r => {
            const key = `${r.dow}-${r.hour}`;
            map[key] = parseInt(r.sales_count);
        });

        const maxVal = Math.max(1, ...Object.values(map));

        // Build table
        let html = `
            <table style="border-collapse:collapse; table-layout:fixed; width:100%; font-size:11px;">
                <thead><tr><th style="width:36px;"></th>`;
        HOURS.forEach(h => {
            html += `<th style="font-weight:500; padding:2px 1px; text-align:center;">${h}</th>`;
        });
        html += `</tr></thead><tbody>`;

        DAYS.forEach((day, dow) => {
            html += `<tr><td style="font-weight:600; padding:3px 4px; white-space:nowrap;">${day}</td>`;
            HOURS.forEach((_, hour) => {
                const count = map[`${dow + 1}-${hour}`] || 0;
                const pct   = count / maxVal;
                const alpha = count === 0 ? 0.05 : 0.15 + pct * 0.85;
                const bg    = `rgba(14,165,233,${alpha.toFixed(2)})`;
                const title = `${day} ${HOURS[hour]}: ${count} sale${count !== 1 ? 's' : ''}`;
                html += `<td title="${title}" style="
                    background:${bg};
                    width:auto; height:22px;
                    border-radius:3px;
                    border:1px solid rgba(0,0,0,0.04);
                    text-align:center;
                    color:${pct > 0.5 ? '#fff' : 'transparent'};
                    font-size:9px;
                    cursor:default;
                ">${count > 0 ? count : ''}</td>`;
            });
            html += `</tr>`;
        });

        html += `</tbody></table>
            <div class="d-flex align-items-center gap-2 justify-content-end mt-2">
                <small class="text-muted" style="font-size:10px;">Low</small>
                ${[0.1,0.3,0.5,0.7,0.9].map(v => `<span style="display:inline-block;width:16px;height:14px;border-radius:3px;background:rgba(14,165,233,${v})"></span>`).join('')}
                <small class="text-muted" style="font-size:10px;">High</small>
            </div>`;

        container.innerHTML = html;
    },

    // ─── Sales by Wilaya Bar Chart ────────────────────────────────────────────
    _renderWilayaSales(rows) {
        const labels = rows.map(r => {
            const w = this.wilayas.find(x => x.id === r.wilaya);
            return w ? w.name : (r.wilaya === 'N/A' ? 'Non spécifiée' : r.wilaya);
        });
        const revenue = rows.map(r => parseFloat(r.revenue));

        const cfg = {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Revenue',
                        data: revenue,
                        backgroundColor: 'rgba(14,165,233,0.7)',
                        borderRadius: 6,
                    }
                ]
            },
            options: {
                ...this._commonOptions({ x: true }),
                indexAxis: 'y', // Horizontal bars
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => `Ventes: ${App.formatCurrency(ctx.parsed.x)}`
                        }
                    }
                }
            }
        };

        this._destroyChart('chartWilayaSales');
        const canvas = document.getElementById('chartWilayaSales');
        if (canvas) {
            this.charts['chartWilayaSales'] = new Chart(canvas, cfg);
        }
    },

    // ─── Export Helpers ───────────────────────────────────────────────────────
    _exportPDF() {
        App.toast('info', 'Generating PDF…');
        const { jsPDF } = window.jspdf;
        if (!jsPDF) { App.toast('error', 'jsPDF not loaded'); return; }

        const doc = new jsPDF('l', 'pt', 'a4');
        const { from, to } = this.state;

        doc.setFontSize(18);
        doc.text('Advanced Analytics Report', 40, 40);
        doc.setFontSize(10);
        doc.text(`Period: ${from}  →  ${to}`, 40, 60);

        let y = 90;

        // Screenshot each chart as image
        const chartIds = ['chartRevenueTrend','chartTopProducts','chartPaymentMethods','chartCustomerInsights','chartWilayaSales'];
        chartIds.forEach((id) => {
            const canvas = document.getElementById(id);
            if (!canvas) return;
            const img = canvas.toDataURL('image/png', 0.9);
            if (y + 180 > 580) { doc.addPage(); y = 40; }
            doc.addImage(img, 'PNG', 40, y, 760, 160);
            y += 175;
        });

        doc.save(`Analytics_${from}_to_${to}.pdf`);
    },

    _exportExcel() {
        App.toast('info', 'Exporting…');
        const { from, to, period } = this.state;
        App.api(`analytics.php?action=revenue_trend&from=${from}&to=${to}&period=${period}`).then(res => {
            if (!res || !res.data) return;

            const rows = res.data.map(r => ({
                Period:  r.label,
                Revenue: parseFloat(r.revenue),
                Profit:  parseFloat(r.profit),
            }));

            if (window.XLSX) {
                const ws = XLSX.utils.json_to_sheet(rows);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Revenue Trend');
                XLSX.writeFile(wb, `Analytics_${from}_to_${to}.xlsx`);
            } else {
                // Fallback: CSV
                const csv = ['Period,Revenue,Profit', ...rows.map(r => `${r.Period},${r.Revenue},${r.Profit}`)].join('\n');
                const blob = new Blob([csv], { type: 'text/csv' });
                const url  = URL.createObjectURL(blob);
                const a    = Object.assign(document.createElement('a'), { href: url, download: `Analytics_${from}_to_${to}.csv` });
                a.click();
                URL.revokeObjectURL(url);
            }
        });
    },

    // ─── Chart.js Helpers ─────────────────────────────────────────────────────
    _commonOptions({ x = true } = {}) {
        return {
            responsive:          true,
            maintainAspectRatio: false,
            interaction:         { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    display: true,
                    labels:  { color: this._textColor(), boxWidth: 12, font: { size: 12 } }
                },
                tooltip: {
                    backgroundColor: this._isDark() ? '#1e293b' : '#fff',
                    titleColor:      this._textColor(),
                    bodyColor:       this._textColor(),
                    borderColor:     this._gridColor(),
                    borderWidth:     1,
                }
            },
            scales: x ? {
                y: { beginAtZero: true, grid: { color: this._gridColor() }, ticks: { color: this._textColor() } },
                x: { grid: { color: this._gridColor() },                   ticks: { color: this._textColor() } }
            } : {}
        };
    },

    _isDark() {
        return document.body.classList.contains('dark-mode');
    },
    _textColor() {
        return this._isDark() ? '#cbd5e1' : '#374151';
    },
    _gridColor() {
        return this._isDark() ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
    },

    _redrawCharts() {
        // Light re-render — update colors without full API reload
        Object.values(this.charts).forEach(chart => {
            if (!chart) return;
            if (chart.options.plugins?.legend?.labels) {
                chart.options.plugins.legend.labels.color = this._textColor();
            }
            if (chart.options.scales) {
                Object.values(chart.options.scales).forEach(scale => {
                    if (scale.ticks) scale.ticks.color = this._textColor();
                    if (scale.grid)  scale.grid.color  = this._gridColor();
                });
            }
            chart.update('none');
        });
    },

    _destroyChart(id) {
        if (this.charts[id]) { this.charts[id].destroy(); delete this.charts[id]; }
    },

    _destroyCharts() {
        Object.keys(this.charts).forEach(k => this._destroyChart(k));
    },

    _setText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    }
};

// Auto-init when script is first injected by App.initModule
if (!window.analyticsModule) {
    window.analyticsModule = analyticsModule;
    analyticsModule.init();
} else {
    window.analyticsModule.init();
}
