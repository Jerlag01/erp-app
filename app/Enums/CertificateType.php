<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Certificates and qualifications kept per employee, each with an optional expiry date and PDF.
 */
enum CertificateType: string implements HasLabel
{
    case Ba4 = 'ba4';

    case Ba5 = 'ba5';

    case Vca = 'vca';

    case VcaVol = 'vca_vol';

    case PreventionAdvisor1 = 'prevention_advisor_1';

    case PreventionAdvisor2 = 'prevention_advisor_2';

    case PreventionAdvisor3 = 'prevention_advisor_3';

    case DrivingLicence = 'driving_licence';

    case Forklift = 'forklift';

    case WorkAtHeight = 'work_at_height';

    case SafetyHarness = 'safety_harness';

    case AerialWorkPlatform = 'aerial_work_platform';

    case FirstAid = 'first_aid';

    /**
     * Belgian driving licence categories.
     */
    public const DRIVING_LICENCE_CATEGORIES = ['AM', 'A1', 'A2', 'A', 'B', 'BE', 'C1', 'C1E', 'C', 'CE', 'D1', 'D1E', 'D', 'DE', 'G'];

    public function getLabel(): string
    {
        return __('erp.enums.certificate_type.'.$this->value);
    }
}
