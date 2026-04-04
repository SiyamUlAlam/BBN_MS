<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;

final class WebController
{
    public function home(Request $request): void
    {
                $content = <<<'HTML'
<div class="card">
    <h3>Analytics Scope</h3>
    <form id="dashboard-scope-form" class="grid cols-4">
        <select id="dashboard-period">
            <option value="monthly">Monthly</option>
            <option value="quarterly">Quarterly</option>
            <option value="yearly">Yearly</option>
        </select>
        <input id="dashboard-anchor-month" type="month" />
        <input id="dashboard-year" type="number" min="2000" max="2100" step="1" placeholder="Year" style="display:none;" />
        <select id="dashboard-quarter" style="display:none;">
            <option value="Q1">Q1 (Jan-Mar)</option>
            <option value="Q2">Q2 (Apr-Jun)</option>
            <option value="Q3">Q3 (Jul-Sep)</option>
            <option value="Q4">Q4 (Oct-Dec)</option>
        </select>
        <button type="submit" class="button">Apply Scope</button>
        <div id="dashboard-scope-label" class="chart-note" style="align-self:center;"></div>
    </form>
</div>
<div class="grid cols-4" id="dashboard-cards"></div>
<div class="grid cols-2">
    <div class="card chart-card">
        <div class="chart-head">
            <h3>Net Income Trend</h3>
            <span id="trend-month-label" class="chart-note"></span>
        </div>
        <div id="net-trend-line" class="line-chart-wrap"></div>
    </div>
    <div class="card chart-card">
        <div class="chart-head">
            <h3>Revenue Composition</h3>
            <span class="chart-note">Current month</span>
        </div>
        <div class="split-chart">
            <div id="revenue-donut" class="donut"></div>
            <div id="revenue-legend" class="legend-list"></div>
        </div>
    </div>
</div>
<div class="grid cols-3">
    <div class="card chart-card">
        <div class="chart-head">
            <h3>Customer Growth Trend</h3>
            <span class="chart-note">Total customers by month</span>
        </div>
        <div id="customer-growth-line" class="line-chart-wrap compact-line"></div>
    </div>
    <div class="card chart-card">
        <div class="chart-head">
            <h3>Income Growth Trend</h3>
            <span class="chart-note">Month over month %</span>
        </div>
        <div id="income-growth-line" class="line-chart-wrap compact-line"></div>
    </div>
    <div class="card chart-card">
        <div class="chart-head">
            <h3>Cashflow Trend</h3>
            <span class="chart-note">Inflow vs Outflow vs Net</span>
        </div>
        <div id="cashflow-line" class="line-chart-wrap compact-line"></div>
    </div>
</div>
<div class="grid cols-2">
    <div class="card chart-card">
        <h3>Customer Health</h3>
        <div class="meter-list" id="customer-health"></div>
    </div>
    <div class="card chart-card">
        <h3>Collection Pulse</h3>
        <div class="meter-list" id="collection-pulse"></div>
    </div>
</div>
<div class="grid cols-2">
    <div class="card chart-card">
        <h3>Inventory Snapshot</h3>
        <div class="meter-list" id="inventory-snapshot"></div>
    </div>
    <div class="card chart-card">
        <h3>Operational Risk Snapshot</h3>
        <div class="meter-list" id="ops-risk"></div>
    </div>
</div>
<div class="card">
    <h3>System Shortcuts</h3>
    <div class="grid cols-4">
        <a class="button" href="/customers">Add Customer</a>
        <a class="button" href="/customers/list">Customer List</a>
        <a class="button" href="/topology">Topology</a>
        <a class="button" href="/connections">Connections</a>
        <a class="button" href="/billing">Billing</a>
    </div>
</div>
HTML;

                $script = <<<'JS'
function toMoney(v) {
    return Number(v || 0).toFixed(2);
}

function monthLabelFrom(dateObj, offset) {
    const d = new Date(dateObj.getFullYear(), dateObj.getMonth(), 1);
    d.setMonth(d.getMonth() + offset);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    return `${y}-${m}`;
}

function shortMonth(ym) {
    const [y, m] = String(ym || '').split('-');
    const dt = new Date(Number(y), Number(m) - 1, 1);
    return dt.toLocaleString(undefined, { month: 'short', year: '2-digit' });
}

function renderLineChart(elId, labels, series, isPercent = false) {
    const host = document.getElementById(elId);
    if (!host || !labels.length || !series.length) {
        if (host) {
            host.innerHTML = '';
        }
        return;
    }

    const width = 700;
    const height = 220;
    const padL = 50;
    const padR = 14;
    const padT = 16;
    const padB = 44;
    const chartW = width - padL - padR;
    const chartH = height - padT - padB;

    const allVals = series.flatMap(s => s.values.map(v => Number(v || 0)));
    let minV = Math.min(...allVals, 0);
    let maxV = Math.max(...allVals, 1);
    if (Math.abs(maxV - minV) < 0.0001) {
        maxV += 1;
        minV -= 1;
    }

    const xAt = (idx) => labels.length <= 1
        ? padL + chartW / 2
        : padL + (idx / (labels.length - 1)) * chartW;
    const yAt = (val) => {
        const ratio = (Number(val || 0) - minV) / (maxV - minV);
        return padT + (1 - ratio) * chartH;
    };

    const gridLines = Array.from({ length: 5 }, (_, i) => {
        const y = padT + (i / 4) * chartH;
        const v = maxV - ((i / 4) * (maxV - minV));
        const txt = isPercent ? `${v.toFixed(0)}%` : toMoney(v);
        return `
            <line x1="${padL}" y1="${y}" x2="${width - padR}" y2="${y}" stroke="#e2eaf4" stroke-width="1" />
            <text x="${padL - 6}" y="${y + 4}" text-anchor="end" class="chart-axis">${txt}</text>
        `;
    }).join('');

    const xLabels = labels.map((label, idx) => {
        const x = xAt(idx);
        return `<text x="${x}" y="${height - 14}" text-anchor="middle" class="chart-axis">${label}</text>`;
    }).join('');

    const lines = series.map(s => {
        const points = s.values.map((v, idx) => `${xAt(idx)},${yAt(v)}`).join(' ');
        const dots = s.values.map((v, idx) => {
            const x = xAt(idx);
            const y = yAt(v);
            const txt = isPercent ? `${Number(v || 0).toFixed(2)}%` : toMoney(v);
            return `<circle cx="${x}" cy="${y}" r="3.8" fill="${s.color}"><title>${labels[idx]}: ${txt}</title></circle>`;
        }).join('');
        return `<polyline points="${points}" fill="none" stroke="${s.color}" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>${dots}`;
    }).join('');

    const legend = `<div class="line-legend">${series.map(s => `<span><i style="background:${s.color}"></i>${s.name}</span>`).join('')}</div>`;

    host.innerHTML = `
        <svg class="line-chart-svg" viewBox="0 0 ${width} ${height}" role="img" aria-label="trend chart">
            ${gridLines}
            ${lines}
            ${xLabels}
        </svg>
        ${legend}
    `;
}

function getIncomeGrowthValues(rows, valueKey) {
    return rows.map((r, idx) => {
        const current = Number(r[valueKey] || 0);
        if (idx === 0) {
            return 0;
        }
        const prev = Number(rows[idx - 1][valueKey] || 0);
        return prev > 0 ? ((current - prev) / prev) * 100 : (current > 0 ? 100 : 0);
    });
}

function renderLegend(elId, rows) {
    const total = rows.reduce((s, r) => s + Number(r.value || 0), 0);
    const el = document.getElementById(elId);
    el.innerHTML = rows.map(r => {
        const pct = total > 0 ? ((Number(r.value || 0) / total) * 100) : 0;
        return `<div class="legend-item"><span class="dot" style="background:${r.color}"></span><span>${r.label}</span><strong>${toMoney(r.value)} (${pct.toFixed(1)}%)</strong></div>`;
    }).join('');
}

function renderMeters(elId, rows) {
    const el = document.getElementById(elId);
    el.innerHTML = rows.map(r => `
        <div class="meter-row">
            <div class="meter-top"><span>${r.label}</span><strong>${r.valueText}</strong></div>
            <div class="meter-track"><div class="meter-fill" style="width:${Math.max(0, Math.min(100, r.percent))}%;background:${r.color}"></div></div>
        </div>
    `).join('');
}

function getAnchorDate() {
    const val = String(document.getElementById('dashboard-anchor-month').value || '');
    if (!/^\d{4}-\d{2}$/.test(val)) {
        const now = new Date();
        return new Date(now.getFullYear(), now.getMonth(), 1);
    }

    const [y, m] = val.split('-').map(Number);
    return new Date(y, m - 1, 1);
}

function getSelectedYear() {
    const yearVal = Number(document.getElementById('dashboard-year').value || 0);
    if (yearVal >= 2000 && yearVal <= 2100) {
        return yearVal;
    }

    return new Date().getFullYear();
}

function quarterStartMonth(quarter) {
    const map = { Q1: 1, Q2: 4, Q3: 7, Q4: 10 };
    return map[quarter] || 1;
}

function getMonthsByPeriod(period) {
    const anchorDate = getAnchorDate();

    if (period === 'yearly') {
        const year = getSelectedYear();
        return Array.from({ length: 12 }, (_, i) => `${year}-${String(i + 1).padStart(2, '0')}`);
    }

    if (period === 'quarterly') {
        const year = getSelectedYear();
        const quarter = String(document.getElementById('dashboard-quarter').value || 'Q1');
        const start = quarterStartMonth(quarter);
        return Array.from({ length: 3 }, (_, i) => `${year}-${String(start + i).padStart(2, '0')}`);
    }

    return [monthLabelFrom(anchorDate, 0)];
}

function quarterMonthsText(quarter) {
    const labels = {
        Q1: 'Jan-Mar',
        Q2: 'Apr-Jun',
        Q3: 'Jul-Sep',
        Q4: 'Oct-Dec',
    };
    return labels[quarter] || 'Jan-Mar';
}

function updateScopeInputs() {
    const period = String(document.getElementById('dashboard-period').value || 'monthly');
    const monthInput = document.getElementById('dashboard-anchor-month');
    const yearInput = document.getElementById('dashboard-year');
    const quarterInput = document.getElementById('dashboard-quarter');

    if (period === 'monthly') {
        monthInput.style.display = '';
        yearInput.style.display = 'none';
        quarterInput.style.display = 'none';
        return;
    }

    if (period === 'yearly') {
        monthInput.style.display = 'none';
        yearInput.style.display = '';
        quarterInput.style.display = 'none';
        return;
    }

    monthInput.style.display = 'none';
    yearInput.style.display = '';
    quarterInput.style.display = '';
}

function aggregateSummaries(summaries) {
    const latest = summaries[summaries.length - 1] || {};
    const sum = (key) => summaries.reduce((acc, row) => acc + Number(row[key] || 0), 0);

    return {
        month: latest.month,
        customers_total: Number(latest.customers_total || 0),
        customers_active: Number(latest.customers_active || 0),
        total_due: Number(latest.total_due || 0),
        connections_total: sum('connections_total'),
        product_sold_income: sum('product_sold_income'),
        connection_charge_income: sum('connection_charge_income'),
        connection_total_income: sum('connection_total_income'),
        product_sold_cost: sum('product_sold_cost'),
        customer_bill_payment_total: sum('customer_bill_payment_total'),
        today_collection_amount: Number(latest.today_collection_amount || 0),
        week_collection_amount: Number(latest.week_collection_amount || 0),
        inventory_stock_quantity: Number(latest.inventory_stock_quantity || 0),
        inventory_sell_value: Number(latest.inventory_sell_value || 0),
        inventory_cost_value: Number(latest.inventory_cost_value || 0),
        company_expense_total: sum('company_expense_total'),
        total_income: sum('total_income'),
        net_income: sum('net_income'),
        low_stock_products: Number(latest.low_stock_products || 0),
    };
}

function scopeLabel(period, months) {
    if (!months.length) return '';
    if (period === 'yearly') {
        return `Yearly Scope: ${months[0].slice(0, 4)}`;
    }
    if (period === 'quarterly') {
        const quarter = String(document.getElementById('dashboard-quarter').value || 'Q1');
        return `Quarterly Scope: ${quarter} (${quarterMonthsText(quarter)}) ${months[0].slice(0, 4)}`;
    }
    return `Monthly Scope: ${shortMonth(months[0])}`;
}

async function loadDashboard() {
    const period = String(document.getElementById('dashboard-period').value || 'monthly');
    const months = getMonthsByPeriod(period);

    const summaries = await Promise.all(months.map(async (m) => {
        const res = await fetch('/api/dashboard/summary?month=' + encodeURIComponent(m));
        const payload = await res.json();
        return payload.data || { month: m };
    }));

    const d = aggregateSummaries(summaries);
    const avgIncome = months.length > 0 ? Number(d.total_income || 0) / months.length : 0;
    const cards = [
        ['Total Income', toMoney(d.total_income ?? 0)],
        ['Net Income', toMoney(d.net_income ?? 0)],
        ['Avg Income / Month', toMoney(avgIncome)],
        ['Today Collection', toMoney(d.today_collection_amount ?? 0)],
        ['This Week Collection', toMoney(d.week_collection_amount ?? 0)],
        ['Connections', d.connections_total ?? 0],
        ['Active Customers', d.customers_active ?? 0],
        ['Due Amount', toMoney(d.total_due ?? 0)],
        ['Low Stock Products', d.low_stock_products ?? 0],
        ['Inventory Qty', d.inventory_stock_quantity ?? 0],
        ['Inventory Sell Value', toMoney(d.inventory_sell_value ?? 0)],
        ['Inventory Cost', toMoney(d.inventory_cost_value ?? 0)],
        ['Company Expense', toMoney(d.company_expense_total ?? 0)],
    ];

    const el = document.getElementById('dashboard-cards');
    el.innerHTML = cards.map(c => `<div class="card stat"><div class="label">${c[0]}</div><div class="value">${c[1]}</div></div>`).join('');
    document.getElementById('dashboard-scope-label').textContent = scopeLabel(period, months);

    const shortLabels = months.map(shortMonth);
    renderLineChart('net-trend-line', shortLabels, [
        { name: 'Net Income', color: '#0f5cc8', values: summaries.map(r => Number(r.net_income || 0)) },
    ]);
    renderLineChart('customer-growth-line', shortLabels, [
        { name: 'Customers', color: '#0f766e', values: summaries.map(r => Number(r.customers_total || 0)) },
    ]);
    renderLineChart('income-growth-line', shortLabels, [
        { name: 'Income Growth %', color: '#ea580c', values: getIncomeGrowthValues(summaries, 'total_income') },
    ], true);
    renderLineChart('cashflow-line', shortLabels, [
        { name: 'Inflow', color: '#0f5cc8', values: summaries.map(r => Number(r.total_income || 0)) },
        { name: 'Outflow', color: '#ea580c', values: summaries.map(r => Number(r.company_expense_total || 0)) },
        { name: 'Net', color: '#0f766e', values: summaries.map(r => Number(r.net_income || 0)) },
    ]);
    document.getElementById('trend-month-label').textContent = months.length > 1
        ? `${shortMonth(months[0])} to ${shortMonth(months[months.length - 1])}`
        : shortMonth(months[0]);

    const composition = [
        { label: 'Products', value: Number(d.product_sold_income || 0), color: '#0f5cc8' },
        { label: 'Connection Charge', value: Number(d.connection_charge_income || 0), color: '#0f766e' },
        { label: 'Bill Payments', value: Number(d.customer_bill_payment_total || 0), color: '#ea580c' },
    ];
    const compTotal = composition.reduce((s, r) => s + r.value, 0);
    const p1 = compTotal > 0 ? (composition[0].value / compTotal) * 100 : 0;
    const p2 = compTotal > 0 ? (composition[1].value / compTotal) * 100 : 0;
    const donut = document.getElementById('revenue-donut');
    donut.style.background = `conic-gradient(${composition[0].color} 0 ${p1}%, ${composition[1].color} ${p1}% ${p1 + p2}%, ${composition[2].color} ${p1 + p2}% 100%)`;
    donut.innerHTML = `<span>${toMoney(compTotal)}</span>`;
    renderLegend('revenue-legend', composition);

    const totalCustomers = Number(d.customers_total || 0);
    const activeCustomers = Number(d.customers_active || 0);
    const dueAmount = Number(d.total_due || 0);
    const monthlyIncome = Number(d.total_income || 0);
    const dueRatio = monthlyIncome > 0 ? (dueAmount / monthlyIncome) * 100 : 0;
    renderMeters('customer-health', [
        {
            label: 'Active Customer Ratio',
            valueText: `${activeCustomers}/${totalCustomers || 0}`,
            percent: totalCustomers > 0 ? (activeCustomers / totalCustomers) * 100 : 0,
            color: '#0f766e',
        },
        {
            label: 'Due vs Monthly Income',
            valueText: `${toMoney(dueAmount)} / ${toMoney(monthlyIncome)}`,
            percent: dueRatio,
            color: '#ea580c',
        },
    ]);

    const todayCollection = Number(d.today_collection_amount || 0);
    const weekCollection = Number(d.week_collection_amount || 0);
    const monthPayments = Number(d.customer_bill_payment_total || 0);
    renderMeters('collection-pulse', [
        {
            label: 'Today Collection',
            valueText: toMoney(todayCollection),
            percent: weekCollection > 0 ? (todayCollection / weekCollection) * 100 : 0,
            color: '#0f5cc8',
        },
        {
            label: 'This Week Collection',
            valueText: toMoney(weekCollection),
            percent: monthPayments > 0 ? (weekCollection / monthPayments) * 100 : 0,
            color: '#0f766e',
        },
        {
            label: 'Month Payment Collection',
            valueText: toMoney(monthPayments),
            percent: Number(d.total_income || 0) > 0 ? (monthPayments / Number(d.total_income || 0)) * 100 : 0,
            color: '#ea580c',
        },
    ]);

    const inventorySell = Number(d.inventory_sell_value || 0);
    const inventoryCost = Number(d.inventory_cost_value || 0);
    const inventoryQty = Number(d.inventory_stock_quantity || 0);
    const marginPotential = Math.max(0, inventorySell - inventoryCost);
    renderMeters('inventory-snapshot', [
        {
            label: 'Inventory Quantity',
            valueText: `${inventoryQty} units`,
            percent: inventoryQty > 0 ? 100 : 0,
            color: '#0f5cc8',
        },
        {
            label: 'Sell vs Cost Value',
            valueText: `${toMoney(inventorySell)} / ${toMoney(inventoryCost)}`,
            percent: inventorySell > 0 ? (inventoryCost / inventorySell) * 100 : 0,
            color: '#7c3aed',
        },
        {
            label: 'Margin Potential',
            valueText: toMoney(marginPotential),
            percent: inventorySell > 0 ? (marginPotential / inventorySell) * 100 : 0,
            color: '#0f766e',
        },
    ]);

    const lowStock = Number(d.low_stock_products || 0);
    const productsTotal = Math.max(1, lowStock + 10);
    const expense = Number(d.company_expense_total || 0);
    const totalIncome = Number(d.total_income || 0);
    renderMeters('ops-risk', [
        {
            label: 'Low Stock Risk',
            valueText: `${lowStock} products`,
            percent: (lowStock / productsTotal) * 100,
            color: '#b91c1c',
        },
        {
            label: 'Expense Burden',
            valueText: `${toMoney(expense)} / ${toMoney(totalIncome)}`,
            percent: totalIncome > 0 ? (expense / totalIncome) * 100 : 0,
            color: '#7c3aed',
        },
    ]);
}

document.getElementById('dashboard-scope-form').addEventListener('submit', (e) => {
    e.preventDefault();
    loadDashboard();
});

document.getElementById('dashboard-period').addEventListener('change', () => {
    updateScopeInputs();
});

(() => {
    const now = new Date();
    document.getElementById('dashboard-anchor-month').value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    document.getElementById('dashboard-year').value = String(now.getFullYear());
    const q = Math.floor(now.getMonth() / 3) + 1;
    document.getElementById('dashboard-quarter').value = `Q${q}`;
    updateScopeInputs();
})();

loadDashboard();
JS;

                $this->render('Dashboard', 'ISP Admin Dashboard', 'Monitor operational and financial performance in one place.', $content, $script);
        }

