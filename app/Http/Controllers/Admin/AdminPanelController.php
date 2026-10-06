<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminPanelController extends Controller
{
    public function loginForm(): View
    {
        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email hoặc mật khẩu không chính xác.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        if ($request->user()->role !== User::ROLE_ADMIN) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('admin.login')->withErrors(['email' => 'Tài khoản không có quyền quản trị.']);
        }

        return to_route('admin.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('admin.login');
    }

    public function dashboard(): View
    {
        return view('admin.dashboard', [
            'bookCount' => Book::query()->count(),
            'chapterCount' => Chapter::query()->count(),
            'userCount' => User::query()->count(),
            'categoryCount' => Category::query()->count(),
            'recentBooks' => Book::query()->with(['category', 'author'])->withCount('chapters')->latest()->limit(8)->get(),
            'recentUsers' => User::query()->latest()->limit(6)->get(),
        ]);
    }

    public function books(): View
    {
        return view('admin.books', [
            'books' => Book::query()->with(['category', 'author'])->withCount('chapters')->latest()->paginate(15),
            'categories' => Category::query()->orderBy('name')->get(),
            'authors' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function storeBook(Request $request): RedirectResponse
    {
        $validated = $this->validatedBook($request);
        Book::query()->create($validated);

        return to_route('admin.books')->with('status', 'Đã thêm sách.');
    }

    public function updateBook(Request $request, Book $book): RedirectResponse
    {
        $book->update($this->validatedBook($request));

        return to_route('admin.books')->with('status', 'Đã cập nhật sách.');
    }

    public function deleteBook(Book $book): RedirectResponse
    {
        $book->delete();

        return to_route('admin.books')->with('status', 'Đã xóa sách và các chương liên quan.');
    }

    public function users(): View
    {
        return view('admin.users', [
            'users' => User::query()->withCount('books')->latest()->paginate(20),
            'adminCount' => User::query()->where('role', User::ROLE_ADMIN)->count(),
        ]);
    }

    public function updateUserRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in([User::ROLE_READER, User::ROLE_ADMIN])],
        ]);

        if ($user->is($request->user()) && $validated['role'] !== User::ROLE_ADMIN) {
            return back()->withErrors(['role' => 'Bạn không thể tự gỡ quyền quản trị của mình.']);
        }

        if ($user->role === User::ROLE_ADMIN && $validated['role'] !== User::ROLE_ADMIN && User::query()->where('role', User::ROLE_ADMIN)->count() <= 1) {
            return back()->withErrors(['role' => 'Hệ thống cần ít nhất một tài khoản quản trị.']);
        }

        $user->role = $validated['role'];
        $user->save();

        return to_route('admin.users')->with('status', 'Đã cập nhật quyền tài khoản.');
    }

    /** @return array{category_id: int, author_id: int, author_name: ?string, title: string, description: ?string, cover: ?string, language: string, status: string} */
    private function validatedBook(Request $request): array
    {
        return $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'author_id' => ['required', 'integer', 'exists:users,id'],
            'author_name' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cover' => ['nullable', 'string', 'max:255'],
            'language' => ['required', 'string', 'max:50'],
            'status' => ['required', 'string', 'max:50'],
        ]);
    }
}
