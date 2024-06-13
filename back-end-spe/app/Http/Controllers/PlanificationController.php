<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Groupe;
use App\Models\Planification;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PlanificationController extends Controller
{
    public function index(Request $request)
    {
        $data=Planification::get();
        $month = $request->query('month');
        $year = $request->query('year');

        $planifications = Planification::with(['controle.category', 'controle.groupe'])
            ->whereMonth('date', '=', $month)
            ->whereYear('date', '=', $year)
            ->get();
          
            
        return response()->json($planifications);
    }
    public function store(Request $request)
    {
        // Validate the request data
        $request->validate([
            '*.controle_id' => 'required|exists:controles,id',
            '*.dates' => 'required|array',
            '*.dates.*' => 'required|date',
        ]);

        $planifications = $request->all();
        $response = [];

        foreach ($planifications as $plan) {
            $controleId = $plan['controle_id'];
            foreach ($plan['dates'] as $date) {
                // Format the date to Y-m-d format
                $formattedDate = \Carbon\Carbon::parse($date)->format('Y-m-d');

                // Check for unique constraint
                $existingPlanification = Planification::where('controle_id', $controleId)
                    ->where('date_planified', $formattedDate)
                    ->first();

                if ($existingPlanification) {
                    $response[] = [
                        'controle_id' => $controleId,
                        'date_planified' => $formattedDate,
                        'status' => 'exists',
                    ];
                } else {
                    // Create a new Planification
                    $newPlanification = new Planification();
                    $newPlanification->controle_id = $controleId;
                    $newPlanification->date_planified = $formattedDate;
                    $newPlanification->save();

                    $response[] = [
                        'controle_id' => $controleId,
                        'date_planified' => $formattedDate,
                        'status' => 'created',
                    ];
                }
            }
        }

        return response()->json($response);
    }

    public function getPlanificationsForToday(Request $request)
    {
        $today = Carbon::today()->format('Y-m-d');
        $planifications = Planification::with(['controle.category', 'controle.groupe'])
            ->whereDate('date_planified', $today)
            ->get();

        $response = $planifications->groupBy('controle.id_categorie')->map(function ($groupedPlanifications, $idCategory) {
            $category = $groupedPlanifications->first()->controle->category;
            return [
                'id_category' => $category->id,
                'designation' => $category->designation,
                'groups' => $groupedPlanifications->groupBy('controle.number_group')->map(function ($groupedPlanifications, $numberGroup) {
                    return [
                        'number_group' => $numberGroup,
                        'equipments' => $groupedPlanifications->groupBy('controle.number_equip')->map(function ($groupedPlanifications) {
                            $controle = $groupedPlanifications->first()->controle;
                            return [
                                'number_equip' => $controle->number_equip,
                                'dates' => $groupedPlanifications->pluck('date_planified')->map(function ($date) {
                                    return $date->format('Y-m-d');
                                })->values()->all(),
                            ];
                        })->values()->all(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return response()->json($response);
    }

// new 
public function getPlanificationsByMonthYear(Request $request)
{
    $request->validate([
        'month' => 'required|integer|min:1|max:12',
        'year' => 'required|integer|min:1900|max:' . date('Y'),
    ]);

    $month = $request->input('month');
    $year = $request->input('year');

    // Fetch the planifications for the given month and year
    $planifications = Planification::whereYear('date_planified', $year)
        ->whereMonth('date_planified', $month)
        ->with('controle.category', 'controle.groupe')
        ->get();

    // Group the planifications by category and format the response
    $response = $planifications->groupBy('controle.id_categorie')->map(function ($groupedPlanifications) {
        $category = $groupedPlanifications->first()->controle->category;
        return [
            'id_category' => $category->id,
            'designation' => $category->designation,
            'groupes' => $groupedPlanifications->groupBy('controle.number_group')->map(function ($groupedPlanifications, $numberGroup) {
                return [
                    'number_group' => $numberGroup,
                    'equipments' => $groupedPlanifications->groupBy('controle.number_equip')->map(function ($groupedPlanifications) {
                        $controle = $groupedPlanifications->first()->controle;
                        return [
                            'number_equip' => $controle->number_equip,
                            'dates' => $groupedPlanifications->map(function ($planification) {
                                return [
                                    'date' => $planification->date_planified->format('Y-m-d'),
                                    'planificationId' => $planification->id,
                                ];
                            })->values()->all(),
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
        ];
    })->values()->all();

    return response()->json($response, 200);
}



/*
    public function getPlanificationsByMonthYear(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:1900|max:' . date('Y'),
        ]);

        $month = $request->input('month');
        $year = $request->input('year');

        // Fetch the planifications for the given month and year
        $planifications = Planification::whereYear('date_planified', $year)
            ->whereMonth('date_planified', $month)
            ->with('controle.category', 'controle.groupe')
            ->get();
            

        // Group the planifications by category and format the response
        $response = $planifications->groupBy('controle.id_categorie')->map(function ($groupedPlanifications, $idCategory) {
            $category = $groupedPlanifications->first()->controle->category;
            return [
                'id_category' => $category->id,
                'designation' => $category->designation,
                'groupes' => $groupedPlanifications->groupBy('controle.number_group')->map(function ($groupedPlanifications, $numberGroup) {
                    return [
                        'number_group' => $numberGroup,
                        'equipments' => $groupedPlanifications->groupBy('controle.number_equip')->map(function ($groupedPlanifications) {
                            $controle = $groupedPlanifications->first()->controle;
                            return [
                                'number_equip' => $controle->number_equip,
                                'dates' => $groupedPlanifications->pluck('date_planified')->map(function ($date) {
                                    return $date->format('Y-m-d');
                                })->values()->all(),
                            ];
                        })->values()->all(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();
        $result = [
            'status' => true,
            'message' => 'Category has been updated successfully',
            'data' => $response,
        ];

       // return response()->json($result, 200);
        return response()->json($response,200);
    }
    */
    public function getControlDataForParameters()
    {
        $categories = Category::with('controles.groupe')->get();

        $data = $categories->map(function ($category) {
            $groups = [];

            $category->controles->groupBy('groupe.id')->each(function ($controles, $groupId) use (&$groups) {
                if ($groupId === '' || $groupId === null) {
                    $equipments = $controles->pluck('number_equip')->toArray();

                    $groups[] = [
                        'number_group' => null,
                        'equipments' => array_values(array_unique($equipments)),
                    ];
                } else {
                    $group = Groupe::findOrNew($groupId);
                    $equipments = $controles->pluck('number_equip')->toArray();

                    $groups[] = [
                        'number_group' => $group->id,
                        'equipments' => array_values(array_unique($equipments)),
                    ];
                }
            });

            return [
                'id' => $category->id,
                'designation' => $category->designation,
                'groups' => $groups,
            ];
        });

        return response()->json($data);
    }
}