<?php

namespace App\Http\Controllers;

use App\Models\ListCatalog;
use Illuminate\Http\Request;

class PublicCatalogController extends Controller
{
    /**
     * Display public catalog page
     */
    public function show($catalogId)
    {
        // Get catalog with eager loading
        $catalog = ListCatalog::find($catalogId);

        if (!$catalog) {
            return view('public.catalog.not-found', [
                'message' => 'Catalog not found'
            ]);
        }

        // Get company details
        $company = $catalog->company;

        return view('public.catalog.index', [
            'catalog' => $catalog,
            'company' => $company,
            'items' => $catalog->items ?? [],
        ]);
    }

    /**
     * API endpoint to get catalog data
     */
    public function getItems($catalogId)
    {
        $catalog = ListCatalog::find($catalogId);

        if (!$catalog) {
            return response()->json([
                'success' => false,
                'message' => 'Catalog not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'catalog' => [
                'id' => $catalog->id,
                'name' => $catalog->name,
                'description' => $catalog->description,
                'items' => $catalog->items ?? [],
            ]
        ]);
    }

    /**
     * Generate WhatsApp order message
     */
    public function generateOrder(Request $request, $catalogId)
    {
        $catalog = ListCatalog::find($catalogId);

        if (!$catalog) {
            return response()->json([
                'success' => false,
                'message' => 'Catalog not found'
            ], 404);
        }

        try {
            $validated = $request->validate([
                'items' => 'required|array',
                'items.*.id' => 'required|string',
                'items.*.quantity' => 'required|integer|min:1',
                'customerName' => 'nullable|string|max:255',
                'customerPhone' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            // Build order message
            $orderMessage = "📦 *New Order from Catalog: " . $catalog->name . "*\n\n";

            if ($validated['customerName'] ?? null) {
                $orderMessage .= "👤 *Customer:* " . $validated['customerName'] . "\n";
            }

            if ($validated['customerPhone'] ?? null) {
                $orderMessage .= "📱 *Phone:* " . $validated['customerPhone'] . "\n";
            }

            $orderMessage .= "\n📋 *Items:*\n";
            $totalPrice = 0;

            foreach ($validated['items'] as $item) {
                $product = $this->findProductInCatalog($catalog->items, $item['id']);
                if ($product) {
                    $itemTotal = ($product['price'] ?? 0) * $item['quantity'];
                    $orderMessage .= "• {$product['title']} (x{$item['quantity']}) - ";
                    if (isset($product['price'])) {
                        $orderMessage .= "Price: {$product['price']} = " . $itemTotal . "\n";
                        $totalPrice += $itemTotal;
                    } else {
                        $orderMessage .= "\n";
                    }
                }
            }

            if ($totalPrice > 0) {
                $orderMessage .= "\n💰 *Total:* " . $totalPrice . "\n";
            }

            if ($validated['notes'] ?? null) {
                $orderMessage .= "\n📝 *Notes:* " . $validated['notes'] . "\n";
            }

            return response()->json([
                'success' => true,
                'message' => $orderMessage,
                'orderData' => $validated,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating order: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Find product in catalog items by ID
     */
    private function findProductInCatalog($items, $productId)
    {
        if (!is_array($items)) {
            return null;
        }

        foreach ($items as $item) {
            if (($item['id'] ?? null) === $productId) {
                return $item;
            }
        }

        return null;
    }
}
