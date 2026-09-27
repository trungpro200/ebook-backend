# Mộc Thư backend

## Xác thực và phân quyền

Chạy `composer install` và `php artisan migrate` trước khi khởi động API. Bộ dependency hiện tại cần PHP 8.4 trở lên; môi trường kiểm thử dùng PHP 8.5.

| Endpoint | Quyền | Dữ liệu |
| --- | --- | --- |
| `POST /api/register` | Khách | `name`, `email`, `password`, `password_confirmation` |
| `POST /api/login` | Khách | `email`, `password` |
| `GET /api/me` | Đã đăng nhập | Header `Authorization: Bearer <token>` |
| `POST /api/logout` | Đã đăng nhập | Thu hồi token đang dùng, trả HTTP 204 |
| `GET /api/books`, `GET /api/books/{id}` | Công khai | Đọc sách |
| `POST /api/books`, `PUT/PATCH/DELETE /api/books/{id}` | `admin` | Quản lý sách |

Đăng ký/đăng nhập trả `{ token, token_type, user: { id, name, email, role } }`. Token Sanctum hết hạn sau 30 ngày. Sai dữ liệu trả 422; thiếu/sai/hết hạn token trả 401; thiếu quyền trả 403; quá giới hạn đăng nhập/đăng ký trả 429. Email được chuẩn hóa về chữ thường. Mật khẩu đăng ký dài 8–72 ký tự và phải xác nhận khớp.

Mọi tài khoản mặc định là `reader`; API đăng ký không cho chỉ định `role`. Sau khi đăng ký tài khoản của mình, cấp hoặc thu hồi quyền bằng lệnh:

```sh
php artisan users:set-role your-email@example.com admin
php artisan users:set-role your-email@example.com reader
```

Đổi vai trò thu hồi tất cả token của tài khoản đó, yêu cầu đăng nhập lại. Không có tài khoản admin được tạo tự động.

```sh
php artisan serve --host=0.0.0.0 --port=8000
php artisan test --compact tests/Feature/AuthTest.php tests/Feature/RbacTest.php
```

Trên điện thoại, đặt `EXPO_PUBLIC_API_URL=http://<IP-LAN-máy-chủ>:8000/api` ở frontend. Dùng HTTPS khi triển khai thực tế. Có thể dọn token hết hạn định kỳ bằng `php artisan sanctum:prune-expired`.

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
