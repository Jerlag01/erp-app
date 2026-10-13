<?php

namespace App\Policies;

/**
 * Certificates employees add themselves are approved by administrators only.
 */
class EmployeeCertificateRequestPolicy extends EmployeeChangeRequestPolicy {}
