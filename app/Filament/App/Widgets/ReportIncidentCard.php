<?php

namespace App\Filament\App\Widgets;

use App\Models\Incident;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

/**
 * The highlighted "report an incident" link at the top of every employee's dashboard.
 */
class ReportIncidentCard extends Widget
{
    protected string $view = 'filament.app.report-incident-card';

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->check() && Gate::allows('create', Incident::class);
    }
}
