<?php

namespace App\Support;

/**
 * The optional modules. Each one works on its own; links between modules
 * (an asset on a vehicle, a team on a vehicle, ...) only show when both are on.
 */
class Modules
{
    public const FLEET = 'fleet';

    public const TEAMS = 'teams';

    public const ASSETS = 'assets';

    public const VACATIONS = 'vacations';

    public const ORDERS = 'orders';

    public const LOCATIONS = 'locations';

    public const STOCK = 'stock';

    public const IT = 'it';

    public const WORK_SITES = 'work_sites';

    public const INCIDENTS = 'incidents';

    public const SUGGESTIONS = 'suggestions';

    public const PROJECTS = 'projects';

    public const KNOWLEDGE_BASE = 'knowledge_base';

    public const EMPLOYEES = 'employees';

    public const WASTE = 'waste';

    public const ALL = [self::FLEET, self::TEAMS, self::ASSETS, self::VACATIONS, self::ORDERS, self::LOCATIONS, self::STOCK, self::IT, self::WORK_SITES, self::INCIDENTS, self::SUGGESTIONS, self::PROJECTS, self::KNOWLEDGE_BASE, self::EMPLOYEES, self::WASTE];

    public function __construct(protected Settings $settings) {}

    public function enabled(string $module): bool
    {
        return (bool) $this->settings->get("modules.{$module}", false);
    }

    public function disabled(string $module): bool
    {
        return ! $this->enabled($module);
    }

    /**
     * Whether every given module is on,, e.g. all(Modules::FLEET, Modules::ASSETS).
     */
    public function all(string ...$modules): bool
    {
        foreach ($modules as $module) {
            if (! $this->enabled($module)) {
                return false;
            }
        }

        return true;
    }

    public function fleet(): bool
    {
        return $this->enabled(self::FLEET);
    }

    public function teams(): bool
    {
        return $this->enabled(self::TEAMS);
    }

    public function assets(): bool
    {
        return $this->enabled(self::ASSETS);
    }

    public function vacations(): bool
    {
        return $this->enabled(self::VACATIONS);
    }

    public function orders(): bool
    {
        return $this->enabled(self::ORDERS);
    }

    public function locations(): bool
    {
        return $this->enabled(self::LOCATIONS);
    }

    public function stock(): bool
    {
        return $this->enabled(self::STOCK);
    }

    public function it(): bool
    {
        return $this->enabled(self::IT);
    }

    public function workSites(): bool
    {
        return $this->enabled(self::WORK_SITES);
    }

    public function incidents(): bool
    {
        return $this->enabled(self::INCIDENTS);
    }

    public function suggestions(): bool
    {
        return $this->enabled(self::SUGGESTIONS);
    }

    public function projects(): bool
    {
        return $this->enabled(self::PROJECTS);
    }

    public function knowledgeBase(): bool
    {
        return $this->enabled(self::KNOWLEDGE_BASE);
    }

    public function employees(): bool
    {
        return $this->enabled(self::EMPLOYEES);
    }

    public function waste(): bool
    {
        return $this->enabled(self::WASTE);
    }
}
