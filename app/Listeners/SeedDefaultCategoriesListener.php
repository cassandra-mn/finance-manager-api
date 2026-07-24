<?php

namespace App\Listeners;

use App\Services\CategoryService;
use Illuminate\Auth\Events\Registered;

final class SeedDefaultCategoriesListener
{
    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    public function handle(Registered $event): void
    {
        $this->categoryService->seedDefaultsForUser($event->user);
    }
}
