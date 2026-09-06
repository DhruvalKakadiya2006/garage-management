<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AppliesSearchFilterSort;
use App\Http\Requests\FilterLabourMastersRequest;
use App\Http\Requests\FilterSparePartsRequest;
use App\Models\LabourMaster;
use App\Models\SparePart;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    use AppliesSearchFilterSort;

    public function index(Request $request, FilterSparePartsRequest $partsRequest, FilterLabourMastersRequest $labourRequest)
    {
        $tab = $request->query('tab', 'parts');

        if (! in_array($tab, ['parts', 'labour'], true)) {
            $tab = 'parts';
        }

        // Build spare parts query with search/filter/sort
        $partsQuery = SparePart::query();

        $partsValidated = $partsRequest->validated();

        // Apply search to spare parts
        if ($partsRequest->filled('search')) {
            $partsQuery = $this->applySearch(
                $partsQuery,
                $partsValidated['search'],
                ['part_name', 'part_code', 'category']
            );
        }

        // Filter by category
        if ($partsRequest->filled('category')) {
            $partsQuery->where('category', $partsValidated['category']);
        }

        // Apply sorting for spare parts
        $partsSortColumn = $partsValidated['sort'] ?? 'part_name';
        $partsSortDirection = $partsValidated['direction'] ?? 'asc';

        $partsQuery = $this->applySort(
            $partsQuery,
            $partsSortColumn,
            $partsSortDirection,
            ['part_name', 'mrp', 'labor_charge', 'category'],
            ['column' => 'part_name', 'direction' => 'asc']
        );

        $partsPerPage = $this->getPerPage($partsRequest);
        $spareParts = $partsQuery->paginate($partsPerPage, ['*'], 'parts_page')->withQueryString();

        // Build labour masters query with search/filter/sort
        $labourQuery = LabourMaster::query();

        $labourValidated = $labourRequest->validated();

        // Apply search to labour masters
        if ($labourRequest->filled('search')) {
            $labourQuery = $this->applySearch(
                $labourQuery,
                $labourValidated['search'],
                ['service_name', 'category']
            );
        }

        // Filter by taxable status
        if ($labourRequest->filled('taxable') && $labourValidated['taxable'] !== 'all') {
            if ($labourValidated['taxable'] === 'taxable') {
                $labourQuery->where('taxable', true);
            } elseif ($labourValidated['taxable'] === 'non_taxable') {
                $labourQuery->where('taxable', false);
            }
        }

        // Apply sorting for labour masters
        $labourSortColumn = $labourValidated['sort'] ?? 'service_name';
        $labourSortDirection = $labourValidated['direction'] ?? 'asc';

        $labourQuery = $this->applySort(
            $labourQuery,
            $labourSortColumn,
            $labourSortDirection,
            ['service_name', 'standard_rate', 'taxable'],
            ['column' => 'service_name', 'direction' => 'asc']
        );

        $labourPerPage = $this->getPerPage($labourRequest);
        $labourMasters = $labourQuery->paginate($labourPerPage, ['*'], 'labours_page')->withQueryString();

        // Get unique categories for filter dropdown
        $categories = SparePart::distinct()->pluck('category')->filter()->sort()->values();

        return view('inventory.index', compact('spareParts', 'labourMasters', 'tab', 'categories'));
    }
}
