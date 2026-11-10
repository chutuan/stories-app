<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Quản trị') · TunaStory Admin</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/icon-32.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    {{-- ?v= theo giờ sửa file: deploy CSS mới là trình duyệt tải lại ngay, không cần xoá cache. --}}
    <link href="/css/admin.css?v={{ @filemtime(public_path('css/admin.css')) ?: 1 }}" rel="stylesheet">
    @stack('head')
</head>
<body>
@auth
@php
    $user = auth()->user();
    // Nhóm menu: [nhãn nhóm => [[route, khớp, icon, tên], ...]]
    $nav = [
        null => [
            ['admin.dashboard', 'admin.dashboard', 'grid-1x2', 'Tổng quan'],
        ],
        'Nội dung' => [
            ['admin.stories.index', 'admin.stories.*', 'book', 'Truyện'],
            ['admin.queue.index', 'admin.queue.*', 'inboxes', 'Kho chờ đăng'],
            ['admin.categories.index', 'admin.categories.*', 'tags', 'Thể loại'],
        ],
        'Người đọc' => [
            ['admin.votes.index', 'admin.votes.*', 'emoji-smile', 'Phiếu đánh giá'],
            ['admin.audio.index', 'admin.audio.*', 'headphones', 'Yêu cầu audio'],
        ],
        'Hệ thống' => [
            ['admin.settings.index', 'admin.settings.*', 'sliders', 'Cấu hình app'],
            ['admin.security', 'admin.security*', 'shield-lock', 'Bảo mật'],
        ],
    ];
@endphp
<div class="admin-shell">
    <aside class="admin-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="adminNav" aria-label="Menu quản trị">
        <div class="offcanvas-body">
            <div class="d-flex align-items-start justify-content-between">
                <a href="{{ route('admin.dashboard') }}" class="brand">
                    <img src="/icons/icon-192.png" alt="">
                    <span>
                        <span class="brand-name d-block">TunaStory</span>
                        <span class="brand-sub">Quản trị nội dung</span>
                    </span>
                </a>
                <button type="button" class="btn-close d-lg-none mt-2" data-bs-dismiss="offcanvas" data-bs-target="#adminNav" aria-label="Đóng menu"></button>
            </div>

            <nav>
                @foreach ($nav as $group => $items)
                    @if ($group)
                        <div class="nav-group-label">{{ $group }}</div>
                    @endif
                    @foreach ($items as [$route, $match, $icon, $label])
                        <a href="{{ route($route) }}" @class(['side-link', 'active' => request()->routeIs($match)])
                           @if (request()->routeIs($match)) aria-current="page" @endif>
                            <i class="bi bi-{{ $icon }}"></i>
                            <span>{{ $label }}</span>
                        </a>
                    @endforeach
                @endforeach
            </nav>

            <div class="side-foot">
                <div class="side-user">
                    <div class="avatar">{{ mb_strtoupper(mb_substr($user->name ?: $user->email, 0, 1)) }}</div>
                    <div class="min-w-0" style="min-width:0">
                        <div class="side-user-email" title="{{ $user->email }}">{{ $user->email }}</div>
                        <div class="side-user-meta">
                            @if ($user->hasTwoFactorEnabled())
                                <i class="bi bi-shield-check"></i> Đã bật 2FA
                            @else
                                <i class="bi bi-shield-exclamation" style="color:#b86e00"></i> Chưa bật 2FA
                            @endif
                        </div>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="side-link w-100 border-0 bg-transparent text-start">
                        <i class="bi bi-box-arrow-right"></i><span>Đăng xuất</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <button class="nav-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminNav" aria-controls="adminNav" aria-label="Mở menu">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div style="min-width:0">
                @hasSection('crumb')
                    <div class="crumb">@yield('crumb')</div>
                @endif
                <h1>@yield('heading', 'Quản trị')</h1>
            </div>
            @hasSection('actions')
                <div class="topbar-actions">@yield('actions')</div>
            @endif
        </header>

        <main class="admin-content">
            @if (session('status'))
                <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div>{{ session('status') }}</div></div>
            @endif
            @if (session('warning'))
                <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill"></i><div>{{ session('warning') }}</div></div>
            @endif
            {{-- Vài thao tác trả lỗi bằng with('error') (vd. tạo audio cho chương đã có audio) — trước
                 đây không trang nào in ra nên bấm xong không thấy gì. --}}
            @if (session('error'))
                <div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i><div>{{ session('error') }}</div></div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    <i class="bi bi-x-circle-fill"></i>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
@else
    @yield('content')
@endauth
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
