<?php

namespace Database\Factories;

use App\Models\EodAttachment;
use App\Models\EodReportItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EodAttachment>
 */
class EodAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'eod_report_item_id' => EodReportItem::factory(),
            'disk' => 'local',
            'path' => 'eod-screenshots/example.png',
            'original_name' => 'example.png',
            'mime_type' => 'image/png',
            'size' => 1024,
        ];
    }
}
