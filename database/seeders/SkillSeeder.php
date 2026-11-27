<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SkillSeeder extends Seeder
{
    public function run(): void
    {
        $skills = [
            ['Relief Goods Packing', 'Logistics & Relief Operations'],
            ['Donation Sorting', 'Logistics & Relief Operations'],
            ['Inventory & Listing', 'Logistics & Relief Operations'],
            ['Debris Clearing & Heavy Lifting', 'Physical Labor & Clean-Up'],
            ['Mud Clearing & Sweeping', 'Physical Labor & Clean-Up'],
            ['Basic Carpentry', 'Physical Labor & Clean-Up'],
            ['Community Kitchen / Cooking', 'Community Support & Welfare'],
            ['Crowd Control & Ushering', 'Community Support & Welfare'],
            ['Child & Elderly Care', 'Community Support & Welfare'],
            ['Basic First Aid / CPR', 'Medical & First Aid'],
            ['Medical Professional', 'Medical & First Aid'],
            ['Psychological First Aid', 'Medical & First Aid'],
            ['Driver (4-Wheel / Truck / Van)', 'Transport & Technical'],
            ['Basic Electrical / Wiring Safety', 'Transport & Technical'],
            ['Boat / Bangka Operator', 'Transport & Technical'],
        ];

        foreach ($skills as [$name, $category]) {
            DB::table('skills')->updateOrInsert(
                ['name' => $name],
                ['category' => $category, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}