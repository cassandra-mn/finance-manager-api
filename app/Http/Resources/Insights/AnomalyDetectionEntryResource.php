<?php

namespace App\Http\Resources\Insights;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{category_id: int, category_name: string, category_color: ?string, current_cents: int, average_cents: int, deviation_percentage: ?float, is_anomalous: bool, is_new_category: bool} $resource
 */
class AnomalyDetectionEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'category_id' => $this->resource['category_id'],
            'category_name' => $this->resource['category_name'],
            'category_color' => $this->resource['category_color'],
            'current_cents' => $this->resource['current_cents'],
            'average_cents' => $this->resource['average_cents'],
            'deviation_percentage' => $this->resource['deviation_percentage'],
            'is_anomalous' => $this->resource['is_anomalous'],
            'is_new_category' => $this->resource['is_new_category'],
        ];
    }
}
