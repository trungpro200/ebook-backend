<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookSourceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'provider' => $this->provider,
            'source_identifier' => $this->source_identifier,
            'source_url' => $this->source_url,
            'license_name' => $this->license_name,
            'license_url' => $this->license_url,
            'rights_jurisdiction' => $this->rights_jurisdiction,
            'rights_note' => $this->rights_note,
            'rights_checked_at' => $this->rights_checked_at?->toDateString(),
        ];
    }
}
