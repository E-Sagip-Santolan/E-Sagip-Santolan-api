<?php

namespace App\Http\Controllers;

use App\Http\Resources\SkillResource;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;

class SkillController extends Controller
{
    /**
     * Return all available skills for registration and profile editing.
     */
    public function index(): JsonResponse
    {
        $skills = Skill::orderBy('id')->get();

        return response()->json([
            'data' => SkillResource::collection($skills),
            'message' => 'OK',
        ], 200);
    }
}
