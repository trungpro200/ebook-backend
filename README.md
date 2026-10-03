# Mộc Thư backend

## Khởi động

Giả sử `.env` đã được cấu hình và `php`, `python`, `composer`, `ffmpeg` đã có trong `PATH`. Thiết lập lần đầu trong thư mục `ebook-backend`:

```powershell
composer install
php artisan migrate
php artisan storage:link
.\setup-tts.ps1
```

Mỗi lệnh sau chạy ở một terminal riêng trong `ebook-backend`:

- API: `php artisan serve --host=0.0.0.0 --port=8000`
- TTS: `.\.venv-tts\Scripts\python.exe -m uvicorn tts_server:app --host 127.0.0.1 --port 8765`
- Worker: `php artisan queue:work database --queue=tts --timeout=1800`

`queue:work` chạy liên tục và thường không in gì khi đang chờ tác vụ; cứ để terminal đó mở. Nhấn `Ctrl+C` để dừng. Nếu dừng TTS server giữa lúc tạo audio, hãy khởi động lại TTS và worker rồi nhấn “Thử lại” trong ứng dụng.

Trên điện thoại, đặt `EXPO_PUBLIC_API_URL=http://<IP-LAN-máy-chủ>:8000/api` ở frontend.

## Xác thực và phân quyền

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

Đổi vai trò thu hồi tất cả token của tài khoản đó, yêu cầu đăng nhập lại. Migration tạo một tài khoản admin riêng cho việc nhập Standard Ebooks với mật khẩu ngẫu nhiên không được cung cấp; tài khoản admin để đăng nhập vẫn cần được tạo và cấp quyền riêng.

Có thể dọn token hết hạn định kỳ bằng `php artisan sanctum:prune-expired`.

## Nhập sách Standard Ebooks

`php artisan migrate` tạo tài khoản admin `standard-ebooks-import@mocthu.invalid` để sở hữu sách nhập. Tên tác giả hiển thị vẫn là tác giả gốc. Nhập tối đa 100 sách và kiểm tra tiến độ:

```powershell
php artisan books:import-standard-ebooks --limit=100
php artisan books:import-standard-ebooks --status
```

`--limit` là số sách hợp lệ cần có sau khi chạy (mặc định 10, tối đa 1000), không phải số sách mới tải trong mỗi lần chạy. Lệnh lấy các tựa bổ sung từ sitemap công khai của Standard Ebooks, chỉ chọn tác giả trong danh sách đã kiểm tra và bỏ qua bản dịch chưa được xét quyền riêng. Nếu một tựa lỗi, lệnh thử tựa tiếp theo cho đến khi đạt mục tiêu hoặc hết danh sách. Khi Standard Ebooks trả HTTP 429, lệnh dừng ngay để tôn trọng giới hạn tải; đợi rồi chạy lại đúng lệnh trên để tiếp tục. `--delay-ms` mặc định 40000 ms giữa hai lượt tải sách mới để giảm nguy cơ bị giới hạn. Sách đã nhập sẽ báo `already present` và không bị tạo trùng.

## Đọc sách bằng Kokoro 82M

TTS chỉ hỗ trợ sách tiếng Anh. Khi người đọc mở “Nghe AI Voice”, API tạo đoạn đang nghe và tối đa 8 đoạn kế tiếp; khi tua sang vị trí khác, cửa sổ tạo audio chuyển theo. Ứng dụng phát ngay khi đoạn đầu sẵn sàng, cho tua trên toàn chương và tô sáng từ đang đọc. Kokoro cung cấp mốc âm vị để đồng bộ từ; nếu văn bản không khớp mốc (ví dụ số hoặc viết tắt), ứng dụng ghi rõ phần tô sáng chỉ là ước lượng. Cache nằm ở `storage/app/private/tts/<BookID>_<BookName>/<ChapterID>_<ChapterName>/mp3` và `json`. File cache cũ dạng phẳng được chuyển sang cấu trúc này khi truy cập. Khóa cache gồm nội dung chương, giọng đọc và phiên bản mô hình, nên sửa nội dung sẽ tạo file mới. Python server chỉ lắng nghe `127.0.0.1:8765` và nhận yêu cầu có token do Laravel gửi.

`setup-tts.ps1` tải Kokoro 82M ONNX và giọng `af_heart` vào `.tts-models`, tạo token kết nối nội bộ trong `.env` nếu thiếu. Sau lần tạo audio đầu tiên, `http://127.0.0.1:8765/health` sẽ báo `CUDAExecutionProvider` khi GPU hoạt động. Khi đổi `.env`, khởi động lại worker.

API công khai: `POST /api/chapters/{id}/audio` nhận `{"index": 0}` để tạo từ đoạn cần nghe; `GET /api/chapters/{id}/audio` trả `queued`, `processing`, `paused` hoặc `ready` kèm tiến độ và URL của từng đoạn đã sẵn sàng. URL file hỗ trợ HTTP Range để tua. Yêu cầu trùng nhau dùng chung một tác vụ và file cache. Sách ngôn ngữ khác tiếng Anh trả 422.

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
