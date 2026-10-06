<div class="form-grid">
    <div class="field"><label>Tiêu đề</label><input name="title" value="{{ $book ? $book->title : old('title') }}" maxlength="255" required></div>
    <div class="field"><label>Ngôn ngữ</label><input name="language" value="{{ $book ? $book->language : old('language', 'vi') }}" maxlength="50" required></div>
    <div class="field"><label>Thể loại</label><select name="category_id" required><option value="">Chọn thể loại</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) ($book?->category_id ?? old('category_id')) === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
    <div class="field"><label>Tài khoản sở hữu</label><select name="author_id" required><option value="">Chọn tài khoản</option>@foreach ($authors as $author)<option value="{{ $author->id }}" @selected((string) ($book?->author_id ?? old('author_id')) === (string) $author->id)>{{ $author->name }} ({{ $author->email }})</option>@endforeach</select></div>
    <div class="field"><label>Tác giả hiển thị trong sách</label><input name="author_name" value="{{ $book ? $book->author_name : old('author_name') }}" maxlength="255"></div>
    <div class="field"><label>Trạng thái</label><input name="status" value="{{ $book ? $book->status : old('status', 'ongoing') }}" maxlength="50" required></div>
    <div class="field"><label>Ảnh bìa (URL hoặc đường dẫn)</label><input name="cover" value="{{ $book ? $book->cover : old('cover') }}" maxlength="255"></div>
</div>
<div class="field"><label>Mô tả</label><textarea name="description">{{ $book ? $book->description : old('description') }}</textarea></div>
<button class="button small" type="submit">{{ $submitLabel }}</button>
