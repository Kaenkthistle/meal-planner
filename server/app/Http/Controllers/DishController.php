<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class DishController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('dish');

        // 🔎 Поиск
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // 🍽 Вид блюда (OR)
        if ($request->dish_types) {
            $ids = explode(',', $request->dish_types);
            $query->whereIn('dish_type_id', $ids);
        }

        // 🍴 Приём пищи (OR)
        if ($request->meal_times) {
            $ids = explode(',', $request->meal_times);

            $query->whereExists(function ($q) use ($ids) {
                $q->select(DB::raw(1))
                    ->from('dish_meal_time')
                    ->whereColumn('dish_meal_time.dish_id', 'dish.dish_id')
                    ->whereIn('meal_time_id', $ids);
            });
        }

        // 🌿 Ингредиенты (с учётом дочерних)
        if ($request->ingredients) {
            $ids = explode(',', $request->ingredients);

            $allIds = DB::table('ingredient')
                ->whereIn('ingredient_id', $ids)
                ->orWhereIn('parent_ingredient_id', $ids)
                ->pluck('ingredient_id');

            $query->whereExists(function ($q) use ($allIds) {
                $q->select(DB::raw(1))
                    ->from('dish_ingredient')
                    ->whereColumn('dish_ingredient.dish_id', 'dish.dish_id')
                    ->whereIn('ingredient_id', $allIds);
            });
        }

        // 🔥 Калории диапазон (на порцию!)
        if ($request->calories_from || $request->calories_to) {
            $query->whereRaw('
                (calories * mass / 100) BETWEEN ? AND ?
            ', [
                $request->calories_from ?? 0,
                $request->calories_to ?? 999999
            ]);
        }

        // 💪 Высокобелковое (>20% калорий из белка)
        if ($request->high_protein) {
            $query->whereRaw('
                (proteins * 4) / calories >= 0.2
            ');
        }

        // 🧪 Микронутриент (один)
        if ($request->micronutrient_id) {
            $id = $request->micronutrient_id;

            $query->join('dish_micronutrient as dm', 'dish.dish_id', '=', 'dm.dish_id')
                ->join('micronutrient as m', 'dm.micronutrient_id', '=', 'm.micronutrient_id')
                ->where('dm.micronutrient_id', $id)
                ->whereRaw('(dm.mass / m.recommended_norm) >= 0.1')
                ->orderByRaw('(dm.mass / m.recommended_norm) DESC');
        }

        // 📦 Пагинация
        $dishes = $query->select([
                'dish.dish_id as id',
                'dish.name',
                'dish.mass',
                'dish.portion_type',
                DB::raw('(dish.proteins * dish.mass / 100) as proteins'),
                DB::raw('(dish.fats * dish.mass / 100) as fats'),
                DB::raw('(dish.carbs * dish.mass / 100) as carbs'),
                DB::raw('(dish.calories * dish.mass / 100) as calories')
            ])
            ->paginate(12);

        return response()->json($dishes, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function show($id)
    {
        $dish = DB::table('dish')
            ->select(
                'dish_id as id',
                'name',
                'mass',
                'portion_type',
                'dish_type_id',
                'max_portions',
                'proteins',
                'fats',
                'carbs',
                'calories',
                'description',
                'ingredients'
            )
            ->where('dish_id', $id)
            ->first();

        if (!$dish) {
            return response()->json([
                'message' => 'Dish not found'
            ], 404);
        }

        // Пересчитываем КБЖУ на порцию (mass грамм)
        $portionProteins = round($dish->proteins * $dish->mass / 100, 1);
        $portionFats = round($dish->fats * $dish->mass / 100, 1);
        $portionCarbs = round($dish->carbs * $dish->mass / 100, 1);
        $portionCalories = round($dish->calories * $dish->mass / 100, 0);

        // Получаем приёмы пищи для блюда
        $mealTimes = DB::table('dish_meal_time')
            ->join('meal_time', 'dish_meal_time.meal_time_id', '=', 'meal_time.meal_time_id')
            ->where('dish_meal_time.dish_id', $id)
            ->select('meal_time.meal_time_id', 'meal_time.name')
            ->get();

        // Получаем ингредиенты (категории из каталога)
        $ingredients = DB::table('dish_ingredient')
            ->join('ingredient', 'dish_ingredient.ingredient_id', '=', 'ingredient.ingredient_id')
            ->where('dish_ingredient.dish_id', $id)
            ->select('ingredient.ingredient_id', 'ingredient.name')
            ->get();

        // Получаем микронутриенты
        $micronutrients = DB::table('dish_micronutrient')
            ->join('micronutrient', 'dish_micronutrient.micronutrient_id', '=', 'micronutrient.micronutrient_id')
            ->where('dish_micronutrient.dish_id', $id)
            ->select(
                'micronutrient.micronutrient_id',
                'micronutrient.name',
                'dish_micronutrient.mass',
                'micronutrient.unit'
            )
            ->get();

        // Декодируем ingredients_list из JSON поля
        $ingredientsList = null;
        if ($dish->ingredients) {
            $ingredientsList = json_decode($dish->ingredients, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $ingredientsList = null;
            }
        }

        // Генерируем URL фото
        $imageUrl = null;
        $imagePath = storage_path('app/public/images/gallery/' . $id . '.webp');
        if (file_exists($imagePath)) {
            $imageUrl = asset('storage/images/gallery/' . $id . '.webp');
        }

        return response()->json([
            'id' => $dish->id,
            'name' => $dish->name,
            'mass' => $dish->mass,
            'dish_type_id' => $dish->dish_type_id,
            'portion_type' => $dish->portion_type,
            'max_portions' => $dish->max_portions,
            'proteins' => $portionProteins,
            'fats' => $portionFats,
            'carbs' => $portionCarbs,
            'calories' => $portionCalories,
            'description' => $dish->description,
            'ingredients_list' => $ingredientsList,
            'meal_times' => $mealTimes,
            'ingredients' => $ingredients,
            'micronutrients' => $micronutrients,
            'image_url' => $imageUrl
        ]);
    }

    /**
     * Создание нового блюда (только для админов)
     */
    public function store(Request $request)
    {
        // Только админ
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Доступ запрещён. Требуется роль администратора.'
            ], 403);
        }

        // ОТЛАДКА: логируем входящие данные
        Log::info('=== СОЗДАНИЕ БЛЮДА ===');
        Log::info('Все поля: ' . json_encode($request->all()));
        Log::info('Есть ли файл image: ' . ($request->hasFile('image') ? 'ДА' : 'НЕТ'));
        if ($request->hasFile('image')) {
            Log::info('Имя файла: ' . $request->file('image')->getClientOriginalName());
            Log::info('Размер: ' . $request->file('image')->getSize());
            Log::info('MIME тип: ' . $request->file('image')->getMimeType());
        }

        // Валидация
        $validated = $request->validate([
            'name' => 'required|string|max:150|unique:dish,name',
            'proteins' => 'required|numeric|min:0|max:100',
            'fats' => 'required|numeric|min:0|max:100',
            'carbs' => 'required|numeric|min:0|max:100',
            'calories' => 'required|numeric|min:0|max:1000',
            'mass' => 'required|numeric|min:1|max:5000',
            'description' => 'nullable|string',
            'ingredients' => 'nullable|json',
            'dish_type_id' => 'required|exists:dish_type,dish_type_id',
            'portion_type' => ['required', Rule::in(['scalable', 'whole', 'fixed'])],
            'max_portions' => 'required|integer|min:1|max:255',
            
            'meal_time_ids' => 'nullable|array',
            'meal_time_ids.*' => 'exists:meal_time,meal_time_id',
            
            'ingredient_ids' => 'nullable|array',
            'ingredient_ids.*' => 'exists:ingredient,ingredient_id',
            
            'micronutrients' => 'nullable|json',
            
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048'
        ]);

        try {
            DB::beginTransaction();

            $galleryPath = storage_path('app/public/images/gallery');
            if (!file_exists($galleryPath)) {
                mkdir($galleryPath, 0777, true);
                Log::info('Папка создана: ' . $galleryPath);
            }

            // Декодируем ingredients
            $ingredientsArray = null;
            if (!empty($validated['ingredients'])) {
                $ingredientsArray = json_decode($validated['ingredients'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Ошибка декодирования ingredients: ' . json_last_error_msg());
                }
                Log::info('ingredients декодированы: ' . json_encode($ingredientsArray));
            }

            // Декодируем micronutrients
            $micronutrientsArray = null;
            if (!empty($validated['micronutrients'])) {
                $decoded = json_decode($validated['micronutrients'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Ошибка декодирования micronutrients: ' . json_last_error_msg());
                }
                
                if (is_array($decoded)) {
                    $micronutrientsArray = [];
                    foreach ($decoded as $item) {
                        if (isset($item['micronutrient_id']) && isset($item['mass'])) {
                            $micronutrientsArray[] = [
                                'micronutrient_id' => $item['micronutrient_id'],
                                'mass' => $item['mass']
                            ];
                        } elseif (isset($item['id']) && isset($item['mass'])) {
                            $micronutrientsArray[] = [
                                'micronutrient_id' => $item['id'],
                                'mass' => $item['mass']
                            ];
                        }
                    }
                }
                Log::info('micronutrients обработаны: ' . json_encode($micronutrientsArray));
            }

            // 1. Создаём блюдо
            $dishId = DB::table('dish')->insertGetId([
                'name' => $validated['name'],
                'proteins' => $validated['proteins'],
                'fats' => $validated['fats'],
                'carbs' => $validated['carbs'],
                'calories' => $validated['calories'],
                'mass' => $validated['mass'],
                'description' => $validated['description'] ?? null,
                'ingredients' => $ingredientsArray ? json_encode($ingredientsArray, JSON_UNESCAPED_UNICODE) : null,
                'dish_type_id' => $validated['dish_type_id'],
                'portion_type' => $validated['portion_type'],
                'max_portions' => $validated['max_portions']
            ]);

            Log::info('Блюдо создано с ID: ' . $dishId);

            // 2. Добавляем связи с приёмами пищи
            if (!empty($validated['meal_time_ids'])) {
                $mealTimeData = array_map(function($mealTimeId) use ($dishId) {
                    return [
                        'dish_id' => $dishId,
                        'meal_time_id' => $mealTimeId
                    ];
                }, $validated['meal_time_ids']);
                
                DB::table('dish_meal_time')->insert($mealTimeData);
                Log::info('Добавлены приёмы пищи: ' . json_encode($validated['meal_time_ids']));
            }

            // 3. Добавляем связи с ингредиентами
            if (!empty($validated['ingredient_ids'])) {
                $ingredientData = array_map(function($ingredientId) use ($dishId) {
                    return [
                        'dish_id' => $dishId,
                        'ingredient_id' => $ingredientId
                    ];
                }, $validated['ingredient_ids']);
                
                DB::table('dish_ingredient')->insert($ingredientData);
                Log::info('Добавлены ингредиенты: ' . json_encode($validated['ingredient_ids']));
            }

            // 4. Добавляем микронутриенты
            if (!empty($micronutrientsArray) && is_array($micronutrientsArray)) {
                $micronutrientData = array_map(function($item) use ($dishId) {
                    return [
                        'dish_id' => $dishId,
                        'micronutrient_id' => $item['micronutrient_id'],
                        'mass' => $item['mass']
                    ];
                }, $micronutrientsArray);
                
                DB::table('dish_micronutrient')->insert($micronutrientData);
                Log::info('Добавлены микронутриенты: ' . json_encode($micronutrientsArray));
            }

            // 5. Сохраняем фото (если есть)
            $imageUrl = null;
            if ($request->hasFile('image')) {
                $filename = $dishId . '.webp';
                $path = $request->file('image')->storeAs('images/gallery', $filename, 'public');
                $imageUrl = asset('storage/' . $path);
                
                $fullPath = storage_path('app/public/images/gallery/' . $filename);
                if (file_exists($fullPath)) {
                    Log::info('Фото сохранено: ' . $fullPath);
                } else {
                    Log::error('Фото НЕ сохранилось: ' . $fullPath);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Блюдо успешно создано',
                'data' => [
                    'dish_id' => $dishId,
                    'name' => $validated['name'],
                    'image_url' => $imageUrl
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при создании блюда: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании блюда: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновление блюда (только для админов)
     */
    public function update(Request $request, $id)
    {
        // Только админ
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Доступ запрещён. Требуется роль администратора.'
            ], 403);
        }

        Log::info('=== ОБНОВЛЕНИЕ БЛЮДА ID: ' . $id . ' ===');
        Log::info('Метод запроса: ' . $request->method());
        Log::info('Все поля: ' . json_encode($request->all()));
        Log::info('Все файлы: ' . json_encode(array_keys($request->files->all())));
        Log::info('Есть ли файл image: ' . ($request->hasFile('image') ? 'ДА' : 'НЕТ'));
        
        if ($request->hasFile('image')) {
            Log::info('Имя файла: ' . $request->file('image')->getClientOriginalName());
            Log::info('Размер: ' . $request->file('image')->getSize());
            Log::info('MIME тип: ' . $request->file('image')->getMimeType());
        }

        $dish = DB::table('dish')->where('dish_id', $id)->first();
        if (!$dish) {
            return response()->json(['message' => 'Dish not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150|unique:dish,name,' . $id . ',dish_id',
            'proteins' => 'sometimes|numeric|min:0|max:100',
            'fats' => 'sometimes|numeric|min:0|max:100',
            'carbs' => 'sometimes|numeric|min:0|max:100',
            'calories' => 'sometimes|numeric|min:0|max:1000',
            'mass' => 'sometimes|numeric|min:1|max:5000',
            'description' => 'nullable|string',
            'ingredients' => 'nullable|json',
            'dish_type_id' => 'sometimes|exists:dish_type,dish_type_id',
            'portion_type' => ['sometimes', Rule::in(['scalable', 'whole', 'fixed'])],
            'max_portions' => 'sometimes|integer|min:1|max:255',
            
            'meal_time_ids' => 'nullable|array',
            'meal_time_ids.*' => 'exists:meal_time,meal_time_id',
            
            'ingredient_ids' => 'nullable|array',
            'ingredient_ids.*' => 'exists:ingredient,ingredient_id',
            
            'micronutrients' => 'nullable|json',
            
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048'
        ]);

        try {
            DB::beginTransaction();

            $galleryPath = storage_path('app/public/images/gallery');
            if (!file_exists($galleryPath)) {
                mkdir($galleryPath, 0777, true);
                Log::info('Папка создана: ' . $galleryPath);
            }

            // 1. Обновляем основные поля
            $updateData = [];
            if (isset($validated['name'])) $updateData['name'] = $validated['name'];
            if (isset($validated['proteins'])) $updateData['proteins'] = $validated['proteins'];
            if (isset($validated['fats'])) $updateData['fats'] = $validated['fats'];
            if (isset($validated['carbs'])) $updateData['carbs'] = $validated['carbs'];
            if (isset($validated['calories'])) $updateData['calories'] = $validated['calories'];
            if (isset($validated['mass'])) $updateData['mass'] = $validated['mass'];
            if (isset($validated['description'])) $updateData['description'] = $validated['description'];
            if (isset($validated['dish_type_id'])) $updateData['dish_type_id'] = $validated['dish_type_id'];
            if (isset($validated['portion_type'])) $updateData['portion_type'] = $validated['portion_type'];
            if (isset($validated['max_portions'])) $updateData['max_portions'] = $validated['max_portions'];
            
            // Обработка ingredients
            if (isset($validated['ingredients'])) {
                if (!empty($validated['ingredients'])) {
                    $ingredientsArray = json_decode($validated['ingredients'], true);
                    if (json_last_error() === JSON_ERROR_NONE && $ingredientsArray !== null) {
                        $updateData['ingredients'] = json_encode($ingredientsArray, JSON_UNESCAPED_UNICODE);
                    } else {
                        $updateData['ingredients'] = null;
                    }
                } else {
                    $updateData['ingredients'] = null;
                }
            }

            if (!empty($updateData)) {
                DB::table('dish')->where('dish_id', $id)->update($updateData);
                Log::info('Обновлены поля: ' . json_encode(array_keys($updateData)));
            }

            // 2. Обновляем связи с приёмами пищи
            if (isset($validated['meal_time_ids'])) {
                DB::table('dish_meal_time')->where('dish_id', $id)->delete();
                
                if (!empty($validated['meal_time_ids'])) {
                    $mealTimeData = array_map(function($mealTimeId) use ($id) {
                        return [
                            'dish_id' => $id,
                            'meal_time_id' => $mealTimeId
                        ];
                    }, $validated['meal_time_ids']);
                    
                    DB::table('dish_meal_time')->insert($mealTimeData);
                    Log::info('Обновлены приёмы пищи: ' . json_encode($validated['meal_time_ids']));
                }
            }

            // 3. Обновляем связи с ингредиентами
            if (isset($validated['ingredient_ids'])) {
                DB::table('dish_ingredient')->where('dish_id', $id)->delete();
                
                if (!empty($validated['ingredient_ids'])) {
                    $ingredientData = array_map(function($ingredientId) use ($id) {
                        return [
                            'dish_id' => $id,
                            'ingredient_id' => $ingredientId
                        ];
                    }, $validated['ingredient_ids']);
                    
                    DB::table('dish_ingredient')->insert($ingredientData);
                    Log::info('Обновлены ингредиенты: ' . json_encode($validated['ingredient_ids']));
                }
            }

            // 4. Обновляем микронутриенты
            if (isset($validated['micronutrients'])) {
                DB::table('dish_micronutrient')->where('dish_id', $id)->delete();
                
                if (!empty($validated['micronutrients'])) {
                    $decoded = json_decode($validated['micronutrients'], true);
                    if (json_last_error() === JSON_ERROR_NONE && !empty($decoded) && is_array($decoded)) {
                        $micronutrientsArray = [];
                        foreach ($decoded as $item) {
                            if (isset($item['micronutrient_id']) && isset($item['mass'])) {
                                $micronutrientsArray[] = [
                                    'micronutrient_id' => $item['micronutrient_id'],
                                    'mass' => $item['mass']
                                ];
                            } elseif (isset($item['id']) && isset($item['mass'])) {
                                $micronutrientsArray[] = [
                                    'micronutrient_id' => $item['id'],
                                    'mass' => $item['mass']
                                ];
                            }
                        }
                        
                        if (!empty($micronutrientsArray)) {
                            $micronutrientData = array_map(function($item) use ($id) {
                                return [
                                    'dish_id' => $id,
                                    'micronutrient_id' => $item['micronutrient_id'],
                                    'mass' => $item['mass']
                                ];
                            }, $micronutrientsArray);
                            
                            DB::table('dish_micronutrient')->insert($micronutrientData);
                            Log::info('Обновлены микронутриенты: ' . json_encode($micronutrientsArray));
                        }
                    }
                }
            }

            // 5. Обновляем фото
            $imageUrl = null;
            if ($request->hasFile('image')) {
                $oldImagePath = storage_path('app/public/images/gallery/' . $id . '.webp');
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                    Log::info('Удалено старое фото: ' . $oldImagePath);
                }
                
                $filename = $id . '.webp';
                $path = $request->file('image')->storeAs('images/gallery', $filename, 'public');
                $imageUrl = asset('storage/' . $path);
                
                $fullPath = storage_path('app/public/images/gallery/' . $filename);
                if (file_exists($fullPath)) {
                    Log::info('Новое фото сохранено: ' . $fullPath);
                } else {
                    Log::error('Новое фото НЕ сохранилось: ' . $fullPath);
                }
            } elseif ($request->input('remove_image') === 'true') {
                // Удаляем фото
                $oldImagePath = storage_path('app/public/images/gallery/' . $id . '.webp');
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                    Log::info('Фото удалено по запросу: ' . $oldImagePath);
                }
            } else {
                $existingImagePath = storage_path('app/public/images/gallery/' . $id . '.webp');
                if (file_exists($existingImagePath)) {
                    $imageUrl = asset('storage/images/gallery/' . $id . '.webp');
                    Log::info('Используем существующее фото: ' . $existingImagePath);
                }
            }

            DB::commit();

            $updatedDish = DB::table('dish')->where('dish_id', $id)->first();

            return response()->json([
                'success' => true,
                'message' => 'Блюдо успешно обновлено',
                'data' => [
                    'dish_id' => $id,
                    'name' => $updatedDish->name,
                    'image_url' => $imageUrl
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при обновлении блюда: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении блюда: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удаление блюда (только для админов)
     */
    public function destroy(Request $request, $id)
    {
        // Только админ
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Доступ запрещён. Требуется роль администратора.'
            ], 403);
        }

        $dish = DB::table('dish')->where('dish_id', $id)->first();
        if (!$dish) {
            return response()->json(['message' => 'Dish not found'], 404);
        }

        try {
            DB::beginTransaction();

            $imagePath = storage_path('app/public/images/gallery/' . $id . '.webp');
            if (file_exists($imagePath)) {
                unlink($imagePath);
                Log::info('Удалено фото: ' . $imagePath);
            }

            DB::table('dish_meal_time')->where('dish_id', $id)->delete();
            DB::table('dish_ingredient')->where('dish_id', $id)->delete();
            DB::table('dish_micronutrient')->where('dish_id', $id)->delete();
            
            DB::table('dish')->where('dish_id', $id)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Блюдо успешно удалено'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при удалении блюда: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении блюда: ' . $e->getMessage()
            ], 500);
        }
    }
}