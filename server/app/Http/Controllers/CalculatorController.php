<?php


namespace App\Http\Controllers;


use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class CalculatorController extends Controller
{
    public function calculate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'gender' => ['required', 'in:male,female'],
            'height' => ['required', 'numeric', 'min:100', 'max:250'], // см
            'weight' => ['required', 'numeric', 'min:30', 'max:300'],  // кг
            'age' => ['required', 'integer', 'min:10', 'max:120'],
            'goal' => ['required', 'in:lose,maintain,gain'],
            'activity_level' => [
                'required',
                'in:low,medium,high'
            ],
        ]);


        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }


        $gender = $request->gender;
        $height = (float) $request->height;
        $weight = (float) $request->weight;
        $age = (int) $request->age;
        $goal = $request->goal;
        $activityLevel = $request->activity_level;


        /*
         |------------------------------------------------------------
         | Формула Миффлина-Сан Жеора
         |------------------------------------------------------------
         */
        if ($gender === 'male') {
            $bmr = (10 * $weight) + (6.25 * $height) - (5 * $age) + 5;
        } else {
            $bmr = (10 * $weight) + (6.25 * $height) - (5 * $age) - 161;
        }


        /*
         |------------------------------------------------------------
         | Коэффициент активности
         |------------------------------------------------------------
         */
        $activityMultiplier = match ($activityLevel) {
            'low' => 1.2,
            'medium' => 1.55,
            'high' => 1.9,
        };


        $calories = $bmr * $activityMultiplier;


        /*
         |------------------------------------------------------------
         | Корректировка под цель
         |------------------------------------------------------------
         */
        $calories = match ($goal) {
            'lose' => $calories - 400,
            'gain' => $calories + 400,
            default => $calories,
        };


        $calories = max($calories, 1200);


        /*
         |------------------------------------------------------------
         | Расчёт БЖУ
         |------------------------------------------------------------
         |
         | Белки: 2 г на кг
         | Жиры: 1 г на кг
         | Остальное — углеводы
         |
         */


        $proteins = $weight * 2;
        $fats = $weight * 1;


        $proteinCalories = $proteins * 4;
        $fatCalories = $fats * 9;


        $carbohydrateCalories = $calories - ($proteinCalories + $fatCalories);
        $carbohydrates = $carbohydrateCalories / 4;


        return response()->json([
            'data' => [
                'calories' => round($calories),
                'proteins' => round($proteins),
                'fats' => round($fats),
                'carbohydrates' => round($carbohydrates),
            ]
        ]);
    }
}

