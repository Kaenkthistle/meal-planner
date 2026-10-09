<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class FilterController extends Controller
{
    public function index()
    {
        return response()->json([
            'dish_types' => $this->getDishTypes(),
            'ingredients' => $this->getIngredientsTree(),
            'micronutrients' => $this->getMicronutrients(),
        ]);
    }

    private function getDishTypes()
    {
        return DB::table('dish_type')
            ->select('dish_type_id as id', 'name')
            ->orderBy('dish_type_id')
            ->get();
    }

    private function getMicronutrients()
    {
        return DB::table('micronutrient')
            ->select('micronutrient_id as id', 'name')
            ->orderBy('name')
            ->get();
    }

    private function getIngredientsTree()
    {
        $items = DB::table('ingredient')
            ->select('ingredient_id as id', 'parent_ingredient_id as parent_id', 'name')
            ->whereNotIn('ingredient_id',[6, 8, 9])
            ->orderBy('ingredient_id')
            ->get();

        $grouped = [];

        foreach ($items as $item) {
            $grouped[$item->parent_id ?? 0][] = $item;
        }

        $build = function ($parentId) use (&$build, $grouped) {
            $tree = [];

            foreach ($grouped[$parentId] ?? [] as $item) {
                $tree[] = [
                    'id' => $item->id,
                    'name' => $item->name,
                    'children' => $build($item->id),
                ];
            }

            return $tree;
        };

        return $build(0);
    }
}

