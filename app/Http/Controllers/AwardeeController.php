<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Awardee;
use App\Services\Monitoring\MonitoringEvaluationCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AwardeeController extends Controller
{
    public function update(Request $request, Awardee $awardee, MonitoringEvaluationCache $evaluationCache): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'rank' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $updates = [];
        $fullName = $validated['full_name'] ?? null;
        if (is_string($fullName)) {
            $updates['full_name'] = trim($fullName);
        }

        if (array_key_exists('rank', $validated)) {
            $rank = is_string($validated['rank']) ? trim($validated['rank']) : '';
            $updates['rank'] = $rank !== '' ? $rank : null;
        }

        $awardee->update($updates);
        $evaluationCache->invalidate();

        return response()->json([
            'id' => $awardee->id,
            'full_name' => $awardee->full_name,
            'rank' => $awardee->rank,
        ]);
    }
}
