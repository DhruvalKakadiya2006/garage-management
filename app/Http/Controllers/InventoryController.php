<?php

namespace App\Http\Controllers;

use App\Models\LabourMaster;
use App\Models\SparePart;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'parts');

        if (! in_array($tab, ['parts', 'labour'], true)) {
            $tab = 'parts';
        }

        $lowStock = $request->boolean('low_stock');

        $spareParts = SparePart::when($lowStock, function ($query) {
                $query->whereColumn('current_stock_qty', '<=', 'reorder_level');
            })
            ->latest()
            ->paginate(15, ['*'], 'parts_page')
            ->withQueryString();

        $labourMasters = LabourMaster::latest()
            ->paginate(15, ['*'], 'labours_page')
            ->withQueryString();

        return view('inventory.index', compact('spareParts', 'labourMasters', 'lowStock', 'tab'));
    }
}
