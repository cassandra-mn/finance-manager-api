<?php

namespace App\Data\Categories;

use App\Enum\TransactionType;
use App\Http\Requests\Categories\UpdateCategoryRequest;

final readonly class UpdateCategoryData
{
    public function __construct(
        public ?string $name,
        public ?TransactionType $type,
        public ?string $color,
        public ?string $icon,
    ) {}

    public static function fromRequest(UpdateCategoryRequest $request): self
    {
        return new self(
            name: $request->filled('name') ? $request->string('name')->toString() : null,
            type: $request->filled('type') ? TransactionType::from($request->string('type')->toString()) : null,
            color: $request->filled('color') ? $request->string('color')->toString() : null,
            icon: $request->filled('icon') ? $request->string('icon')->toString() : null,
        );
    }
}
