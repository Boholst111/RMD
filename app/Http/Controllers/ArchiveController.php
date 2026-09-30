<?php

namespace App\Http\Controllers;

use App\Models\TruckLoad;
use App\Models\Supplier;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ArchiveController extends Controller
{
    /**
     * Show archived records for Super Admin
     */
    public function index()
    {
        $archivedTruckLoads = TruckLoad::onlyTrashed()->with('supplier')->latest('deleted_at')->paginate(20);
        $archivedSuppliers = Supplier::onlyTrashed()->latest('deleted_at')->paginate(20);

        return view('admin.archive', compact('archivedTruckLoads', 'archivedSuppliers'));
    }

    public function restoreTruckLoad(Request $request, $id)
    {
        $truckLoad = DB::transaction(function () use ($request, $id) {
            $truckLoad = TruckLoad::onlyTrashed()->where('id', $id)->firstOrFail();
            $truckLoad->restore();

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name,
                'action' => 'TruckLoad Restored',
                'details' => "Super Admin restored Scale Sheet #{$truckLoad->scale_sheet_no}.",
                'ip_address' => $request->ip(),
            ]);

            return $truckLoad;
        });

        return back()->with('success', "Scale Sheet #{$truckLoad->scale_sheet_no} restored.");
    }

    public function forceDeleteTruckLoad(Request $request, $id)
    {
        $sheetNo = DB::transaction(function () use ($request, $id) {
            $truckLoad = TruckLoad::onlyTrashed()->where('id', $id)->firstOrFail();
            $sheetNo = $truckLoad->scale_sheet_no;
            $truckLoad->forceDelete();

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name,
                'action' => 'TruckLoad Permanently Deleted',
                'details' => "Super Admin permanently deleted Scale Sheet #{$sheetNo}.",
                'ip_address' => $request->ip(),
            ]);

            return $sheetNo;
        });

        return back()->with('success', "Scale Sheet #{$sheetNo} permanently deleted.");
    }

    public function restoreSupplier(Request $request, $id)
    {
        $supplier = DB::transaction(function () use ($request, $id) {
            $supplier = Supplier::onlyTrashed()->where('id', $id)->firstOrFail();
            $supplier->restore();

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name,
                'action' => 'Supplier Restored',
                'details' => "Super Admin restored supplier {$supplier->name}.",
                'ip_address' => $request->ip(),
            ]);

            return $supplier;
        });

        return back()->with('success', "Supplier {$supplier->name} restored.");
    }

    public function forceDeleteSupplier(Request $request, $id)
    {
        $name = DB::transaction(function () use ($request, $id) {
            $supplier = Supplier::onlyTrashed()->where('id', $id)->firstOrFail();
            $name = $supplier->name;
            $supplier->forceDelete();

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name,
                'action' => 'Supplier Permanently Deleted',
                'details' => "Super Admin permanently deleted supplier {$name}.",
                'ip_address' => $request->ip(),
            ]);

            return $name;
        });

        return back()->with('success', "Supplier {$name} permanently deleted.");
    }
}
