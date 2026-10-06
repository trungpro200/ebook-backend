<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_are_backend_routes_and_require_an_admin_session(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/admin/login')->assertOk()->assertSee('KHU VỰC QUẢN TRỊ');

        $reader = User::factory()->create(['email' => 'reader@example.test', 'password' => 'password']);
        $this->post('/admin/login', ['email' => $reader->email, 'password' => 'password'])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertOk()->assertSee('Tổng quan hệ thống');
        $this->get('/admin/books')->assertOk()->assertSee('Quản lý sách');
        $this->get('/admin/users')->assertOk()->assertSee('Quản lý tài khoản');
        $this->actingAs($reader)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_create_update_and_delete_books_from_the_backend_panel(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $category = Category::query()->create(['name' => 'Tiểu thuyết']);
        $this->actingAs($admin);

        $this->post('/admin/books', [
            'category_id' => $category->id,
            'author_id' => $admin->id,
            'author_name' => 'Tác giả gốc',
            'title' => 'Sách thử',
            'language' => 'vi',
            'status' => 'completed',
        ])->assertRedirect(route('admin.books'));

        $book = Book::query()->where('title', 'Sách thử')->firstOrFail();
        $this->assertSame('Tác giả gốc', $book->author_name);
        $this->put("/admin/books/{$book->id}", [
            'category_id' => $category->id,
            'author_id' => $admin->id,
            'author_name' => 'Tên tác giả',
            'title' => 'Sách đã sửa',
            'language' => 'vi',
            'status' => 'ongoing',
        ])->assertRedirect(route('admin.books'));
        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => 'Sách đã sửa']);

        $this->delete("/admin/books/{$book->id}")->assertRedirect(route('admin.books'));
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_admin_can_change_roles_but_cannot_remove_the_last_admin(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $reader = User::factory()->create();
        $this->actingAs($admin);

        $this->patch("/admin/users/{$reader->id}/role", ['role' => User::ROLE_ADMIN])
            ->assertRedirect(route('admin.users'));
        $this->assertSame(User::ROLE_ADMIN, $reader->fresh()->role);

        $this->patch("/admin/users/{$admin->id}/role", ['role' => User::ROLE_READER])
            ->assertSessionHasErrors('role');
        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);
    }
}
