import assert from 'node:assert/strict';
import { test } from 'node:test';
import { renderAwardeeRow } from '../../resources/js/awardees-table.js';
import { AjaxTable } from '../../resources/js/ajax-table.js';
import { escapeHtml } from '../../resources/js/html.js';
import { renderMonitoringRow } from '../../resources/js/monitoring-table.js';
import { renderOrderRow } from '../../resources/js/orders-table.js';
import { renderSearchRow } from '../../resources/js/search-table.js';

test('escapeHtml makes dynamic names, awards, titles, and attributes inert', () => {
    const values = [
        `<img src=x onerror="alert('name')">&`,
        '<script>alert("award")</script>',
        '" onmouseover="alert(1)',
        "' autofocus onfocus='alert(1)",
    ];

    for (const value of values) {
        const escaped = escapeHtml(value);
        const textMarkup = `<span>${escaped}</span>`;
        const attributeMarkup = `<span title="${escaped}"></span>`;

        assert.doesNotMatch(textMarkup, /<(?:img|script)\b/i);
        assert.doesNotMatch(attributeMarkup, /title="[^"]*"\s+on/i);
        assert.equal(escaped.includes('&lt;'), value.includes('<'));
        assert.equal(escaped.includes('&amp;'), value.includes('&'));
    }
});

