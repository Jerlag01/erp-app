<?php

namespace App\Policies;

/**
 * Battery moves (605) follow the "enersys" permissions, as the Enersys dossiers of the same projects.
 */
class MoveDossierPolicy extends EnersysDossierPolicy {}
