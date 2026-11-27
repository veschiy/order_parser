import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

function storage() {
    const values = new Map();
    return {
        getItem: key => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
    };
}

for (const path of ['public/js/excel-table.js']) {
    test(`${path}: filters survive reload, reset, and expire with the window session`, () => {
        const localStorage = storage();
        let sessionStorage = storage();
        const document = { addEventListener() {}, querySelector: () => null, getElementById: () => null };
        const source = readFileSync(path, 'utf8').replace('export class ExcelTable', 'class ExcelTable');
        const context = vm.createContext({ document, localStorage, sessionStorage });
        const ExcelTable = vm.runInContext(`${source}\nExcelTable`, context);
        const rows = ['Шевченко', 'Коваль'].map(value => ({ children: [{ dataset: { value } }], style: {} }));
        const headers = [{ dataset: { colIndex: '0' }, querySelector: () => null }];
        const table = { id: 'monitoringTable', querySelectorAll: () => headers, querySelector: () => ({ querySelectorAll: () => rows }) };
        const makeTable = () => {
            const instance = Object.create(ExcelTable.prototype);
            Object.assign(instance, { table, tableId: table.id, columnFilters: {}, updateRowCount() {} });
            instance.restoreFilters();
            instance.renderFilters();
            return instance;
        };

        const first = makeTable();
        first.columnFilters[0] = new Set(['Шевченко']);
        first.renderFilters();
        assert.deepEqual(rows.map(row => row.style.display), ['', 'none']);
        const reloaded = makeTable();
        assert.deepEqual(rows.map(row => row.style.display), ['', 'none']);
        reloaded.resetAllFilters();
        makeTable();
        assert.deepEqual(rows.map(row => row.style.display), ['', '']);

        reloaded.columnFilters[0] = new Set();
        reloaded.renderFilters();
        makeTable();
        assert.deepEqual(rows.map(row => row.style.display), ['none', 'none']);
        sessionStorage = storage();
        context.sessionStorage = sessionStorage;
        makeTable();
        assert.deepEqual(rows.map(row => row.style.display), ['', '']);
    });
}

test('auto-initialized ExcelTable instances remain accessible by table id', () => {
    let initializeTables;
    const table = {
        id: 'ordersTable',
        querySelectorAll: () => [],
        querySelector: () => ({ querySelectorAll: () => [] }),
    };
    const document = {
        addEventListener: (eventName, callback) => {
            if (eventName === 'DOMContentLoaded') {
                initializeTables = callback;
            }
        },
        getElementById: id => id === 'excelFilterDropdown' ? {} : null,
        querySelector: () => null,
        querySelectorAll: selector => selector === '[data-excel-table]' ? [table] : [],
    };
    const window = {};
    const context = vm.createContext({ document, localStorage: storage(), sessionStorage: storage(), window });
    const source = readFileSync('public/js/excel-table.js', 'utf8');

    vm.runInContext(source, context);
    initializeTables();

    assert.equal(window.excelTables.get(table.id).table, table);
});

test('sortTable preserves numeric, date, and locale-aware sorting', () => {
    const document = { addEventListener() {} };
    const window = {};
    const context = vm.createContext({ document, window });
    const source = readFileSync('public/js/excel-table.js', 'utf8').replace('export class ExcelTable', 'class ExcelTable');
    const ExcelTable = vm.runInContext(`${source}\nExcelTable`, context);
    const cases = [
        { type: 'number', values: ['10', '2'], expected: ['2', '10'] },
        { type: 'number', direction: 'desc', values: ['2', '10'], expected: ['10', '2'] },
        { type: 'date', values: ['2026-10-02', '', '2026-01-03'], expected: ['2026-01-03', '2026-10-02', ''] },
        { type: 'date', direction: 'desc', values: ['2026-10-02', '', '2026-01-03'], expected: ['', '2026-10-02', '2026-01-03'] },
        { type: 'text', values: ['Шевченко', 'Іваненко'], expected: ['Іваненко', 'Шевченко'] },
        { type: 'text', direction: 'desc', values: ['Іваненко', 'Шевченко'], expected: ['Шевченко', 'Іваненко'] },
    ];

    for (const { type, direction, values, expected } of cases) {
        const rows = values.map(value => ({ children: [{ dataset: { value }, innerText: value }] }));
        const bodyRows = [...rows];
        const tbody = {
            querySelectorAll: () => [...bodyRows],
            appendChild(row) {
                bodyRows.splice(bodyRows.indexOf(row), 1);
                bodyRows.push(row);
            },
        };
        const icon = { textContent: '', classList: { add() {}, remove() {} } };
        const header = {
            dataset: { colIndex: '0' },
            querySelector: () => icon,
        };
        const table = {
            querySelector: selector => selector === 'tbody' ? tbody : { dataset: { type } },
            querySelectorAll: () => [header],
        };
        const instance = Object.create(ExcelTable.prototype);
        Object.assign(instance, {
            table,
            currentSort: { colIndex: null, direction: 'asc' },
            activeFilterColIndex: null,
        });

        instance.sortTable(0, direction);

        assert.deepEqual(bodyRows.map(row => row.children[0].dataset.value), expected);
        assert.equal(icon.textContent, direction === 'desc' ? '▼' : '▲');
        assert.equal(instance.getCellText({ children: [{ innerText: 'Plain text' }] }, 0), 'Plain text');
    }
});