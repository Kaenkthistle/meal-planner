<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MicronutrientController extends Controller
{
    public function check(Request $request)
    {
        $validated = $request->validate([
            'dishes' => ['required', 'array', 'min:1'],

            'dishes.*.dish_id' => [
                'required',
                'integer',
                'exists:dish,dish_id'
            ],

            'dishes.*.mass' => [
                'required',
                'numeric',
                'min:1'
            ],
        ]);

        $totals = [];

        foreach ($validated['dishes'] as $dishData) {

            $dish = DB::table('dish')
                ->select('dish_id', 'mass')
                ->where('dish_id', $dishData['dish_id'])
                ->first();

            if (!$dish || !$dish->mass) {
                continue;
            }

            $ratio = $dishData['mass'] / $dish->mass;

            $micronutrients = DB::table('dish_micronutrient')
                ->where('dish_id', $dish->dish_id)
                ->get();

            foreach ($micronutrients as $micro) {

                $value = $micro->mass * $ratio;

                if (!isset($totals[$micro->micronutrient_id])) {
                    $totals[$micro->micronutrient_id] = 0;
                }

                $totals[$micro->micronutrient_id] += $value;
            }
        }

        $micronutrients = DB::table('micronutrient')->get();

        $low = [];
        $good = [];

        foreach ($micronutrients as $micro) {

            $current =
                $totals[$micro->micronutrient_id] ?? 0;

            $percent = 0;

            if ($micro->recommended_norm > 0) {

                $percent =
                    ($current / $micro->recommended_norm) * 100;
            }

            if ($percent < 54) {

                $low[] = [
                    'micronutrient_id' =>
                        $micro->micronutrient_id,

                    'percent' =>
                        round($percent, 1),
                ];
            }

            if ($percent > 89) {

                $good[] = [
                    'micronutrient_id' =>
                        $micro->micronutrient_id,

                    'percent' =>
                        round($percent, 1),
                ];
            }
        }

        usort($low, function ($a, $b) {
            return $a['percent'] <=> $b['percent'];
        });

        return response()->json([
            'low_micronutrients' => $low,
            'good_micronutrients' => $good,
        ]);
    }
}
