<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminOnlyPage;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    use AdminOnlyPage;
}
