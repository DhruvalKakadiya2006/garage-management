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
});

Alpine.start();
