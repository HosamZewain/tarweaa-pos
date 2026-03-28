<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MealBenefitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealBenefitController extends Controller
{
    public function __construct(
        private readonly MealBenefitService $mealBenefitService,
    ) {}

    public function summary(Request $request, User $user): JsonResponse
    {
        if (!$request->user()?->hasPermission('reports.meal_benefits.view')) {
            return $this->error('ليس لديك صلاحية للوصول إلى هذه البيانات.', 403);
        }

        return $this->success($this->mealBenefitService->getMonthlySummary($user));
    }
}