        public function customers(Request $request): void
        {
                $content = <<<'HTML'
<div class="card">
    <h3>Add Customer</h3>
    <form id="customer-form" class="grid cols-4">
        <div class="field-wrap">
            <input id="customer-id-input" name="customer_id" placeholder="Customer ID" required />
            <div class="field-error" id="customer-id-error"></div>
        </div>
        <input name="full_name" placeholder="Full Name" required />
        <div class="field-wrap">
            <input id="customer-phone-input" name="phone" placeholder="Phone" />
            <div class="field-error" id="customer-phone-error"></div>
        </div>
        <input name="email" placeholder="Email" />
        <input name="nid" placeholder="NID" />
        <input name="address" placeholder="Address" />
        <select name="zone_id" id="zone-select"></select>
        <select name="area_id" id="area-select"></select>
        <select name="package_id" id="package-select"></select>
        <input name="monthly_bill_amount" type="number" step="0.01" placeholder="Monthly Bill" />
        <input name="due_amount" type="number" step="0.01" placeholder="Due Amount" />
        <input name="service_charge" type="number" step="0.01" placeholder="Service Charge" />
        <input name="connected_on" type="date" />
        <input name="technician" placeholder="Technician" />
        <select name="connection_status">
            <option value="connected">Connected</option>
            <option value="pending">Pending</option>
            <option value="cancelled">Cancelled</option>
        </select>
        <select name="line_source_id" id="line-source-select"></select>
        <select name="distribution_box_id" id="distribution-box-select"></select>
        <div class="section-title">Connection Equipment</div>
        <div class="equipment-toolbar">
            <input id="equipment-search" placeholder="Search equipment by name" />
        </div>
        <div id="equipment-checkboxes" class="equipment-grid"></div>
        <div class="actions-row">
            <button type="button" class="button button-secondary" id="customer-clear-items">Clear Equipment</button>
            <button type="submit" class="button" id="customer-submit-btn">Create Customer</button>
        </div>
    </form>
    <h4>Required Connection Equipment</h4>
    <table>
        <thead><tr><th>Product</th><th>Quantity</th></tr></thead>
        <tbody id="customer-items-tbody"><tr><td colspan="2">No equipment selected</td></tr></tbody>
    </table>
    <h4>Connection Cost Preview</h4>
    <table>
        <thead><tr><th>Products Total</th><th>Service Charge</th><th>Grand Total</th></tr></thead>
        <tbody id="customer-cost-tbody"><tr><td>0.00</td><td>0.00</td><td>0.00</td></tr></tbody>
    </table>
</div>
<div id="customer-confirm-modal" class="modal-backdrop" style="display:none;">
    <div class="modal-card">
        <h3>Confirm New Customer Connection</h3>
        <p class="chart-note" id="customer-confirm-note"></p>
        <div class="modal-meta" id="customer-confirm-meta"></div>
        <div class="actions-row" style="margin-top:12px;">
            <button type="button" class="button button-secondary" id="customer-confirm-cancel">Cancel</button>
            <button type="button" class="button" id="customer-confirm-approve">Approve and Create</button>
        </div>
    </div>
</div>
HTML;

                $script = <<<'JS'
function fillSelect(id, rows, emptyText = 'Select') {
    const sel = document.getElementById(id);
    sel.innerHTML = `<option value="">${emptyText}</option>` + rows.map(r => `<option value="${r._id}">${r.name || r.customer_id}</option>`).join('');
}

let productNameMap = {};
let customerConnectionItems = [];
let customerCostPreview = { products_total: 0, service_charge: 0, grand_total: 0 };
let productRowsCache = [];
let customerUniqueState = { customerIdOk: true, phoneOk: true };
let customerUniqueTimers = { customerId: null, phone: null };

function setFieldError(inputEl, errorEl, message) {
    if (!inputEl || !errorEl) {
        return;
    }

    errorEl.textContent = message || '';
    inputEl.classList.toggle('field-invalid', Boolean(message));
}

async function checkCustomerUnique(values) {
    const params = new URLSearchParams();
    if (values.customer_id) {
        params.set('customer_id', values.customer_id);
    }
    if (values.phone) {
        params.set('phone', values.phone);
    }

    if (!params.toString()) {
        return { customer_id_exists: false, phone_exists: false };
    }

    const res = await fetch('/api/customers/check-unique?' + params.toString());
    const payload = await res.json();
    return payload.data || { customer_id_exists: false, phone_exists: false };
}

function wireCustomerUniqueValidation() {
    const idInput = document.getElementById('customer-id-input');
    const idError = document.getElementById('customer-id-error');
    const phoneInput = document.getElementById('customer-phone-input');
    const phoneError = document.getElementById('customer-phone-error');

    const runIdCheck = async () => {
        const customerId = String(idInput?.value || '').trim();
        if (!customerId) {
            customerUniqueState.customerIdOk = true;
            setFieldError(idInput, idError, '');
            return;
        }

        const result = await checkCustomerUnique({ customer_id: customerId });
        const exists = Boolean(result.customer_id_exists);
        customerUniqueState.customerIdOk = !exists;
        setFieldError(idInput, idError, exists ? 'Customer ID already exists' : '');
    };

    const runPhoneCheck = async () => {
        const phone = String(phoneInput?.value || '').trim();
        if (!phone) {
            customerUniqueState.phoneOk = true;
            setFieldError(phoneInput, phoneError, '');
            return;
        }

        const result = await checkCustomerUnique({ phone });
        const exists = Boolean(result.phone_exists);
        customerUniqueState.phoneOk = !exists;
        setFieldError(phoneInput, phoneError, exists ? 'Phone number already exists' : '');
    };

    idInput?.addEventListener('input', () => {
        setFieldError(idInput, idError, '');
        clearTimeout(customerUniqueTimers.customerId);
        customerUniqueTimers.customerId = setTimeout(runIdCheck, 300);
    });
    idInput?.addEventListener('blur', runIdCheck);

    phoneInput?.addEventListener('input', () => {
        setFieldError(phoneInput, phoneError, '');
        clearTimeout(customerUniqueTimers.phone);
        customerUniqueTimers.phone = setTimeout(runPhoneCheck, 300);
    });
    phoneInput?.addEventListener('blur', runPhoneCheck);
}

async function validateCustomerUniqueBeforeSubmit() {
    const idInput = document.getElementById('customer-id-input');
    const idError = document.getElementById('customer-id-error');
    const phoneInput = document.getElementById('customer-phone-input');
    const phoneError = document.getElementById('customer-phone-error');

    const customerId = String(idInput?.value || '').trim();
    const phone = String(phoneInput?.value || '').trim();
    const result = await checkCustomerUnique({ customer_id: customerId, phone });

    const idExists = Boolean(result.customer_id_exists);
    const phoneExists = Boolean(result.phone_exists);

    customerUniqueState.customerIdOk = !idExists;
    customerUniqueState.phoneOk = !phoneExists;

    setFieldError(idInput, idError, idExists ? 'Customer ID already exists' : '');
    setFieldError(phoneInput, phoneError, phoneExists ? 'Phone number already exists' : '');

    return !idExists && !phoneExists;
}

async function loadLookups() {
    const [zones, packs, sources, boxes, products] = await Promise.all([
        fetch('/api/zones').then(r => r.json()),
        fetch('/api/packages').then(r => r.json()),
        fetch('/api/line-sources').then(r => r.json()),
        fetch('/api/distribution-boxes').then(r => r.json()),
        fetch('/api/products').then(r => r.json()),
    ]);

    const zoneRows = zones.data || [];
    const packageRows = packs.data || [];
    const sourceRows = sources.data || [];
    const boxRows = boxes.data || [];
    const productRows = products.data || [];
    productRowsCache = productRows;

    productNameMap = Object.fromEntries(productRows.map(r => [r._id, r.name || r._id]));

    const areaChunks = await Promise.all(zoneRows.map(z =>
        fetch('/api/areas?zone_id=' + encodeURIComponent(z._id))
            .then(r => r.json())
            .then(p => p.data || [])
    ));
    areaChunks.flat();

    fillSelect('zone-select', zoneRows, 'Select Zone');
    fillSelect('package-select', packageRows, 'Select Package');
    fillSelect('line-source-select', sourceRows, 'Select Line Source');
    fillSelect('distribution-box-select', boxRows, 'Select Distribution Box');
    renderEquipmentPicker(productRows);
    document.getElementById('area-select').innerHTML = '<option value="">Select Area</option>';
}

function renderEquipmentPicker(rows) {
    const wrap = document.getElementById('equipment-checkboxes');
    if (!rows.length) {
        wrap.innerHTML = '<div class="card">No products available for equipment selection.</div>';
        return;
    }

    wrap.innerHTML = rows.map(r => `
        <label class="equip-item" data-product-name="${String(r.name || r._id).toLowerCase()}">
            <input type="checkbox" class="equip-check" data-product-id="${r._id}" />
            <span class="equip-name">${r.name || r._id}</span>
            <input type="number" class="equip-qty" data-product-id="${r._id}" min="1" value="1" placeholder="Qty" />
        </label>
    `).join('');

    wrap.querySelectorAll('.equip-check').forEach(el => {
        el.addEventListener('change', rebuildItemsFromCheckboxes);
    });
    wrap.querySelectorAll('.equip-qty').forEach(el => {
        el.addEventListener('input', rebuildItemsFromCheckboxes);
    });

    const searchInput = document.getElementById('equipment-search');
    if (searchInput) {
        searchInput.value = '';
        searchInput.oninput = filterEquipmentPicker;
    }
}

function filterEquipmentPicker() {
    const keyword = String(document.getElementById('equipment-search')?.value || '').trim().toLowerCase();
    document.querySelectorAll('.equip-item').forEach(el => {
        const name = String(el.getAttribute('data-product-name') || '');
        el.style.display = !keyword || name.includes(keyword) ? 'grid' : 'none';
    });
}

function rebuildItemsFromCheckboxes() {
    const checks = Array.from(document.querySelectorAll('.equip-check'));
    checks.forEach(el => {
        const item = el.closest('.equip-item');
        if (!item) {
            return;
        }

        if (el.checked) {
            item.classList.add('equip-item-active');
        } else {
            item.classList.remove('equip-item-active');
        }
    });

    customerConnectionItems = checks
        .filter(el => el.checked)
        .map(el => {
            const productId = String(el.getAttribute('data-product-id') || '');
            const qtyInput = document.querySelector(`.equip-qty[data-product-id="${productId}"]`);
            const quantity = Number(qtyInput?.value || 0);
            return { product_id: productId, quantity };
        })
        .filter(item => item.product_id && item.quantity > 0);

    drawCustomerItems();
}

function drawCustomerItems() {
    const tbody = document.getElementById('customer-items-tbody');
    if (!customerConnectionItems.length) {
        tbody.innerHTML = '<tr><td colspan="2">No equipment selected</td></tr>';
        previewCustomerConnectionCost();
        return;
    }

    tbody.innerHTML = customerConnectionItems.map(i => `
        <tr>
            <td>${productNameMap[i.product_id] || i.product_id}</td>
            <td>${i.quantity}</td>
        </tr>
    `).join('');
    previewCustomerConnectionCost();
}

async function previewCustomerConnectionCost() {
    const serviceCharge = Number(document.querySelector('input[name="service_charge"]').value || 0);
    const payload = await fetch('/api/connections/preview-cost', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({items: customerConnectionItems, service_charge: serviceCharge}),
    }).then(r => r.json());

    const data = payload.data || {
        products_total: 0,
        service_charge: serviceCharge,
        grand_total: serviceCharge,
    };

    customerCostPreview = {
        products_total: Number(data.products_total || 0),
        service_charge: Number(data.service_charge || 0),
        grand_total: Number(data.grand_total || 0),
    };

    document.getElementById('customer-cost-tbody').innerHTML = `
        <tr>
            <td>${customerCostPreview.products_total.toFixed(2)}</td>
            <td>${customerCostPreview.service_charge.toFixed(2)}</td>
            <td>${customerCostPreview.grand_total.toFixed(2)}</td>
        </tr>
    `;
}

document.getElementById('customer-clear-items').addEventListener('click', () => {
    customerConnectionItems = [];
    document.querySelectorAll('.equip-check').forEach(el => {
        el.checked = false;
    });
    document.querySelectorAll('.equip-qty').forEach(el => {
        el.value = '1';
    });
    drawCustomerItems();
});

document.querySelector('input[name="service_charge"]').addEventListener('input', () => {
    previewCustomerConnectionCost();
});

document.getElementById('zone-select').addEventListener('change', async (e) => {
    const zoneId = e.target.value;
    if (!zoneId) {
        document.getElementById('area-select').innerHTML = '<option value="">Select Area</option>';
        return;
    }

    const payload = await fetch('/api/areas?zone_id=' + encodeURIComponent(zoneId)).then(r => r.json());
    fillSelect('area-select', payload.data || [], 'Select Area');
});

function resetCustomerForm() {
    const form = document.getElementById('customer-form');
    form.reset();
    customerConnectionItems = [];
    customerCostPreview = { products_total: 0, service_charge: 0, grand_total: 0 };
    renderEquipmentPicker(productRowsCache);
    drawCustomerItems();
    document.getElementById('customer-cost-tbody').innerHTML = '<tr><td>0.00</td><td>0.00</td><td>0.00</td></tr>';
    setFieldError(document.getElementById('customer-id-input'), document.getElementById('customer-id-error'), '');
    setFieldError(document.getElementById('customer-phone-input'), document.getElementById('customer-phone-error'), '');
}

function openCustomerConfirmDialog() {
    return new Promise((resolve) => {
        const modal = document.getElementById('customer-confirm-modal');
        const note = document.getElementById('customer-confirm-note');
        const meta = document.getElementById('customer-confirm-meta');
        const cancelBtn = document.getElementById('customer-confirm-cancel');
        const approveBtn = document.getElementById('customer-confirm-approve');

        note.textContent = 'Stock will be deducted after confirmation.';
        meta.innerHTML = `
            <p><strong>Products Total:</strong> ${customerCostPreview.products_total.toFixed(2)}</p>
            <p><strong>Service Charge:</strong> ${customerCostPreview.service_charge.toFixed(2)}</p>
            <p><strong>Grand Total:</strong> ${customerCostPreview.grand_total.toFixed(2)}</p>
        `;

        modal.style.display = 'flex';

        const close = (result) => {
            modal.style.display = 'none';
            cancelBtn.removeEventListener('click', onCancel);
            approveBtn.removeEventListener('click', onApprove);
            modal.removeEventListener('click', onBackdrop);
            resolve(result);
        };

        const onCancel = () => close(false);
        const onApprove = () => close(true);
        const onBackdrop = (evt) => {
            if (evt.target === modal) {
                close(false);
            }
        };

        cancelBtn.addEventListener('click', onCancel);
        approveBtn.addEventListener('click', onApprove);
        modal.addEventListener('click', onBackdrop);
    });
}

document.getElementById('customer-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target).entries());
    data.monthly_bill_amount = Number(data.monthly_bill_amount || 0);
    data.due_amount = Number(data.due_amount || 0);
    data.service_charge = Number(data.service_charge || 0);
    data.connection_items = customerConnectionItems;
    if (!data.connected_on) {
        data.connected_on = new Date().toISOString().slice(0, 10);
    }

    if (!customerConnectionItems.length) {
        alert('Please select at least one equipment item before confirming.');
        return;
    }

    const uniqueOk = await validateCustomerUniqueBeforeSubmit();
    if (!uniqueOk) {
        alert('Please fix duplicate customer details before submitting.');
        return;
    }

    await previewCustomerConnectionCost();
    const approved = await openCustomerConfirmDialog();
    if (!approved) {
        return;
    }

    const res = await fetch('/api/customers/with-connection', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data),
    });

    const payload = await res.json();
    if (payload.status === 'error' && typeof payload.message === 'string') {
        if (payload.message.toLowerCase().includes('customer_id already exists')) {
            setFieldError(document.getElementById('customer-id-input'), document.getElementById('customer-id-error'), 'Customer ID already exists');
        }
        if (payload.message.toLowerCase().includes('phone already exists')) {
            setFieldError(document.getElementById('customer-phone-input'), document.getElementById('customer-phone-error'), 'Phone number already exists');
        }
    }
    alert(payload.message || (payload.status === 'success' ? 'Created' : 'Failed'));
    if (payload.status === 'success') {
        resetCustomerForm();
    }
});

