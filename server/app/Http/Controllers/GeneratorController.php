<?php


namespace App\Http\Controllers;


use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Services\MealPlannerService;


class GeneratorController extends Controller
{
    public function filters()
    {
        $ingredients = DB::table('ingredient')
            ->select('ingredient_id as id', 'parent_ingredient_id', 'name')
            ->whereNotIn('ingredient_id', [18, 19])
            ->get();


        $tree = [];


        foreach ($ingredients as $item) {
            if ($item->parent_ingredient_id === null) {
                $tree[$item->id] = [
                    'id' => $item->id,
                    'name' => $item->name,
                    'children' => []
                ];
            }
        }


        foreach ($ingredients as $item) {
            if ($item->parent_ingredient_id !== null) {


                if (isset($tree[$item->parent_ingredient_id])) {


                    $tree[$item->parent_ingredient_id]['children'][] = [
                        'id' => $item->id,
                        'name' => $item->name
                    ];
                }
            }
        }
        return response()->json([
            'ingredients' => array_values($tree)
        ]);
    }

    public function generate(Request $request)
    {
        $service = new MealPlannerService();


        return response()->json(
            $service->generate(
                [
                    'calories' => $request->calories,
                    'proteins' => $request->proteins,
                    'fats' => $request->fats,
                    'carbs' => $request->carbs,
                ],


                (int)$request->input('meals', 4),


                [
                    'exclude_dish_types' =>
                        $request->input('exclude_dish_types', []),


                    'exclude_ingredients' =>
                        $request->input('exclude_ingredients', []),


                    'exclude_properties' =>
                        $request->input('exclude_properties', []),


                    'allow_coffee' =>
                        $request->input('allow_coffee', false),
                ]
            )
        );
    }
}

