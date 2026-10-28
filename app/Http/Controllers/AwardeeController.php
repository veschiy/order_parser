<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Awardee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AwardeeController extends Controller
{
    public function update(Request $request, Awardee $awardee): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
        ]);

        $fullName = is_string($validated['full_name'] ?? null) ? trim($validated['full_name']) : '';

        $awardee->update([
            'full_name' => $fullName,
        ]);

        return response()->json([
            'id' => $awardee->id,
            'full_name' => $awardee->full_name,
        ]);
    }
}
