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

    public const FINANCE = 'finance';

    public const PLANNING = 'planning';

    public const INTERVENTIONS = 'interventions';

    public const BATTERIES = 'batteries';

    public const TIMESHEETS = 'timesheets';

    public const CHARGING = 'charging';

    public const CABLE_STRIPPING = 'cable_stripping';

    public const TOOLBOX = 'toolbox';

    public const ENERSYS = 'enersys';

    public const ANNOUNCEMENTS = 'announcements';

    public const ASBUILT = 'asbuilt';

    public const HR_CALCULATIONS = 'hr_calculations';

    public const BOARD_DELIVERIES = 'board_deliveries';

    public const BATTERY_ROOMS = 'battery_rooms';

    public const ALL = [self::FLEET, self::TEAMS, self::ASSETS, self::VACATIONS, self::ORDERS, self::LOCATIONS, self::STOCK, self::IT, self::WORK_SITES, self::INCIDENTS, self::SUGGESTIONS, self::PROJECTS, self::KNOWLEDGE_BASE, self::EMPLOYEES, self::WASTE, self::FINANCE, self::PLANNING, self::INTERVENTIONS, self::BATTERIES, self::TIMESHEETS, self::CHARGING, self::CABLE_STRIPPING, self::ENERSYS, self::TOOLBOX, self::ANNOUNCEMENTS, self::ASBUILT, self::HR_CALCULATIONS, self::BOARD_DELIVERIES, self::BATTERY_ROOMS];

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

    public function finance(): bool
    {
        return $this->enabled(self::FINANCE);
    }

    public function planning(): bool
    {
        return $this->enabled(self::PLANNING);
    }

    /**
     * Charging stations and 4G routers belong to the 608 projects, so both modules must be on.
     */
    /**
     * Enersys dossiers on 605 projects; they build on the projects.
     */
    public function enersys(): bool
    {
        return $this->enabled(self::ENERSYS) && $this->projects();
    }

    public function cableStripping(): bool
    {
        return $this->enabled(self::CABLE_STRIPPING);
    }

    public function asbuilt(): bool
    {
        return $this->enabled(self::ASBUILT) && $this->projects();
    }

    public function hrCalculations(): bool
    {
        return $this->enabled(self::HR_CALCULATIONS);
    }

    /**
     * Follow-up of the electrical boards per supplier for the 610–650 projects.
     */
    public function boardDeliveries(): bool
    {
        return $this->enabled(self::BOARD_DELIVERIES) && $this->projects();
    }

    /**
     * Battery rooms (609) brought up to standard after a battery replacement in Flanders or Brussels.
     */
    public function batteryRooms(): bool
    {
        return $this->enabled(self::BATTERY_ROOMS) && $this->projects();
    }

    public function announcements(): bool
    {
        return $this->enabled(self::ANNOUNCEMENTS);
    }

    public function toolbox(): bool
    {
        return $this->enabled(self::TOOLBOX);
    }

    public function charging(): bool
    {
        return $this->enabled(self::CHARGING) && $this->projects();
    }

    public function timesheets(): bool
    {
        return $this->enabled(self::TIMESHEETS);
    }

    /**
     * Battery dossiers belong to 604 projects, so both modules must be on.
     */
    public function batteries(): bool
    {
        return $this->enabled(self::BATTERIES) && $this->projects();
    }

    /**
     * Interventions build on projects (category 600), so both modules must be on.
     */
    public function interventions(): bool
    {
        return $this->enabled(self::INTERVENTIONS) && $this->projects();
    }
}
