<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * Site-wide settings managed from the admin panel, stored per group
 * (general, modules, mail, appearance) in the settings table.
 */
class Settings
{
    public const CACHE_KEY = 'erp-settings';

    public const GROUPS = ['general', 'modules', 'mail', 'appearance'];

    /** @var array<string, array<string, mixed>>|null */
    protected ?array $values = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            'general' => [
                'site_name' => 'ERP',
                'company_name' => null,
                'warning_days' => 30,
                'it_audit_months' => 12,
            ],
            'modules' => [
                Modules::FLEET => true,
                Modules::TEAMS => true,
                Modules::ASSETS => true,
                Modules::VACATIONS => true,
                Modules::ORDERS => true,
                Modules::LOCATIONS => true,
                Modules::STOCK => true,
                Modules::IT => true,
                Modules::WORK_SITES => true,
                Modules::INCIDENTS => true,
                Modules::SUGGESTIONS => true,
                Modules::PROJECTS => true,
                Modules::KNOWLEDGE_BASE => true,
                Modules::EMPLOYEES => true,
                Modules::WASTE => true,
            ],
            'mail' => [
                'mailer' => null,
                'host' => null,
                'port' => 587,
                'username' => null,
                'password' => null,
                'scheme' => null,
                'from_address' => null,
                'from_name' => null,
            ],
            'appearance' => [
                'primary' => '#002b45',
                'accent' => '#f7941d',
                'logo' => null,
                'favicon' => null,
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->values ??= Cache::rememberForever(self::CACHE_KEY, function (): array {
            try {
                $stored = Setting::query()->pluck('value', 'key')->all();
            } catch (QueryException) {
                $stored = [];
            }

            return collect(static::defaults())
                ->map(fn (array $defaults, string $group): array => [...$defaults, ...($stored[$group] ?? [])])
                ->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(string $group, array $values): void
    {
        Setting::updateOrCreate(['key' => $group], ['value' => $values]);

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->values = null;
    }

    public function siteName(): string
    {
        return filled($name = $this->get('general.site_name')) ? $name : config('app.name');
    }

    /**
     * Days before a control or inspection date that it is flagged as "due soon".
     */
    public function warningDays(): int
    {
        return max(0, (int) $this->get('general.warning_days', 30));
    }

    /**
     * Override the mail configuration with the values set in the admin panel.
     * When no mailer is chosen there, the .env configuration stays in effect.
     */
    public function applyMailConfig(): void
    {
        $mail = $this->get('mail');

        if (blank($mail['mailer'] ?? null)) {
            return;
        }

        config(array_filter([
            'mail.default' => $mail['mailer'],
            'mail.mailers.smtp.host' => $mail['host'],
            'mail.mailers.smtp.port' => $mail['port'],
            'mail.mailers.smtp.username' => $mail['username'],
            'mail.mailers.smtp.password' => filled($mail['password']) ? Crypt::decryptString($mail['password']) : null,
            'mail.mailers.smtp.scheme' => $mail['scheme'],
            'mail.from.address' => $mail['from_address'],
            'mail.from.name' => $mail['from_name'] ?: $this->siteName(),
        ], fn (mixed $value): bool => filled($value)));
    }

    public function logoUrl(): ?string
    {
        $logo = $this->get('appearance.logo');

        return filled($logo) ? Storage::disk('public')->url($logo) : null;
    }

    public function faviconUrl(): ?string
    {
        $favicon = $this->get('appearance.favicon');

        return filled($favicon) ? Storage::disk('public')->url($favicon) : null;
    }

    /**
     * Months between two audits of an IT asset.
     */
    public function itAuditMonths(): int
    {
        return max(1, (int) $this->get('general.it_audit_months', 12));
    }

    public function color(string $name): string
    {
        $fallback = static::defaults()['appearance'][$name];
        $value = (string) $this->get("appearance.{$name}");

        return preg_match('/^#[0-9a-f]{6}$/i', $value) ? $value : $fallback;
    }
}
