<?php

namespace App\Listeners;

use App\Actions\Categories\SeedDefaultCategoriesForUserAction;
use Illuminate\Auth\Events\Registered;

final class SeedDefaultCategoriesListener
{
    public function __construct(
        private readonly SeedDefaultCategoriesForUserAction $seedDefaultCategories,
    ) {}

    public function handle(Registered $event): void
    {
        $this->seedDefaultCategories->execute($event->user);
    }
}
