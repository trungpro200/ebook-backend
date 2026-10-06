@extends('admin.layout')

@section('title', 'Tổng quan')

@section('content')
    <h1>Tổng quan hệ thống</h1>
    <p class="muted">Tình hình nội dung và tài khoản trên Mộc Thư.</p>
    <section class="cards">
        <div class="card"><span>Sách</span><strong>{{ number_format($bookCount) }}</strong></div>
        <div class="card"><span>Chương</span><strong>{{ number_format($chapterCount) }}</strong></div>
        <div class="card"><span>Tài khoản</span><strong>{{ number_format($userCount) }}</strong></div>
        <div class="card"><span>Thể loại</span><strong>{{ number_format($categoryCount) }}</strong></div>
    </section>
    <section class="panel">
        <div class="toolbar" style="margin-top:0"><h2>Sách mới cập nhật</h2><a class="button secondary" href="{{ route('admin.books') }}">Quản lý sách</a></div>
        <div class="table-wrap"><table><thead><tr><th>Tên sách</th><th>Tác giả</th><th>Thể loại</th><th>Số chương</th><th>Cập nhật</th></tr></thead><tbody>
        @forelse ($recentBooks as $book)
            <tr><td><strong>{{ $book->title }}</strong><div class="muted">#{{ $book->id }} · {{ strtoupper($book->language) }}</div></td><td>{{ $book->author_name ?: $book->author?->name }}</td><td>{{ $book->category?->name }}</td><td>{{ $book->chapters_count }}</td><td>{{ $book->updated_at->format('d/m/Y H:i') }}</td></tr>
        @empty<tr><td colspan="5" class="muted">Chưa có sách.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    <section class="panel">
        <div class="toolbar" style="margin-top:0"><h2>Tài khoản mới</h2><a class="button secondary" href="{{ route('admin.users') }}">Quản lý tài khoản</a></div>
        <div class="table-wrap"><table><thead><tr><th>Tên</th><th>Email</th><th>Vai trò</th><th>Ngày tạo</th></tr></thead><tbody>
        @foreach ($recentUsers as $user)<tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td><span class="badge {{ $user->role === 'admin' ? 'admin' : '' }}">{{ $user->role }}</span></td><td>{{ $user->created_at->format('d/m/Y') }}</td></tr>@endforeach
        </tbody></table></div>
    </section>
@endsection
