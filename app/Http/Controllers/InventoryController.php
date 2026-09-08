<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AppliesSearchFilterSort;
use App\Models\LabourMaster;
use App\Models\SparePart;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    use AppliesSearchFilterSort;

    public function index(Request $request)
    {
        $tab = $request->query('tab', 'parts');

        if (! in_array($tab, ['parts', 'labour'], true)) {
            $tab = 'parts';
        }

        // Build spare parts query with search/filter/sort
        $partsQuery = SparePart::query();

        // Apply search to spare parts
        if ($request->filled('search')) {
            $partsQuery = $this->applySearch(
                $partsQuery,
                $request->input('search'),
                ['part_name', 'part_code', 'category']
            );
        }

        // Filter by category
        if ($request->filled('category')) {
            $partsQuery->where('category', $request->input('category'));
        }

        // Apply sorting for spare parts
        $partsSortColumn = $request->input('sort', 'part_name');
        $partsSortDirection = $request->input('direction', 'asc');

        $partsQuery = $this->applySort(
            $partsQuery,
            $partsSortColumn,
            $partsSortDirection,
            ['part_name', 'mrp', 'labor_charge', 'category'],
            ['column' => 'part_name', 'direction' => 'asc']
        );

        $partsPerPage = $this->getPerPage($request);
        $spareParts = $partsQuery->paginate($partsPerPage, ['*'], 'parts_page')->withQueryString();

        // Build labour masters query with search/filter/sort
        $labourQuery = LabourMaster::query();

        // Apply search to labour masters
        if ($request->filled('search')) {
            $labourQuery = $this->applySearch(
                $labourQuery,
                $request->input('search'),
                ['service_name', 'category']
            );
        }

        // Filter by taxable status
        if ($request->filled('taxable') && $request->input('taxable') !== 'all') {
            if ($request->input('taxable') === 'taxable') {
                $labourQuery->where('taxable', true);
            } elseif ($request->input('taxable') === 'non_taxable') {
                $labourQuery->where('taxable', false);
            }
        }

        // Apply sorting for labour masters
        $labourSortColumn = $request->input('sort', 'service_name');
        $labourSortDirection = $request->input('direction', 'asc');

        $labourQuery = $this->applySort(
            $labourQuery,
            $labourSortColumn,
            $labourSortDirection,
            ['service_name', 'standard_rate', 'taxable'],
            ['column' => 'service_name', 'direction' => 'asc']
        );

        $labourPerPage = $this->getPerPage($request);
        $labourMasters = $labourQuery->paginate($labourPerPage, ['*'], 'labours_page')->withQueryString();

        // Get unique categories for filter dropdown
        $categories = SparePart::distinct()->pluck('category')->filter()->sort()->values();

        return view('inventory.index', compact('spareParts', 'labourMasters', 'tab', 'categories'));
    }
}
