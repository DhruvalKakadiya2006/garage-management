<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div x-data="sidebar()" x-cloak class="min-h-screen bg-muted">
            @include('layouts.navigation')

            <!-- Mobile backdrop -->
            <div x-show="mobileOpen" x-transition.opacity @click="closeMobile()"
                 class="fixed inset-0 z-30 bg-gray-900/50 md:hidden"></div>

            <!-- Main column -->
            <div class="min-h-screen flex flex-col transition-all duration-300 ease-in-out"
                 :class="collapsed ? 'md:ml-20' : 'md:ml-64'">

                <!-- Slim top bar: page title only -->
                <header class="sticky top-0 z-20 bg-background border-b border-border">
                    <div class="flex items-center px-4 sm:px-6 lg:px-8 min-h-[3.5rem]">
                        <button type="button" @click="openMobile()"
                                class="me-3 -ms-2 inline-flex items-center justify-center p-2 rounded-md text-muted-foreground hover:text-foreground hover:bg-accent md:hidden focus:outline-none focus:bg-accent focus:text-foreground transition duration-150 ease-in-out">
                            <x-heroicon-o-bars-3 class="w-6 h-6" />
                        </button>

                        <div class="flex-1 min-w-0">
                            @isset($header)
                                {{ $header }}
                            @endisset
                        </div>
                    </div>
                </header>

                <!-- Page Content -->
                <main class="flex-1">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
