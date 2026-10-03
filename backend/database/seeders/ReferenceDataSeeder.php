<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\MeasurementField;
use App\Models\MeasurementTemplate;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'female' => ['Female', [
                ['bust', 'Bust'],
                ['waist', 'Waist'],
                ['hip', 'Hip'],
                ['shoulder', 'Shoulder'],
                ['sleeve_length', 'Sleeve Length'],
                ['armhole', 'Armhole'],
                ['neck', 'Neck'],
                ['blouse_length', 'Blouse Length'],
                ['gown_length', 'Gown Length'],
                ['skirt_length', 'Skirt Length'],
                ['trouser_length', 'Trouser Length'],
                ['thigh', 'Thigh'],
                ['knee', 'Knee'],
                ['ankle', 'Ankle'],
            ]],
            'male' => ['Male', [
                ['neck', 'Neck'],
                ['shoulder', 'Shoulder'],
                ['chest', 'Chest'],
                ['stomach', 'Stomach'],
                ['waist', 'Waist'],
                ['hip', 'Hip'],
                ['sleeve_length', 'Sleeve Length'],
                ['biceps', 'Biceps'],
                ['shirt_length', 'Shirt Length'],
                ['trouser_length', 'Trouser Length'],
                ['thigh', 'Thigh'],
                ['knee', 'Knee'],
                ['inseam', 'Inseam'],
                ['outseam', 'Outseam'],
            ]],
            'child' => ['Child', [
                ['chest', 'Chest'],
                ['waist', 'Waist'],
                ['hip', 'Hip'],
                ['shoulder', 'Shoulder'],
                ['sleeve', 'Sleeve'],
                ['length', 'Length'],
                ['trouser_length', 'Trouser length'],
                ['neck', 'Neck'],
            ]],
        ];

        $sort = 1;

        foreach ($templates as $slug => [$name, $fields]) {
            $template = MeasurementTemplate::query()->updateOrCreate(
                ['slug' => $slug, 'business_id' => null],
                ['name' => $name, 'sort_order' => $sort],
            );

            foreach ($fields as $index => [$key, $label]) {
                MeasurementField::query()->updateOrCreate(
                    ['measurement_template_id' => $template->id, 'key' => $key],
                    ['label' => $label, 'sort_order' => $index + 1],
                );
            }

            $sort++;
        }

        $categories = [
            ['fabric', 'Fabric'],
            ['transport', 'Transport'],
            ['electricity', 'Electricity'],
            ['staff', 'Staff'],
            ['rent', 'Rent'],
            ['machine_repair', 'Machine repair'],
            ['accessories', 'Accessories'],
            ['food', 'Food'],
            ['marketing', 'Marketing'],
            ['other', 'Other'],
        ];

        foreach ($categories as $index => [$slug, $name]) {
            ExpenseCategory::query()->updateOrCreate(
                ['slug' => $slug, 'business_id' => null],
                ['name' => $name, 'sort_order' => $index + 1],
            );
        }
    }
}
