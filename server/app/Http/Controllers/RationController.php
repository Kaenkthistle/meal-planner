<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class RationController extends Controller
{
    /**
     * Сохранение составленного рациона
     */
    public function store(Request $request)
    {
        // Только авторизованные пользователи
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Необходимо авторизоваться'
            ], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'totals' => 'required|array',
            'totals.calories' => 'required|numeric',
            'totals.proteins' => 'required|numeric',
            'totals.fats' => 'required|numeric',
            'totals.carbohydrates' => 'required|numeric',
            'meals' => 'required|array',
            'meals.*.meal_time_id' => 'required|integer',
            'meals.*.items' => 'array',
            'meals.*.items.*.dish_id' => 'required|exists:dish,dish_id',
            'meals.*.items.*.multiplier' => 'numeric|min:0.5|max:2',
            'meals.*.items.*.excluded' => 'boolean'
        ]);

        try {
            DB::beginTransaction();

            // 1. Создаём рацион
            $rationId = DB::table('ration')->insertGetId([
                'name' => $validated['name'],
                'created_date' => now(),
                'user_id' => $request->user()->id
            ]);

            // 2. Сохраняем блюда в рационе (dish_ration)
            foreach ($validated['meals'] as $meal) {
                foreach ($meal['items'] as $item) {
                    // Пропускаем исключённые блюда
                    if (isset($item['excluded']) && $item['excluded'] === true) {
                        continue;
                    }

                    DB::table('dish_ration')->insert([
                        'dish_multiplier' => $item['multiplier'] ?? 1,
                        'meal_type' => $this->getMealTypeNameById($meal['meal_time_id']),
                        'ration_id' => $rationId,
                        'dish_id' => $item['dish_id']
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Рацион успешно сохранён',
                'data' => [
                    'ration_id' => $rationId,
                    'name' => $validated['name']
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при сохранении рациона: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получение списка рационов текущего пользователя
     */
    public function index(Request $request)
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Необходимо авторизоваться'
            ], 401);
        }

        $rations = DB::table('ration')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_date', 'desc')
            ->get();

        $result = [];

        foreach ($rations as $ration) {
            // Получаем блюда с их КБЖУ (фактические, с учётом множителя)
            $dishes = DB::table('dish_ration')
                ->join('dish', 'dish_ration.dish_id', '=', 'dish.dish_id')
                ->where('dish_ration.ration_id', $ration->id)
                ->select(
                    'dish_ration.meal_type',
                    'dish.name',
                    DB::raw('(dish.proteins * dish.mass / 100 * dish_ration.dish_multiplier) as proteins'),
                    DB::raw('(dish.fats * dish.mass / 100 * dish_ration.dish_multiplier) as fats'),
                    DB::raw('(dish.carbs * dish.mass / 100 * dish_ration.dish_multiplier) as carbs'),
                    DB::raw('(dish.calories * dish.mass / 100 * dish_ration.dish_multiplier) as calories')
                )
                ->get();

            // Считаем фактические итоги
            $totals = [
                'calories' => round($dishes->sum('calories')),
                'proteins' => round($dishes->sum('proteins'), 1),
                'fats' => round($dishes->sum('fats'), 1),
                'carbohydrates' => round($dishes->sum('carbs'), 1)
            ];

            // Группируем по приёмам для превью (первые 3 приёма)
            $groupedMeals = [];
            foreach ($dishes as $dish) {
                if (!isset($groupedMeals[$dish->meal_type])) {
                    $groupedMeals[$dish->meal_type] = [];
                }
                if (!in_array($dish->name, $groupedMeals[$dish->meal_type])) {
                    $groupedMeals[$dish->meal_type][] = $dish->name;
                }
            }

            $mealOrder = ['Завтрак', 'Второй завтрак', 'Обед', 'Полдник', 'Ужин'];
            $previewMeals = [];
            $mealCounter = 0;
            
            foreach ($mealOrder as $mealType) {
                if (isset($groupedMeals[$mealType]) && $mealCounter < 3) {
                    $dishNames = $groupedMeals[$mealType];
                    $previewMeals[] = [
                        'meal_type' => $mealType,
                        'dishes_string' => implode(', ', array_slice($dishNames, 0, 3)) . 
                                          (count($dishNames) > 3 ? '...' : '')
                    ];
                    $mealCounter++;
                }
            }

            $result[] = [
                'id' => $ration->id,
                'name' => $ration->name,
                'created_date' => $ration->created_date,
                'totals' => $totals,
                'preview_meals' => $previewMeals,
                'total_dishes' => $dishes->count()
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * Получение полной информации о рационе
     */
    public function show(Request $request, $id)
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Необходимо авторизоваться'
            ], 401);
        }

        $ration = DB::table('ration')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$ration) {
            return response()->json([
                'success' => false,
                'message' => 'Рацион не найден'
            ], 404);
        }

        // Получаем все блюда с их КБЖУ
        $dishes = DB::table('dish_ration')
            ->join('dish', 'dish_ration.dish_id', '=', 'dish.dish_id')
            ->where('dish_ration.ration_id', $id)
            ->select(
                'dish_ration.meal_type',
                'dish_ration.dish_multiplier as multiplier',
                'dish.dish_id as id',
                'dish.name',
                'dish.mass',
                'dish.portion_type',
                DB::raw('(dish.proteins * dish.mass / 100) as base_proteins'),
                DB::raw('(dish.fats * dish.mass / 100) as base_fats'),
                DB::raw('(dish.carbs * dish.mass / 100) as base_carbs'),
                DB::raw('(dish.calories * dish.mass / 100) as base_calories'),
                DB::raw('(dish.proteins * dish.mass / 100 * dish_ration.dish_multiplier) as proteins'),
                DB::raw('(dish.fats * dish.mass / 100 * dish_ration.dish_multiplier) as fats'),
                DB::raw('(dish.carbs * dish.mass / 100 * dish_ration.dish_multiplier) as carbs'),
                DB::raw('(dish.calories * dish.mass / 100 * dish_ration.dish_multiplier) as calories')
            )
            ->get();

        // Группируем по приёмам
        $meals = [];
        $mealOrder = ['Завтрак', 'Второй завтрак', 'Обед', 'Полдник', 'Ужин'];
        
        foreach ($dishes as $dish) {
            if (!isset($meals[$dish->meal_type])) {
                $meals[$dish->meal_type] = [
                    'meal_type' => $dish->meal_type,
                    'items' => []
                ];
            }
            
            $meals[$dish->meal_type]['items'][] = [
                'id' => $dish->id,
                'name' => $dish->name,
                'mass' => round($dish->mass),
                'portion_type' => $dish->portion_type,
                'multiplier' => $dish->multiplier,
                'base_proteins' => round($dish->base_proteins, 1),
                'base_fats' => round($dish->base_fats, 1),
                'base_carbs' => round($dish->base_carbs, 1),
                'base_calories' => round($dish->base_calories),
                'proteins' => round($dish->proteins, 1),
                'fats' => round($dish->fats, 1),
                'carbs' => round($dish->carbs, 1),
                'calories' => round($dish->calories)
            ];
        }

        // Сортируем
        $sortedMeals = [];
        foreach ($mealOrder as $mealType) {
            if (isset($meals[$mealType])) {
                $sortedMeals[] = $meals[$mealType];
            }
        }

        // Фактические итоги
        $totals = [
            'proteins' => round($dishes->sum('proteins'), 1),
            'fats' => round($dishes->sum('fats'), 1),
            'carbohydrates' => round($dishes->sum('carbs'), 1),
            'calories' => round($dishes->sum('calories'))
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $ration->id,
                'name' => $ration->name,
                'created_date' => $ration->created_date,
                'totals' => $totals,
                'meals' => $sortedMeals
            ]
        ]);
    }

    /**
     * Обновление рациона
     */
    public function update(Request $request, $id)
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Необходимо авторизоваться'
            ], 401);
        }

        $ration = DB::table('ration')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$ration) {
            return response()->json([
                'success' => false,
                'message' => 'Рацион не найден'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'meals' => 'sometimes|array'
        ]);

        try {
            DB::beginTransaction();

            // Обновляем название
            if (isset($validated['name'])) {
                DB::table('ration')->where('id', $id)->update(['name' => $validated['name']]);
            }

            // Если переданы новые блюда, обновляем связи
            if (isset($validated['meals'])) {
                // Удаляем старые связи
                DB::table('dish_ration')->where('ration_id', $id)->delete();

                // Добавляем новые
                foreach ($validated['meals'] as $meal) {
                    foreach ($meal['items'] ?? [] as $item) {
                        if (isset($item['excluded']) && $item['excluded'] === true) {
                            continue;
                        }

                        DB::table('dish_ration')->insert([
                            'dish_multiplier' => $item['multiplier'] ?? 1,
                            'meal_type' => $this->getMealTypeNameById($meal['meal_time_id']),
                            'ration_id' => $id,
                            'dish_id' => $item['dish_id']
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Рацион успешно обновлён'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении рациона: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удаление рациона
     */
    public function destroy(Request $request, $id)
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Необходимо авторизоваться'
            ], 401);
        }

        $ration = DB::table('ration')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$ration) {
            return response()->json([
                'success' => false,
                'message' => 'Рацион не найден'
            ], 404);
        }

        try {
            DB::beginTransaction();
            DB::table('dish_ration')->where('ration_id', $id)->delete();
            DB::table('ration')->where('id', $id)->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Рацион успешно удалён'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении рациона: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить название приёма пищи по ID
     */
    private function getMealTypeNameById($mealTimeId)
    {
        $mealTypes = [
            1 => 'Завтрак',
            2 => 'Обед',
            3 => 'Полдник',
            4 => 'Ужин',
            5 => 'Второй завтрак'
        ];
        
        return $mealTypes[$mealTimeId] ?? 'Завтрак';
    }
}