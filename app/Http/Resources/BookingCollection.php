<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class BookingCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => BookingResource::collection($this->collection),
            'meta' => [
                'total' => $this->total(),
                'count' => $this->count(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'total_pages' => $this->lastPage(),
            ],
            'summary' => $this->getSummary(),
            'links' => [
                'self' => $this->url($this->currentPage()),
                'first' => $this->url(1),
                'last' => $this->url($this->lastPage()),
                'prev' => $this->previousPageUrl(),
                'next' => $this->nextPageUrl(),
            ],
        ];
    }

    private function getSummary(): array
    {
        $totalAmount = $this->collection->sum('total_price');
        $confirmedCount = $this->collection->where('status', 'confirmed')->count();
        $pendingCount = $this->collection->where('status', 'pending')->count();

        return [
            'total_amount' => $totalAmount,
            'confirmed_count' => $confirmedCount,
            'pending_count' => $pendingCount,
            'total_bookings' => $this->collection->count(),
        ];
    }
}
