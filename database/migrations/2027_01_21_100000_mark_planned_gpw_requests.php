<?php

use App\Actions\Gpw\MarkGpwPlanned;
use App\Enums\GpwStatus;
use App\Models\GpwRequest;
use Illuminate\Database\Migrations\Migration;

/**
 * Approved GPW requests whose project is already in the planning within their period become "planned".
 */
return new class extends Migration
{
    public function up(): void
    {
        GpwRequest::query()->where('status', GpwStatus::Approved)->each(fn (GpwRequest $request): bool => app(MarkGpwPlanned::class)->handle($request));
    }

    public function down(): void
    {
        //
    }
};
