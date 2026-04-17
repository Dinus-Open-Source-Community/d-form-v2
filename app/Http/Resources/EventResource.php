<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'banner' => $this->banner,
            'banner_url' => $this->banner ? Storage::disk('public')->url($this->banner) : null,
            'price' => $this->price,
            'quota' => $this->quota,
            'registered_count' => $this->registered_count,
            'session' => $this->session->value,
            'category' => $this->category->value,
            'status' => $this->status->value,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'registration_start' => $this->registration_start?->toIso8601String(),
            'registration_end' => $this->registration_end?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
