<?php

namespace App\Http\Controllers;

use App\Models\PriceMatrix;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\TruckLoad;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Super Admin Master Dashboard
     */
    public function dashboard()
    {
        $staffUsers = User::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();
        $priceMatrices = PriceMatrix::orderBy('category')
            ->orderBy('length')
            ->orderBy('dia_min')
            ->orderBy('dia_max')
            ->get()
            ->unique(fn ($row) => sprintf('%s|%s|%s|%s', strtoupper($row->category), $row->length, $row->dia_min, $row->dia_max))
            ->values();
        $auditLogs = AuditLog::latest()->take(25)->get();
        $completedLoads = TruckLoad::with('supplier')->latest()->paginate(10);

        return view('admin.dashboard', compact(
            'staffUsers',
            'suppliers',
            'priceMatrices',
            'auditLogs',
            'completedLoads'
        ));
    }

    public function prices()
    {
        $priceMatrices = PriceMatrix::whereIn('category', ['FALCATA', 'SAWMILL'])
            ->orderBy('dia_min', 'asc')
            ->orderBy('length', 'asc')
            ->get();

        $categories = Category::orderBy('name')->get();

        return view('admin.prices', compact('priceMatrices', 'categories'));
    }

    /**
     * Store a new dynamic category
     */
    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:categories,name|max:255',
        ]);

        DB::transaction(fn () => Category::create(['name' => $validated['name']]));

        // Invalidate cached categories and price matrix
        Cache::forget('active_price_categories');
        Cache::forget('active_price_matrix');

        return back()->with('success', 'New Category added successfully!');
    }

    /**
     * Update an existing category and propagate to price_matrices
     */
    public function updateCategory(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
        ]);

        $oldName = $category->name;
        DB::transaction(function () use ($category, $oldName, $request) {
            $category->update(['name' => $request->name]);

            PriceMatrix::where('category', $oldName)->update(['category' => $request->name]);
        });

        Cache::forget('active_price_categories');
        Cache::forget('active_price_matrix');

        return back()->with('success', 'Category updated successfully!');
    }

    /**
     * Delete a category (and optionally orphaned price rows)
     */
    public function destroyCategory(Request $request, Category $category)
    {
        $count = PriceMatrix::where('category', $category->name)->count();
        if ($count > 0) {
            return back()->with('error', 'Category cannot be deleted because there are ' . $count . ' price matrix rows referencing it. Please delete or reassign those rows first.');
        }

        DB::transaction(function () use ($category, $request) {
            $category->delete();

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name,
                'action' => 'Category Deleted',
                'details' => "Deleted category {$category->name}",
                'ip_address' => $request->ip(),
            ]);
        });

        Cache::forget('active_price_categories');
        Cache::forget('active_price_matrix');

        return back()->with('success', 'Category deleted successfully.');
    }

    /**
     * JSON endpoint for active price matrix - FRESH FROM DB, NO CACHE
     * Called by scaler forms to refresh prices dynamically
     * Returns current prices from database directly (strict real-time sync)
     */
    public function apiPriceMatrix()
    {
        $data = PricingService::getFreshPriceMatrix();
        return response()->json($data);
    }

    /**
     * JSON endpoint to fetch a single rate on demand
     * Scaler forms call this when rows change to get fresh rates
     * Parameters: category, length, diameter, grade
     */
    public function apiGetRate(Request $request)
    {
        $category = $request->query('category', 'FALCATA');
        $length = (float) $request->query('length', 2.6);
        $diameter = (int) $request->query('diameter', 20);
        $grade = $request->query('grade', 'Good');

        $rate = PricingService::getRate($category, $length, $diameter, $grade);
        
        return response()->json([
            'rate' => $rate,
            'category' => $category,
            'length' => $length,
            'diameter' => $diameter,
            'grade' => $grade,
        ]);
    }

    /**
     * Update Dynamic Price Matrix
     * STRICT: Clears all caches immediately so scalers get fresh rates on next request
     */
    public function updatePriceMatrix(Request $request)
    {
        $rawPrices = $request->input('prices', []);

        $normalizedPrices = [];
        foreach ($rawPrices as $index => $item) {
            $id = $item['id'] ?? null;
            $price = $item['price'] ?? $item['price_per_cu_m'] ?? null;

            if ($id !== null && $price !== null && is_numeric($price)) {
                $normalizedPrices[$index] = [
                    'id' => $id,
                    'price' => (float) $price,
                ];
            }
        }

        $request->merge(['prices' => $normalizedPrices]);

        $validated = $request->validate([
            'prices' => 'required|array',
            'prices.*.id' => 'required|exists:price_matrices,id',
            'prices.*.price' => 'required|numeric|min:0|max:999999999999.99',
        ]);

        DB::transaction(function () use ($validated, $request) {
            foreach ($validated['prices'] as $item) {
                $pm = PriceMatrix::find($item['id']);
                if (! $pm) {
                    continue;
                }

                $matchingRows = PriceMatrix::where('category', $pm->category)
                    ->where('length', $pm->length)
                    ->where('dia_min', $pm->dia_min)
                    ->where('dia_max', $pm->dia_max)
                    ->get();

                foreach ($matchingRows as $matchingRow) {
                    $matchingRow->price_per_cu_m = (float) $item['price'];
                    $matchingRow->save();
                }
            }

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name,
                'action' => 'Price Matrix Updated',
                'details' => 'Super Admin updated global wood pricing rates.',
                'ip_address' => $request->ip(),
            ]);
        });

        // STRICT: Clear ALL pricing caches immediately - no stale rates for scalers
        PricingService::clearPricingCache();

        return redirect()->route('admin.dashboard')->with('success', 'Dynamic Price Matrix updated successfully! Scalers will see new rates immediately.');
    }

    /**
     * Add a new price matrix row for a category/length/diameter bracket
     * STRICT: Clears all caches immediately for real-time sync
     */
    public function addPriceMatrixRow(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string|max:50',
            'length' => 'required|numeric|min:0.01',
            'dia_min' => 'required|integer|min:0',
            'dia_max' => 'required|integer|min:0|gte:dia_min',
            'price_per_cu_m' => 'required|numeric|min:0|max:999999999999.99',
        ]);

        DB::transaction(function () use ($validated, $request) {
            PriceMatrix::create([
                'category' => strtoupper(trim($validated['category'])),
                'length' => $validated['length'],
                'dia_min' => $validated['dia_min'],
                'dia_max' => $validated['dia_max'],
                'price_per_cu_m' => $validated['price_per_cu_m'],
            ]);

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name,
                'action' => 'Price Matrix Row Added',
                'details' => sprintf('Super Admin added new category rate: %s %.3fm, %d-%d cm, ₱%.3f.',
                    strtoupper(trim($validated['category'])),
                    $validated['length'],
                    $validated['dia_min'],
                    $validated['dia_max'],
                    $validated['price_per_cu_m']
                ),
                'ip_address' => $request->ip(),
            ]);
        });

        PricingService::clearPricingCache();

        return redirect()->route('admin.dashboard')->with('success', 'New price matrix row added! Scalers will see it immediately.');
    }

    public function destroyPriceMatrixRow(Request $request, PriceMatrix $priceMatrix = null)
    {
        if ($priceMatrix) {
            $details = sprintf('Deleted price matrix row: %s %.3fm, %d-%d cm, ₱%.3f. Cache cleared immediately for real-time sync.',
                $priceMatrix->category,
                $priceMatrix->length,
                $priceMatrix->dia_min,
                $priceMatrix->dia_max,
                $priceMatrix->price_per_cu_m
            );

            DB::transaction(function () use ($priceMatrix, $details, $request) {
                $priceMatrix->delete();

                AuditLog::create([
                    'user_id' => Auth::id(),
                    'user_name' => Auth::user()->name,
                    'action' => 'Price Matrix Row Deleted',
                    'details' => $details,
                    'ip_address' => $request->ip(),
                ]);
            });

            PricingService::clearPricingCache();
        }

        return redirect()->route('admin.dashboard')->with('success', 'Price matrix row deleted successfully. Scalers will see changes immediately.');
    }

    /**
     * Create New Supplier Record
     */
    public function storeSupplier(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150|unique:suppliers,name',
            'contact_no' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
        ]);

        $supplier = DB::transaction(function () use ($validated, $request) {
            $supplier = Supplier::create([
                'name' => trim($validated['name']),
                'contact_no' => $validated['contact_no'] ?? null,
                'address' => $validated['address'] ?? null,
            ]);

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name,
                'action' => 'Supplier Added',
                'details' => "Super Admin added supplier {$supplier->name}.",
                'ip_address' => $request->ip(),
            ]);

            return $supplier;
        });

        return back()->with('success', 'Supplier created successfully!');
    }

    public function destroySupplier(Request $request, Supplier $supplier)
    {
        DB::transaction(function () use ($supplier, $request) {
            $supplier->delete();

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name,
                'action' => 'Supplier Deleted',
                'details' => "Super Admin deleted supplier {$supplier->name}.",
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('success', 'Supplier deleted successfully.');
    }

    /**
     * Create New Staff / Scaler User Account
     */
    public function storeStaff(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,super_admin',
        ]);

        $user = DB::transaction(function () use ($validated, $request) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => strtolower(trim($validated['email'])),
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'status' => 'active',
            ]);

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name,
                'action' => 'User Created',
                'details' => "Created new account {$user->name} ({$user->email}) with role {$user->role}.",
                'ip_address' => $request->ip(),
            ]);

            return $user;
        });

        return back()->with('success', "Staff account {$user->name} created successfully!");
    }

    /**
     * Toggle User Account Status (Active / Suspended)
     */
    public function toggleUserStatus(Request $request, User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot suspend your own account!');
        }

        DB::transaction(function () use ($user, $request) {
            $user->status = $user->status === 'active' ? 'suspended' : 'active';
            $user->save();

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name,
                'action' => 'User Status Changed',
                'details' => "Changed status of {$user->name} to {$user->status}.",
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('success', "Account status for {$user->name} updated to {$user->status}.");
    }

    /**
     * Super Admin Historic Data Override: Unlock Record
     */
    public function unlockScaleSheet(Request $request, TruckLoad $truckLoad)
    {
        DB::transaction(function () use ($request, $truckLoad) {
            $truckLoad->status = 'draft';
            $truckLoad->save();

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name,
                'action' => 'Record Unlocked',
                'details' => "Super Admin unlocked Scale Sheet #{$truckLoad->scale_sheet_no} (Invoice #{$truckLoad->invoice_no}) for editing.",
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('success', "Scale Sheet #{$truckLoad->scale_sheet_no} unlocked! Staff can now edit this record.");
    }

    /**
     * Super Admin Override: Force Delete Scale Sheet
     */
    public function destroyScaleSheet(Request $request, TruckLoad $truckLoad)
    {
        $sheetNo = $truckLoad->scale_sheet_no;
        $invoiceNo = $truckLoad->invoice_no;

        DB::transaction(function () use ($request, $truckLoad, $sheetNo, $invoiceNo) {
            $truckLoad->delete();

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name,
                'action' => 'Record Archived',
                'details' => "Super Admin archived Scale Sheet #{$sheetNo} (Invoice #{$invoiceNo}).",
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('success', "Scale Sheet #{$sheetNo} archived by Super Admin.");
    }

    /**
     * Super Admin Override: Toggle Scale Sheet Status (Draft vs Completed/Locked)
     */
    public function updateScaleSheetStatus(Request $request, TruckLoad $truckLoad)
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,completed',
        ]);

        DB::transaction(function () use ($request, $truckLoad, $validated) {
            $oldStatus = $truckLoad->status;
            $truckLoad->status = $validated['status'];
            $truckLoad->save();

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name,
                'action' => 'Scale Sheet Status Changed',
                'details' => sprintf('Super Admin changed Scale Sheet #%s status from %s to %s.',
                    $truckLoad->scale_sheet_no,
                    strtoupper($oldStatus),
                    strtoupper($truckLoad->status)
                ),
                'ip_address' => $request->ip(),
            ]);
        });

        $statusLabel = $truckLoad->status === 'completed' ? 'Finalized / Locked' : 'Draft / Unlocked';
        return back()->with('success', "Scale Sheet #{$truckLoad->scale_sheet_no} status updated to {$statusLabel}.");
    }
}
