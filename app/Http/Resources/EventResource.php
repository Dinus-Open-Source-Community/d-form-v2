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
            'session' => $this->sessionTokens(),
            'category' => $this->categoryTokens(),
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

    /**
     * Explode and normalize the stored CSV `category` into a unique list of tokens.
     *
     * @return list<string>
     */
    private function categoryTokens(): array
    {
        return $this->explodeCsv((string) $this->category);
    }

    /**
     * Explode and normalize the stored CSV `session` into a unique list of tokens.
     *
     * @return list<string>
     */
    private function sessionTokens(): array
    {
        return $this->explodeCsv((string) $this->session);
    }

    /**
     * @return list<string>
     */
    private function explodeCsv(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(static fn (string $s) => trim($s))
            ->filter(static fn (string $s) => $s !== '')
            ->unique()
            ->values()
            ->all();
    }
}