loadLookups().then(() => {
    wireCustomerUniqueValidation();
    drawCustomerItems();
});
JS;

                $this->render('Customers', 'Customer Management', 'Register and manage customers with zone-aware addressing.', $content, $script);
        }

        public function customerList(Request $request): void
        {
                $content = <<<'HTML'
<div class="card">
    <h3>Customer Directory</h3>
    <form id="customer-list-filter-form" class="grid cols-4">
        <input id="customer-list-search" placeholder="Search by ID, name, phone, email" />
        <select id="customer-list-status">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="pending_connection">Pending Connection</option>
            <option value="inactive">Inactive</option>
        </select>
        <button class="button" type="submit">Apply</button>
        <button class="button" type="button" id="customer-list-reset">Reset</button>
    </form>
</div>
<div class="card">
    <h3>Customers</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Customer ID</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Package</th>
                <th>Monthly</th>
                <th>Due</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="customer-list-tbody"></tbody>
    </table>
</div>
<div class="card" id="customer-list-detail-card">
    <h3>Customer Details</h3>
    <div id="customer-list-detail-content">Select a customer to view details.</div>
</div>
HTML;

                $script = <<<'JS'
let customerListRows = [];
let filteredCustomerRows = [];
let packageNameMap = {};
let productNameMap = {};

function money(value) {
    return Number(value || 0).toFixed(2);
}

function safeText(value) {
    return String(value || '').trim();
}

async function loadCustomerListLookups() {
    const [packages, products] = await Promise.all([
        fetch('/api/packages').then(r => r.json()),
        fetch('/api/products').then(r => r.json()),
    ]);

    packageNameMap = Object.fromEntries((packages.data || []).map(p => [p._id, p.name || p._id]));
    productNameMap = Object.fromEntries((products.data || []).map(p => [p._id, p.name || p._id]));
}

async function loadCustomerList() {
    const payload = await fetch('/api/customers').then(r => r.json());
    customerListRows = payload.data || [];
    applyCustomerListFilters();
}

function renderCustomerListTable(rows) {
    const tbody = document.getElementById('customer-list-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="10">No customer found for current filters.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map((row, idx) => `
        <tr>
            <td>${idx + 1}</td>
            <td>${safeText(row.customer_id)}</td>
            <td>${safeText(row.full_name)}</td>
            <td>${safeText(row.phone)}</td>
            <td>${safeText(row.email)}</td>
            <td>${safeText(packageNameMap[row.package_id] || row.package_id)}</td>
            <td>${money(row.monthly_bill_amount)}</td>
            <td>${money(row.due_amount)}</td>
            <td>${safeText(row.status)}</td>
            <td>
                <button class="button" type="button" onclick="viewCustomerListDetails(decodeURIComponent('${encodeURIComponent(row.customer_id || '')}'))">View</button>
                <a class="button" href="/print/customer-profile?customer_id=${encodeURIComponent(row.customer_id || '')}" target="_blank">Print</a>
            </td>
        </tr>
    `).join('');
}

function applyCustomerListFilters() {
    const search = safeText(document.getElementById('customer-list-search').value).toLowerCase();
    const status = safeText(document.getElementById('customer-list-status').value).toLowerCase();

    filteredCustomerRows = customerListRows.filter(row => {
        const fields = [row.customer_id, row.full_name, row.phone, row.email].map(v => safeText(v).toLowerCase());
        const statusValue = safeText(row.status).toLowerCase();
        const matchSearch = !search || fields.some(v => v.includes(search));
        const matchStatus = !status || statusValue === status;
        return matchSearch && matchStatus;
    });

    renderCustomerListTable(filteredCustomerRows);
}

function viewCustomerListDetails(customerId) {
    const row = customerListRows.find(c => String(c.customer_id) === String(customerId));
    const detail = document.getElementById('customer-list-detail-content');
    if (!row) {
        detail.textContent = 'Customer not found.';
        return;
    }

    const items = Array.isArray(row.connection_items) ? row.connection_items : [];
    const equipmentRows = items.length
        ? items.map(item => `
            <tr>
                <td>${safeText(productNameMap[item.product_id] || item.product_id)}</td>
                <td>${safeText(item.product_id)}</td>
                <td>${Number(item.quantity || 0)}</td>
            </tr>
        `).join('')
        : '<tr><td colspan="3">No equipment recorded</td></tr>';

    detail.innerHTML = `
        <div class="grid cols-2">
            <div>
                <h4>Identity</h4>
                <p><strong>Customer ID:</strong> ${safeText(row.customer_id)}</p>
                <p><strong>Full Name:</strong> ${safeText(row.full_name)}</p>
                <p><strong>NID:</strong> ${safeText(row.nid)}</p>
                <p><strong>Status:</strong> ${safeText(row.status)}</p>
            </div>
            <div>
                <h4>Contact and Billing</h4>
                <p><strong>Phone:</strong> ${safeText(row.phone)}</p>
                <p><strong>Email:</strong> ${safeText(row.email)}</p>
                <p><strong>Address:</strong> ${safeText(row.address)}</p>
                <p><strong>Package:</strong> ${safeText(packageNameMap[row.package_id] || row.package_id)}</p>
                <p><strong>Monthly Bill:</strong> ${money(row.monthly_bill_amount)}</p>
                <p><strong>Due:</strong> ${money(row.due_amount)}</p>
            </div>
        </div>
        <h4>Connection Equipment</h4>
        <table>
            <thead><tr><th>Product</th><th>Product ID</th><th>Qty</th></tr></thead>
            <tbody>${equipmentRows}</tbody>
        </table>
        <p style="margin-top:10px;">
            <a class="button" href="/print/customer-profile?customer_id=${encodeURIComponent(row.customer_id || '')}" target="_blank">Print Full Profile</a>
        </p>
    `;

    document.getElementById('customer-list-detail-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

document.getElementById('customer-list-filter-form').addEventListener('submit', (e) => {
    e.preventDefault();
    applyCustomerListFilters();
});

document.getElementById('customer-list-reset').addEventListener('click', () => {
    document.getElementById('customer-list-search').value = '';
    document.getElementById('customer-list-status').value = '';
    applyCustomerListFilters();
});

loadCustomerListLookups().then(loadCustomerList);
JS;

                $this->render('Customer List', 'Customer List', 'Review all customers, inspect full details, and print profile PDF-ready pages.', $content, $script);
        }

        public function topology(Request $request): void
        {
                $content = <<<'HTML'
<div class="grid cols-2">
    <div class="card">
        <h3>Create Zone</h3>
        <form id="zone-form" class="stack">
            <input name="id" id="zone-id" type="hidden" />
            <input name="name" placeholder="Zone Name" required />
            <input name="code" placeholder="Zone Code" />
            <button class="button" id="zone-submit-btn" type="submit">Create Zone</button>
            <button class="button" id="zone-cancel-btn" type="button" style="display:none;">Cancel Edit</button>
        </form>
    </div>
    <div class="card">
        <h3>Create Area</h3>
        <form id="area-form" class="stack">
            <input name="id" id="area-id" type="hidden" />
            <select name="zone_id" id="topology-zone"></select>
            <input name="name" placeholder="Area Name" required />
            <input name="code" placeholder="Area Code" />
            <button class="button" id="area-submit-btn" type="submit">Create Area</button>
            <button class="button" id="area-cancel-btn" type="button" style="display:none;">Cancel Edit</button>
        </form>
    </div>
</div>

<div class="grid cols-2">
    <div class="card">
        <h3>Create Line Source</h3>
        <form id="line-form" class="stack">
            <input name="id" id="line-id" type="hidden" />
            <input name="name" placeholder="Line Source Name" required />
            <input name="provider" placeholder="Provider" />
            <input name="capacity_mbps" placeholder="Capacity Mbps" type="number" />
            <button class="button" id="line-submit-btn" type="submit">Create Line Source</button>
            <button class="button" id="line-cancel-btn" type="button" style="display:none;">Cancel Edit</button>
        </form>
    </div>
    <div class="card">
        <h3>Create Distribution Box</h3>
        <form id="box-form" class="stack">
            <input name="id" id="box-id" type="hidden" />
            <select name="zone_id" id="box-zone"></select>
            <input name="name" placeholder="Box Name" required />
            <input name="code" placeholder="Box Code" />
            <input name="capacity_ports" placeholder="Capacity Ports" type="number" />
            <input name="used_ports" placeholder="Used Ports" type="number" />
            <input name="status" placeholder="Status" value="active" />
            <button class="button" id="box-submit-btn" type="submit">Create Distribution Box</button>
            <button class="button" id="box-cancel-btn" type="button" style="display:none;">Cancel Edit</button>
        </form>
    </div>
</div>

<div class="card">
    <h3>Zones</h3>
    <table>
        <thead><tr><th>Name</th><th>Code</th><th>Created At</th><th>Actions</th></tr></thead>
        <tbody id="zones-tbody"></tbody>
    </table>
</div>

<div class="card">
    <h3>Areas</h3>
    <table>
        <thead><tr><th>Zone</th><th>Area</th><th>Code</th><th>Created At</th><th>Actions</th></tr></thead>
        <tbody id="areas-tbody"></tbody>
    </table>
</div>

<div class="card">
    <h3>Line Sources</h3>
    <table>
        <thead><tr><th>Name</th><th>Provider</th><th>Capacity (Mbps)</th><th>Created At</th><th>Actions</th></tr></thead>
        <tbody id="line-sources-tbody"></tbody>
    </table>
</div>

<div class="card">
    <h3>Distribution Boxes</h3>
    <table>
        <thead><tr><th>Name</th><th>Zone</th><th>Line Source</th><th>Capacity</th><th>Used</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody id="boxes-tbody"></tbody>
    </table>
</div>
HTML;

                $script = <<<'JS'
const postJson = (url, data) => fetch(url, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)}).then(r => r.json());

let zoneRows = [];
let areaRows = [];
let sourceRows = [];
let boxRows = [];

async function loadTopology() {
    const [zones, sources, boxes] = await Promise.all([
        fetch('/api/zones').then(r => r.json()),
        fetch('/api/line-sources').then(r => r.json()),
        fetch('/api/distribution-boxes').then(r => r.json()),
    ]);

    const zRows = zones.data || [];
    zoneRows = zRows;
    const zoneMap = Object.fromEntries(zRows.map(z => [z._id, z.name]));
    const zoneOpts = '<option value="">Select Zone</option>' + zRows.map(z => `<option value="${z._id}">${z.name}</option>`).join('');
    document.getElementById('topology-zone').innerHTML = zoneOpts;
    document.getElementById('box-zone').innerHTML = zoneOpts;

    const areaChunks = await Promise.all(zRows.map(z =>
        fetch('/api/areas?zone_id=' + encodeURIComponent(z._id))
            .then(r => r.json())
            .then(p => p.data || [])
    ));
    const allAreas = areaChunks.flat();

    sourceRows = sources.data || [];
    const sourceMap = Object.fromEntries(sourceRows.map(s => [s._id, s.name]));
    boxRows = boxes.data || [];
    areaRows = allAreas;

    document.getElementById('zones-tbody').innerHTML = zRows.map(z => `
        <tr>
            <td>${z.name || ''}</td>
            <td>${z.code || ''}</td>
            <td>${z.created_at || ''}</td>
            <td>
                <button class="button" type="button" onclick="editZone('${z._id || ''}')">Edit</button>
                <button class="button" type="button" onclick="deleteZone('${z._id || ''}')">Delete</button>
            </td>
        </tr>
    `).join('');

    document.getElementById('areas-tbody').innerHTML = allAreas.map(a => `
        <tr>
            <td>${zoneMap[a.zone_id] || a.zone_id || ''}</td>
            <td>${a.name || ''}</td>
            <td>${a.code || ''}</td>
            <td>${a.created_at || ''}</td>
            <td>
                <button class="button" type="button" onclick="editArea('${a._id || ''}')">Edit</button>
                <button class="button" type="button" onclick="deleteArea('${a._id || ''}')">Delete</button>
            </td>
        </tr>
    `).join('');

    document.getElementById('line-sources-tbody').innerHTML = sourceRows.map(s => `
        <tr>
            <td>${s.name || ''}</td>
            <td>${s.provider || ''}</td>
            <td>${s.capacity_mbps || 0}</td>
            <td>${s.created_at || ''}</td>
            <td>
                <button class="button" type="button" onclick="editLineSource('${s._id || ''}')">Edit</button>
                <button class="button" type="button" onclick="deleteLineSource('${s._id || ''}')">Delete</button>
            </td>
        </tr>
    `).join('');

    document.getElementById('boxes-tbody').innerHTML = boxRows.map(b => `
        <tr>
            <td>${b.name || ''}</td>
            <td>${zoneMap[b.zone_id] || b.zone_id || ''}</td>
            <td>${sourceMap[b.line_source_id] || b.line_source_id || ''}</td>
            <td>${b.capacity_ports || 0}</td>
            <td>${b.used_ports || 0}</td>
            <td>${b.status || ''}</td>
            <td>
                <button class="button" type="button" onclick="editBox('${b._id || ''}')">Edit</button>
                <button class="button" type="button" onclick="deleteBox('${b._id || ''}')">Delete</button>
            </td>
        </tr>
    `).join('');
}

async function editZone(id) {
    const row = zoneRows.find(x => x._id === id);
    if (!row) return;
    const form = document.getElementById('zone-form');
    form.id.value = row._id || '';
    form.name.value = row.name || '';
    form.code.value = row.code || '';
    document.getElementById('zone-submit-btn').textContent = 'Update Zone';
    document.getElementById('zone-cancel-btn').style.display = 'inline-block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function deleteZone(id) {
    const payload = await postJson('/api/zones/delete', {id});
    alert(payload.message || payload.status || 'Deleted');
    loadTopology();
}

async function editArea(id) {
    const row = areaRows.find(x => x._id === id);
    if (!row) return;
    const form = document.getElementById('area-form');
    form.id.value = row._id || '';
    form.zone_id.value = row.zone_id || '';
    form.name.value = row.name || '';
    form.code.value = row.code || '';
    document.getElementById('area-submit-btn').textContent = 'Update Area';
    document.getElementById('area-cancel-btn').style.display = 'inline-block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function deleteArea(id) {
    const payload = await postJson('/api/areas/delete', {id});
    alert(payload.message || payload.status || 'Deleted');
    loadTopology();
}

async function editLineSource(id) {
    const row = sourceRows.find(x => x._id === id);
    if (!row) return;
    const form = document.getElementById('line-form');
    form.id.value = row._id || '';
    form.name.value = row.name || '';
    form.provider.value = row.provider || '';
    form.capacity_mbps.value = row.capacity_mbps || 0;
    document.getElementById('line-submit-btn').textContent = 'Update Line Source';
    document.getElementById('line-cancel-btn').style.display = 'inline-block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function deleteLineSource(id) {
    const payload = await postJson('/api/line-sources/delete', {id});
    alert(payload.message || payload.status || 'Deleted');
    loadTopology();
}

async function editBox(id) {
    const row = boxRows.find(x => x._id === id);
    if (!row) return;
    const form = document.getElementById('box-form');
    form.id.value = row._id || '';
    form.zone_id.value = row.zone_id || '';
    form.name.value = row.name || '';
    form.code.value = row.code || '';
    form.capacity_ports.value = row.capacity_ports || 0;
    form.used_ports.value = row.used_ports || 0;
    form.status.value = row.status || 'active';
    document.getElementById('box-submit-btn').textContent = 'Update Distribution Box';
    document.getElementById('box-cancel-btn').style.display = 'inline-block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function deleteBox(id) {
    const payload = await postJson('/api/distribution-boxes/delete', {id});
    alert(payload.message || payload.status || 'Deleted');
    loadTopology();
}

document.getElementById('zone-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target).entries());
    const endpoint = data.id ? '/api/zones/update' : '/api/zones';
    const payload = await postJson(endpoint, data);
    alert(payload.status === 'success' ? (data.id ? 'Zone updated' : 'Zone created') : payload.message);
    e.target.reset();
    document.getElementById('zone-submit-btn').textContent = 'Create Zone';
    document.getElementById('zone-cancel-btn').style.display = 'none';
    loadTopology();
});

document.getElementById('area-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target).entries());
    const endpoint = data.id ? '/api/areas/update' : '/api/areas';
    const payload = await postJson(endpoint, data);
    alert(payload.status === 'success' ? (data.id ? 'Area updated' : 'Area created') : payload.message);
    e.target.reset();
    document.getElementById('area-submit-btn').textContent = 'Create Area';
    document.getElementById('area-cancel-btn').style.display = 'none';
    loadTopology();
});

document.getElementById('line-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target).entries());
    const endpoint = data.id ? '/api/line-sources/update' : '/api/line-sources';
    data.capacity_mbps = Number(data.capacity_mbps || 0);
    const payload = await postJson(endpoint, data);
    alert(payload.status === 'success' ? (data.id ? 'Line source updated' : 'Line source created') : payload.message);
    e.target.reset();
    document.getElementById('line-submit-btn').textContent = 'Create Line Source';
    document.getElementById('line-cancel-btn').style.display = 'none';
    loadTopology();
});

document.getElementById('box-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target).entries());
    const endpoint = data.id ? '/api/distribution-boxes/update' : '/api/distribution-boxes';
    data.capacity_ports = Number(data.capacity_ports || 0);
    data.used_ports = Number(data.used_ports || 0);
    const payload = await postJson(endpoint, data);
    alert(payload.status === 'success' ? (data.id ? 'Distribution box updated' : 'Distribution box created') : payload.message);
    e.target.reset();
    document.getElementById('box-submit-btn').textContent = 'Create Distribution Box';
    document.getElementById('box-cancel-btn').style.display = 'none';
    loadTopology();
});

document.getElementById('zone-cancel-btn').addEventListener('click', () => {
    const form = document.getElementById('zone-form');
    form.reset();
    form.id.value = '';
    document.getElementById('zone-submit-btn').textContent = 'Create Zone';
    document.getElementById('zone-cancel-btn').style.display = 'none';
});

document.getElementById('area-cancel-btn').addEventListener('click', () => {
    const form = document.getElementById('area-form');
    form.reset();
    form.id.value = '';
    document.getElementById('area-submit-btn').textContent = 'Create Area';
    document.getElementById('area-cancel-btn').style.display = 'none';
});

document.getElementById('line-cancel-btn').addEventListener('click', () => {
    const form = document.getElementById('line-form');
    form.reset();
    form.id.value = '';
    document.getElementById('line-submit-btn').textContent = 'Create Line Source';
    document.getElementById('line-cancel-btn').style.display = 'none';
});

document.getElementById('box-cancel-btn').addEventListener('click', () => {
    const form = document.getElementById('box-form');
    form.reset();
    form.id.value = '';
    form.status.value = 'active';
    document.getElementById('box-submit-btn').textContent = 'Create Distribution Box';
    document.getElementById('box-cancel-btn').style.display = 'none';
});

loadTopology();
JS;

                $this->render('Topology', 'Topology Management', 'Manage zones, areas, line sources, and distribution boxes.', $content, $script);
        }

        public function packages(Request $request): void
        {
                $content = <<<'HTML'
<div class="card">
    <h3>Create Package</h3>
    <form id="package-form" class="grid cols-4">
        <input name="id" id="package-id" type="hidden" />
        <input name="name" placeholder="Package Name" required />
        <input name="speed_mbps" type="number" placeholder="Speed Mbps" />
        <input name="monthly_price" type="number" step="0.01" placeholder="Monthly Price" />
        <input name="installation_charge" type="number" step="0.01" placeholder="Installation Charge" />
        <input name="status" placeholder="Status" value="active" />
        <button type="submit" class="button" id="package-submit-btn">Create Package</button>
        <button type="button" class="button" id="package-cancel-btn" style="display:none;">Cancel Edit</button>
    </form>
</div>
<div class="card">
    <h3>Package List</h3>
    <table><thead><tr><th>Name</th><th>Speed</th><th>Monthly</th><th>Install</th><th>Status</th><th>Actions</th></tr></thead><tbody id="package-tbody"></tbody></table>
</div>
HTML;

                $script = <<<'JS'
async function loadPackages() {
    const payload = await fetch('/api/packages').then(r => r.json());
    const rows = payload.data || [];
    document.getElementById('package-tbody').innerHTML = rows.map(r => `<tr><td>${r.name || ''}</td><td>${r.speed_mbps || 0}</td><td>${r.monthly_price || 0}</td><td>${r.installation_charge || 0}</td><td>${r.status || ''}</td><td><button class="button" type="button" onclick="editPackage('${r._id || ''}')">Edit</button> <button class="button" type="button" onclick="deletePackage('${r._id || ''}')">Delete</button></td></tr>`).join('');
}

async function editPackage(id) {
    const res = await fetch('/api/packages').then(r => r.json());
    const row = (res.data || []).find(x => x._id === id);
    if (!row) return;

    const form = document.getElementById('package-form');
    form.id.value = row._id || '';
    form.name.value = row.name || '';
    form.speed_mbps.value = row.speed_mbps || 0;
    form.monthly_price.value = row.monthly_price || 0;
    form.installation_charge.value = row.installation_charge || 0;
    form.status.value = row.status || 'active';

    document.getElementById('package-submit-btn').textContent = 'Update Package';
    document.getElementById('package-cancel-btn').style.display = 'inline-block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function deletePackage(id) {
    const payload = await fetch('/api/packages/delete', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({id}),
    }).then(r => r.json());

    alert(payload.message || payload.status || 'Deleted');
    loadPackages();
}

function resetPackageForm() {
    const form = document.getElementById('package-form');
    form.reset();
    form.id.value = '';
    form.status.value = 'active';
    document.getElementById('package-submit-btn').textContent = 'Create Package';
    document.getElementById('package-cancel-btn').style.display = 'none';
}

document.getElementById('package-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target).entries());
    const isEdit = Boolean(data.id);
    data.speed_mbps = Number(data.speed_mbps || 0);
    data.monthly_price = Number(data.monthly_price || 0);
    data.installation_charge = Number(data.installation_charge || 0);
    const endpoint = isEdit ? '/api/packages/update' : '/api/packages';
    const payload = await fetch(endpoint, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)}).then(r => r.json());
    alert(payload.status === 'success' ? (isEdit ? 'Package updated' : 'Package created') : payload.message);
    if (payload.status === 'success') { resetPackageForm(); loadPackages(); }
});

document.getElementById('package-cancel-btn').addEventListener('click', resetPackageForm);

loadPackages();
JS;

                $this->render('Packages', 'Package Management', 'Create and review internet service packages.', $content, $script);
        }

        public function products(Request $request): void
        {
                $content = <<<'HTML'
<div class="card">
    <h3>Create Product</h3>
    <form id="product-form" class="grid cols-4">
        <input name="id" id="product-id" type="hidden" />
        <input name="sku" placeholder="SKU" />
        <input name="name" placeholder="Product Name" required />
        <input name="category" placeholder="Category" />
        <input name="price" type="number" step="0.01" placeholder="Price" />
        <input name="cost_price" type="number" step="0.01" placeholder="Cost Price" />
        <input name="stock" type="number" placeholder="Stock" />
        <input name="reorder_level" type="number" placeholder="Reorder Level" />
        <button type="submit" class="button" id="product-submit-btn">Create Product</button>
        <button type="button" class="button" id="product-cancel-btn" style="display:none;">Cancel Edit</button>
    </form>
</div>
<div class="card">
    <h3>Product List</h3>
    <table><thead><tr><th>SKU</th><th>Name</th><th>Category</th><th>Sell Price</th><th>Cost Price</th><th>Stock</th><th>Reorder</th><th>Actions</th></tr></thead><tbody id="product-tbody"></tbody></table>
</div>
HTML;

                $script = <<<'JS'
async function loadProducts() {
    const payload = await fetch('/api/products').then(r => r.json());
    const rows = payload.data || [];
    document.getElementById('product-tbody').innerHTML = rows.map(r => `<tr><td>${r.sku || ''}</td><td>${r.name || ''}</td><td>${r.category || ''}</td><td>${r.price || 0}</td><td>${r.cost_price || 0}</td><td>${r.stock || 0}</td><td>${r.reorder_level || 0}</td><td><button class="button" type="button" onclick="editProduct('${r._id || ''}')">Edit</button> <button class="button" type="button" onclick="deleteProduct('${r._id || ''}')">Delete</button></td></tr>`).join('');
}

async function editProduct(id) {
    const res = await fetch('/api/products').then(r => r.json());
    const row = (res.data || []).find(x => x._id === id);
    if (!row) return;

    const form = document.getElementById('product-form');
    form.id.value = row._id || '';
    form.sku.value = row.sku || '';
    form.name.value = row.name || '';
    form.category.value = row.category || '';
    form.price.value = row.price || 0;
    form.cost_price.value = row.cost_price || 0;
    form.stock.value = row.stock || 0;
    form.reorder_level.value = row.reorder_level || 0;

    document.getElementById('product-submit-btn').textContent = 'Update Product';
    document.getElementById('product-cancel-btn').style.display = 'inline-block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function deleteProduct(id) {
    const payload = await fetch('/api/products/delete', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({id}),
    }).then(r => r.json());

    alert(payload.message || payload.status || 'Deleted');
    loadProducts();
}

function resetProductForm() {
    const form = document.getElementById('product-form');
    form.reset();
    form.id.value = '';
    document.getElementById('product-submit-btn').textContent = 'Create Product';
    document.getElementById('product-cancel-btn').style.display = 'none';
}

document.getElementById('product-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target).entries());
    const isEdit = Boolean(data.id);
    data.price = Number(data.price || 0);
    data.cost_price = Number(data.cost_price || 0);
    data.stock = Number(data.stock || 0);
    data.reorder_level = Number(data.reorder_level || 0);
    const endpoint = isEdit ? '/api/products/update' : '/api/products';
    const payload = await fetch(endpoint, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)}).then(r => r.json());
    alert(payload.status === 'success' ? (isEdit ? 'Product updated' : 'Product created') : payload.message);
    if (payload.status === 'success') { resetProductForm(); loadProducts(); }
});

document.getElementById('product-cancel-btn').addEventListener('click', resetProductForm);

loadProducts();
JS;

                $this->render('Products', 'Product Management', 'Manage install items, stock, and product pricing.', $content, $script);
        }

        public function connections(Request $request): void
        {
                $content = <<<'HTML'
<div class="card">
    <h3>Connection Overview</h3>
    <div class="grid cols-4" id="connection-kpis">
        <div class="card stat"><div class="label">Total Connections</div><div class="value" id="kpi-total">0</div></div>
        <div class="card stat"><div class="label">Connected</div><div class="value" id="kpi-connected">0</div></div>
        <div class="card stat"><div class="label">Pending</div><div class="value" id="kpi-pending">0</div></div>
        <div class="card stat"><div class="label">Revenue (Filtered)</div><div class="value" id="kpi-revenue">0.00</div></div>
    </div>
</div>
<div class="card">
    <h3>Search and Filters</h3>
    <form id="connection-filter-form" class="grid cols-4">
        <input id="connection-search" placeholder="Search by customer, phone, technician" />
        <select id="connection-filter-status">
            <option value="">All Status</option>
            <option value="connected">Connected</option>
            <option value="pending">Pending</option>
            <option value="cancelled">Cancelled</option>
        </select>
        <input id="connection-filter-from" type="date" />
        <input id="connection-filter-to" type="date" />
        <button class="button" type="submit">Apply Filters</button>
        <button class="button" type="button" id="connection-filter-reset">Reset Filters</button>
        <button class="button" type="button" id="connection-export-csv">Export CSV</button>
    </form>
</div>
<div class="card">
    <h3>Connections</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Customer ID</th>
                <th>Customer Name</th>
                <th>Phone</th>
                <th>Technician</th>
                <th>Line Source</th>
                <th>Box</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="connection-tbody"></tbody>
    </table>
</div>
<div class="card" id="connection-detail-card">
    <h3>Connection Details</h3>
    <div id="connection-detail-content">Select a connection record to view full details.</div>
</div>
HTML;

                $script = <<<'JS'
let productMap = {};
let sourceMap = {};
let boxMap = {};
let customerMap = {};
let allConnections = [];
let filteredConnections = [];

function formatMoney(value) {
    return Number(value || 0).toFixed(2);
}

function toComparableDate(value) {
    if (!value) return '';
    return String(value).slice(0, 10);
}

function customerNameById(customerId) {
    return customerMap[customerId]?.full_name || '';
}

function customerPhoneById(customerId) {
    return customerMap[customerId]?.phone || '';
}

async function loadConnectionLookups() {
    const [sources, boxes, products, customers] = await Promise.all([
        fetch('/api/line-sources').then(r => r.json()),
        fetch('/api/distribution-boxes').then(r => r.json()),
        fetch('/api/products').then(r => r.json()),
        fetch('/api/customers').then(r => r.json()),
    ]);

    const productRows = products.data || [];
    const sourceRows = sources.data || [];
    const boxRows = boxes.data || [];
    const customerRows = customers.data || [];

    productMap = Object.fromEntries(productRows.map(p => [p._id, p.name || p._id]));
    sourceMap = Object.fromEntries(sourceRows.map(s => [s._id, s.name || s._id]));
    boxMap = Object.fromEntries(boxRows.map(b => [b._id, b.name || b._id]));
    customerMap = Object.fromEntries(customerRows.map(c => [c.customer_id, c]));
}

async function loadConnections() {
    const payload = await fetch('/api/connections').then(r => r.json()).catch(() => ({ status: 'error', data: [] }));
    allConnections = payload.data || [];
    applyConnectionFilters();
}

function renderConnectionStats(rows) {
    const total = rows.length;
    const connected = rows.filter(r => String(r.status || '').toLowerCase() === 'connected').length;
    const pending = rows.filter(r => String(r.status || '').toLowerCase() === 'pending').length;
    const revenue = rows.reduce((sum, r) => sum + Number(r.grand_total || 0), 0);

    document.getElementById('kpi-total').textContent = String(total);
    document.getElementById('kpi-connected').textContent = String(connected);
    document.getElementById('kpi-pending').textContent = String(pending);
    document.getElementById('kpi-revenue').textContent = formatMoney(revenue);
}

function renderConnectionsTable(rows) {
    const tbody = document.getElementById('connection-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="10">No connection records match current filters.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map((r, idx) => {
        const customerName = customerNameById(r.customer_id);
        const customerPhone = customerPhoneById(r.customer_id);

        return `
        <tr>
            <td>${idx + 1}</td>
            <td>${r.customer_id || ''}</td>
            <td>${customerName}</td>
            <td>${customerPhone}</td>
            <td>${r.technician || ''}</td>
            <td>${sourceMap[r.line_source_id] || r.line_source_id || ''}</td>
            <td>${boxMap[r.distribution_box_id] || r.distribution_box_id || ''}</td>
            <td>${r.status || ''}</td>
            <td>${r.connected_on || ''}</td>
            <td>
                <button class="button" type="button" onclick="viewConnectionDetails('${r._id || ''}')">View</button>
                <a class="button" href="/print/connection-summary?id=${r._id}" target="_blank">Print</a>
                <button class="button" type="button" onclick="deleteConnection('${r._id || ''}')">Delete</button>
            </td>
        </tr>
    `;
    }).join('');
}

function applyConnectionFilters() {
    const search = String(document.getElementById('connection-search').value || '').trim().toLowerCase();
    const status = String(document.getElementById('connection-filter-status').value || '').trim().toLowerCase();
    const from = toComparableDate(document.getElementById('connection-filter-from').value);
    const to = toComparableDate(document.getElementById('connection-filter-to').value);

    filteredConnections = allConnections.filter(row => {
        const rowStatus = String(row.status || '').toLowerCase();
        const rowDate = toComparableDate(row.connected_on);
        const customerName = customerNameById(row.customer_id).toLowerCase();
        const customerPhone = customerPhoneById(row.customer_id).toLowerCase();
        const technician = String(row.technician || '').toLowerCase();
        const customerId = String(row.customer_id || '').toLowerCase();

        const matchesSearch = !search || [customerId, customerName, customerPhone, technician].some(v => v.includes(search));
        const matchesStatus = !status || rowStatus === status;
        const matchesFrom = !from || (rowDate !== '' && rowDate >= from);
        const matchesTo = !to || (rowDate !== '' && rowDate <= to);

        return matchesSearch && matchesStatus && matchesFrom && matchesTo;
    });

    renderConnectionStats(filteredConnections);
    renderConnectionsTable(filteredConnections);
}

function viewConnectionDetails(id) {
    const row = allConnections.find(x => x._id === id);
    const holder = document.getElementById('connection-detail-content');
    if (!row) {
        holder.textContent = 'Connection not found.';
        return;
    }

    const customer = customerMap[row.customer_id] || {};
    const items = Array.isArray(row.items) ? row.items : [];
    const itemsHtml = items.length
        ? items.map(item => `
            <tr>
                <td>${productMap[item.product_id] || item.product_id || ''}</td>
                <td>${item.quantity || 0}</td>
                <td>${formatMoney(item.unit_price || 0)}</td>
                <td>${formatMoney(item.line_total || 0)}</td>
            </tr>
        `).join('')
        : '<tr><td colspan="4">No items recorded</td></tr>';

    holder.innerHTML = `
        <div class="grid cols-2">
            <div>
                <h4>Customer Profile</h4>
                <p><strong>Customer ID:</strong> ${row.customer_id || ''}</p>
                <p><strong>Customer Name:</strong> ${customer.full_name || ''}</p>
                <p><strong>Phone:</strong> ${customer.phone || ''}</p>
                <p><strong>Address:</strong> ${customer.address || ''}</p>
            </div>
            <div>
                <h4>Connection Profile</h4>
                <p><strong>Technician:</strong> ${row.technician || ''}</p>
                <p><strong>Line Source:</strong> ${sourceMap[row.line_source_id] || row.line_source_id || ''}</p>
                <p><strong>Distribution Box:</strong> ${boxMap[row.distribution_box_id] || row.distribution_box_id || ''}</p>
                <p><strong>Status:</strong> ${row.status || ''}</p>
                <p><strong>Connected On:</strong> ${row.connected_on || ''}</p>
            </div>
        </div>
        <h4>Equipment Breakdown</h4>
        <table>
            <thead><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Line Total</th></tr></thead>
            <tbody>${itemsHtml}</tbody>
        </table>
        <h4>Financial Summary</h4>
        <p><strong>Products Total:</strong> ${formatMoney(row.products_total || 0)}</p>
        <p><strong>Service Charge:</strong> ${formatMoney(row.service_charge || 0)}</p>
        <p><strong>Grand Total:</strong> ${formatMoney(row.grand_total || 0)}</p>
    `;

    document.getElementById('connection-detail-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function deleteConnection(id) {
    const payload = await fetch('/api/connections/delete', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({id}),
    }).then(r => r.json());

    alert(payload.message || payload.status || 'Deleted');
    loadConnections();
}

document.getElementById('connection-filter-form').addEventListener('submit', (e) => {
    e.preventDefault();
    applyConnectionFilters();
});

document.getElementById('connection-filter-reset').addEventListener('click', () => {
    document.getElementById('connection-search').value = '';
    document.getElementById('connection-filter-status').value = '';
    document.getElementById('connection-filter-from').value = '';
    document.getElementById('connection-filter-to').value = '';
    applyConnectionFilters();
});

document.getElementById('connection-export-csv').addEventListener('click', () => {
    const header = [
        'connection_id', 'customer_id', 'customer_name', 'phone', 'technician',
        'line_source', 'distribution_box', 'status', 'connected_on',
        'products_total', 'service_charge', 'grand_total', 'item_count'
    ];

    const rows = filteredConnections.map(r => {
        const itemCount = Array.isArray(r.items) ? r.items.reduce((sum, item) => sum + Number(item.quantity || 0), 0) : 0;
        return [
            r._id || '',
            r.customer_id || '',
            customerNameById(r.customer_id),
            customerPhoneById(r.customer_id),
            r.technician || '',
            sourceMap[r.line_source_id] || r.line_source_id || '',
            boxMap[r.distribution_box_id] || r.distribution_box_id || '',
            r.status || '',
            r.connected_on || '',
            formatMoney(r.products_total || 0),
            formatMoney(r.service_charge || 0),
            formatMoney(r.grand_total || 0),
            itemCount,
        ];
    });

    const csv = [header, ...rows]
        .map(cols => cols.map(v => '"' + String(v).replace(/"/g, '""') + '"').join(','))
        .join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'connections.csv';
    a.click();
    URL.revokeObjectURL(url);
});

loadConnectionLookups().then(() => {
    loadConnections();
});
JS;

                $this->render('Connections', 'Connection Management', 'Review complete connection records with rich filters, financial totals, and detailed diagnostics.', $content, $script);
        }

        public function billing(Request $request): void
        {
                $content = <<<'HTML'
<div class="grid cols-2">
    <div class="card">
        <h3>Billing Filters</h3>
        <form id="billing-filter-form" class="stack">
            <input id="billing-month" type="month" />
            <input id="billing-customer-id" placeholder="Customer ID (optional for payment history)" />
            <button class="button" type="submit">Refresh Billing Data</button>
        </form>
    </div>
    <div class="card">
        <h3>Post Customer Payment</h3>
        <p>Use this for monthly collection. Bill status and due will update automatically.</p>
        <form id="payment-form" class="stack">
            <input name="customer_id" placeholder="Customer ID" required />
            <input name="bill_month" id="payment-bill-month" type="month" />
            <input name="amount" type="number" step="0.01" placeholder="Amount" required />
            <select name="method">
                <option value="cash">Cash</option>
                <option value="bkash">bKash</option>
                <option value="nagad">Nagad</option>
                <option value="rocket">Rocket</option>
                <option value="upay">Upay</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="card">Card</option>
                <option value="cheque">Cheque</option>
                <option value="online_gateway">Online Gateway</option>
                <option value="other">Other</option>
            </select>
            <button class="button" type="submit">Post Payment</button>
        </form>
    </div>
</div>

<div class="card">
    <h3>Monthly Bill Status</h3>
    <table>
        <thead><tr><th>Customer</th><th>Month</th><th>Total</th><th>Paid</th><th>Due</th><th>Status</th></tr></thead>
        <tbody id="bill-tbody"></tbody>
    </table>
</div>

<div class="card">
    <h3>Payment History</h3>
    <table>
        <thead><tr><th>Customer</th><th>Bill Month</th><th>Amount</th><th>Method</th></tr></thead>
        <tbody id="payments-tbody"></tbody>
    </table>
</div>
HTML;

                $script = <<<'JS'
let customerNameMap = {};

function customerLabel(customerId) {
    const name = customerNameMap[customerId] || '';
    return name ? (customerId + ' - ' + name) : (customerId || '');
}

async function loadCustomerMap() {
    const payload = await fetch('/api/customers').then(r => r.json());
    const rows = payload.data || [];
    customerNameMap = Object.fromEntries(rows.map(r => [r.customer_id, r.full_name || '']));
}

async function loadBills() {
    const month = document.getElementById('billing-month').value || new Date().toISOString().slice(0,7);
    const payload = await fetch('/api/bills?month=' + month).then(r => r.json());
    const rows = payload.data || [];
    document.getElementById('bill-tbody').innerHTML = rows.map(r => `<tr><td>${customerLabel(r.customer_id)}</td><td>${r.billing_month || ''}</td><td>${r.total_bill || 0}</td><td>${r.paid_amount || 0}</td><td>${r.due_amount || 0}</td><td>${r.status || ''}</td></tr>`).join('');
}

async function loadPayments() {
    const customerId = document.getElementById('billing-customer-id').value.trim();
    const q = customerId ? ('?customer_id=' + encodeURIComponent(customerId)) : '';
    const payload = await fetch('/api/payments' + q).then(r => r.json());
    const rows = payload.data || [];
    document.getElementById('payments-tbody').innerHTML = rows.map(r => `<tr><td>${customerLabel(r.customer_id)}</td><td>${r.bill_month || ''}</td><td>${r.amount || 0}</td><td>${r.method || ''}</td></tr>`).join('');
}

document.getElementById('payment-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target).entries());
    if (!data.bill_month) {
        data.bill_month = new Date().toISOString().slice(0,7);
    }
    data.amount = Number(data.amount || 0);
    const payload = await fetch('/api/payments', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)}).then(r => r.json());
    alert(payload.message || payload.status);
    if (payload.status === 'success') {
        const monthValue = document.getElementById('billing-month').value;
        e.target.reset();
        document.getElementById('payment-bill-month').value = monthValue || new Date().toISOString().slice(0,7);
        loadBills();
        loadPayments();
    }
});

document.getElementById('billing-filter-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const month = document.getElementById('billing-month').value || new Date().toISOString().slice(0,7);
    document.getElementById('payment-bill-month').value = month;
    await loadBills();
    await loadPayments();
});

document.getElementById('billing-month').value = new Date().toISOString().slice(0,7);
document.getElementById('payment-bill-month').value = new Date().toISOString().slice(0,7);

loadCustomerMap().then(async () => {
    await loadBills();
    await loadPayments();
});
JS;

                $this->render('Billing', 'Billing and Payments', 'Generate monthly bills and record collections.', $content, $script);
        }

        public function finance(Request $request): void
        {
                $content = <<<'HTML'
<div class="grid cols-2">
    <div class="card">
        <h3>Add Income</h3>
        <form id="income-form" class="stack">
            <input name="date" type="date" />
            <input name="source" placeholder="Source" />
            <input name="category" placeholder="Category" />
            <input name="amount" type="number" step="0.01" placeholder="Amount" required />
            <input name="note" placeholder="Note" />
            <button class="button" type="submit">Save Income</button>
        </form>
    </div>
    <div class="card">
        <h3>Add Expense</h3>
        <form id="expense-form" class="stack">
            <input name="date" type="date" />
            <input name="category" placeholder="Category" />
            <input name="amount" type="number" step="0.01" placeholder="Amount" required />
            <input name="note" placeholder="Note" />
            <button class="button" type="submit">Save Expense</button>
        </form>
    </div>
</div>
<div class="card"><h3>Monthly Summary</h3><pre id="finance-summary"></pre></div>
HTML;

                $script = <<<'JS'
const monthNow = new Date().toISOString().slice(0,7);

async function refreshSummary() {
    const payload = await fetch('/api/finance/summary?month=' + monthNow).then(r => r.json());
    document.getElementById('finance-summary').textContent = JSON.stringify(payload.data || {}, null, 2);
}

document.getElementById('income-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target).entries());
    data.amount = Number(data.amount || 0);
    const payload = await fetch('/api/finance/income', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)}).then(r => r.json());
    alert(payload.status === 'success' ? 'Income saved' : payload.message);
    if (payload.status === 'success') { e.target.reset(); refreshSummary(); }
});

document.getElementById('expense-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target).entries());
    data.amount = Number(data.amount || 0);
    const payload = await fetch('/api/finance/expenses', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)}).then(r => r.json());
    alert(payload.status === 'success' ? 'Expense saved' : payload.message);
    if (payload.status === 'success') { e.target.reset(); refreshSummary(); }
});

refreshSummary();
JS;

                $this->render('Finance', 'Income and Expense', 'Maintain business-level income and expense records.', $content, $script);
        }

        public function reports(Request $request): void
        {
                $content = <<<'HTML'
<div class="card">
    <h3>Enterprise Report Center</h3>
    <form id="report-form" class="grid cols-4">
        <input id="report-month" type="month" value="" />
        <button class="button" type="submit">Refresh Snapshot</button>
        <div id="report-scope" class="chart-note" style="align-self:center;"></div>
    </form>
</div>
<div class="card">
    <h3>Reporting Snapshot</h3>
    <div class="grid cols-4" id="report-kpi-cards"></div>
</div>
<div class="grid cols-2">
    <div class="card">
        <h3>Financial Reports</h3>
        <div class="stack">
            <a id="report-income-print" class="button" href="#" target="_blank">Print Income and Expense Statement</a>
            <a id="report-income-csv" class="button button-secondary" href="#">Download Income and Expense CSV</a>
            <a id="report-transactions-print" class="button" href="#" target="_blank">Print Transaction Ledger</a>
            <a id="report-transactions-csv" class="button button-secondary" href="#">Download Transaction Ledger CSV</a>
            <a id="report-bills-csv" class="button button-secondary" href="#">Download Bills CSV</a>
            <a id="report-payments-csv" class="button button-secondary" href="#">Download Payments CSV</a>
        </div>
    </div>
    <div class="card">
        <h3>Operations and Customer Reports</h3>
        <div class="stack">
            <a id="report-customers-print" class="button" href="/api/reports/customers/print" target="_blank">Print Customer List</a>
            <a id="report-customers-csv" class="button button-secondary" href="/api/reports/customers/csv">Download Customer List CSV</a>
            <a id="report-connections-csv" class="button button-secondary" href="/api/reports/connections/csv">Download Connections CSV</a>
            <a id="report-inventory-csv" class="button button-secondary" href="/api/reports/inventory/csv">Download Inventory and Cost CSV</a>
        </div>
    </div>
</div>
<div class="grid cols-2">
    <div class="card">
        <h3>Customer Detail Print</h3>
        <form id="report-customer-print-form" class="stack">
            <input id="report-customer-id" placeholder="Customer ID (for profile print)" />
            <button type="submit" class="button">Open Customer Profile Print</button>
            <div class="chart-note">Includes profile, equipment, and connection history.</div>
        </form>
    </div>
    <div class="card">
        <h3>How to Use</h3>
        <ul>
            <li>Set month scope and refresh snapshot.</li>
            <li>Use print actions for paper/PDF output from browser print dialog.</li>
            <li>Use CSV downloads for Excel analysis or sharing.</li>
            <li>Use customer profile print for single-customer full detail export.</li>
        </ul>
    </div>
</div>
HTML;

                $script = <<<'JS'
const monthInput = document.getElementById('report-month');
const scopeLabel = document.getElementById('report-scope');
const kpiWrap = document.getElementById('report-kpi-cards');

monthInput.value = new Date().toISOString().slice(0,7);

function toMoney(v) {
    return Number(v || 0).toFixed(2);
}

function monthValue() {
    return monthInput.value || new Date().toISOString().slice(0,7);
}

function setHref(id, url) {
    const el = document.getElementById(id);
    if (el) {
        el.href = url;
    }
}

function syncReportUrls() {
    const m = monthInput.value || new Date().toISOString().slice(0,7);
    setHref('report-income-print', '/api/reports/income-expense/print?month=' + encodeURIComponent(m));
    setHref('report-income-csv', '/api/reports/income-expense/csv?month=' + encodeURIComponent(m));
    setHref('report-transactions-print', '/api/reports/transactions/print?month=' + encodeURIComponent(m));
    setHref('report-transactions-csv', '/api/reports/transactions/csv?month=' + encodeURIComponent(m));
    setHref('report-bills-csv', '/api/reports/bills/csv?month=' + encodeURIComponent(m));
    setHref('report-payments-csv', '/api/reports/payments/csv?month=' + encodeURIComponent(m));
    scopeLabel.textContent = 'Scope: ' + m;
}

function renderKpis(d) {
    const cards = [
        ['Income', toMoney(d.income_total)],
        ['Expense', toMoney(d.expense_total)],
        ['Net', toMoney(d.net_total)],
        ['Bill Due', toMoney(d.bill_due_total)],
        ['Payment Collected', toMoney(d.payment_total)],
        ['Customers', d.customers_total || 0],
        ['Connections', d.connections_total || 0],
        ['Inventory Sell Value', toMoney(d.inventory_sell_value)],
    ];

    kpiWrap.innerHTML = cards
        .map(c => `<div class="card stat"><div class="label">${c[0]}</div><div class="value">${c[1]}</div></div>`)
        .join('');
}

async function loadOverview() {
    const m = monthValue();
    const payload = await fetch('/api/reports/overview?month=' + encodeURIComponent(m)).then(r => r.json());
    const data = payload.data || {};
    renderKpis(data);
}

document.getElementById('report-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    syncReportUrls();
    await loadOverview();
});

monthInput.addEventListener('input', () => {
    syncReportUrls();
});

document.getElementById('report-customer-print-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const customerId = String(document.getElementById('report-customer-id').value || '').trim();
    if (!customerId) {
        alert('Enter a customer ID first');
        return;
    }

    window.open('/print/customer-profile?customer_id=' + encodeURIComponent(customerId), '_blank');
});

syncReportUrls();
loadOverview();
JS;

                $this->render('Reports', 'Report and Print Center', 'Generate printable operational and financial reports.', $content, $script);
        }

        public function users(Request $request): void
        {
                $content = <<<'HTML'
<div class="card">
    <h3>Create User</h3>
    <form id="user-form" class="grid cols-4">
        <input name="full_name" placeholder="Full Name" required />
        <input name="username" placeholder="Username" required />
        <input name="email" placeholder="Email" />
        <input name="password" type="password" placeholder="Password" required />
        <select name="role"><option value="staff">Staff</option><option value="manager">Manager</option><option value="super_admin">Super Admin</option></select>
        <button type="submit" class="button">Create User</button>
    </form>
</div>
<div class="card">
    <h3>User List</h3>
    <table><thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th></tr></thead><tbody id="users-tbody"></tbody></table>
</div>
HTML;

                $script = <<<'JS'
async function loadUsers() {
    const payload = await fetch('/api/users').then(r => r.json());
    const rows = payload.data || [];
    document.getElementById('users-tbody').innerHTML = rows.map(r => `<tr><td>${r.full_name || ''}</td><td>${r.username || ''}</td><td>${r.email || ''}</td><td>${r.role || ''}</td><td>${r.status || ''}</td></tr>`).join('');
}

document.getElementById('user-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target).entries());
    const payload = await fetch('/api/users', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data),
    }).then(r => r.json());

    alert(payload.status === 'success' ? 'User created' : payload.message);
    if (payload.status === 'success') {
        e.target.reset();
        loadUsers();
    }
});

loadUsers();
JS;

                $this->render('Users', 'User Management', 'Manage internal users and operational roles.', $content, $script);
        }

        public function tickets(Request $request): void
        {
                $content = <<<'HTML'
<div class="card">
    <h3>Create Support Ticket</h3>
    <form id="ticket-form" class="grid cols-4">
        <input name="customer_id" placeholder="Customer ID" />
        <input name="subject" placeholder="Subject" required />
        <input name="description" placeholder="Description" />
        <select name="priority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option></select>
        <button class="button" type="submit">Create Ticket</button>
    </form>
</div>
<div class="card">
    <h3>Support Tickets</h3>
    <table>
        <thead><tr><th>Ticket</th><th>Customer</th><th>Subject</th><th>Priority</th><th>Status</th><th>Action</th></tr></thead>
        <tbody id="tickets-tbody"></tbody>
    </table>
</div>
HTML;

                $script = <<<'JS'
async function loadTickets() {
    const payload = await fetch('/api/tickets').then(r => r.json());
    const rows = payload.data || [];
    document.getElementById('tickets-tbody').innerHTML = rows.map(r => `
        <tr>
            <td>${r.ticket_no || ''}</td>
            <td>${r.customer_id || ''}</td>
            <td>${r.subject || ''}</td>
            <td>${r.priority || ''}</td>
            <td>${r.status || ''}</td>
            <td>
                <button class="button" onclick="setTicketStatus('${r._id}','in_progress')">In Progress</button>
                <button class="button" onclick="setTicketStatus('${r._id}','resolved')">Resolve</button>
            </td>
        </tr>
    `).join('');
}

async function setTicketStatus(id, status) {
    const payload = await fetch('/api/tickets/status', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id, status}),
    }).then(r => r.json());
    alert(payload.status === 'success' ? 'Ticket updated' : payload.message);
    loadTickets();
}

document.getElementById('ticket-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target).entries());
    const payload = await fetch('/api/tickets', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data),
    }).then(r => r.json());

    alert(payload.status === 'success' ? 'Ticket created' : payload.message);
    if (payload.status === 'success') {
        e.target.reset();
        loadTickets();
    }
});

loadTickets();
JS;

                $this->render('Tickets', 'Support Tickets', 'Register, assign, and resolve customer support tickets.', $content, $script);
        }

        public function payments(Request $request): void
        {
                $content = <<<'HTML'
<div class="grid cols-2">
    <div class="card">
        <h3>Payment History Filter</h3>
        <form id="payment-filter-form" class="stack">
            <input id="payment-customer-id" placeholder="Customer ID (optional)" />
            <button class="button" type="submit">Load Payments</button>
        </form>
    </div>
    <div class="card">
        <h3>Quick Payment Entry</h3>
        <form id="payment-quick-form" class="stack">
            <input name="customer_id" placeholder="Customer ID" required />
            <input name="bill_month" type="month" required />
            <input name="amount" type="number" step="0.01" placeholder="Amount" required />
            <input name="method" placeholder="cash/bank/mobile" />
            <button class="button" type="submit">Post Payment</button>
        </form>
    </div>
</div>
<div class="card">
    <h3>Payments</h3>
    <table>
        <thead><tr><th>Customer</th><th>Bill Month</th><th>Amount</th><th>Method</th><th>Date</th></tr></thead>
        <tbody id="payments-tbody"></tbody>
    </table>
</div>
HTML;

                $script = <<<'JS'
async function loadPayments(customerId = '') {
    const q = customerId ? ('?customer_id=' + encodeURIComponent(customerId)) : '';
    const payload = await fetch('/api/payments' + q).then(r => r.json());
    const rows = payload.data || [];
    document.getElementById('payments-tbody').innerHTML = rows.map(r => `
        <tr>
            <td>${r.customer_id || ''}</td>
            <td>${r.bill_month || ''}</td>
            <td>${r.amount || 0}</td>
            <td>${r.method || ''}</td>
            <td>${r.created_at ? 'saved' : ''}</td>
        </tr>
    `).join('');
}

document.getElementById('payment-filter-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const customerId = document.getElementById('payment-customer-id').value.trim();
    loadPayments(customerId);
});

document.getElementById('payment-quick-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target).entries());
    data.amount = Number(data.amount || 0);
    const payload = await fetch('/api/payments', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data),
    }).then(r => r.json());

    alert(payload.message || payload.status);
    if (payload.status === 'success') {
        e.target.reset();
        loadPayments();
    }
});

loadPayments();
JS;

                $this->render('Payments', 'Payment Management', 'Track, filter, and post customer payments.', $content, $script);
        }

        public function operations(Request $request): void
        {
                $content = <<<'HTML'
<div class="grid cols-2">
    <div class="card">
        <h3>Network Operations Snapshot</h3>
        <pre id="ops-topology"></pre>
    </div>
    <div class="card">
        <h3>Commercial Operations Snapshot</h3>
        <pre id="ops-commercial"></pre>
    </div>
</div>
<div class="card">
    <h3>Operational Checklist</h3>
    <ul>
        <li>Verify low stock items before new installations</li>
        <li>Review high due customers and payment follow-up list</li>
        <li>Check distribution box used ports against capacity</li>
        <li>Run monthly bill generation at cycle start</li>
    </ul>
</div>
HTML;

                $script = <<<'JS'
async function loadOps() {
    const [zones, boxes, products, dashboard] = await Promise.all([
        fetch('/api/zones').then(r => r.json()),
        fetch('/api/distribution-boxes').then(r => r.json()),
        fetch('/api/products').then(r => r.json()),
        fetch('/api/dashboard/summary').then(r => r.json()),
    ]);

    document.getElementById('ops-topology').textContent = JSON.stringify({
        zones: (zones.data || []).length,
        distribution_boxes: boxes.data || [],
    }, null, 2);

    document.getElementById('ops-commercial').textContent = JSON.stringify({
        products_total: (products.data || []).length,
        dashboard: dashboard.data || {},
    }, null, 2);
}

loadOps();
JS;

                $this->render('Operations', 'Operations Control', 'Monitor day-to-day network and business operations.', $content, $script);
        }

        public function settings(Request $request): void
        {
                $content = <<<'HTML'
<div class="grid cols-2">
    <div class="card">
        <h3>System Settings (Phase 1)</h3>
        <form id="settings-form" class="stack">
            <input id="company-name" placeholder="Company Name" value="BBN ISP" />
            <input id="company-phone" placeholder="Company Phone" value="" />
            <input id="company-email" placeholder="Company Email" value="" />
            <button type="submit" class="button">Save Local Preferences</button>
        </form>
        <p>These settings are stored in browser local storage for now. Backend settings module can be added next.</p>
    </div>
    <div class="card">
        <h3>Module Access</h3>
        <ul>
            <li><a href="/customers">Customer Management</a></li>
            <li><a href="/topology">Topology Management</a></li>
            <li><a href="/connections">Connection Management</a></li>
            <li><a href="/billing">Billing</a></li>
        </ul>
    </div>
</div>
HTML;

                $script = <<<'JS'
const settingsKey = 'bbn_isp_settings';

function loadSettings() {
    try {
        const saved = JSON.parse(localStorage.getItem(settingsKey) || '{}');
        document.getElementById('company-name').value = saved.company_name || 'BBN ISP';
        document.getElementById('company-phone').value = saved.company_phone || '';
        document.getElementById('company-email').value = saved.company_email || '';
    } catch (e) {
    }
}

document.getElementById('settings-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const payload = {
        company_name: document.getElementById('company-name').value,
        company_phone: document.getElementById('company-phone').value,
        company_email: document.getElementById('company-email').value,
    };
    localStorage.setItem(settingsKey, JSON.stringify(payload));
    alert('Settings saved locally');
});

loadSettings();
JS;

                $this->render('Settings', 'System Settings', 'Configure basic platform preferences and access links.', $content, $script);
        }

        private function render(string $title, string $heading, string $description, string $content, string $script = ''): void
        {
                header('Content-Type: text/html; charset=utf-8');

                $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
                $navItems = [
                    ['href' => '/', 'label' => 'Dashboard'],
                    ['href' => '/customers', 'label' => 'Add Customer'],
                    ['href' => '/customers/list', 'label' => 'Customer List'],
                    ['href' => '/topology', 'label' => 'Topology'],
                    ['href' => '/packages', 'label' => 'Packages'],
                    ['href' => '/products', 'label' => 'Products'],
                    ['href' => '/connections', 'label' => 'Connections'],
                    ['href' => '/billing', 'label' => 'Billing'],
                    ['href' => '/reports', 'label' => 'Reports'],
                    ['href' => '/settings', 'label' => 'Settings'],
                    ['href' => '/logout', 'label' => 'Logout'],
                ];

                $navLinks = '';
                foreach ($navItems as $item) {
                    $href = $item['href'];
                    $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
                    $active = $path === $href;
                    $activeClass = $active ? ' active' : '';
                    $activeAttr = $active ? ' aria-current="page"' : '';
                    $navLinks .= '<a class="nav-link' . $activeClass . '" href="' . $href . '"' . $activeAttr . '>' . $label . '</a>';
                }

                $nav = <<<HTML
<nav class="sidebar">
    <div class="brand-wrap">
        <div class="brand-mark">BI</div>
        <div>
            <h2>BBN ISP Suite</h2>
            <p class="brand-sub">Operations Control Center</p>
        </div>
    </div>
    <div class="nav-group">
        {$navLinks}
    </div>
</nav>
HTML;

                $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
                $safeHeading = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
                $safeDescription = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');

                echo <<<HTML
<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{$safeTitle} - BBN ISP</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap');

        :root{
            --bg:#edf2f8;
            --surface:#ffffff;
            --surface-soft:#f4f8fd;
            --text:#0d1a2f;
            --line:#d4deeb;
            --brand:#1666cc;
            --brand-strong:#124e9d;
            --muted:#4a617f;
            --ok:#0f766e;
            --danger:#b91c1c;
            --shadow:0 10px 34px rgba(12, 28, 50, 0.08);
            --sidebar-bg:#0a1a2f;
            --sidebar-line:rgba(167, 187, 214, 0.22);
        }
        *{box-sizing:border-box}
        html,body{height:100%}
        body{
            margin:0;
            font-family:"Manrope","Aptos","Segoe UI",sans-serif;
            color:var(--text);
            background:
                radial-gradient(circle at 6% 0%, rgba(22,102,204,0.14), transparent 32%),
                radial-gradient(circle at 100% 100%, rgba(8,145,178,0.14), transparent 36%),
                var(--bg);
        }
        .layout{display:grid;grid-template-columns:276px 1fr;min-height:100vh;position:relative}
        .sidebar{
            background:linear-gradient(180deg,var(--sidebar-bg),#081629);
            color:#e6edf7;
            padding:18px 14px;
            display:flex;
            flex-direction:column;
            gap:14px;
            border-right:1px solid var(--sidebar-line);
            position:sticky;
            top:0;
            height:100vh;
        }
        .brand-wrap{display:flex;align-items:center;gap:10px;padding:10px;border:1px solid rgba(141, 167, 203, 0.26);border-radius:13px;background:rgba(22,38,64,0.72)}
        .brand-mark{width:38px;height:38px;border-radius:10px;background:linear-gradient(180deg,#2a76d8,#1956ab);display:grid;place-items:center;font-weight:800;color:#fff;font-size:13px;letter-spacing:.5px}
        .sidebar h2{margin:0;font-size:18px;letter-spacing:.3px}
        .brand-sub{margin:2px 0 0 0;font-size:11px;color:#99afd0;text-transform:uppercase;letter-spacing:.7px}
        .nav-group{display:grid;gap:6px}
        .nav-link{
            color:#c7d5e8;
            text-decoration:none;
            padding:10px 12px;
            border-radius:10px;
            border:1px solid transparent;
            transition:all .2s ease;
            font-size:13px;
            font-weight:600;
            letter-spacing:.2px;
            position:relative;
            overflow:hidden;
        }
        .nav-link::before{content:"";position:absolute;left:0;top:0;bottom:0;width:0;background:linear-gradient(180deg,#3f8ff2,#2b6ec2);transition:width .2s ease;opacity:.9}
        .nav-link:hover{background:rgba(30,64,118,.55);border-color:rgba(113,145,191,.35);color:#fff;transform:translateX(1px)}
        .nav-link.active{background:rgba(31,77,145,.62);border-color:rgba(137,167,214,.46);color:#fff;padding-left:16px}
        .nav-link.active::before{width:4px}
        .main{padding:24px 26px;max-width:1500px;width:100%}
        .header{margin-bottom:18px;padding:18px;border:1px solid rgba(184,198,218,.7);border-radius:14px;background:linear-gradient(145deg,#ffffff,#f2f7fd);box-shadow:0 8px 22px rgba(14, 32, 58, 0.06)}
        .header h1{margin:0 0 6px 0;font-size:31px;letter-spacing:.2px;font-weight:800}
        .header p{margin:0;color:var(--muted);font-size:14px}
        .card{
            background:var(--surface);
            border:1px solid var(--line);
            border-radius:16px;
            padding:16px;
            margin-bottom:14px;
            box-shadow:var(--shadow);
            transition:transform .22s ease, box-shadow .22s ease, border-color .22s ease;
        }
        .card:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(12, 30, 53, 0.09);border-color:#c7d4e8}
        @keyframes cardReveal{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
        .card-reveal{animation:cardReveal .36s ease both}
        .card h3,.card h4{margin-top:0}
        .stat{
            background:linear-gradient(180deg,var(--surface),var(--surface-soft));
        }
        .stat .label{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.55px}
        .stat .value{font-size:27px;font-weight:700;margin-top:7px;color:#0d2b55}
        .grid{display:grid;gap:12px}
        .cols-4{grid-template-columns:repeat(auto-fit,minmax(190px,1fr))}
        .cols-2{grid-template-columns:repeat(auto-fit,minmax(300px,1fr))}
        .stack{display:grid;gap:9px}
        .section-title{grid-column:1/-1;font-weight:700;color:#143257;font-size:13px;text-transform:uppercase;letter-spacing:.5px;margin-top:4px}
        .equipment-toolbar{grid-column:1/-1}
        .equipment-grid{grid-column:1/-1;display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}
        .equip-item{display:grid;grid-template-columns:22px 1fr 88px;align-items:center;gap:10px;padding:10px;border:1px solid #dbe4f0;border-radius:12px;background:#fff;transition:border-color .2s ease, box-shadow .2s ease, background .2s ease}
        .equip-item:hover{border-color:#b7cae4;box-shadow:0 4px 12px rgba(15,92,200,.08)}
        .equip-item-active{border-color:#78a3de;background:#f4f9ff}
        .equip-name{font-weight:600;color:#102746;line-height:1.25}
        .actions-row{grid-column:1/-1;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
        .field-wrap{display:grid;gap:4px}
        .field-error{min-height:14px;font-size:11px;color:#b91c1c;line-height:1.2}
        .field-invalid{border-color:#ef4444 !important;box-shadow:0 0 0 3px rgba(239,68,68,.12) !important}
        input,select,button{
            padding:10px 11px;
            border-radius:11px;
            border:1px solid #c9d6e6;
            background:#fff;
            color:var(--text);
            min-height:40px;
            font-family:inherit;
            font-size:13px;
        }
        input:focus,select:focus,button:focus{outline:none;border-color:#7aa6e8;box-shadow:0 0 0 3px rgba(15,92,200,.12)}
        .button{
            display:inline-block;
            background:linear-gradient(180deg,var(--brand),var(--brand-strong));
            color:#fff;
            border:none;
            text-decoration:none;
            cursor:pointer;
            text-align:center;
            padding:10px 13px;
            border-radius:10px;
            font-weight:600;
            transition:transform .15s ease, filter .2s ease;
            letter-spacing:.2px;
        }
        .button:hover{filter:brightness(1.04);transform:translateY(-1px)}
        .button-secondary{background:#e7eef9;color:#123258;border:1px solid #c8d7ef}
        .button-danger{background:linear-gradient(180deg,#d94b4b,#b93232)}
        .modal-backdrop{position:fixed;inset:0;z-index:60;background:rgba(8,18,33,.5);display:flex;align-items:center;justify-content:center;padding:16px}
        .modal-card{width:min(520px,100%);background:#fff;border:1px solid #cfdbeb;border-radius:14px;box-shadow:0 16px 34px rgba(10,22,40,.2);padding:16px}
        .modal-card h3{margin:0 0 8px 0}
        .modal-meta{display:grid;gap:6px;margin-top:10px}
        .modal-meta p{margin:0;padding:9px 10px;background:#f6f9ff;border:1px solid #d9e4f5;border-radius:10px;font-size:13px}
        .chart-card{min-height:280px}
        .chart-head{display:flex;justify-content:space-between;align-items:center;gap:10px}
        .chart-note{font-size:12px;color:var(--muted)}
        .line-chart-wrap{margin-top:8px;border:1px solid #dbe4f0;border-radius:12px;padding:6px 8px 4px;background:#fff}
        .compact-line .line-chart-svg{height:190px}
        .line-chart-svg{width:100%;height:220px;display:block}
        .chart-axis{font-size:10px;fill:#6b7f99}
        .line-legend{display:flex;gap:12px;flex-wrap:wrap;padding:2px 2px 6px 6px}
        .line-legend span{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#304968}
        .line-legend i{display:inline-block;width:10px;height:10px;border-radius:50%}
        .split-chart{display:grid;grid-template-columns:180px 1fr;gap:12px;align-items:center}
        .donut{width:170px;height:170px;border-radius:50%;display:grid;place-items:center;position:relative}
        .donut::before{content:"";position:absolute;width:92px;height:92px;border-radius:50%;background:#fff;box-shadow:inset 0 0 0 1px #dbe4f0}
        .donut span{position:relative;z-index:1;font-size:14px;font-weight:700;color:#102746;text-align:center;line-height:1.2;padding:0 8px}
        .legend-list{display:grid;gap:8px}
        .legend-item{display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:13px;padding:8px 10px;border:1px solid #dbe4f0;border-radius:10px;background:#fff}
        .dot{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:7px}
        .meter-list{display:grid;gap:12px}
        .meter-row{display:grid;gap:6px}
        .meter-top{display:flex;justify-content:space-between;gap:10px;font-size:13px}
        .meter-track{height:10px;background:#e6edf7;border-radius:999px;overflow:hidden}
        .meter-fill{height:100%;border-radius:999px}
        table{
            width:100%;
            border-collapse:separate;
            border-spacing:0;
            border:1px solid var(--line);
            border-radius:12px;
            overflow:hidden;
            background:#fff;
        }
        th,td{padding:10px 11px;font-size:13px;text-align:left;border-bottom:1px solid #e7edf5;vertical-align:top}
        th{background:#eef3f9;color:#2f4665;font-size:12px;text-transform:uppercase;letter-spacing:.45px;position:sticky;top:0;z-index:1}
        tbody tr:nth-child(even){background:#fafcff}
        tbody tr:hover{background:#f2f7ff}
        pre{background:#0b1220;color:#e2e8f0;padding:12px;border-radius:10px;overflow:auto}
        @media (max-width: 980px){
            .layout{grid-template-columns:1fr}
            .sidebar{position:sticky;top:0;z-index:5;flex-direction:row;flex-wrap:wrap;align-items:center;padding:10px 12px;height:auto;gap:8px}
            .brand-wrap{width:100%}
            .nav-group{grid-template-columns:repeat(auto-fit,minmax(130px,1fr));width:100%}
            .main{padding:14px}
            .header h1{font-size:24px}
            .split-chart{grid-template-columns:1fr}
            .donut{margin-inline:auto}
            .line-chart-svg{height:200px}
            .compact-line .line-chart-svg{height:180px}
        }
    </style>
</head>
<body>
    <div class="layout">
        {$nav}
        <main class="main">
            <div class="header">
                <h1>{$safeHeading}</h1>
                <p>{$safeDescription}</p>
            </div>
            {$content}
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.classList.add('card-reveal');
                card.style.animationDelay = Math.min(index * 35, 320) + 'ms';
            });
        });
    </script>
    <script>{$script}</script>
</body>
</html>
HTML;
    }
}

