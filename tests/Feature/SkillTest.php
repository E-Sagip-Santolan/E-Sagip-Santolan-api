<?php

namespace Tests\Feature;

use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_list_skills_without_authentication(): void
    {
        $first = Skill::create(['name' => 'First Aid', 'category' => 'Medical & First Aid']);
        $second = Skill::create(['name' => 'Relief Goods Packing', 'category' => 'Logistics & Relief Operations']);

        $response = $this->getJson('/api/skills');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'OK')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0', [
                'id' => $first->id,
                'name' => 'First Aid',
                'category' => 'Medical & First Aid',
            ])
            ->assertJsonPath('data.1', [
                'id' => $second->id,
                'name' => 'Relief Goods Packing',
                'category' => 'Logistics & Relief Operations',
            ]);
    }
}
