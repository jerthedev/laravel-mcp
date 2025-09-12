<?php

namespace App\Mcp\Tools;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use JTD\LaravelMCP\Abstracts\McpTool;

/**
 * E-commerce product search tool
 *
 * Provides advanced product search capabilities with filters,
 * sorting, and pagination for e-commerce applications.
 */
class ProductSearchTool extends McpTool
{
    public function getName(): string
    {
        return 'product_search';
    }

    public function getDescription(): string
    {
        return 'Advanced product search with filters, sorting, and pagination for e-commerce applications.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Search query for product names and descriptions',
                ],
                'category' => [
                    'type' => 'string',
                    'description' => 'Filter by category slug',
                ],
                'min_price' => [
                    'type' => 'number',
                    'description' => 'Minimum price filter',
                ],
                'max_price' => [
                    'type' => 'number',
                    'description' => 'Maximum price filter',
                ],
                'in_stock' => [
                    'type' => 'boolean',
                    'description' => 'Filter by stock availability',
                ],
                'sort_by' => [
                    'type' => 'string',
                    'enum' => ['price_asc', 'price_desc', 'name', 'newest', 'popularity'],
                    'description' => 'Sort order for results',
                ],
                'page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Page number for pagination',
                ],
                'per_page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100,
                    'description' => 'Results per page (max 100)',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments): array
    {
        try {
            $query = Product::query()->with(['category', 'images']);

            // Apply search filters
            $this->applySearchFilters($query, $arguments);

            // Apply sorting
            $this->applySorting($query, $arguments['sort_by'] ?? 'popularity');

            // Paginate results
            $page = max(1, $arguments['page'] ?? 1);
            $perPage = min(100, max(1, $arguments['per_page'] ?? 20));

            $products = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $this->formatResults($products, $arguments),
                    ],
                ],
                'results' => [
                    'total' => $products->total(),
                    'per_page' => $products->perPage(),
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'products' => $products->items()->map(function ($product) {
                        return [
                            'id' => $product->id,
                            'name' => $product->name,
                            'slug' => $product->slug,
                            'price' => $product->price,
                            'sale_price' => $product->sale_price,
                            'category' => $product->category?->name,
                            'in_stock' => $product->stock_quantity > 0,
                            'stock_quantity' => $product->stock_quantity,
                            'rating' => $product->average_rating,
                            'image_url' => $product->images->first()?->url,
                        ];
                    }),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Search error: {$e->getMessage()}",
                    ],
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    private function applySearchFilters(Builder $query, array $arguments): void
    {
        // Text search
        if (! empty($arguments['query'])) {
            $searchTerm = $arguments['query'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('sku', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Category filter
        if (! empty($arguments['category'])) {
            $query->whereHas('category', function ($q) use ($arguments) {
                $q->where('slug', $arguments['category']);
            });
        }

        // Price filters
        if (isset($arguments['min_price'])) {
            $query->where('price', '>=', $arguments['min_price']);
        }

        if (isset($arguments['max_price'])) {
            $query->where('price', '<=', $arguments['max_price']);
        }

        // Stock filter
        if (isset($arguments['in_stock'])) {
            if ($arguments['in_stock']) {
                $query->where('stock_quantity', '>', 0);
            } else {
                $query->where('stock_quantity', '<=', 0);
            }
        }

        // Only active products
        $query->where('is_active', true);
    }

    private function applySorting(Builder $query, string $sortBy): void
    {
        match ($sortBy) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'name' => $query->orderBy('name', 'asc'),
            'newest' => $query->orderBy('created_at', 'desc'),
            'popularity' => $query->orderBy('view_count', 'desc')
                ->orderBy('average_rating', 'desc'),
            default => $query->orderBy('created_at', 'desc')
        };
    }

    private function formatResults($products, array $arguments): string
    {
        $total = $products->total();
        $searchQuery = $arguments['query'] ?? '';

        if ($total === 0) {
            return $searchQuery
                ? "No products found for '{$searchQuery}'"
                : 'No products found matching your criteria';
        }

        $text = "Found {$total} product(s)";

        if ($searchQuery) {
            $text .= " for '{$searchQuery}'";
        }

        $text .= " (Page {$products->currentPage()} of {$products->lastPage()}):\n\n";

        foreach ($products->items() as $product) {
            $price = $product->sale_price ?? $product->price;
            $originalPrice = $product->sale_price ? " (was \${$product->price})" : '';
            $stock = $product->stock_quantity > 0 ? "In Stock ({$product->stock_quantity})" : 'Out of Stock';

            $text .= "• {$product->name}\n";
            $text .= "  Price: \${$price}{$originalPrice}\n";
            $text .= "  Category: {$product->category?->name}\n";
            $text .= "  Stock: {$stock}\n";
            if ($product->average_rating) {
                $text .= "  Rating: {$product->average_rating}/5\n";
            }
            $text .= "\n";
        }

        return $text;
    }
}
