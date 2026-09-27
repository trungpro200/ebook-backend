<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    private function book(): Book
    {
        return Book::create([
            'title' => 'Test book',
            'category_id' => Category::create(['name' => 'Test category'])->id,
            'author_id' => User::factory()->create()->id,
        ]);
    }

    public function test_guests_can_read_books_but_cannot_write(): void
    {
        $book = $this->book();
        $this->getJson('/api/books')->assertOk();
        $this->getJson('/api/books/'.$book->id)->assertOk();
        $this->postJson('/api/books', [])->assertUnauthorized();
        $this->putJson('/api/books/'.$book->id, ['title' => 'Changed'])->assertUnauthorized();
        $this->deleteJson('/api/books/'.$book->id)->assertUnauthorized();
    }

    public function test_reader_cannot_create_update_or_delete_books(): void
    {
        $book = $this->book();
        $token = User::factory()->create()->createToken('reader')->plainTextToken;
        $this->withToken($token)->postJson('/api/books', [])->assertForbidden();
        $this->withToken($token)->patchJson('/api/books/'.$book->id, ['title' => 'Changed'])->assertForbidden();
        $this->withToken($token)->deleteJson('/api/books/'.$book->id)->assertForbidden();
        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => 'Test book']);
    }

    public function test_admin_can_create_update_and_delete_books(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('admin')->plainTextToken;
        $category = Category::create(['name' => 'Test category']);
        $response = $this->withToken($token)->postJson('/api/books', [
            'title' => 'New book', 'category_id' => $category->id, 'author_id' => $admin->id,
        ])->assertCreated();
        $id = $response->json('data.id');
        $this->withToken($token)->putJson('/api/books/'.$id, ['title' => 'Updated'])->assertOk();
        $this->assertDatabaseHas('books', ['id' => $id, 'title' => 'Updated']);
        $this->withToken($token)->deleteJson('/api/books/'.$id)->assertOk();
        $this->assertDatabaseMissing('books', ['id' => $id]);
    }

    public function test_role_command_updates_existing_user_and_revokes_sessions(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('session')->plainTextToken;
        $this->artisan('users:set-role', ['email' => $user->email, 'role' => 'admin'])->assertSuccessful();
        $this->assertSame('admin', $user->fresh()->role);
        $this->assertNull(PersonalAccessToken::findToken($token));
        $this->artisan('users:set-role', ['email' => $user->email, 'role' => 'reader'])->assertSuccessful();
        $this->assertSame('reader', $user->fresh()->role);
    }

    public function test_role_command_rejects_unknown_roles_and_missing_users(): void
    {
        $user = User::factory()->create();
        $this->artisan('users:set-role', ['email' => $user->email, 'role' => 'superadmin'])->assertFailed();
        $this->artisan('users:set-role', ['email' => 'missing@example.com', 'role' => 'admin'])->assertFailed();
        $this->assertSame('reader', $user->fresh()->role);
    }
}
