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