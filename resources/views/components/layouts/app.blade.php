<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#123c35">
    <meta name="description" content="Ruang Stok membantu Anda mencatat inventaris, memantau persediaan, dan mengelola barang dalam satu ruang kerja yang rapi.">
    <meta name="robots" content="index,follow">
    <title>{{ $title ?? 'Ruang Stok · Manajemen Inventaris' }}</title>
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body x-data="{ sidebarOpen: false, wide: window.innerWidth >= 1024 }" @resize.window.debounce="wide = window.innerWidth >= 1024; if (wide) sidebarOpen = false" @keydown.escape.window="sidebarOpen = false" class="bg-stone-50 text-slate-800 antialiased {{ request()->routeIs('login', 'register') ? 'login-page' : '' }} {{ request()->routeIs('register') ? 'register-page' : '' }}">
    <a class="skip-link" href="#main-content">Lewati ke konten utama</a>
    @auth
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" class="fixed inset-0 z-30 bg-slate-950/50 lg:hidden"></div>
        <aside :inert="!wide &amp;&amp; !sidebarOpen" :aria-hidden="!wide &amp;&amp; !sidebarOpen" x-trap.inert.noscroll="sidebarOpen &amp;&amp; !wide" id="sidebar" :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="sidebar lg:translate-x-0" aria-label="Sidebar">
            <a href="{{ route('home') }}" class="brand">
                <svg width="36" height="36" viewBox="0 0 36 36" fill="none" aria-hidden="true"><rect width="36" height="36" rx="10" fill="#d9eea4"/><path d="m9 13 9-5 9 5v11l-9 5-9-5V13Z" stroke="#123c35" stroke-width="2"/><path d="m9 13 9 5 9-5M18 18v11m-4-18 9 5" stroke="#123c35" stroke-width="2"/></svg>
                <span>ruang<span class="font-normal text-emerald-200">stok.</span><small>INVENTORY WORKSPACE</small></span>
            </a>
            <button type="button" @click="sidebarOpen = false" class="absolute right-4 top-7 lg:hidden text-white p-2" aria-label="Tutup navigasi">✕</button>
            <p class="sidebar-label">RUANG KERJA</p>
            <nav aria-label="Navigasi utama" class="space-y-2">
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.users') }}" @class(['nav-link','active' => request()->routeIs('admin.*')]) @if(request()->routeIs('admin.*')) aria-current="page" @endif><span aria-hidden="true">▦</span> Pengguna & inventaris</a>
                @else
                    <a href="{{ route('stocks.index') }}" @class(['nav-link','active' => request()->routeIs('stocks.*')]) @if(request()->routeIs('stocks.*')) aria-current="page" @endif><span aria-hidden="true">▦</span> Inventaris saya</a>
                @endif
                <a href="{{ route('profile') }}" @class(['nav-link','active' => request()->routeIs('profile')]) @if(request()->routeIs('profile')) aria-current="page" @endif><span aria-hidden="true">◎</span> Profil & pengaturan</a>
            </nav>
            <div class="sidebar-note"><span class="text-lime-200 text-sm">● &nbsp; Lebih teratur, lebih tenang.</span><p>Persediaan yang tercatat adalah awal keputusan yang tepat.</p></div>
            <div class="sidebar-user">
                <img src="{{ auth()->user()->profilePhotoUrl() }}" alt="" width="40" height="40" class="h-10 w-10 rounded-full object-cover bg-emerald-900">
                <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-white">{{ auth()->user()->name }}</p><p class="truncate text-xs text-emerald-100">{{ auth()->user()->isAdmin() ? 'Administrator' : 'Member workspace' }}</p></div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="p-2 text-emerald-100 hover:text-white" aria-label="Keluar dari akun" title="Keluar"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M9 5H4v14h5m5-14 7 7-7 7m-6-7h13"/></svg></button></form>
            </div>
        </aside>
        <div class="lg:pl-64 min-h-screen">
            <header class="topbar">
                <div class="flex items-center gap-3"><button type="button" @click="sidebarOpen = !sidebarOpen" :aria-expanded="sidebarOpen" aria-controls="sidebar" aria-label="Buka navigasi" class="btn-icon lg:hidden">☰</button><span class="text-sm text-slate-500">Workspace <span class="mx-3 text-stone-300">/</span> <span class="text-slate-800 font-medium">{{ request()->routeIs('admin.*') ? 'Administrasi' : (request()->routeIs('profile') ? 'Profil' : 'Inventaris') }}</span></div>
                <span class="hidden sm:inline-flex items-center gap-2 text-xs font-medium text-slate-600"><span class="h-2 w-2 rounded-full bg-emerald-600" aria-hidden="true"></span> {{ now()->locale('id')->translatedFormat('d F Y') }}</span>
            </header>
            <main id="main-content" class="mx-auto max-w-screen-2xl p-5 sm:p-8 lg:p-10">
                @if(session('success'))<div role="status" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('success') }}</div>@endif
                {{ $slot }}
            </main>
            <footer class="px-6 pb-6 text-center text-xs text-slate-500">Ruang Stok <span aria-hidden="true">·</span> PABWE 2026</footer>
        </div>
    @else
        <main id="main-content" @class(['login-page-main' => request()->routeIs('login', 'register'), 'min-h-screen flex flex-col items-center justify-center p-5 sm:p-10' => !request()->routeIs('login', 'register')])>
            @unless(request()->routeIs('login', 'register'))
                <a href="{{ route('home') }}" class="mb-8 text-2xl font-bold tracking-tight text-emerald-950">ruang<span class="font-normal">stok.</span></a>
            @endunless
            {{ $slot }}
            @unless(request()->routeIs('login', 'register'))
                <p class="mt-8 text-xs text-slate-500">Inventaris tertata. Aktivitas lebih bermakna.</p>
            @endunless
        </main>
    @endauth
    @livewireScripts
</body>
</html>
