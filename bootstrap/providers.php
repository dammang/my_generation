<?php

use App\Providers\AppServiceProvider;
use App\Providers\DatabaseMacroServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    // Registers Blueprint macros the migrations depend on.
    DatabaseMacroServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
];
