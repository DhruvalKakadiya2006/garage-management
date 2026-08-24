@php
$navItems = [
    ['route' => 'dashboard', 'pattern' => 'dashboard', 'icon' => 'home', 'label' => __('Dashboard')],
    ['route' => 'job-cards.index', 'pattern' => 'job-cards.*', 'icon' => 'wrench-screwdriver', 'label' => __('Job Cards')],
    ['route' => 'customers.index', 'pattern' => 'customers.*', 'icon' => 'users', 'label' => __('Customers')],
    ['route' => 'vehicle-catalog.index', 'pattern' => ['vehicle-catalog.*', 'vehicle-brands.*', 'vehicle-models.*'], 'icon' => 'book-open', 'label' => __('Vehicle Catalog')],
    ['route' => 'counter-invoices.index', 'pattern' => 'counter-invoices.*', 'icon' => 'receipt-percent', 'label' => __('Counter Invoice')],
    ['route' => 'payments.index', 'pattern' => 'payments.*', 'icon' => 'banknotes', 'label' => __('Payments')],
    ['route' => 'inventory.index', 'pattern' => ['inventory.*', 'spare-parts.*', 'labour-masters.*'], 'icon' => 'cube', 'label' => __('Inventory')],
    ['route' => 'analytics.index', 'pattern' => 'analytics.*', 'icon' => 'chart-bar', 'label' => __('Analytics')],
    ['route' => 'employees.index', 'pattern' => 'employees.*', 'icon' => 'user-group', 'label' => __('Employees')],
    ['route' => 'company-profile.index', 'pattern' => 'company-profile.*', 'icon' => 'building-office-2', 'label' => __('Company Profile')],
];

$userInitials = collect(explode(' ', trim((string) Auth::user()->name)))
    ->filter(fn ($word) => $word !== '')
    ->take(2)
    ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
    ->implode('');
@endphp

<!-- Desktop Sidebar -->
<aside class="fixed inset-y-0 left-0 z-30 hidden md:flex flex-col bg-background border-r border-border transition-all duration-300 ease-in-out"
       :class="collapsed ? 'w-20' : 'w-64'">
    <!-- Sidebar Header -->
    <div class="flex items-center justify-center h-14 shrink-0 border-b border-border overflow-hidden">
        <a href="{{ route('dashboard') }}" class="flex items-center justify-center" :class="collapsed ? 'h-5' : 'h-9'">
            <x-application-logo class="h-full w-auto" />
        </a>
    </div>

    <!-- Nav Items -->
    <nav class="flex-1 overflow-y-auto py-4">
        @include('layouts.sidebar-nav', ['collapsible' => true])
    </nav>

    <!-- Footer: collapse toggle + user dropdown -->
    <div class="shrink-0 border-t border-border">
        <!-- Collapse control -->
        <div class="flex justify-center px-2 pt-2 pb-2">
            <button type="button" @click="toggleCollapsed()"
                    :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-md bg-muted text-muted-foreground hover:bg-accent hover:text-accent-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-1 transition duration-150 ease-in-out">
                <x-heroicon-o-chevron-left x-show="!collapsed" class="w-4 h-4" />
                <x-heroicon-o-chevron-right x-show="collapsed" class="w-4 h-4" />
            </button>
        </div>

        <!-- Divider -->
        <div class="border-t border-border" aria-hidden="true"></div>

        <!-- User profile -->
        <div class="p-2">
            <x-dropdown align="left" width="56" direction="up">
                <x-slot name="trigger">
                    <button type="button"
                            class="w-full flex items-center px-2 py-2 rounded-lg text-gray-700 hover:bg-accent focus:outline-none transition duration-150 ease-in-out"
                            :class="collapsed ? 'justify-center px-0' : ''">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-primary text-white text-sm font-semibold shrink-0">
                            {{ $userInitials }}
                        </span>
                        <span x-show="!collapsed" class="ms-3 flex-1 min-w-0 text-start">
                            <span class="block text-sm font-medium text-gray-800 truncate">{{ Auth::user()->name }}</span>
                            <span class="block text-xs text-gray-500 truncate">{{ Auth::user()->email }}</span>
                        </span>
                        <x-heroicon-o-chevron-down x-show="!collapsed" class="w-4 h-4 text-gray-400 shrink-0" />
                    </button>
                </x-slot>

                <x-slot name="content">
                    <x-dropdown-link :href="route('profile.edit')">
                        <span class="inline-flex items-center">
                            <x-heroicon-o-user-circle class="w-4 h-4 me-2" />
                            {{ __('Profile') }}
                        </span>
                    </x-dropdown-link>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                            <span class="inline-flex items-center">
                                <x-heroicon-o-arrow-right-start-on-rectangle class="w-4 h-4 me-2" />
                                {{ __('Log Out') }}
                            </span>
                        </x-dropdown-link>
                    </form>
                </x-slot>
            </x-dropdown>
        </div>
    </div>
</aside>

<!-- Mobile Drawer -->
<aside class="fixed inset-y-0 left-0 z-40 w-64 flex flex-col bg-background border-r border-border shadow-xl md:hidden transition-transform duration-300 ease-in-out"
       :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'">
    <!-- Drawer Header -->
    <div class="flex items-center justify-between h-14 px-4 shrink-0 border-b border-border">
        <a href="{{ route('dashboard') }}" class="flex items-center">
            <x-application-logo class="w-auto h-8" />
        </a>

        <button type="button" @click="closeMobile()"
                class="inline-flex items-center justify-center p-2 rounded-md text-muted-foreground hover:text-foreground hover:bg-accent focus:outline-none focus:bg-accent focus:text-foreground transition duration-150 ease-in-out">
            <x-heroicon-o-x-mark class="w-6 h-6" />
        </button>
    </div>

    <!-- Nav Items -->
    <nav class="flex-1 overflow-y-auto py-4">
        @include('layouts.sidebar-nav', ['collapsible' => false])
    </nav>

    <!-- Footer: user dropdown -->
    <div class="p-2 border-t border-border">
        <x-dropdown align="left" width="56" direction="up">
            <x-slot name="trigger">
                <button type="button"
                        class="w-full flex items-center px-2 py-2 rounded-lg text-gray-700 hover:bg-accent focus:outline-none transition duration-150 ease-in-out">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-primary text-white text-sm font-semibold shrink-0">
                        {{ $userInitials }}
                    </span>
                    <span class="ms-3 flex-1 min-w-0 text-start">
                        <span class="block text-sm font-medium text-gray-800 truncate">{{ Auth::user()->name }}</span>
                        <span class="block text-xs text-gray-500 truncate">{{ Auth::user()->email }}</span>
                    </span>
                    <x-heroicon-o-chevron-down class="w-4 h-4 text-gray-400 shrink-0" />
                </button>
            </x-slot>

            <x-slot name="content">
                <x-dropdown-link :href="route('profile.edit')">
                    <span class="inline-flex items-center">
                        <x-heroicon-o-user-circle class="w-4 h-4 me-2" />
                        {{ __('Profile') }}
                    </span>
                </x-dropdown-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-dropdown-link :href="route('logout')"
                            onclick="event.preventDefault(); this.closest('form').submit();">
                        <span class="inline-flex items-center">
                            <x-heroicon-o-arrow-right-start-on-rectangle class="w-4 h-4 me-2" />
                            {{ __('Log Out') }}
                        </span>
                    </x-dropdown-link>
                </form>
            </x-slot>
        </x-dropdown>
    </div>
</aside>
