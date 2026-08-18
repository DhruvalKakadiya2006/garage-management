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
