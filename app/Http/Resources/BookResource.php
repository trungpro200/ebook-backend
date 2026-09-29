<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $coverUrl = null;

        if ($this->cover !== null) {
            $coverUrl = Str::startsWith($this->cover, ['http://', 'https://'])
                ? $this->cover
                : url(Storage::disk('public')->url($this->cover));
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'cover_url' => $coverUrl,
            'language' => $this->language,
            'status' => $this->status,
            'view_count' => $this->view_count,
            'chapters_count' => $this->whenCounted('chapters'),
            'author' => $this->whenLoaded('author', fn (): array => [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ]),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'chapters' => ChapterSummaryResource::collection($this->whenLoaded('chapters')),
        ];
    }
}
