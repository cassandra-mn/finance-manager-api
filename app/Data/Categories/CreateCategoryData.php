<?php

namespace App\Data\Categories;

use App\Enum\TransactionType;
use App\Http\Requests\Categories\StoreCategoryRequest;

final readonly class CreateCategoryData
{
    public function __construct(
        public int $userId,
        public string $name,
        public TransactionType $type,
        public ?string $color,
        public ?string $icon,
    ) {}

    public static function fromRequest(StoreCategoryRequest $request, int $userId): self
    {
        return new self(
            userId: $userId,
            name: $request->string('name')->toString(),
            type: TransactionType::from($request->string('type')->toString()),
            color: $request->string('color')->toString() ?: null,
            icon: $request->string('icon')->toString() ?: null,
        );
    }
}
