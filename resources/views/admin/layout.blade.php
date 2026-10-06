<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Quản trị') · Mộc Thư</title>
    <style>
        :root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#1d2939;background:#f4f6f8;font-synthesis:none}*{box-sizing:border-box}body{margin:0}.shell{min-height:100vh}.top{height:68px;background:#fff;border-bottom:1px solid #e5e9ef;display:flex;align-items:center;justify-content:space-between;padding:0 max(24px,calc((100% - 1200px)/2));position:sticky;top:0;z-index:2}.brand{font-size:19px;font-weight:750;color:#173d35;text-decoration:none}.top-right{display:flex;align-items:center;gap:18px;color:#667085;font-size:14px}.logout{border:0;background:none;color:#475467;font:inherit;cursor:pointer}.layout{max-width:1200px;margin:auto;padding:28px 24px;display:grid;grid-template-columns:210px minmax(0,1fr);gap:28px}.nav{display:flex;flex-direction:column;gap:6px}.nav a{padding:11px 13px;border-radius:9px;color:#475467;text-decoration:none;font-size:14px}.nav a.active,.nav a:hover{background:#e7f1ed;color:#17634d;font-weight:650}.content h1{margin:0 0 7px;font-size:26px;letter-spacing:-.4px}.muted{color:#667085;font-size:14px}.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:24px 0}.card,.panel{background:#fff;border:1px solid #e8ecf0;border-radius:12px;padding:18px}.card span{font-size:13px;color:#667085}.card strong{display:block;font-size:28px;margin-top:8px}.panel{margin-top:18px}.panel h2{font-size:17px;margin:0 0 16px}.table-wrap{overflow:auto}table{border-collapse:collapse;width:100%;font-size:14px}th,td{text-align:left;padding:12px 10px;border-bottom:1px solid #edf0f2;vertical-align:top}th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#667085;font-weight:650}tr:last-child td{border-bottom:0}.button{display:inline-flex;align-items:center;justify-content:center;background:#17634d;color:#fff;border:0;border-radius:8px;padding:9px 13px;font:inherit;font-size:13px;font-weight:600;text-decoration:none;cursor:pointer}.button.secondary{background:#eef3f1;color:#17634d}.button.danger{background:#fff0ef;color:#b42318}.button.small{padding:7px 10px}.toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:20px 0}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.field{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}.field label{font-size:13px;font-weight:600;color:#344054}.field input,.field select,.field textarea{width:100%;padding:10px 11px;border:1px solid #d0d5dd;border-radius:8px;font:inherit;font-size:14px;background:#fff;color:#1d2939}.field textarea{min-height:84px;resize:vertical}.notice{padding:11px 14px;background:#eaf7ef;color:#17634d;border-radius:8px;margin:12px 0}.errors{padding:11px 14px;background:#fff0ef;color:#b42318;border-radius:8px;margin:12px 0}.inline-form{display:flex;align-items:center;gap:8px}.inline-form select{padding:7px;border:1px solid #d0d5dd;border-radius:7px;background:white}.actions{display:flex;gap:7px;align-items:center}.pagination{margin-top:18px}.pagination nav{display:flex;justify-content:space-between}.pagination a,.pagination span{padding:7px 10px;color:#475467;text-decoration:none}.login-page{min-height:100vh;display:grid;place-items:center;padding:20px}.login-card{background:white;padding:32px;border:1px solid #e8ecf0;border-radius:16px;width:min(420px,100%);box-shadow:0 14px 40px #173d3510}.login-card h1{font-size:23px;margin:0 0 8px}.login-card .button{width:100%;padding:11px}.badge{display:inline-block;padding:4px 8px;border-radius:99px;background:#eef3f1;color:#475467;font-size:12px}.badge.admin{background:#e7f1ed;color:#17634d}@media(max-width:820px){.layout{grid-template-columns:1fr;gap:12px}.nav{flex-direction:row;overflow:auto}.nav a{white-space:nowrap}.cards{grid-template-columns:repeat(2,1fr)}.top{padding:0 18px}.form-grid{grid-template-columns:1fr}}@media(max-width:480px){.layout{padding:20px 14px}.cards{gap:8px}.card{padding:13px}.card strong{font-size:23px}.top-right .identity{display:none}}
    </style>
</head>
<body>
<div class="shell">
    <header class="top">
        <a class="brand" href="{{ route('admin.dashboard') }}">Mộc Thư <span class="muted">/ Quản trị</span></a>
        <div class="top-right"><span class="identity">{{ auth()->user()->name }} · Admin</span><form method="post" action="{{ route('admin.logout') }}">@csrf<button class="logout" type="submit">Đăng xuất</button></form></div>
    </header>
    <div class="layout">
        <nav class="nav" aria-label="Điều hướng quản trị">
            <a class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Tổng quan</a>
            <a class="{{ request()->routeIs('admin.books*') ? 'active' : '' }}" href="{{ route('admin.books') }}">Quản lý sách</a>
            <a class="{{ request()->routeIs('admin.users*') ? 'active' : '' }}" href="{{ route('admin.users') }}">Tài khoản</a>
        </nav>
        <main class="content">
            @if (session('status'))<div class="notice">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="errors"><ul style="margin:0;padding-left:20px">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
