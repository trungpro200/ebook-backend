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
        <div class="table-wrap">
            <table>
                <thead><tr><th>Sách</th><th>Tác giả</th><th>Thể loại</th><th>Chương</th><th></th></tr></thead>
                <tbody>
                @forelse ($books as $book)
                    <tr>
                        <td style="min-width:210px"><strong>{{ $book->title }}</strong><div class="muted">#{{ $book->id }} · {{ strtoupper($book->language) }}</div></td>
                        <td>{{ $book->author_name ?: $book->author?->name }}</td>
                        <td>{{ $book->category?->name }}</td>
                        <td>{{ $book->chapters_count }}</td>
                        <td><button class="button secondary small" type="button" data-modal-trigger onclick="document.getElementById('book-modal-{{ $book->id }}').showModal()">Chi tiết</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Chưa có sách.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @foreach ($books as $book)
            <dialog class="book-modal" id="book-modal-{{ $book->id }}" aria-labelledby="book-modal-title-{{ $book->id }}">
                <div class="book-modal-content">
                    <div class="book-modal-header">
                        <div><h2 id="book-modal-title-{{ $book->id }}">Chi tiết sách</h2><p class="muted">Cập nhật thông tin “{{ $book->title }}”</p></div>
                        <form method="dialog"><button class="button secondary small" type="submit" aria-label="Đóng">Đóng</button></form>
                    </div>
                    <form method="post" action="{{ route('admin.books.update', $book) }}">
                        @csrf @method('put')
                        @include('admin.book-fields', ['book' => $book, 'submitLabel' => 'Lưu thay đổi'])
                    </form>
                    <div class="book-modal-footer">
                        <form method="post" action="{{ route('admin.books.delete', $book) }}" onsubmit="return confirm('Xóa sách này và toàn bộ chương của sách?')">
                            @csrf @method('delete')
                            <button class="button danger small" type="submit">Xóa sách</button>
                        </form>
                        <form method="dialog"><button class="button secondary small" type="submit">Đóng</button></form>
                    </div>
                </div>
            </dialog>
        @endforeach

        <div class="pagination">{{ $books->links() }}</div>
    </section>
@endsection
