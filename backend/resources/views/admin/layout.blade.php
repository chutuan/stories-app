<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Quản trị') · Stories Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f7; }
        .sidebar { min-height: 100vh; background: #1f1a33; }
        .sidebar .nav-link { color: #cfc9e6; border-radius: .5rem; }
        .sidebar .nav-link:hover { background: #2c2547; color: #fff; }
        .sidebar .nav-link.active { background: #534ab7; color: #fff; }
        .brand { color: #fff; font-weight: 700; letter-spacing: .5px; }
        .thumb { width: 44px; height: 60px; object-fit: cover; border-radius: 4px; background:#e6e2f2; }
        .thumb-lg { max-width: 160px; border-radius: 8px; }
    </style>
</head>
<body>
@auth
<div class="container-fluid">
    <div class="row">
        <nav class="col-md-3 col-lg-2 d-md-block sidebar p-3">
            <a href="{{ route('admin.dashboard') }}" class="brand d-block fs-4 mb-4 text-decoration-none">📚 Stories</a>
            <ul class="nav flex-column gap-1">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Bảng điều khiển</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.stories.*') ? 'active' : '' }}" href="{{ route('admin.stories.index') }}">Truyện</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}">Thể loại</a>
                </li>
            </ul>
            <hr class="text-secondary">
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button class="btn btn-outline-light btn-sm w-100">Đăng xuất</button>
            </form>
        </nav>

        <main class="col-md-9 col-lg-10 ms-sm-auto px-md-4 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">@yield('heading', 'Quản trị')</h1>
                <div>@yield('actions')</div>
            </div>

            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
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
</body>
</html>
