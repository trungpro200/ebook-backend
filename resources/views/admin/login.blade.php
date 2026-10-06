<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập quản trị · Mộc Thư</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f4f6f8;color:#1d2939;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}.login-page{min-height:100vh;display:grid;place-items:center;padding:20px}.login-card{background:#fff;padding:32px;border:1px solid #e8ecf0;border-radius:16px;width:min(420px,100%);box-shadow:0 14px 40px #173d3510}.brand{font-weight:750;color:#17634d;font-size:14px}.login-card h1{font-size:24px;margin:20px 0 7px}.muted{color:#667085;font-size:14px}.field{display:flex;flex-direction:column;gap:6px;margin:19px 0}.field label{font-size:13px;font-weight:600}.field input{padding:11px;border:1px solid #d0d5dd;border-radius:8px;font:14px inherit}.button{width:100%;background:#17634d;color:white;border:0;border-radius:8px;padding:12px;font:600 14px inherit;cursor:pointer}.error{background:#fff0ef;color:#b42318;padding:10px;border-radius:8px;font-size:13px;margin:15px 0}
    </style>
</head>
<body><main class="login-page"><section class="login-card">
    <div class="brand">MỘC THƯ · KHU VỰC QUẢN TRỊ</div>
    <h1>Đăng nhập</h1><p class="muted">Đăng nhập bằng tài khoản có quyền quản trị hệ thống.</p>
    @if ($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('admin.login.submit') }}">
        @csrf
        <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus></div>
        <div class="field"><label for="password">Mật khẩu</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
        <button class="button" type="submit">Vào trang quản trị</button>
    </form>
</section></main></body>
</html>
