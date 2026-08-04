<?php

namespace App\Http\Resources\Categories;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): ?array
    {
        // The underlying category can be null here even though the resource
        // was instantiated directly (not via whenLoaded()) — e.g. a budget
        // or recurrence whose category was soft-deleted. Category uses
        // SoftDeletes, so the FK row still exists but Eloquent's default
        // scope makes the relation resolve to null; without this guard,
        // every property access below throws.
        if ($this->resource === null) {
            return null;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'type_label' => $this->type->label(),
            'color' => $this->color,
            'icon' => $this->icon,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
