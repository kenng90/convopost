<?php

namespace App\Services\Catalog;

use App\Models\CatalogInventoryReservation;
use App\Models\CatalogItem;
use App\Models\ListCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CatalogInventoryService
{
    public const DEFAULT_RESERVATION_MINUTES = 30;

    /**
     * @param  list<array{id: string, quantity: int}>  $cartItems
     * @return Collection<int, CatalogInventoryReservation>
     */
    public function reserveForCart(ListCatalog $catalog, array $cartItems, ?string $referenceType = null, ?int $referenceId = null): Collection
    {
        return DB::transaction(function () use ($catalog, $cartItems, $referenceType, $referenceId) {
            $reservations = collect();

            foreach ($cartItems as $cartItem) {
                $itemId = (string) ($cartItem['id'] ?? '');
                $quantity = max(1, (int) ($cartItem['quantity'] ?? 1));

                if ($itemId === '') {
                    throw new RuntimeException('Invalid cart item.');
                }

                $item = app(CatalogItemRepository::class)->findByCatalogItemId($catalog, $itemId);
                if (! $item) {
                    throw new RuntimeException("Product {$itemId} not found.");
                }

                $item = CatalogItem::withoutGlobalScopes()->lockForUpdate()->find($item->id);

                if (! $item->isPurchasable($quantity)) {
                    throw new RuntimeException("Insufficient stock for {$item->title}.");
                }

                if ($item->quantity_available !== null) {
                    $item->quantity_reserved = (int) $item->quantity_reserved + $quantity;
                    if ($item->availableQuantity() < 0) {
                        throw new RuntimeException("Insufficient stock for {$item->title}.");
                    }
                    $item->stock_status = $item->availableQuantity() === 0
                        ? CatalogItem::STOCK_OUT
                        : ($item->availableQuantity() <= 5 ? CatalogItem::STOCK_LOW : CatalogItem::STOCK_IN);
                    $item->save();
                }

                $reservations->push(CatalogInventoryReservation::create([
                    'catalog_item_id' => $item->id,
                    'company_id' => $catalog->company_id,
                    'catalog_id' => $catalog->id,
                    'quantity' => $quantity,
                    'status' => CatalogInventoryReservation::STATUS_RESERVED,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'expires_at' => now()->addMinutes(self::DEFAULT_RESERVATION_MINUTES),
                ]));
            }

            app(CatalogItemRepository::class)->syncJsonColumn($catalog);

            return $reservations;
        });
    }

    public function commitReservation(CatalogInventoryReservation $reservation): void
    {
        if ($reservation->status !== CatalogInventoryReservation::STATUS_RESERVED) {
            return;
        }

        DB::transaction(function () use ($reservation) {
            $item = CatalogItem::withoutGlobalScopes()->lockForUpdate()->find($reservation->catalog_item_id);
            if (! $item) {
                return;
            }

            if ($item->quantity_available !== null) {
                $item->quantity_available = max(0, (int) $item->quantity_available - $reservation->quantity);
                $item->quantity_reserved = max(0, (int) $item->quantity_reserved - $reservation->quantity);
                $item->stock_status = $item->quantity_available <= 0
                    ? CatalogItem::STOCK_OUT
                    : ($item->quantity_available <= 5 ? CatalogItem::STOCK_LOW : CatalogItem::STOCK_IN);
                $item->save();
            }

            $reservation->update([
                'status' => CatalogInventoryReservation::STATUS_COMMITTED,
                'committed_at' => now(),
            ]);

            if ($reservation->catalog_id) {
                $catalog = ListCatalog::withoutGlobalScopes()->find($reservation->catalog_id);
                if ($catalog) {
                    app(CatalogItemRepository::class)->syncJsonColumn($catalog);
                    app(CatalogStoreSyncService::class)->pushInventoryForItem($item);
                }
            }
        });
    }

    public function releaseReservation(CatalogInventoryReservation $reservation): void
    {
        if ($reservation->status !== CatalogInventoryReservation::STATUS_RESERVED) {
            return;
        }

        DB::transaction(function () use ($reservation) {
            $item = CatalogItem::withoutGlobalScopes()->lockForUpdate()->find($reservation->catalog_item_id);
            if ($item && $item->quantity_available !== null) {
                $item->quantity_reserved = max(0, (int) $item->quantity_reserved - $reservation->quantity);
                $item->stock_status = $item->availableQuantity() === 0 && $item->quantity_available > 0
                    ? CatalogItem::STOCK_LOW
                    : ($item->availableQuantity() > 0 ? CatalogItem::STOCK_IN : CatalogItem::STOCK_OUT);
                $item->save();
            }

            $reservation->update([
                'status' => CatalogInventoryReservation::STATUS_RELEASED,
                'released_at' => now(),
            ]);

            if ($reservation->catalog_id) {
                $catalog = ListCatalog::withoutGlobalScopes()->find($reservation->catalog_id);
                if ($catalog) {
                    app(CatalogItemRepository::class)->syncJsonColumn($catalog);
                }
            }
        });
    }

    public function releaseExpiredReservations(): int
    {
        $count = 0;

        CatalogInventoryReservation::query()
            ->where('status', CatalogInventoryReservation::STATUS_RESERVED)
            ->where('expires_at', '<', now())
            ->each(function (CatalogInventoryReservation $reservation) use (&$count) {
                $this->releaseReservation($reservation);
                $count++;
            });

        return $count;
    }
}