test('table row renderers escape database values in text and attribute contexts', () => {
    const hostileText = '<img src=x onerror="alert(1)">';
    const hostileAttribute = '1" onfocus="alert(1)';
    const monitoringMarkup = renderMonitoringRow({
        id: hostileAttribute,
        sheet: hostileText,
        rank: hostileText,
        full_name: hostileText,
        submission_date: hostileText,
        submission_date_display: hostileText,
        award: hostileText,
        score: 0,
        matchesSummary: hostileText,
        matches: [{
            order_id: hostileAttribute,
            order_display: hostileText,
            score: 1,
            award: hostileText,
            rankMatched: false,
            awardMatched: false,
            dateMatched: false,
        }],
    }, hostileAttribute);
    const awardeeMarkup = renderAwardeeRow({
        id: hostileAttribute,
        full_name: hostileText,
        rank: hostileAttribute,
        award: hostileText,
    });
    const orderMarkup = renderOrderRow({
        order_id: hostileAttribute,
        number: hostileText,
        title: hostileText,
        awardees_count: 1,
        parsed_at_sort: hostileText,
        parsed_at: hostileText,
    });
    const searchMarkup = renderSearchRow({
        id: hostileAttribute,
        full_name: hostileText,
        rank: hostileText,
        award: hostileText,
        order_id: hostileAttribute,
        order_display: hostileText,
    });

    for (const markup of [monitoringMarkup, awardeeMarkup, orderMarkup, searchMarkup]) {
        assert.doesNotMatch(markup, /<img src=x/);
        assert.doesNotMatch(markup, /onfocus="alert/);
    }

    assert.match(monitoringMarkup, /data-full-name="&lt;img src=x onerror=&quot;alert\(1\)&quot;&gt;"/);
    assert.match(awardeeMarkup, /value="&lt;img src=x onerror=&quot;alert\(1\)&quot;&gt;"/);
    assert.match(orderMarkup, /data-value="&lt;img src=x onerror=&quot;alert\(1\)&quot;&gt;"/);
    assert.match(searchMarkup, /data-value="&lt;img src=x onerror=&quot;alert\(1\)&quot;&gt;"/);
    assert.match(monitoringMarkup, /value="1&quot; onfocus=&quot;alert\(1\)"/);
    assert.match(awardeeMarkup, /data-row="1&quot; onfocus=&quot;alert\(1\)"/);
});

test('AjaxTable filter options escape database values in labels and attributes', () => {
    const originalDocument = globalThis.document;
    const listEl = { innerHTML: '' };
    const table = Object.create(AjaxTable.prototype);
    Object.assign(table, {
        tableId: 'testTable',
        activeFilterColIndex: 0,
        columnFilterAllValues: {},
        columnFilters: {},
    });
    globalThis.document = { getElementById: () => ({ checked: false }) };

    try {
        table.renderDistinctCheckboxes(['<img src=x onerror="alert(1)">', '" onfocus="alert(1)'], listEl);

        assert.doesNotMatch(listEl.innerHTML, /<img src=x/);
        assert.doesNotMatch(listEl.innerHTML, /value="[^\"]*"\s+onfocus=/);
        assert.match(listEl.innerHTML, /value="&lt;img src=x onerror=&quot;alert\(1\)&quot;&gt;"/);
        assert.match(listEl.innerHTML, /&quot; onfocus=&quot;alert\(1\)/);
    } finally {
        if (originalDocument === undefined) {
            delete globalThis.document;
        } else {
            globalThis.document = originalDocument;
        }
    }
});

test('AjaxTable loads only after explicit initialization', async () => {
    const originalDocument = globalThis.document;
    const originalFetch = globalThis.fetch;
    const originalWindow = globalThis.window;
    const elements = new Map();
    let fetchCalls = 0;

    globalThis.document = {
        addEventListener() {},
        body: { appendChild() {} },
        createElement: () => ({ addEventListener() {}, classList: { add() {}, remove() {} } }),
        getElementById(id) {
            if (!elements.has(id)) {
                elements.set(id, { addEventListener() {} });
            }

            return elements.get(id);
        },
        querySelector: () => null,
        querySelectorAll: () => [],
    };
    globalThis.window = { location: { origin: 'https://example.test' } };
    globalThis.fetch = async () => {
        fetchCalls++;

        return { ok: true, json: async () => ({ data: [], pagination: {} }) };
    };

    try {
        const table = new AjaxTable('testTable', { url: '/test', renderRow: () => '' });
        assert.equal(fetchCalls, 0);

        await table.initialize();

        assert.equal(fetchCalls, 1);
        assert.equal(table.isLoading, false);
    } finally {
        if (originalDocument === undefined) {
            delete globalThis.document;
        } else {
            globalThis.document = originalDocument;
        }
        if (originalWindow === undefined) {
            delete globalThis.window;
        } else {
            globalThis.window = originalWindow;
        }
        globalThis.fetch = originalFetch;
    }
});

test('AjaxTable renders page gaps and the matching navigation links', () => {
    const originalDocument = globalThis.document;
    const container = { innerHTML: '', querySelectorAll: () => [] };
    const table = Object.create(AjaxTable.prototype);
    Object.assign(table, { paginationSelector: '#pagination', currentPage: 1 });
    globalThis.document = { querySelector: () => container };

    try {
        table.renderPagination({ current_page: 5, last_page: 12, total: 120, from: 41, to: 50 });

        assert.match(container.innerHTML, /data-page="4">Назад/);
        assert.match(container.innerHTML, /data-page="6">Далі/);
        assert.match(container.innerHTML, /bg-indigo-600[^>]*>5<\/span>/);
        assert.equal(container.innerHTML.match(/>…<\/span>/g)?.length, 2);
    } finally {
        if (originalDocument === undefined) {
            delete globalThis.document;
        } else {
            globalThis.document = originalDocument;
        }
    }
});

test('AjaxTable load swallows request failures and clears its loading state', async () => {
    const originalDocument = globalThis.document;
    const originalFetch = globalThis.fetch;
    const originalConsoleError = console.error;
    let loadingHidden = false;

    globalThis.document = { querySelector: () => null };
    globalThis.fetch = async () => {
        throw new Error('test network failure');
    };
    console.error = () => {};

    const table = Object.create(AjaxTable.prototype);
    Object.assign(table, {
        isLoading: false,
        loadingSelector: null,
        buildParams: () => ({}),
        buildUrl: () => '/test',
        showLoading: () => {},
        hideLoading: () => {
            loadingHidden = true;
        },
    });

    try {
        await assert.doesNotReject(() => table.load());
        assert.equal(table.isLoading, false);
        assert.equal(loadingHidden, true);
    } finally {
        if (originalDocument === undefined) {
            delete globalThis.document;
        } else {
            globalThis.document = originalDocument;
        }
        globalThis.fetch = originalFetch;
        console.error = originalConsoleError;
    }
});