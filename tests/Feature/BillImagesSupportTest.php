<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillImagesSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_bill_images_are_persisted_as_array(): void
    {
        $supplier = Supplier::create([
            'name' => 'Test Supplier',
            'contact_person' => 'Supplier Contact',
            'phone' => '01000000001',
            'email' => 'supplier@example.com',
            'is_active' => true,
        ]);

        $purchase = Purchase::create([
            'purchase_number' => 'PO-TEST-001',
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'subtotal' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 100,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
            'bill_images' => [
                'purchases/bills/invoice-1.jpg',
                'purchases/bills/invoice-2.jpg',
            ],
        ]);

        $purchase->refresh();

        $this->assertSame([
            'purchases/bills/invoice-1.jpg',
            'purchases/bills/invoice-2.jpg',
        ], $purchase->bill_images);
    }

    public function test_expense_bill_images_are_persisted_as_array(): void
    {
        $category = ExpenseCategory::create([
            'name' => 'Office',
            'is_active' => true,
        ]);

        $expense = Expense::create([
            'category_id' => $category->id,
            'amount' => 55.5,
            'description' => 'Office supplies',
            'payment_method' => 'cash',
            'expense_date' => now()->toDateString(),
            'bill_images' => [
                'expenses/bills/receipt-1.jpg',
                'expenses/bills/receipt-2.jpg',
            ],
        ]);

        $expense->refresh();

        $this->assertSame([
            'expenses/bills/receipt-1.jpg',
            'expenses/bills/receipt-2.jpg',
        ], $expense->bill_images);
    }
}
