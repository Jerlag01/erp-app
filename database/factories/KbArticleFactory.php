<?php

namespace Database\Factories;

use App\Enums\KbFormat;
use App\Models\KbArticle;
use App\Models\KbCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KbArticle>
 */
class KbArticleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kb_category_id' => KbCategory::factory(),
            'title' => ['nl' => 'Hoe vraag ik verlof aan?', 'fr' => 'Comment demander un congé ?', 'en' => 'How do I request leave?'],
            'format' => KbFormat::Markdown,
            'body' => ['nl' => 'Open **Mijn verlof** en klik op *Aanvragen*.', 'fr' => 'Ouvrez **Mes congés**.', 'en' => 'Open **My leave**.'],
            'is_published' => true,
            'sort_order' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(['is_published' => false]);
    }
}
