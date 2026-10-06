@extends('admin.layout')

@section('title', 'Quản lý tài khoản')

@section('content')
    <h1>Quản lý tài khoản</h1>
    <p class="muted">Có {{ number_format($adminCount) }} tài khoản quản trị. Hệ thống luôn giữ ít nhất một admin.</p>
    <section class="panel">
        <div class="table-wrap"><table><thead><tr><th>Tài khoản</th><th>Sách sở hữu</th><th>Ngày tham gia</th><th>Vai trò</th><th>Cập nhật quyền</th></tr></thead><tbody>
        @forelse ($users as $user)
            <tr>
                <td><strong>{{ $user->name }}</strong><div class="muted">{{ $user->email }} · #{{ $user->id }}</div></td>
                <td>{{ $user->books_count }}</td><td>{{ $user->created_at->format('d/m/Y') }}</td>
                <td><span class="badge {{ $user->role === 'admin' ? 'admin' : '' }}">{{ $user->role }}</span></td>
                <td><form class="inline-form" method="post" action="{{ route('admin.users.role', $user) }}">@csrf @method('patch')
                    <select name="role" aria-label="Vai trò của {{ $user->name }}"><option value="reader" @selected($user->role === 'reader')>reader</option><option value="admin" @selected($user->role === 'admin')>admin</option></select>
                    <button class="button secondary small" type="submit">Lưu</button>
                </form></td>
            </tr>
        @empty<tr><td colspan="5" class="muted">Chưa có tài khoản.</td></tr>@endforelse
        </tbody></table></div>
        <div class="pagination">{{ $users->links() }}</div>
    </section>
@endsection
