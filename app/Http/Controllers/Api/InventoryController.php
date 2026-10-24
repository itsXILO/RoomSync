<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Services\InventorySyncService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * Update availability and rate for an inventory date, and push to all active channels.
     */
    public function update(Request $request, Inventory $inventory): JsonResponse
    {
        $validated = $request->validate([
            'available' => 'required|integer|min:0',
            'rate'      => 'required|numeric|min:0',
        ]);

        $inventory->update($validated);

        $dateStr = Carbon::parse($inventory->date)->toDateString();

        // Push new inventory and rate to all active channels
        InventorySyncService::pushToChannels(
            $inventory->room_type_id,
            $dateStr,
            $dateStr
        );

        return response()->json($inventory->fresh(), 200);
    }
}
