<?php

use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BranchAdminController;
use App\Http\Controllers\BranchSelectionController;
use App\Http\Controllers\CashierShiftController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SalesReportController;
use App\Http\Controllers\UserAdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('branches.select')
        : redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::middleware(['auth', 'account.active'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/account/password', [AccountPasswordController::class, 'edit'])->name('account.password.edit');
    Route::put('/account/password', [AccountPasswordController::class, 'update'])
        ->middleware('throttle:10,1')
        ->name('account.password.update');

    Route::get('/admin/users', [UserAdminController::class, 'index'])
        ->middleware('permission:users.manage')
        ->name('admin.users.index');
    Route::post('/admin/users', [UserAdminController::class, 'store'])
        ->middleware(['permission:users.manage', 'throttle:30,1'])
        ->name('admin.users.store');
    Route::put('/admin/users/{user}', [UserAdminController::class, 'update'])
        ->middleware('permission:users.manage')
        ->name('admin.users.update');
    Route::put('/admin/users/{user}/password', [UserAdminController::class, 'resetPassword'])
        ->middleware(['permission:users.manage', 'throttle:10,1'])
        ->name('admin.users.password');
    Route::get('/admin/branches', [BranchAdminController::class, 'index'])
        ->middleware('permission:branches.manage')
        ->name('admin.branches.index');
    Route::post('/admin/branches', [BranchAdminController::class, 'store'])
        ->middleware(['permission:branches.manage', 'throttle:30,1'])
        ->name('admin.branches.store');
    Route::put('/admin/branches/{branch}', [BranchAdminController::class, 'update'])
        ->middleware('permission:branches.manage')
        ->name('admin.branches.update');
    Route::get('/branches/select', [BranchSelectionController::class, 'index'])->name('branches.select');
    Route::post('/branches/select', [BranchSelectionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('branches.select.store');

    Route::get('/dashboard', function (Request $request) {
        return view('dashboard', [
            'branch' => $request->attributes->get('activeBranch'),
        ]);
    })->middleware('branch.access')->name('dashboard');

    Route::middleware('branch.access')->group(function (): void {
        Route::get('/suppliers', [SupplierController::class, 'index'])
            ->middleware('permission:purchases.manage')
            ->name('suppliers.index');
        Route::post('/suppliers', [SupplierController::class, 'store'])
            ->middleware(['permission:purchases.manage', 'throttle:30,1'])
            ->name('suppliers.store');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])
            ->middleware('permission:purchases.manage')
            ->name('suppliers.update');
        Route::get('/purchases', [PurchaseOrderController::class, 'index'])
            ->middleware('permission:purchases.view')
            ->name('purchases.index');
        Route::get('/purchases/create', [PurchaseOrderController::class, 'create'])
            ->middleware('permission:purchases.manage')
            ->name('purchases.create');
        Route::post('/purchases', [PurchaseOrderController::class, 'store'])
            ->middleware(['permission:purchases.manage', 'throttle:30,1'])
            ->name('purchases.store');
        Route::get('/purchases/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
            ->middleware('permission:purchases.view')
            ->name('purchases.show');
        Route::post('/purchases/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])
            ->middleware(['permission:purchases.receive', 'throttle:30,1'])
            ->name('purchases.receive');
        Route::get('/categories', [CategoryController::class, 'index'])
            ->middleware('permission:products.manage')
            ->name('categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])
            ->middleware(['permission:products.manage', 'throttle:30,1'])
            ->name('categories.store');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])
            ->middleware('permission:products.manage')
            ->name('categories.update');
        Route::get('/products', [ProductController::class, 'index'])
            ->middleware('permission:products.view')
            ->name('products.index');
        Route::get('/products/create', [ProductController::class, 'create'])
            ->middleware('permission:products.manage')
            ->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])
            ->middleware(['permission:products.manage', 'throttle:30,1'])
            ->name('products.store');

        Route::get('/pos', [PosController::class, 'index'])
            ->middleware('permission:sales.create')
            ->name('pos.index');
        Route::get('/pos/products', [PosController::class, 'search'])
            ->middleware(['permission:sales.create', 'throttle:60,1'])
            ->name('pos.products.search');
        Route::post('/pos/checkout', [PosController::class, 'store'])
            ->middleware(['permission:sales.create', 'throttle:30,1'])
            ->name('pos.checkout');
        Route::get('/shifts', [CashierShiftController::class, 'index'])
            ->middleware('permission:shifts.manage')
            ->name('shifts.index');
        Route::post('/shifts', [CashierShiftController::class, 'open'])
            ->middleware(['permission:shifts.manage', 'throttle:10,1'])
            ->name('shifts.open');
        Route::post('/shifts/{shift}/close', [CashierShiftController::class, 'close'])
            ->middleware(['permission:shifts.manage', 'throttle:10,1'])
            ->name('shifts.close');
        Route::get('/reports/sales', [SalesReportController::class, 'index'])
            ->middleware('permission:reports.sales')
            ->name('reports.sales');
        Route::get('/reports/financial', [SalesReportController::class, 'financial'])
            ->middleware('permission:reports.financial')
            ->name('reports.financial');
        Route::get('/expenses', [ExpenseController::class, 'index'])
            ->middleware('permission:expenses.manage')
            ->name('expenses.index');
        Route::get('/expenses/create', [ExpenseController::class, 'create'])
            ->middleware('permission:expenses.manage')
            ->name('expenses.create');
        Route::post('/expenses', [ExpenseController::class, 'store'])
            ->middleware(['permission:expenses.manage', 'throttle:30,1'])
            ->name('expenses.store');
        Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])
            ->middleware(['permission:expenses.approve', 'throttle:30,1'])
            ->name('expenses.approve');
        Route::post('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])
            ->middleware(['permission:expenses.approve', 'throttle:30,1'])
            ->name('expenses.reject');
        Route::get('/expenses/{expense}/receipt', [ExpenseController::class, 'receipt'])
            ->middleware('permission:expenses.manage')
            ->name('expenses.receipt');
        Route::get('/expense-categories', [ExpenseCategoryController::class, 'index'])
            ->middleware('permission:expenses.manage')
            ->name('expense-categories.index');
        Route::post('/expense-categories', [ExpenseCategoryController::class, 'store'])
            ->middleware(['permission:expenses.manage', 'throttle:30,1'])
            ->name('expense-categories.store');
        Route::put('/expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'update'])
            ->middleware('permission:expenses.manage')
            ->name('expense-categories.update');
        Route::get('/sales', [SaleController::class, 'index'])
            ->middleware('permission:sales.view')
            ->name('sales.index');
        Route::get('/sales/{sale}/receipt', [SaleController::class, 'receipt'])
            ->middleware('permission:sales.view')
            ->name('sales.receipt');
    });
});
