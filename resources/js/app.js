import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart = Chart;

document.addEventListener('alpine:init', () => {
    Alpine.data('sidebar', () => ({
        collapsed: false,
        mobileOpen: false,

        init() {
            this.collapsed = localStorage.getItem('sidebar.collapsed') === '1';
        },

        toggleCollapsed() {
            this.collapsed = !this.collapsed;
            localStorage.setItem('sidebar.collapsed', this.collapsed ? '1' : '0');
        },

        openMobile() {
            this.mobileOpen = true;
        },

        closeMobile() {
            this.mobileOpen = false;
        },
    }));

    /**
     * Lightweight searchable combobox for picking master records (customers,
     * spare parts) via a JSON endpoint instead of embedding entire tables in
     * the page. Only the selected record's id is submitted.
     *
     * Props:
     *   endpoint   URL returning an array of { id, label, ...record }
     *   initialId  preselect an id (string)
     *   initialLabel display text for the preselected record
     *   name       static hidden-input name (single fields)
     *   searchParam query string key (default "q")
     *
     * For per-row picks (e.g. parts in a variable line-items table), pass
     * `bare` from the Blade view so no hidden input is rendered; the parent
     * Alpine scope owns the hidden input (`:name="`parts[${index}][spare_part_id]`"`)
     * and renders the selected value as a chip keyed off its own state.
     *
     * Emits:
     *   combobox-selected  detail = selected record
     *   combobox-cleared   no detail
     */
    Alpine.data('searchableCombobox', (config) => ({
        endpoint: config.endpoint,
        initialId: config.initialId !== undefined ? config.initialId : '',
        initialLabel: config.initialLabel !== undefined ? config.initialLabel : '',
        name: config.name || '',
        searchParam: config.searchParam || 'q',
        placeholder: config.placeholder || '-- Search --',
        minChars: config.minChars || 2,
        required: !!config.required,

        query: '',
        open: false,
        busy: false,
        results: [],
        highlighted: -1,
        selected: null,
        _debounceTimer: null,

        get fullName() {
            return this.name;
        },

        get selectedLabel() {
            return this.selected ? (this.selected.label || this.initialLabel) : '';
        },

        init() {
            this.selected = this.initialId ? { id: String(this.initialId), label: this.initialLabel } : null;
            document.addEventListener('mousedown', this.onDocMousedown);
        },

        destroy() {
            document.removeEventListener('mousedown', this.onDocMousedown);
            if (this._debounceTimer) clearTimeout(this._debounceTimer);
        },

        onDocMousedown(e) {
            if (this.$root && !this.$root.contains(e.target)) {
                this.open = false;
            }
        },

        onInput() {
            // Editing the text invalidates any previously picked record so the
            // form never submits a stale id.
            if (this.selected && this.query !== this.selectedLabel) {
                this.selected = null;
                this.$dispatch('combobox-cleared');
            }
            if (this._debounceTimer) clearTimeout(this._debounceTimer);
            this._debounceTimer = setTimeout(() => this.fetchResults(), 250);
        },

        async fetchResults() {
            const q = this.query.trim();
            if (q.length < this.minChars) {
                this.results = [];
                this.open = false;
                return;
            }
            this.busy = true;
            try {
                const url = `${this.endpoint}?${encodeURIComponent(this.searchParam)}=${encodeURIComponent(q)}`;
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('Request failed');
                const data = await res.json();
                this.results = Array.isArray(data) ? data : [];
                this.highlighted = -1;
                this.open = this.results.length > 0;
            } catch (e) {
                this.results = [];
                this.open = false;
            } finally {
                this.busy = false;
            }
        },

        select(record) {
            this.selected = record;
            this.query = record.label || '';
            this.open = false;
            this.highlighted = -1;
            this.$dispatch('combobox-selected', record);
        },

        clear() {
            this.selected = null;
            this.query = '';
            this.open = false;
            this.results = [];
            this.$dispatch('combobox-cleared');
        },

        // Remove the checkmark / change affordance once editing has begun.
        get isFresh() {
            return this.selected !== null && this.query === this.selectedLabel;
        },

        onKeydown(e) {
            if (!this.open && e.key !== 'ArrowDown' && e.key !== 'Enter') return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!this.open) {
                    this.fetchResults();
                    return;
                }
                this.highlighted = (this.highlighted + 1) % this.results.length;
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.highlighted = (this.highlighted - 1 + this.results.length) % this.results.length;
            } else if (e.key === 'Enter') {
                if (this.open && this.results.length > 0) {
                    e.preventDefault();
                    const index = this.highlighted >= 0 ? this.highlighted : 0;
                    this.select(this.results[index]);
                }
            } else if (e.key === 'Escape') {
                this.open = false;
            }
        },

        onFocus() {
            if (this.query.trim().length >= this.minChars) {
                this.fetchResults();
            }
        },
    }));

    /**
     * Shared multi-select table state for the data-table pattern.
     *
     * Modules wrap their <x-data-table> (plus <x-table-selection-bar> and the
     * shared checkboxes) in this scope:
     *
     *     x-data="tableSelection({ bulkEndpoint: '{{ route('customers.bulk-delete') }}' })"
     *
     * or merge it with their own page state:
     *
     *     x-data="mergeAlpineData(tableSelection({...}), moduleList())"
     *
     * (A plain `{ ...tableSelection({...}), ...moduleList() }` spread invokes
     * every getter eagerly and crashes — see mergeAlpineData below.)
     *
     * Selection is an array of record id strings that may span pages. The
     * visible rows on the current page (`pageIds`, read from the row
     * checkboxes' `data-row-id`) drive the header select-all / indeterminate
     * checkbox.
     *
     * `bulkEndpoint` is optional; without it the selection bar renders no
     * Delete button. When present, one request deletes every selected record
     * (ids[]), each deletion guarded per-record on the server — protected
     * records are skipped, never aborting the batch. The shared response
     * shape is surfaced as a dismissible result banner.
     */
    const tableSelection = (config = {}) => ({
        config: config,
        bulkEndpoint: config.bulkEndpoint || '',
        bulkExportEndpoint: config.bulkExportEndpoint || '',
        rowAttr: config.rowAttr || 'data-row-id',
        modalName: config.modalName || 'bulk-delete-confirm',
        selected: [],
        busy: false,
        result: null,

        init() {
            // A bulk delete just ran on the previous page load; surface its
            // result banner after the reload that followed the deletion.
            const raw = sessionStorage.getItem('gms.bulkResult');
            if (raw) {
                sessionStorage.removeItem('gms.bulkResult');
                try {
                    const data = JSON.parse(raw);
                    this.result = {
                        type: data.skipped_count > 0 ? 'warning' : 'success',
                        message: data.message || 'Records deleted.',
                    };
                } catch (e) {
                    this.result = null;
                }
            }
        },

        get pageIds() {
            return Array.from(this.$root.querySelectorAll('[' + this.rowAttr + ']'))
                .map((el) => el.getAttribute(this.rowAttr));
        },

        get pageIdSet() {
            return new Set(this.pageIds);
        },

        get selectedCount() {
            return this.selected.length;
        },

        get singleId() {
            return this.selectedCount === 1 ? this.selected[0] : null;
        },

        get allSelected() {
            return this.pageIds.length > 0
                && this.pageIds.every((id) => this.selected.includes(id));
        },

        get someSelected() {
            return this.pageIds.some((id) => this.selected.includes(id));
        },

        get resultMessage() {
            return this.result ? this.result.message : '';
        },

        get resultType() {
            return this.result ? this.result.type : 'success';
        },

        isSelected(id) {
            return this.selected.includes(String(id));
        },

        toggleRow(id) {
            id = String(id);
            this.selected = this.selected.includes(id)
                ? this.selected.filter((x) => x !== id)
                : [...this.selected, id];
        },

        toggleAll() {
            const page = this.pageIdSet;
            if (this.allSelected) {
                this.selected = this.selected.filter((id) => !page.has(id));
            } else {
                const merged = new Set(this.selected);
                page.forEach((id) => merged.add(id));
                this.selected = Array.from(merged);
            }
        },

        clearSelection() {
            this.selected = [];
        },

        dismissResult() {
            this.result = null;
        },

        async bulkDelete() {
            if (!this.bulkEndpoint || this.selected.length === 0 || this.busy) return;
            this.busy = true;
            this.$dispatch('close-modal', this.modalName);
            try {
                const form = new FormData();
                this.selected.forEach((id) => form.append('ids[]', id));

                const meta = document.querySelector('meta[name="csrf-token"]');
                const res = await fetch(this.bulkEndpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': meta ? meta.getAttribute('content') : '',
                    },
                    body: form,
                });
                const data = await res.json().catch(() => ({}));

                if (!res.ok) {
                    this.result = { type: 'error', message: data.message || 'Bulk delete failed.' };
                    return;
                }

                // Deletions happened server-side; reload the list and show the
                // result banner carried across the reload via sessionStorage.
                sessionStorage.setItem('gms.bulkResult', JSON.stringify(data));
                window.location.reload();
            } catch (e) {
                this.result = { type: 'error', message: 'Bulk delete failed. Please try again.' };
            } finally {
                this.busy = false;
            }
        },

        async exportSelected() {
            if (!this.bulkExportEndpoint || this.selected.length === 0 || this.busy) return;
            this.busy = true;
            try {
                const form = new FormData();
                this.selected.forEach((id) => form.append('ids[]', id));

                const meta = document.querySelector('meta[name="csrf-token"]');
                const res = await fetch(this.bulkExportEndpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'text/csv',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': meta ? meta.getAttribute('content') : '',
                    },
                    body: form,
                });

                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    this.result = { type: 'error', message: data.message || 'Export failed.' };
                    return;
                }

                const blob = await res.blob();
                const disposition = res.headers.get('Content-Disposition') || '';
                const match = disposition.match(/filename="?([^";]+)"?/);
                const filename = match ? match[1] : 'export.csv';

                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            } catch (e) {
                this.result = { type: 'error', message: 'Export failed. Please try again.' };
            } finally {
                this.busy = false;
            }
        },
    });

    Alpine.data('tableSelection', tableSelection);
    window.tableSelection = tableSelection;

    /**
     * Merge several Alpine component objects while preserving accessor
     * properties (getters). A plain `{ ...a, ...b }` spread invokes every
     * getter eagerly and copies its *value*, freezing it at that instant —
     * and getters that read Alpine magics (e.g. `this.$root` in pageIds)
     * throw outright because the raw object has no magic properties. Copying
     * property descriptors instead keeps the getters lazy so they resolve
     * through Alpine's proxy (where magics live) on every access, keeping the
     * reactive state (selected[], allSelected, selectedCount, ...) live.
     */
    window.mergeAlpineData = (...sources) => {
        const merged = {};
        for (const source of sources) {
            for (const key of Object.keys(source)) {
                Object.defineProperty(merged, key, Object.getOwnPropertyDescriptor(source, key));
            }
        }
        return merged;
    };
});

Alpine.start();

document.addEventListener('submit', (e) => {
    if (e.defaultPrevented) return;

    const form = e.target;
    if (!(form instanceof HTMLFormElement) || form.dataset.guarded) return;
    form.dataset.guarded = '1';

    const submitter = e.submitter;
    if (submitter && 'disabled' in submitter) {
        submitter.disabled = true;
    } else {
        form.querySelectorAll('[type="submit"]').forEach((btn) => {
            btn.disabled = true;
        });
    }
});
