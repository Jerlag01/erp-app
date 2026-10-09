<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\Field;

/**
 * Drawing board for a floor plan: free drawing, lines, arrows, measurement arrows with their
 * length, rectangles, circles and text, which can be moved and removed afterwards. The state is the
 * drawing as a list of shapes; on every change the editor also writes a PNG of it to the field
 * named by pngStatePath() (used in the PDF).
 */
class FloorPlanEditor extends Field
{
    public const WIDTH = 1200;

    public const HEIGHT = 800;

    protected string $view = 'filament.forms.floor-plan-editor';

    protected string $pngStatePath = 'floor_plan_png';

    public function pngStatePath(string $path): static
    {
        $this->pngStatePath = $path;

        return $this;
    }

    public function getPngStatePath(): string
    {
        return $this->getContainer()->getStatePath().'.'.$this->pngStatePath;
    }

    /**
     * Keeps only known shapes with sane values, so the stored drawing can always be rendered.
     *
     * @return list<array<string, mixed>>
     */
    public static function sanitize(mixed $shapes): array
    {
        if (is_string($shapes)) {
            $shapes = json_decode($shapes, true);
        }

        $number = fn (mixed $value): float => is_numeric($value) ? round(max(-5000, min(5000, (float) $value)), 1) : 0.0;
        $color = fn (mixed $value): string => is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) ? $value : '#0f172a';
        $clean = [];

        foreach (array_slice(is_array($shapes) ? $shapes : [], 0, 2000) as $shape) {
            $type = $shape['t'] ?? null;
            $base = ['t' => $type, 'c' => $color($shape['c'] ?? null), 'w' => max(1, min(12, (int) ($shape['w'] ?? 3)))];

            $clean[] = match ($type) {
                'pen' => [...$base, 'pts' => array_map(fn (mixed $point): array => [$number($point[0] ?? 0), $number($point[1] ?? 0)], array_slice((array) ($shape['pts'] ?? []), 0, 4000))],
                'line', 'arrow', 'dim' => [...$base, 'x1' => $number($shape['x1'] ?? 0), 'y1' => $number($shape['y1'] ?? 0), 'x2' => $number($shape['x2'] ?? 0), 'y2' => $number($shape['y2'] ?? 0), 'label' => mb_substr((string) ($shape['label'] ?? ''), 0, 40)],
                'rect', 'ellipse' => [...$base, 'x' => $number($shape['x'] ?? 0), 'y' => $number($shape['y'] ?? 0), 'wd' => $number($shape['wd'] ?? 0), 'ht' => $number($shape['ht'] ?? 0), 'label' => mb_substr((string) ($shape['label'] ?? ''), 0, 40), 'fill' => (bool) ($shape['fill'] ?? false)],
                'text' => [...$base, 'x' => $number($shape['x'] ?? 0), 'y' => $number($shape['y'] ?? 0), 'text' => mb_substr((string) ($shape['text'] ?? ''), 0, 200), 'size' => max(8, min(72, (int) ($shape['size'] ?? 20)))],
                default => null,
            };
        }

        return array_values(array_filter($clean));
    }
}
