<?php

namespace App\Actions\ProductPromotion;

use App\Models\ProductPromotion;
use App\Models\Team;
use Illuminate\Support\Collection;

class EvaluateCartPromotionsAction
{
    /**
     * Deteksi promosi Buy-X-Get-Y mana saja yang syaratnya sudah
     * terpenuhi oleh isi keranjang SAAT INI (produk biasa, bukan yang
     * sudah dipilih manual sebagai baris "Promosi X").
     *
     * Sebelumnya kasir HARUS tahu & mencari nama promosi secara manual
     * di katalog. Sekarang sistem yang mendeteksi otomatis dari isi
     * keranjang — kasir tinggal klik "Terapkan" kalau mau (tetap tidak
     * auto-inject diam-diam, supaya kasir selalu sadar promo apa yang
     * ditambahkan dan berapa kali).
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $cartItems  baris produk di keranjang (item_type=product saja)
     * @return Collection<int, array{promotion: ProductPromotion, times: int}>
     */
    public function execute(Team $team, array $cartItems): Collection
    {
        $quantityByProductId = collect($cartItems)
            ->groupBy('product_id')
            ->map(fn (Collection $items) => $items->sum('quantity'));

        if ($quantityByProductId->isEmpty()) {
            return collect();
        }

        $promotions = ProductPromotion::query()
            ->where('team_id', $team->id)
            ->active()
            ->whereHas('triggers', fn ($query) => $query->whereIn('product_id', $quantityByProductId->keys()))
            ->with(['triggers', 'rewards.product:id,name,sku,stock,is_active'])
            ->get();

        return $promotions
            ->map(fn (ProductPromotion $promotion) => [
                'promotion' => $promotion,
                'times' => $this->timesSatisfied($promotion, $quantityByProductId),
            ])
            ->filter(fn (array $result) => $result['times'] > 0)
            ->values();
    }

    /**
     * Berapa kali syarat promosi ini terpenuhi oleh keranjang — dibatasi
     * oleh trigger PALING SEDIKIT terpenuhi kalau promosinya butuh lebih
     * dari satu produk sekaligus (mis. beli 1 Nasi + 1 Ayam gratis 1 Es
     * Teh: kalau di keranjang ada 3 Nasi tapi cuma 1 Ayam, promo cuma
     * terpenuhi 1 kali, bukan 3).
     */
    private function timesSatisfied(ProductPromotion $promotion, Collection $quantityByProductId): int
    {
        if ($promotion->triggers->isEmpty() || $promotion->rewards->isEmpty()) {
            return 0;
        }

        return (int) $promotion->triggers
            ->map(function ($trigger) use ($quantityByProductId) {
                if ((int) $trigger->min_quantity <= 0) {
                    return 0;
                }

                $availableQuantity = (int) ($quantityByProductId->get($trigger->product_id) ?? 0);

                return intdiv($availableQuantity, (int) $trigger->min_quantity);
            })
            ->min();
    }
}
