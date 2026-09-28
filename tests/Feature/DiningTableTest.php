<?php

use App\Models\DiningTable;
use App\TableStatus;

describe('access', function () {
    test('staff without tables.view cannot see the floor plan', function () {
        $this->actingAs(staffWithPermissions('orders.view'))->get(route('tables.index'))->assertForbidden();
    });

    test('a waiter may change table status but not edit tables', function () {
        $waiter = staffWithPermissions('tables.view', 'tables.status');
        $table = DiningTable::factory()->status(TableStatus::Dirty)->create();

        $this->actingAs($waiter)->put(route('tables.status.update', $table), ['status' => 'available'])->assertRedirect();
        $this->actingAs($waiter)->get(route('tables.create'))->assertForbidden();
        $this->actingAs($waiter)->put(route('tables.update', $table), ['table_no' => 99, 'capacity' => 2])->assertForbidden();
        $this->actingAs($waiter)->delete(route('tables.destroy', $table))->assertForbidden();

        expect($table->fresh())->status->toBe(TableStatus::Available)->table_no->not->toBe(99);
    });

    test('staff who may only view cannot change status', function () {
        $table = DiningTable::factory()->status(TableStatus::Dirty)->create();

        $this->actingAs(staffWithPermissions('tables.view'))
            ->put(route('tables.status.update', $table), ['status' => 'available'])
            ->assertForbidden();

        expect($table->fresh()->status)->toBe(TableStatus::Dirty);
    });
});

describe('floor plan', function () {
    test('tables are grouped by section with status counts', function () {
        DiningTable::factory()->create(['table_no' => 1, 'section' => 'Tầng 1', 'capacity' => 4]);
        DiningTable::factory()->status(TableStatus::Dirty)->create(['table_no' => 7, 'section' => 'Sân vườn', 'capacity' => 2]);

        $this->actingAs(owner())
            ->get(route('tables.index'))
            ->assertOk()
            ->assertSee('2 bàn · 6 chỗ ngồi')
            ->assertSeeInOrder(['Sân vườn', 'Cần dọn', 'Đã dọn xong', 'Tầng 1', 'Trống']);
    });

    test('an empty floor plan explains what to do', function () {
        $this->actingAs(owner())->get(route('tables.index'))->assertOk()->assertSee('Chưa có bàn nào');
    });

    test('the "cleaned" button only appears for tables that need cleaning', function () {
        DiningTable::factory()->create();

        $this->actingAs(owner())->get(route('tables.index'))->assertDontSee('Đã dọn xong');
    });
});

describe('crud', function () {
    test('the create and edit forms render', function () {
        DiningTable::factory()->create(['table_no' => 4]);
        $table = DiningTable::factory()->create(['table_no' => 9]);

        $this->actingAs(owner())->get(route('tables.create'))->assertOk()->assertSee('value="10"', false);
        $this->actingAs(owner())->get(route('tables.edit', $table))->assertOk()->assertSee('Bàn 9');
    });

    test('a new table starts available', function () {
        $this->actingAs(owner())
            ->post(route('tables.store'), ['table_no' => '12', 'capacity' => '4', 'section' => ' Tầng 2 ', 'location' => 'Cạnh cửa sổ'])
            ->assertRedirect(route('tables.index'))
            ->assertSessionHas('status', 'Đã thêm bàn 12.');

        expect(DiningTable::firstWhere('table_no', 12))
            ->capacity->toBe(4)
            ->section->toBe('Tầng 2')
            ->status->toBe(TableStatus::Available);
    });

    test('table numbers must be unique', function () {
        DiningTable::factory()->create(['table_no' => 5]);

        $this->actingAs(owner())
            ->post(route('tables.store'), ['table_no' => '5', 'capacity' => '2'])
            ->assertSessionHasErrors(['table_no' => 'Số bàn này đã tồn tại.']);
    });

    test('required fields and limits are checked', function () {
        $this->actingAs(owner())
            ->post(route('tables.store'), ['capacity' => '0'])
            ->assertSessionHasErrors([
                'table_no' => 'Vui lòng nhập số bàn.',
                'capacity' => 'Bàn phải có ít nhất 1 chỗ.',
            ]);
    });

    test('editing keeps the table number when unchanged', function () {
        $table = DiningTable::factory()->create(['table_no' => 5, 'capacity' => 2]);

        $this->actingAs(owner())
            ->put(route('tables.update', $table), ['table_no' => '5', 'capacity' => '6'])
            ->assertSessionHasNoErrors();

        expect($table->fresh()->capacity)->toBe(6);
    });

    test('a free table can be deleted', function () {
        $table = DiningTable::factory()->create();

        $this->actingAs(owner())->delete(route('tables.destroy', $table))->assertRedirect(route('tables.index'));

        $this->assertModelMissing($table);
    });

    test('an occupied table cannot be deleted', function () {
        $table = DiningTable::factory()->status(TableStatus::Occupied)->create(['table_no' => 3]);

        $this->actingAs(owner())
            ->delete(route('tables.destroy', $table))
            ->assertSessionHas('error', 'Bàn 3 đang có khách, không xóa được.');

        $this->assertModelExists($table);
    });
});

describe('status', function () {
    test('a table can be cleaned, reserved or put under maintenance', function (TableStatus $status) {
        $table = DiningTable::factory()->status(TableStatus::Dirty)->create(['table_no' => 2]);

        $this->actingAs(owner())
            ->put(route('tables.status.update', $table), ['status' => $status->value])
            ->assertSessionHas('status', "Bàn 2: {$status->label()}.");

        expect($table->fresh()->status)->toBe($status);
    })->with([TableStatus::Available, TableStatus::Reserved, TableStatus::Maintenance]);

    test('"occupied" cannot be set by hand', function () {
        $table = DiningTable::factory()->create();

        $this->actingAs(owner())
            ->put(route('tables.status.update', $table), ['status' => 'occupied'])
            ->assertSessionHasErrors(['status' => 'Không thể chọn trạng thái này bằng tay.']);

        expect($table->fresh()->status)->toBe(TableStatus::Available);
    });
});
