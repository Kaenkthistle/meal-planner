<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MealPlannerService
{
    private array $usedDishes = [];
    private array $usedIngredients = [];
    private array $excludedIngredients = [];
    private bool $allowCoffee = false;
    private bool $hasSaladToday = false;
    private Collection $dishCache;
    private Collection $dishIngredients;
    private Collection $dishMealTimes;
    private Collection $ingredientParents;
    private Collection $dishMicronutrients;
    private Collection $micronutrients;
    private array $ingredientAncestors = [];
    private array $currentMicro = [];
    private array $typeRole = [
        1  => 'soup',
        2  => 'salad',
        3  => 'main',
        4  => 'side',
        5  => 'dessert',
        6  => 'snack',
        7  => 'drink',
        8  => 'fruit',
        9  => 'addon',
        10 => 'main_side',
    ];
    private bool $isLowCalorie = false;
    private int $heavyDishCount = 0;
    private int $maxHeavyDishes = 1;

    /* =========================================================
     * MAIN
     * ========================================================= */

    public function generate(array $target, int $mealsCount, array $filters = []): array
    {
        $this->reset();
        $target = $this->validateTarget($target);
        $this->isLowCalorie = $target['calories'] <= 1800;
        $this->maxHeavyDishes = $target['calories'] <= 1800 ? 1 : 2;
        $this->loadAllData();
        $this->processFilters($filters);
        $this->resetMicroTracking();
        
        $mealTimes = $this->resolveMealTimes($mealsCount);
        $plan = $this->buildPlan($mealsCount);
        
        // Распределяем калории по приёмам пищи
        $mealCalorieTargets = $this->distributeCalories($target['calories'], $mealsCount);
        
        $meals = [];
        $totals = ['k' => 0.0, 'p' => 0.0, 'f' => 0.0, 'c' => 0.0];
        
        foreach ($mealTimes as $i => $mealTimeId) {
            $mealTarget = [
                'calories' => $mealCalorieTargets[$i],
                'proteins' => $target['proteins'] * ($mealCalorieTargets[$i] / $target['calories']),
                'fats' => $target['fats'] * ($mealCalorieTargets[$i] / $target['calories']),
                'carbs' => $target['carbs'] * ($mealCalorieTargets[$i] / $target['calories']),
            ];
            
            $roles = $plan[$i] ?? ['main_side'];
            $meal = $this->generateMeal($mealTimeId, $roles, $mealTarget, $totals);
            $this->addTotals($totals, $meal);
            $meals[] = $meal;
        }
        
        $meals = $this->calorieFill($meals, $target, $totals);
        
        if (!$this->hasSaladToday) {
            $meals = $this->ensureDailySalad($meals, $totals);
        }
        
        // Улучшаем точность КБЖУ
        $meals = $this->refineNutrients($meals, $target, $totals);
        
        return [
            'meals' => $meals,
            'totals' => [
                'k' => round($totals['k'], 1),
                'p' => round($totals['p'], 1),
                'f' => round($totals['f'], 1),
                'c' => round($totals['c'], 1),
            ],
            'target' => $target,
        ];
    }
    
    /**
     * Распределение калорий по приёмам пищи
     */
    private function distributeCalories(int $totalCalories, int $mealsCount): array
    {
        if ($mealsCount === 3) {
            return [
                round($totalCalories * 0.25), // завтрак 25%
                round($totalCalories * 0.40), // обед 40%
                round($totalCalories * 0.35), // ужин 35%
            ];
        } else {
            return [
                round($totalCalories * 0.25), // завтрак 25%
                round($totalCalories * 0.35), // обед 35%
                round($totalCalories * 0.15), // полдник 15%
                round($totalCalories * 0.25), // ужин 25%
            ];
        }
    }
    
    /**
     * Постобработка для улучшения точности КБЖУ
     */
    private function refineNutrients(array $meals, array $target, array &$totals): array
    {
        // Находим блюда, которые можно скорректировать
        $adjustableItems = [];
        foreach ($meals as $mealIndex => &$meal) {
            foreach ($meal['items'] as $itemIndex => &$item) {
                if ($item['portion_type'] === 'scalable' && !in_array($item['portion_type'], ['fixed', 'whole'])) {
                    $adjustableItems[] = [
                        'mealIndex' => $mealIndex,
                        'itemIndex' => $itemIndex,
                        'multiplier' => $item['multiplier'],
                        'dish' => $this->dishCache[$item['id']] ?? null
                    ];
                }
            }
        }
        
        // Корректируем пока не улучшим попадание
        $attempts = 0;
        $bestTotals = $totals;
        $bestMeals = $meals;
        $bestScore = $this->calculateNutrientScore($totals, $target);
        
        while ($attempts < 20) {
            $improved = false;
            
            foreach ($adjustableItems as &$adj) {
                $dish = $adj['dish'];
                if (!$dish) continue;
                
                $currentMultiplier = $adj['multiplier'];
                
                foreach ([-0.05, 0.05, -0.03, 0.03] as $delta) {
                    $newMultiplier = max(0.7, min(1.1, $currentMultiplier + $delta));
                    if ($newMultiplier == $currentMultiplier) continue;
                    
                    $factor = ((float)$dish->mass * $newMultiplier) / 100;
                    $oldFactor = ((float)$dish->mass * $currentMultiplier) / 100;
                    
                    $newTotals = [
                        'k' => $totals['k'] - $dish->calories * $oldFactor + $dish->calories * $factor,
                        'p' => $totals['p'] - $dish->proteins * $oldFactor + $dish->proteins * $factor,
                        'f' => $totals['f'] - $dish->fats * $oldFactor + $dish->fats * $factor,
                        'c' => $totals['c'] - $dish->carbs * $oldFactor + $dish->carbs * $factor,
                    ];
                    
                    $newScore = $this->calculateNutrientScore($newTotals, $target);
                    
                    if ($newScore < $bestScore && $newTotals['k'] <= $target['calories'] * 1.02) {
                        $bestScore = $newScore;
                        $bestTotals = $newTotals;
                        $adj['multiplier'] = $newMultiplier;
                        $improved = true;
                    }
                }
            }
            
            if (!$improved) break;
            
            // Применяем лучшие изменения
            foreach ($adjustableItems as $adj) {
                $meal = &$meals[$adj['mealIndex']];
                $item = &$meal['items'][$adj['itemIndex']];
                $oldMultiplier = $item['multiplier'];
                $item['multiplier'] = $adj['multiplier'];
                
                $factor = ((float)$item['mass'] / $oldMultiplier * $adj['multiplier']) / 100;
                $item['mass'] = round((float)$item['mass'] / $oldMultiplier * $adj['multiplier'], 1);
                $item['calories'] = round((float)$item['calories'] / $oldMultiplier * $adj['multiplier'], 1);
                $item['proteins'] = round((float)$item['proteins'] / $oldMultiplier * $adj['multiplier'], 1);
                $item['fats'] = round((float)$item['fats'] / $oldMultiplier * $adj['multiplier'], 1);
                $item['carbs'] = round((float)$item['carbs'] / $oldMultiplier * $adj['multiplier'], 1);
            }
            
            $totals = $bestTotals;
            $attempts++;
        }
        
        return $meals;
    }
    
    /**
     * Расчёт точности попадания в КБЖУ
     */
    private function calculateNutrientScore(array $totals, array $target): float
    {
        $score = 0;
        
        if ($target['calories'] > 0) {
            $calError = abs($target['calories'] - $totals['k']) / $target['calories'];
            $score += $calError * 50;
        }
        
        if ($target['proteins'] > 0) {
            $protError = abs($target['proteins'] - $totals['p']) / $target['proteins'];
            $score += $protError * 20;
        }
        
        if ($target['fats'] > 0) {
            $fatError = abs($target['fats'] - $totals['f']) / $target['fats'];
            $score += $fatError * 15;
        }
        
        if ($target['carbs'] > 0) {
            $carbError = abs($target['carbs'] - $totals['c']) / $target['carbs'];
            $score += $carbError * 15;
        }
        
        return $score;
    }

    private function reset(): void
    {
        $this->usedDishes = [];
        $this->usedIngredients = [];
        $this->excludedIngredients = [];
        $this->heavyDishCount = 0;
        $this->currentMicro = [];
        $this->allowCoffee = false;
        $this->hasSaladToday = false;
        $this->isLowCalorie = false;
        $this->ingredientAncestors = [];
    }

    private function resetMicroTracking(): void
    {
        $this->currentMicro = [];
        foreach ($this->micronutrients as $micro) {
            $this->currentMicro[$micro->micronutrient_id] = 0.0;
        }
    }

    private function loadAllData(): void
    {
        $this->dishCache = DB::table('dish')->get()->keyBy('dish_id');
        $this->dishIngredients = DB::table('dish_ingredient')->get()->groupBy('dish_id');
        $this->dishMealTimes = DB::table('dish_meal_time')->get()->groupBy('dish_id');
        $this->ingredientParents = DB::table('ingredient')->pluck('parent_ingredient_id', 'ingredient_id');
        $this->micronutrients = DB::table('micronutrient')->get()->keyBy('micronutrient_id');
        $this->dishMicronutrients = DB::table('dish_micronutrient')->get()->groupBy('dish_id');
        $this->buildIngredientAncestors();
    }

    private function buildIngredientAncestors(): void
    {
        foreach ($this->ingredientParents as $id => $parent) {
            $ancestors = [];
            $current = $parent;
            while ($current !== null) {
                $ancestors[] = (int)$current;
                $current = $this->ingredientParents[$current] ?? null;
            }
            $this->ingredientAncestors[(int)$id] = $ancestors;
        }
    }

    private function processFilters(array $filters): void
    {
        $excluded = array_map('intval', $filters['exclude_ingredients'] ?? []);
        $this->excludedIngredients = $this->getAllChildIngredients($excluded);
        $this->allowCoffee = (bool)($filters['allow_coffee'] ?? false);
    }

    private function getAllChildIngredients(array $ids): array
    {
        $result = [];
        $queue = $ids;
        while (!empty($queue)) {
            $current = (int)array_shift($queue);
            if (in_array($current, $result, true)) continue;
            $result[] = $current;
            foreach ($this->ingredientParents as $childId => $parentId) {
                if ($parentId !== null && (int)$parentId === $current) {
                    $queue[] = (int)$childId;
                }
            }
        }
        return $result;
    }

    private function resolveMealTimes(int $count): array
    {
        return match ($count) {
            3 => [1, 2, 4],
            4 => [1, 2, 3, 4],
            default => [1, 2, 4],
        };
    }

    private function buildPlan(int $count): array
    {
        return match ($count) {
            3 => [['main_side'], ['soup', 'main_side', 'salad'], ['main_side']],
            4 => [['main_side'], ['soup', 'main_side', 'salad'], ['snack', 'dessert', 'fruit'], ['main_side']],
            default => [['main_side'], ['soup', 'main_side', 'salad'], ['main_side']],
        };
    }

    /* =========================================================
     * MEAL GENERATION
     * ========================================================= */

    private function generateMeal(int $mealTimeId, array $roles, array $target, array $totals): array
    {
        $items = [];
        $pool = $this->getFilteredPool($mealTimeId);
        
        if ($mealTimeId === 4) {
            $items = $this->generateDinner($pool, $target, $totals);
        } elseif ($mealTimeId === 3) {
            $items = $this->generateLightSnack($pool, $target, $totals);
        } else {
            foreach ($roles as $role) {
                $candidates = $this->scoreDishes($pool, $role, $target, $totals, $items);
                if ($candidates->isEmpty()) continue;
                $dish = $candidates->take(10)->random();
                $optimized = $this->optimizePortion($dish, $target, $totals);
                $item = $this->formatDish($optimized['dish'], $optimized['multiplier']);
                $items[] = $item;
                $this->markUsed($dish, $dish->calories);
                if ((int)$dish->dish_type_id === 2) $this->hasSaladToday = true;
            }
        }
        
        if ($mealTimeId === 2 && count($items) <= 2 && random_int(1, 100) <= 55) {
            $this->tryAddFruitToLunch($pool, $items, $target, $totals);
        }
        
        if ($mealTimeId === 1 && $this->allowCoffee) {
            $coffee = $this->tryAddCoffee($pool, $items);
            if ($coffee) $items[] = $coffee;
        }
        
        return ['meal_time_id' => $mealTimeId, 'items' => $items];
    }

    private function generateDinner(Collection $pool, array $target, array $totals): array
    {
        $items = [];
        $maxPerDish = $this->getMaxCaloriesPerDish($target['calories']);
        
        if ($this->heavyDishCount >= $this->maxHeavyDishes) {
            $light = $pool->whereIn('dish_type_id', [4, 6, 8, 10])
                ->filter(fn($d) => !$this->used($d) && $d->calories <= 420);
            if (!$light->isEmpty()) {
                $dish = $light->random();
                $opt = $this->optimizePortion($dish, $target, $totals);
                $items[] = $this->formatDish($opt['dish'], $opt['multiplier']);
                $this->markUsed($dish, $dish->calories);
            }
            return $items;
        }
        
        $mainSide = $pool->where('dish_type_id', 10)
            ->filter(fn($d) => !$this->used($d) && $d->calories <= $maxPerDish);
        
        if (!$mainSide->isEmpty() && random_int(1, 100) <= 58) {
            $dish = $mainSide->random();
            $opt = $this->optimizePortion($dish, $target, $totals);
            $items[] = $this->formatDish($opt['dish'], $opt['multiplier']);
            $this->markUsed($dish, $dish->calories);
            return $items;
        }
        
        $mains = $pool->whereIn('dish_type_id', [3, 10])
            ->filter(fn($d) => !$this->used($d) && $d->calories <= $maxPerDish * 1.15);
        $sides = $pool->where('dish_type_id', 4)
            ->filter(fn($d) => !$this->used($d) && $d->calories <= 380);
        
        if (!$mains->isEmpty() && !$sides->isEmpty()) {
            $main = $mains->random();
            $side = $sides->random();
            $optMain = $this->optimizePortion($main, $target, $totals);
            $optSide = $this->optimizePortion($side, $target, $totals);
            $items[] = $this->formatDish($optMain['dish'], $optMain['multiplier']);
            $items[] = $this->formatDish($optSide['dish'], $optSide['multiplier']);
            $this->markUsed($main, $main->calories);
            $this->markUsed($side, $side->calories);
            return $items;
        }
        
        foreach (['main_side', 'main', 'side'] as $role) {
            $candidates = $this->scoreDishes($pool, $role, $target, $totals, $items);
            if ($candidates->isNotEmpty()) {
                $dish = $candidates->take(8)->random();
                $opt = $this->optimizePortion($dish, $target, $totals);
                $items[] = $this->formatDish($opt['dish'], $opt['multiplier']);
                $this->markUsed($dish, $dish->calories);
                break;
            }
        }
        
        return $items;
    }

    private function generateLightSnack(Collection $pool, array $target, array $totals): array
    {
        $items = [];
        $lightPool = $pool->whereIn('dish_type_id', [5, 6, 8])
            ->filter(fn($d) => !$this->used($d) && $d->calories <= 350);
        
        if (!$lightPool->isEmpty()) {
            $dish = $lightPool->random();
            $multiplier = in_array($dish->portion_type, ['fixed', 'whole']) ? 1.0 :
                         $this->optimizePortion($dish, $target, $totals)['multiplier'];
            $items[] = $this->formatDish($dish, $multiplier);
            $this->markUsed($dish, $dish->calories);
        }
        return $items;
    }

    private function markUsed($dish, int $baseCalories): void
    {
        $this->usedDishes[$dish->dish_id] = ($this->usedDishes[$dish->dish_id] ?? 0) + 1;
        $this->registerIngredients($dish->dish_id);
        $this->addMicroFromDish($dish->dish_id);
        if ($baseCalories > 480) $this->heavyDishCount++;
    }

    private function addMicroFromDish(int $dishId): void
    {
        if (!isset($this->dishMicronutrients[$dishId])) return;
        foreach ($this->dishMicronutrients[$dishId] as $dm) {
            $id = (int)$dm->micronutrient_id;
            if (isset($this->currentMicro[$id])) {
                $this->currentMicro[$id] += (float)$dm->mass;
            }
        }
    }

    /* =========================================================
     * MICRONUTRIENT BONUS (усилен)
     * ========================================================= */

    private function calculateMicroBonus($dish): float
    {
        if (!isset($this->dishMicronutrients[$dish->dish_id])) return 0;
        $bonus = 0.0;
        $coveredCount = 0;
        foreach ($this->dishMicronutrients[$dish->dish_id] as $dm) {
            $id = (int)$dm->micronutrient_id;
            if (!isset($this->micronutrients[$id]) || !isset($this->currentMicro[$id])) continue;
            $norm = (float)$this->micronutrients[$id]->recommended_norm;
            if ($norm <= 0) continue;
            $current = $this->currentMicro[$id];
            $deficit = max(0, $norm - $current);
            $provided = (float)$dm->mass;
            if ($deficit > 0 && $provided > 0) {
                $coverage = min(1.2, $provided / ($deficit * 0.7));
                $bonus += $coverage * 65;
                $coveredCount++;
            }
        }
        if ($coveredCount >= 2) {
            $bonus += ($coveredCount - 1) * 25;
        }
        return min($bonus, 180);
    }

    private function scoreDishes(Collection $pool, string $role, array $target, array $totals, array $items): Collection
    {
        $remaining = max(0, $target['calories'] - $totals['k']);
        $maxPerDish = $this->getMaxCaloriesPerDish($target['calories']);
        
        return $pool
            ->filter(function ($dish) use ($role, $items, $maxPerDish, $remaining) {
                if (!$this->roleMatches($role, $this->typeRole[$dish->dish_type_id] ?? '')) return false;
                if ($this->used($dish)) return false;
                if ($this->conflicts($dish, $items)) return false;
                if ($this->isLowCalorie && $dish->calories > 480 && $this->heavyDishCount >= $this->maxHeavyDishes) {
                    return false;
                }
                if ($dish->calories > $maxPerDish * 1.3) return false;
                if ($remaining < 400 && $dish->calories > 380) return false;
                return true;
            })
            ->map(function ($dish) use ($remaining) {
                $score = 500;
                if ((int)$dish->dish_type_id === 2) $score += 400;
                if ((int)$dish->dish_type_id === 1) $score += 320;
                if ((int)$dish->dish_type_id === 8) $score += 290;
                if ($this->hasVegetables($dish)) $score += 140;
                $score -= max(0, $dish->calories - 430) * 3.6;
                if ($remaining < 500) {
                    $score += (500 - $dish->calories) * 2.2;
                }
                $score += $this->calculateMicroBonus($dish);
                $score += rand(-40, 40);
                return ['dish' => $dish, 'score' => $score];
            })
            ->sortByDesc('score')
            ->pluck('dish');
    }

    private function getMaxCaloriesPerDish(int $totalTarget): int
    {
        if ($totalTarget <= 1700) return 460;
        if ($totalTarget <= 2000) return 580;
        return 750;
    }

    private function optimizePortion($dish, array $target, array $totals): array
    {
        $portionType = $dish->portion_type ?? 'scalable';
        
        if ($portionType === 'fixed' || $portionType === 'whole' || (int)$dish->dish_type_id === 8) {
            return ['dish' => $dish, 'multiplier' => 1.0];
        }
        
        $remainingCal = $target['calories'] - $totals['k'];
        
        // Рассчитываем идеальный множитель исходя из КБЖУ
        $idealByCal = $remainingCal / max(0.1, $dish->calories);
        $idealByProt = ($target['proteins'] - $totals['p']) / max(0.1, $dish->proteins);
        $idealByFat = ($target['fats'] - $totals['f']) / max(0.1, $dish->fats);
        $idealByCarb = ($target['carbs'] - $totals['c']) / max(0.1, $dish->carbs);
        
        // Усредняем с учетом важности
        $weights = ['cal' => 0.4, 'prot' => 0.3, 'fat' => 0.15, 'carb' => 0.15];
        $idealMultiplier = 0;
        
        if ($dish->calories > 0) $idealMultiplier += $idealByCal * $weights['cal'];
        if ($dish->proteins > 0) $idealMultiplier += $idealByProt * $weights['prot'];
        if ($dish->fats > 0) $idealMultiplier += $idealByFat * $weights['fat'];
        if ($dish->carbs > 0) $idealMultiplier += $idealByCarb * $weights['carb'];
        
        $idealMultiplier = max(0.7, min(1.1, $idealMultiplier));
        
        // Формируем варианты вокруг идеального множителя
        $variants = [];
        for ($m = max(0.7, $idealMultiplier - 0.15); $m <= min(1.1, $idealMultiplier + 0.15); $m += 0.05) {
            $variants[] = round($m, 2);
        }
        $variants = array_unique($variants);
        sort($variants);
        
        // Добавляем стандартные варианты
        if (!in_array(0.7, $variants)) $variants[] = 0.7;
        if (!in_array(0.8, $variants)) $variants[] = 0.8;
        if (!in_array(0.9, $variants)) $variants[] = 0.9;
        if (!in_array(1.0, $variants)) $variants[] = 1.0;
        if (!in_array(1.1, $variants) && $remainingCal > $target['calories'] * 0.25 && !$this->isLowCalorie) {
            $variants[] = 1.1;
        }
        
        sort($variants);
        
        if ($this->isLowCalorie && in_array(1.1, $variants)) {
            $variants = array_filter($variants, fn($m) => $m < 1.1);
        }
        
        $best = 1.0;
        $bestScore = PHP_FLOAT_MAX;
        
        foreach ($variants as $m) {
            $factor = ((float)$dish->mass * $m) / 100;
            $projectedCal = $totals['k'] + ((float)$dish->calories * $factor);
            $projectedProt = $totals['p'] + $dish->proteins * $factor;
            $projectedFat = $totals['f'] + $dish->fats * $factor;
            $projectedCarb = $totals['c'] + $dish->carbs * $factor;
            
            if ($projectedCal > $target['calories'] * 1.02) {
                continue;
            }
            
            $overageCal = max(0, $projectedCal - $target['calories']);
            
            $score = 0;
            
            if ($target['calories'] > 0) {
                $calError = abs($target['calories'] - $projectedCal) / $target['calories'];
                $score += $calError * 60;
            }
            
            if ($target['proteins'] > 0) {
                $protError = abs($target['proteins'] - $projectedProt) / $target['proteins'];
                $score += $protError * 20;
            }
            
            if ($target['fats'] > 0) {
                $fatError = abs($target['fats'] - $projectedFat) / $target['fats'];
                $score += $fatError * 15;
            }
            
            if ($target['carbs'] > 0) {
                $carbError = abs($target['carbs'] - $projectedCarb) / $target['carbs'];
                $score += $carbError * 15;
            }
            
            if ($overageCal > 0) {
                $score += ($overageCal / $target['calories']) * 100;
            }
            
            $distanceFromIdeal = abs($m - $idealMultiplier);
            $score += $distanceFromIdeal * 5;
            
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $m;
            }
        }
        
        $finalCal = $totals['k'] + ((float)$dish->calories * $best);
        if ($finalCal > $target['calories'] * 1.03) {
            foreach ([0.95, 0.9, 0.85, 0.8, 0.75, 0.7] as $m) {
                $testCal = $totals['k'] + ((float)$dish->calories * $m);
                if ($testCal <= $target['calories'] * 1.02) {
                    $best = $m;
                    break;
                }
            }
        }
        
        if ($remainingCal < $target['calories'] * 0.15 && $best > 1.0) {
            $best = 1.0;
        }
        
        if ($best < 0.7) {
            $best = 0.7;
        }
        
        return ['dish' => $dish, 'multiplier' => round($best, 2)];
    }

    private function formatDish($dish, float $multiplier = 1.0): array
    {
        if (in_array($dish->portion_type, ['fixed', 'whole']) || (int)$dish->dish_type_id === 8) {
            $multiplier = 1.0;
        }
        $factor = ((float)$dish->mass * $multiplier) / 100;
        return [
            'id'            => $dish->dish_id,
            'name'          => $dish->name,
            'portion_type'  => $dish->portion_type,
            'multiplier'    => $multiplier,
            'scaled'        => abs($multiplier - 1.0) > 0.01,
            'mass'          => round((float)$dish->mass * $multiplier, 1),
            'calories'      => round((float)$dish->calories * $factor, 1),
            'proteins'      => round((float)$dish->proteins * $factor, 1),
            'fats'          => round((float)$dish->fats * $factor, 1),
            'carbs'         => round((float)$dish->carbs * $factor, 1),
        ];
    }

    // ====================== ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ ======================

    private function getFilteredPool(int $mealTimeId): Collection
    {
        return $this->dishCache->filter(function ($dish) use ($mealTimeId) {
            if ($this->hasExcludedIngredient($dish->dish_id)) return false;
            return isset($this->dishMealTimes[$dish->dish_id])
                && $this->dishMealTimes[$dish->dish_id]->contains('meal_time_id', $mealTimeId);
        });
    }

    private function hasExcludedIngredient(int $dishId): bool
    {
        if (empty($this->excludedIngredients) || !isset($this->dishIngredients[$dishId])) return false;
        foreach ($this->dishIngredients[$dishId] as $link) {
            $id = (int)$link->ingredient_id;
            if (in_array($id, $this->excludedIngredients, true) ||
                array_intersect($this->ingredientAncestors[$id] ?? [], $this->excludedIngredients)) {
                return true;
            }
        }
        return false;
    }

    private function hasVegetables($dish): bool
    {
        $ingredients = $this->dishIngredients[$dish->dish_id] ?? collect();
        foreach ($ingredients as $ing) {
            $id = (int)$ing->ingredient_id;
            if ($id === 18 || in_array(18, $this->ingredientAncestors[$id] ?? [], true)) return true;
        }
        return false;
    }

    private function registerIngredients(int $dishId): void
    {
        $ingredients = $this->dishIngredients[$dishId] ?? collect();
        foreach ($ingredients as $ing) {
            $id = (int)$ing->ingredient_id;
            $this->usedIngredients[$id] = ($this->usedIngredients[$id] ?? 0) + 1;
        }
    }

    private function used($dish): bool
    {
        return isset($this->usedDishes[$dish->dish_id]);
    }

    private function roleMatches(string $requested, string $actual): bool
    {
        if ($requested === $actual) return true;
        if ($actual === 'main_side' && in_array($requested, ['main', 'side'])) return true;
        if ($requested === 'main_side' && in_array($actual, ['main', 'side', 'main_side'])) return true;
        return false;
    }

    private function conflicts($dish, array $items): bool
    {
        $dishType = (int)$dish->dish_type_id;
        $dishRole = $this->typeRole[$dishType] ?? '';
        if ($dishType === 9) {
            $hasSoup = collect($items)->contains(fn($item) =>
                ($this->dishCache[$item['id'] ?? 0] ?? null)?->dish_type_id === 1
            );
            if (!$hasSoup) return true;
        }
        $mainCount = 0;
        foreach ($items as $item) {
            $itemDish = $this->dishCache[$item['id']] ?? null;
            if ($itemDish && in_array($this->typeRole[$itemDish->dish_type_id] ?? '', ['main', 'side', 'main_side'])) {
                $mainCount++;
            }
        }
        if (in_array($dishRole, ['main', 'side', 'main_side']) && $mainCount >= 2) return true;
        return false;
    }

    private function addTotals(array &$totals, array $meal): void
    {
        foreach ($meal['items'] as $item) {
            $totals['k'] += $item['calories'];
            $totals['p'] += $item['proteins'];
            $totals['f'] += $item['fats'];
            $totals['c'] += $item['carbs'];
        }
    }

    private function tryAddCoffee(Collection $pool, array $items): ?array
    {
        $coffee = $pool->first(fn($d) => (int)$d->dish_type_id === 7);
        if (!$coffee || $this->used($coffee) || $this->conflicts($coffee, $items)) return null;
        return $this->formatDish($coffee);
    }

    private function tryAddFruitToLunch(Collection $pool, array &$items, array $target, array $totals): void
    {
        $fruit = $pool->where('dish_type_id', 8)
            ->filter(fn($d) => !$this->used($d))
            ->first();
        if ($fruit) {
            $opt = $this->optimizePortion($fruit, $target, $totals);
            $items[] = $this->formatDish($opt['dish'], $opt['multiplier']);
            $this->markUsed($fruit, $fruit->calories);
        }
    }

    private function calorieFill(array $meals, array $target, array &$totals): array
    {
        $attempts = 0;
        while ($totals['k'] < $target['calories'] * 0.93 && $attempts < 10) {
            $attempts++;
            foreach ($meals as &$meal) {
                if (count($meal['items']) >= 3) continue;
                $pool = $this->getFilteredPool($meal['meal_time_id']);
                $dish = $pool->first(fn($d) => !$this->used($d) && !$this->conflicts($d, $meal['items']));
                if (!$dish) continue;
                $optimized = $this->optimizePortion($dish, $target, $totals);
                $item = $this->formatDish($optimized['dish'], $optimized['multiplier']);
                if ($totals['k'] + $item['calories'] > $target['calories'] * 1.04) break;
                $meal['items'][] = $item;
                $this->markUsed($dish, $dish->calories);
                $this->addTotals($totals, ['items' => [$item]]);
                break;
            }
        }
        return $meals;
    }

    private function ensureDailySalad(array $meals, array &$totals): array
    {
        foreach ($meals as &$meal) {
            if (count($meal['items']) >= 3) continue;
            $pool = $this->getFilteredPool($meal['meal_time_id']);
            $salad = $pool->first(fn($d) =>
                (int)$d->dish_type_id === 2 &&
                !$this->used($d) &&
                !$this->conflicts($d, $meal['items'])
            );
            if (!$salad) continue;
            $item = $this->formatDish($salad, 0.9);
            $meal['items'][] = $item;
            $this->registerIngredients($salad->dish_id);
            $this->addTotals($totals, ['items' => [$item]]);
            $this->hasSaladToday = true;
            break;
        }
        return $meals;
    }

    private function validateTarget(array $t): array
    {
        foreach (['calories', 'proteins', 'fats', 'carbs'] as $key) {
            if (!isset($t[$key]) || $t[$key] <= 0) {
                throw ValidationException::withMessages([$key => 'required and positive']);
            }
        }
        return $t;
    }
}