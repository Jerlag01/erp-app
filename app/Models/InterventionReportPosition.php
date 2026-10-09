<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A breaker or fuse position: board · AC/DC · F (fuse) or Q (breaker) + number · supply side A/B,
 * e.g. "TD-01 · AC · Q12 · A". The board name is typed by the employee: boards are named
 * differently on every site.
 */
#[Fillable(['board_name', 'current', 'device', 'number', 'supply'])]
class InterventionReportPosition extends Model
{
    public const CURRENTS = ['AC', 'DC'];

    public const DEVICES = ['F', 'Q'];

    public const SUPPLIES = ['A', 'B'];

    public function label(): string
    {
        return "{$this->board_name} · {$this->current} · {$this->device}{$this->number} · ".__('erp.interventions.supply_side', ['side' => $this->supply]);
    }
}
