@props(['title' => 'Quản trị'])
@php
    $user = auth()->user();
    $section = match (true) {
        request()->routeIs('admin.submissions.*') => 'submissions',
        request()->routeIs('admin.dealer-registrations.*') => 'registrations',
        request()->routeIs('admin.dealers.*') => 'dealers',
        request()->routeIs('admin.sales.*') => 'sales',
        request()->routeIs('admin.awards.*') => 'awards',
        default => 'overview',
    };
    $sections = ['overview' => 'Tổng quan', 'submissions' => 'Duyệt khách hàng', 'registrations' => 'Duyệt tài khoản', 'dealers' => 'Đại lý', 'sales' => 'Sales', 'awards' => 'Vinh danh'];
    // Queue counts, not demo-filtered metrics or inferred workflow stages.
    $queueCounts = [
        'submissions' => \App\Models\CustomerSubmission::where('status', 'pending')->count(),
        'registrations' => \App\Models\User::where('role', 'dealer')->where('status', 'pending')->count(),
    ];
@endphp
<!doctype html>
<html lang="vi"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Toyota Veloz &amp; Hilux</title>
    <link rel="stylesheet" href="{{ asset('dealer-registration/lookup-fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/admin.css', 'resources/js/admin.js'])
</head><body class="ad-admin">
    <div class="ad-viewport"><div class="ad-shell">
        <x-admin.sidebar :user="$user" :section="$section" :counts="$queueCounts" />
        <div class="ad-workspace"><x-admin.topbar :breadcrumb="'Quản trị / '.$sections[$section]" /><main id="admin-content" class="ad-content">{{ $slot }}</main></div>
    </div></div>
    @stack('admin-overlays')
</body></html>
