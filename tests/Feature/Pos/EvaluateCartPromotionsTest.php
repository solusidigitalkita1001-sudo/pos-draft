<?php

use App\Actions\ProductPromotion\EvaluateCartPromotionsAction;
use App\Models\Product;
use App\Models\ProductPromotion;
use App\Models\ProductPromotionReward;
use App\Models\ProductPromotionTrigger;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function promotionProduct(Team $team, array $overrides = []): Product
{
    return Product::create(array_merge([
        'team_id' => $team->id,
        'name' => 'Produk Promo',
        'sku' => 'SKU-'.fake()->unique()->numerify('####'),
        'price' => 15000,
        'stock' => 100,
        'is_active' => true,
    ], $overrides));
}

function buyOneGetOnePromotion(Team $team, Product $trigger, Product $reward, int $triggerMinQty = 1, int $rewardQty = 1): ProductPromotion
{
    $promotion = ProductPromotion::create([
        'team_id' => $team->id,
        'name' => 'Beli '.$trigger->name.' Gratis '.$reward->name,
        'type' => ProductPromotion::TYPE_BXGY,
        'is_active' => true,
    ]);

    ProductPromotionTrigger::create([
        'promotion_id' => $promotion->id,
        'product_id' => $trigger->id,
        'min_quantity' => $triggerMinQty,
    ]);

    ProductPromotionReward::create([
        'promotion_id' => $promotion->id,
        'product_id' => $reward->id,
        'quantity' => $rewardQty,
        'extra_charge' => 0,
    ]);

    return $promotion;
}

test('a promotion is detected once the cart satisfies its trigger', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $trigger = promotionProduct($team, ['name' => 'Nasi Goreng']);
    $reward = promotionProduct($team, ['name' => 'Es Teh']);

    buyOneGetOnePromotion($team, $trigger, $reward, triggerMinQty: 2);

    $results = (new EvaluateCartPromotionsAction)->execute($team, [
        ['product_id' => $trigger->id, 'quantity' => 2],
    ]);

    expect($results)->toHaveCount(1);
    expect($results->first()['times'])->toBe(1);
});

test('a promotion is not detected when the cart falls short of the trigger quantity', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $trigger = promotionProduct($team);
    $reward = promotionProduct($team);

    buyOneGetOnePromotion($team, $trigger, $reward, triggerMinQty: 3);

    $results = (new EvaluateCartPromotionsAction)->execute($team, [
        ['product_id' => $trigger->id, 'quantity' => 2],
    ]);

    expect($results)->toHaveCount(0);
});

test('times satisfied scales with how many multiples of the trigger are in the cart', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $trigger = promotionProduct($team);
    $reward = promotionProduct($team);

    buyOneGetOnePromotion($team, $trigger, $reward, triggerMinQty: 2);

    $results = (new EvaluateCartPromotionsAction)->execute($team, [
        ['product_id' => $trigger->id, 'quantity' => 7], // 3 full multiples of 2, 1 leftover
    ]);

    expect($results->first()['times'])->toBe(3);
});

test('a promotion requiring two different trigger products is capped by the scarcer one', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $productA = promotionProduct($team, ['name' => 'Nasi']);
    $productB = promotionProduct($team, ['name' => 'Ayam']);
    $reward = promotionProduct($team, ['name' => 'Es Teh']);

    $promotion = ProductPromotion::create([
        'team_id' => $team->id,
        'name' => 'Paket Kombo',
        'type' => ProductPromotion::TYPE_BXGY,
        'is_active' => true,
    ]);

    ProductPromotionTrigger::create(['promotion_id' => $promotion->id, 'product_id' => $productA->id, 'min_quantity' => 1]);
    ProductPromotionTrigger::create(['promotion_id' => $promotion->id, 'product_id' => $productB->id, 'min_quantity' => 1]);
    ProductPromotionReward::create(['promotion_id' => $promotion->id, 'product_id' => $reward->id, 'quantity' => 1, 'extra_charge' => 0]);

    // 3 Nasi but only 1 Ayam in cart — promo should only apply once.
    $results = (new EvaluateCartPromotionsAction)->execute($team, [
        ['product_id' => $productA->id, 'quantity' => 3],
        ['product_id' => $productB->id, 'quantity' => 1],
    ]);

    expect($results->first()['times'])->toBe(1);
});

test('an inactive promotion is never detected', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $trigger = promotionProduct($team);
    $reward = promotionProduct($team);

    $promotion = buyOneGetOnePromotion($team, $trigger, $reward);
    $promotion->update(['is_active' => false]);

    $results = (new EvaluateCartPromotionsAction)->execute($team, [
        ['product_id' => $trigger->id, 'quantity' => 5],
    ]);

    expect($results)->toHaveCount(0);
});

test('a promotion from a different team is never detected', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $otherOwner = User::factory()->create();
    $otherTeam = $otherOwner->currentTeam;

    $trigger = promotionProduct($otherTeam);
    $reward = promotionProduct($otherTeam);
    buyOneGetOnePromotion($otherTeam, $trigger, $reward);

    $myProduct = promotionProduct($team);

    $results = (new EvaluateCartPromotionsAction)->execute($team, [
        ['product_id' => $myProduct->id, 'quantity' => 5],
    ]);

    expect($results)->toHaveCount(0);
});

test('the evaluate-promotions endpoint returns detected promotions as PosItem shaped data', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $trigger = promotionProduct($team, ['name' => 'Nasi Goreng']);
    $reward = promotionProduct($team, ['name' => 'Es Teh']);

    buyOneGetOnePromotion($team, $trigger, $reward);

    $response = $this
        ->actingAs($owner)
        ->postJson("/{$team->slug}/pos/promotions/evaluate", [
            'items' => [['product_id' => $trigger->id, 'quantity' => 1]],
        ]);

    $response->assertOk();
    $response->assertJsonCount(1, 'promotions');
    $response->assertJsonPath('promotions.0.item_type', 'promotion');
    $response->assertJsonPath('promotions.0.suggested_quantity', 1);
});

test('an empty cart returns no promotions from the endpoint', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;

    $response = $this
        ->actingAs($owner)
        ->postJson("/{$team->slug}/pos/promotions/evaluate", ['items' => []]);

    $response->assertOk();
    $response->assertJsonCount(0, 'promotions');
});
