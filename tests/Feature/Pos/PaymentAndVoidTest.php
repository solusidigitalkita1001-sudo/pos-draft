<?php

use App\Actions\Pos\CreatePosTransactionAction;
use App\Actions\Pos\ProcessTransactionPaymentAction;
use App\Actions\Pos\VoidTransactionAction;
use App\Enums\TeamRole;
use App\Models\Product;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function posProduct(Team $team, array $overrides = []): Product
{
    return Product::create(array_merge([
        'team_id' => $team->id,
        'name' => 'Kopi Susu',
        'sku' => 'SKU-'.fake()->unique()->numerify('####'),
        'price' => 20000,
        'stock' => 50,
        'is_active' => true,
    ], $overrides));
}

test('a POS transaction can be created with a partial payment', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $product = posProduct($team);

    $transaction = app(CreatePosTransactionAction::class)->execute($team, $owner, [
        'items' => [['item_type' => 'product', 'product_id' => $product->id, 'quantity' => 1]],
        'payment_method' => 'cash',
        'paid_amount' => 5000, // grand_total is 20000 — clearly a partial payment
    ]);

    expect($transaction->payment_status)->toBe(Transaction::PAYMENT_STATUS_PARTIAL);
    expect($transaction->status)->toBe(Transaction::STATUS_COMPLETED);
    expect((float) $transaction->paid_amount)->toBe(5000.0);
});

test('a partially paid transaction can receive additional top-up payments, not just full settlement', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $product = posProduct($team);

    $transaction = app(CreatePosTransactionAction::class)->execute($team, $owner, [
        'items' => [['item_type' => 'product', 'product_id' => $product->id, 'quantity' => 1]],
        'payment_method' => 'cash',
        'paid_amount' => 5000,
    ]);

    // Top up with ANOTHER partial amount (not the full remaining 15000) —
    // this used to be rejected before the fix.
    $updated = (new ProcessTransactionPaymentAction)->execute($team, $transaction, [
        'payment_method' => 'cash',
        'paid_amount' => 5000,
    ]);

    expect((float) $updated->paid_amount)->toBe(10000.0);
    expect($updated->payment_status)->toBe(Transaction::PAYMENT_STATUS_PARTIAL);

    // Now fully settle it.
    $settled = (new ProcessTransactionPaymentAction)->execute($team, $updated, [
        'payment_method' => 'cash',
        'paid_amount' => 10000,
    ]);

    expect($settled->payment_status)->toBe(Transaction::PAYMENT_STATUS_PAID);
});

test('tax is calculated from the team tax_rate instead of hardcoded to zero', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $team->update(['tax_rate' => 11]);
    $product = posProduct($team, ['price' => 100000]);

    $transaction = app(CreatePosTransactionAction::class)->execute($team, $owner, [
        'items' => [['item_type' => 'product', 'product_id' => $product->id, 'quantity' => 1]],
        'payment_method' => 'cash',
        'paid_amount' => 111000,
    ]);

    expect((float) $transaction->tax_total)->toBe(11000.0);
    expect((float) $transaction->grand_total)->toBe(111000.0);
});

test('a team with tax_rate 0 produces no tax, unaffected by the change', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $product = posProduct($team, ['price' => 50000]);

    $transaction = app(CreatePosTransactionAction::class)->execute($team, $owner, [
        'items' => [['item_type' => 'product', 'product_id' => $product->id, 'quantity' => 1]],
        'payment_method' => 'cash',
        'paid_amount' => 50000,
    ]);

    expect((float) $transaction->tax_total)->toBe(0.0);
});

test('voiding a transaction restores the exact stock that was deducted', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $product = posProduct($team, ['stock' => 50]);

    $transaction = app(CreatePosTransactionAction::class)->execute($team, $owner, [
        'items' => [['item_type' => 'product', 'product_id' => $product->id, 'quantity' => 3]],
        'payment_method' => 'cash',
        'paid_amount' => 60000,
    ]);

    expect($product->fresh()->stock)->toBe(47);

    $voided = app(VoidTransactionAction::class)->execute($team, $transaction, $owner, 'Salah input');

    expect($voided->status)->toBe(Transaction::STATUS_VOID);
    expect($voided->void_reason)->toBe('Salah input');
    expect($voided->voided_by)->toBe($owner->id);
    expect($product->fresh()->stock)->toBe(50); // fully restored
});

test('a transaction cannot be voided twice', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $product = posProduct($team);

    $transaction = app(CreatePosTransactionAction::class)->execute($team, $owner, [
        'items' => [['item_type' => 'product', 'product_id' => $product->id, 'quantity' => 1]],
        'payment_method' => 'cash',
        'paid_amount' => 20000,
    ]);

    app(VoidTransactionAction::class)->execute($team, $transaction, $owner);

    expect(fn () => app(VoidTransactionAction::class)->execute($team, $transaction->fresh(), $owner))
        ->toThrow(ValidationException::class);
});

test('voiding a transaction does not affect products that were already deleted', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $product = posProduct($team);

    $transaction = app(CreatePosTransactionAction::class)->execute($team, $owner, [
        'items' => [['item_type' => 'product', 'product_id' => $product->id, 'quantity' => 1]],
        'payment_method' => 'cash',
        'paid_amount' => 20000,
    ]);

    $product->delete();

    $voided = app(VoidTransactionAction::class)->execute($team, $transaction, $owner);

    expect($voided->status)->toBe(Transaction::STATUS_VOID);
});

test('the owner can void a transaction through the POS endpoint', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $product = posProduct($team);

    $transaction = app(CreatePosTransactionAction::class)->execute($team, $owner, [
        'items' => [['item_type' => 'product', 'product_id' => $product->id, 'quantity' => 2]],
        'payment_method' => 'cash',
        'paid_amount' => 40000,
    ]);

    $this->actingAs($owner)
        ->post("/{$team->slug}/pos/transaction/{$transaction->id}/void", ['reason' => 'Salah input'])
        ->assertRedirect();

    $transaction->refresh();

    expect($transaction->status)->toBe(Transaction::STATUS_VOID);
    expect($transaction->void_reason)->toBe('Salah input');
    expect($transaction->voided_by)->toBe($owner->id);
    expect($product->fresh()->stock)->toBe(50);
});

test('the void transaction endpoint requires owner access', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $product = posProduct($team);

    $transaction = app(CreatePosTransactionAction::class)->execute($team, $owner, [
        'items' => [['item_type' => 'product', 'product_id' => $product->id, 'quantity' => 1]],
        'payment_method' => 'cash',
        'paid_amount' => 20000,
    ]);

    $cashier = User::factory()->create();
    $team->members()->attach($cashier, ['role' => TeamRole::Member->value]);
    $cashier->switchTeam($team);

    setPermissionsTeamId($team->id);
    $cashier->givePermissionTo(Permission::findOrCreate('transaction.create', 'web'));

    $response = $this
        ->actingAs($cashier)
        ->post("/{$team->slug}/pos/transaction/{$transaction->id}/void", ['reason' => 'test']);

    $response->assertForbidden();
    expect($transaction->fresh()->status)->toBe(Transaction::STATUS_COMPLETED);
    expect($product->fresh()->stock)->toBe(49);
});
