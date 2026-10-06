@extends('admin.layout')

@section('title', 'Quản lý sách')

@section('content')
    <h1>Quản lý sách</h1>
    <p class="muted">Tạo, cập nhật và xóa sách trong thư viện backend.</p>
    <section class="panel">
        <h2>Thêm sách</h2>
        <form method="post" action="{{ route('admin.books.store') }}">
            @csrf
            @include('admin.book-fields', ['book' => null, 'submitLabel' => 'Thêm sách'])
        </form>
    </section>
    <section class="panel">
        <h2>Danh sách sách <span class="muted">({{ $books->total() }})</span></h2>
        <div class="table-wrap"><table><thead><tr><th>Sách</th><th>Chỉnh sửa</th><th></th></tr></thead><tbody>
        @forelse ($books as $book)
            <tr>
                <td style="min-width:200px"><strong>{{ $book->title }}</strong><div class="muted">{{ $book->author_name ?: $book->author?->name }} · {{ $book->category?->name }} · {{ $book->chapters_count }} chương</div></td>
                <td style="min-width:360px">
                    <form method="post" action="{{ route('admin.books.update', $book) }}">
                        @csrf @method('put')
                        @include('admin.book-fields', ['book' => $book, 'submitLabel' => 'Lưu thay đổi'])
                    </form>
                </td>
                <td><form method="post" action="{{ route('admin.books.delete', $book) }}" onsubmit="return confirm('Xóa sách này và toàn bộ chương của sách?')">@csrf @method('delete')<button class="button danger small" type="submit">Xóa</button></form></td>
            </tr>
        @empty<tr><td colspan="3" class="muted">Chưa có sách.</td></tr>@endforelse
        </tbody></table></div>
        <div class="pagination">{{ $books->links() }}</div>
    </section>
@endsection
