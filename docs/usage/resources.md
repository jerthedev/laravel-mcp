# Resources Documentation

MCP Resources provide data that AI clients can read and access from your Laravel application. This guide covers everything you need to know about creating and managing Resources.

## What are MCP Resources?

MCP Resources are data sources that AI clients can read from. They're similar to read-only API endpoints but specifically designed for AI interactions. Resources can:

- Expose database models
- Provide file system access
- Aggregate data from multiple sources
- Transform data for AI consumption
- Support real-time subscriptions
- Cache expensive queries

## Basic Resource Structure

All MCP Resources extend the `McpResource` abstract class:

```php
<?php

namespace App\Mcp\Resources;

use JTD\LaravelMCP\Abstracts\McpResource;

class MyResource extends McpResource
{
    protected string $name = 'my_resource';
    protected string $description = 'What this resource provides';
    protected string $uriTemplate = 'my_resource/{id?}';
    
    protected function customRead(array $params): mixed
    {
        // Resource reading logic
        return ['data' => 'example'];
    }
    
    protected function customList(array $params): array
    {
        // Resource listing logic
        return ['items' => []];
    }
}
```

## Creating Your First Resource

### Step 1: Generate the Resource

```bash
php artisan make:mcp-resource ArticleResource
```

### Step 2: Define the Resource

```php
<?php

namespace App\Mcp\Resources;

use App\Models\Article;
use JTD\LaravelMCP\Abstracts\McpResource;

class ArticleResource extends McpResource
{
    protected string $name = 'articles';
    protected string $description = 'Access blog articles and posts';
    protected string $uriTemplate = 'articles/{id?}';
    protected ?string $modelClass = Article::class;

    protected function customRead(array $params): mixed
    {
        if (isset($params['id'])) {
            $article = Article::with(['author', 'tags'])
                ->findOrFail($params['id']);
                
            return [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'content' => $article->content,
                'excerpt' => $article->excerpt,
                'published_at' => $article->published_at?->toISOString(),
                'author' => [
                    'id' => $article->author->id,
                    'name' => $article->author->name,
                ],
                'tags' => $article->tags->pluck('name')->toArray(),
                'word_count' => str_word_count(strip_tags($article->content)),
            ];
        }
        
        return $this->getLatestArticles();
    }
    
    protected function customList(array $params): array
    {
        $query = Article::with(['author', 'tags'])
            ->where('published_at', '<=', now());
            
        // Apply filters
        if (isset($params['author'])) {
            $query->whereHas('author', function ($q) use ($params) {
                $q->where('name', 'like', '%' . $params['author'] . '%');
            });
        }
        
        if (isset($params['tag'])) {
            $query->whereHas('tags', function ($q) use ($params) {
                $q->where('name', $params['tag']);
            });
        }
        
        if (isset($params['search'])) {
            $query->where(function ($q) use ($params) {
                $q->where('title', 'like', '%' . $params['search'] . '%')
                  ->orWhere('content', 'like', '%' . $params['search'] . '%');
            });
        }
        
        // Apply sorting
        $sortBy = $params['sort'] ?? 'published_at';
        $sortDir = $params['direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);
        
        // Apply pagination
        $perPage = min($params['per_page'] ?? 15, 100);
        $page = $params['page'] ?? 1;
        
        return $query->paginate($perPage, [
            'id', 'title', 'slug', 'excerpt', 'published_at'
        ], 'page', $page)->toArray();
    }
    
    private function getLatestArticles(): array
    {
        return Article::with(['author'])
            ->latest('published_at')
            ->take(10)
            ->get([
                'id', 'title', 'slug', 'excerpt', 'published_at'
            ])
            ->map(function ($article) {
                return [
                    'id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'excerpt' => $article->excerpt,
                    'published_at' => $article->published_at?->toISOString(),
                    'author' => $article->author->name,
                ];
            })
            ->toArray();
    }
}
```

## Model-Based Resources

For simple CRUD operations, you can rely on the built-in model integration:

```php
<?php

namespace App\Mcp\Resources;

use App\Models\User;
use JTD\LaravelMCP\Abstracts\McpResource;

class UserResource extends McpResource
{
    protected string $name = 'users';
    protected string $description = 'Access user profiles and information';
    protected string $uriTemplate = 'users/{id?}';
    protected ?string $modelClass = User::class;
    
    // The parent class will automatically handle:
    // - Reading individual users by ID
    // - Listing users with pagination
    // - Basic filtering and searching
    
    // Override if you need custom behavior
    protected function readFromModel(array $params): mixed
    {
        $user = User::with(['profile', 'roles'])
            ->findOrFail($params['id']);
            
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at->toISOString(),
            'profile' => $user->profile?->toArray(),
            'roles' => $user->roles->pluck('name')->toArray(),
        ];
    }
}
```

## URI Templates and Routing

Resources use URI templates to define their access patterns:

### Basic URI Templates

```php
// Single resource type
protected string $uriTemplate = 'articles';

// Resource with optional ID
protected string $uriTemplate = 'articles/{id?}';

// Nested resource
protected string $uriTemplate = 'users/{user_id}/posts/{post_id?}';

// Resource with complex path
protected string $uriTemplate = 'categories/{category}/articles/{id?}';
```

### Handling Complex URIs

```php
<?php

namespace App\Mcp\Resources;

use App\Models\Post;
use App\Models\User;
use JTD\LaravelMCP\Abstracts\McpResource;

class UserPostResource extends McpResource
{
    protected string $name = 'user_posts';
    protected string $description = 'Access posts by specific users';
    protected string $uriTemplate = 'users/{user_id}/posts/{post_id?}';
    
    protected function customRead(array $params): mixed
    {
        $user = User::findOrFail($params['user_id']);
        
        if (isset($params['post_id'])) {
            $post = $user->posts()->findOrFail($params['post_id']);
            
            return [
                'id' => $post->id,
                'title' => $post->title,
                'content' => $post->content,
                'author' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
                'created_at' => $post->created_at->toISOString(),
            ];
        }
        
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'posts' => $user->posts()->latest()->take(10)->get([
                'id', 'title', 'excerpt', 'created_at'
            ])->toArray(),
        ];
    }
    
    protected function customList(array $params): array
    {
        $user = User::findOrFail($params['user_id']);
        
        $query = $user->posts();
        
        // Apply filters specific to this user's posts
        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }
        
        $perPage = min($params['per_page'] ?? 15, 50);
        $page = $params['page'] ?? 1;
        
        return $query->paginate($perPage, [
            'id', 'title', 'excerpt', 'status', 'created_at'
        ], 'page', $page)->toArray();
    }
}
```

## Advanced Resource Examples

### File System Resource

```php
<?php

namespace App\Mcp\Resources;

use JTD\LaravelMCP\Abstracts\McpResource;
use Illuminate\Support\Facades\Storage;

class FileSystemResource extends McpResource
{
    protected string $name = 'files';
    protected string $description = 'Access files and directories';
    protected string $uriTemplate = 'files/{path?}';
    
    protected function customRead(array $params): mixed
    {
        $path = $params['path'] ?? '';
        $fullPath = 'public/' . $path;
        
        if (!Storage::exists($fullPath)) {
            throw new \Exception('Path not found: ' . $path);
        }
        
        if (Storage::directoryExists($fullPath)) {
            return $this->readDirectory($fullPath);
        } else {
            return $this->readFile($fullPath);
        }
    }
    
    protected function customList(array $params): array
    {
        $path = $params['path'] ?? '';
        $fullPath = 'public/' . $path;
        
        if (!Storage::directoryExists($fullPath)) {
            throw new \Exception('Directory not found: ' . $path);
        }
        
        $files = Storage::files($fullPath);
        $directories = Storage::directories($fullPath);
        
        $items = [];
        
        foreach ($directories as $dir) {
            $items[] = [
                'type' => 'directory',
                'path' => str_replace('public/', '', $dir),
                'name' => basename($dir),
                'modified' => Storage::lastModified($dir),
            ];
        }
        
        foreach ($files as $file) {
            $items[] = [
                'type' => 'file',
                'path' => str_replace('public/', '', $file),
                'name' => basename($file),
                'size' => Storage::size($file),
                'mime_type' => Storage::mimeType($file),
                'modified' => Storage::lastModified($file),
            ];
        }
        
        return [
            'path' => $path,
            'items' => $items,
            'total' => count($items),
        ];
    }
    
    private function readDirectory(string $path): array
    {
        $files = Storage::files($path);
        $directories = Storage::directories($path);
        
        return [
            'type' => 'directory',
            'path' => str_replace('public/', '', $path),
            'file_count' => count($files),
            'directory_count' => count($directories),
            'total_size' => array_sum(array_map(fn($file) => Storage::size($file), $files)),
            'items' => array_merge($directories, $files),
        ];
    }
    
    private function readFile(string $path): array
    {
        $content = Storage::get($path);
        $mimeType = Storage::mimeType($path);
        
        return [
            'type' => 'file',
            'path' => str_replace('public/', '', $path),
            'name' => basename($path),
            'size' => Storage::size($path),
            'mime_type' => $mimeType,
            'content' => $this->formatContent($content, $mimeType),
            'modified' => Storage::lastModified($path),
        ];
    }
    
    private function formatContent(string $content, string $mimeType): mixed
    {
        if (str_starts_with($mimeType, 'text/') || $mimeType === 'application/json') {
            return $content;
        }
        
        if (str_starts_with($mimeType, 'image/')) {
            return [
                'type' => 'base64',
                'data' => base64_encode($content),
                'mime_type' => $mimeType,
            ];
        }
        
        return [
            'type' => 'binary',
            'size' => strlen($content),
            'message' => 'Binary content not displayed',
        ];
    }
}
```

### API Aggregation Resource

```php
<?php

namespace App\Mcp\Resources;

use JTD\LaravelMCP\Abstracts\McpResource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class WeatherResource extends McpResource
{
    protected string $name = 'weather';
    protected string $description = 'Current weather and forecast data';
    protected string $uriTemplate = 'weather/{location?}';
    
    protected function customRead(array $params): mixed
    {
        $location = $params['location'] ?? 'New York';
        $type = $params['type'] ?? 'current';
        
        return match ($type) {
            'current' => $this->getCurrentWeather($location),
            'forecast' => $this->getWeatherForecast($location),
            'history' => $this->getWeatherHistory($location, $params),
            default => throw new \InvalidArgumentException('Invalid weather type: ' . $type),
        };
    }
    
    protected function customList(array $params): array
    {
        $locations = $params['locations'] ?? ['New York', 'London', 'Tokyo'];
        
        $weatherData = [];
        
        foreach ($locations as $location) {
            $weatherData[] = $this->getCurrentWeather($location);
        }
        
        return [
            'locations' => $weatherData,
            'total' => count($weatherData),
            'updated_at' => now()->toISOString(),
        ];
    }
    
    private function getCurrentWeather(string $location): array
    {
        $cacheKey = "weather.current.{$location}";
        
        return Cache::remember($cacheKey, 300, function () use ($location) {
            $response = Http::get('https://api.openweathermap.org/data/2.5/weather', [
                'q' => $location,
                'appid' => config('services.openweather.key'),
                'units' => 'metric',
            ]);
            
            if (!$response->successful()) {
                throw new \Exception('Weather data unavailable for: ' . $location);
            }
            
            $data = $response->json();
            
            return [
                'location' => $data['name'],
                'country' => $data['sys']['country'],
                'coordinates' => [
                    'lat' => $data['coord']['lat'],
                    'lon' => $data['coord']['lon'],
                ],
                'current' => [
                    'temperature' => $data['main']['temp'],
                    'feels_like' => $data['main']['feels_like'],
                    'humidity' => $data['main']['humidity'],
                    'pressure' => $data['main']['pressure'],
                    'description' => $data['weather'][0]['description'],
                    'icon' => $data['weather'][0]['icon'],
                ],
                'wind' => [
                    'speed' => $data['wind']['speed'] ?? null,
                    'direction' => $data['wind']['deg'] ?? null,
                ],
                'visibility' => $data['visibility'] ?? null,
                'updated_at' => now()->toISOString(),
            ];
        });
    }
    
    private function getWeatherForecast(string $location): array
    {
        $cacheKey = "weather.forecast.{$location}";
        
        return Cache::remember($cacheKey, 1800, function () use ($location) {
            $response = Http::get('https://api.openweathermap.org/data/2.5/forecast', [
                'q' => $location,
                'appid' => config('services.openweather.key'),
                'units' => 'metric',
            ]);
            
            if (!$response->successful()) {
                throw new \Exception('Forecast data unavailable for: ' . $location);
            }
            
            $data = $response->json();
            
            return [
                'location' => $data['city']['name'],
                'country' => $data['city']['country'],
                'forecast' => collect($data['list'])->take(8)->map(function ($item) {
                    return [
                        'datetime' => $item['dt_txt'],
                        'temperature' => $item['main']['temp'],
                        'description' => $item['weather'][0]['description'],
                        'humidity' => $item['main']['humidity'],
                        'pressure' => $item['main']['pressure'],
                    ];
                })->toArray(),
            ];
        });
    }
    
    private function getWeatherHistory(string $location, array $params): array
    {
        $days = min($params['days'] ?? 7, 30);
        
        // This would typically call a historical weather API
        // For this example, we'll return a placeholder
        
        return [
            'location' => $location,
            'period' => $days . ' days',
            'data' => 'Historical weather data would be here',
            'message' => 'Historical weather API integration needed',
        ];
    }
}
```

## Resource Subscriptions

Resources can support real-time subscriptions for live data updates:

```php
<?php

namespace App\Mcp\Resources;

use App\Events\OrderUpdated;
use App\Models\Order;
use JTD\LaravelMCP\Abstracts\McpResource;

class OrderResource extends McpResource
{
    protected string $name = 'orders';
    protected string $description = 'Real-time order tracking and updates';
    protected string $uriTemplate = 'orders/{id?}';
    
    protected function supportsSubscription(): bool
    {
        return true;
    }
    
    protected function handleSubscribe(array $params): mixed
    {
        $orderId = $params['order_id'] ?? null;
        $userId = auth()->id();
        
        if ($orderId) {
            // Subscribe to specific order updates
            $order = Order::findOrFail($orderId);
            
            // Verify user can access this order
            if ($order->user_id !== $userId) {
                throw new \UnauthorizedHttpException('', 'Cannot subscribe to this order');
            }
            
            return [
                'subscribed' => true,
                'type' => 'order',
                'order_id' => $orderId,
                'events' => ['order.updated', 'order.status_changed'],
                'message' => 'Subscribed to order #' . $orderId,
            ];
        } else {
            // Subscribe to user's order updates
            return [
                'subscribed' => true,
                'type' => 'user_orders',
                'user_id' => $userId,
                'events' => ['order.created', 'order.updated'],
                'message' => 'Subscribed to your order updates',
            ];
        }
    }
    
    protected function customRead(array $params): mixed
    {
        if (isset($params['id'])) {
            $order = Order::with(['items', 'user', 'payments'])
                ->findOrFail($params['id']);
                
            // Verify access
            if ($order->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
                throw new \UnauthorizedHttpException('', 'Cannot access this order');
            }
            
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total' => $order->total,
                'currency' => $order->currency,
                'items' => $order->items->map(function ($item) {
                    return [
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'total' => $item->total,
                    ];
                })->toArray(),
                'created_at' => $order->created_at->toISOString(),
                'updated_at' => $order->updated_at->toISOString(),
                'estimated_delivery' => $order->estimated_delivery?->toISOString(),
            ];
        }
        
        return $this->getUserOrders();
    }
    
    private function getUserOrders(): array
    {
        $orders = Order::where('user_id', auth()->id())
            ->latest()
            ->take(10)
            ->get([
                'id', 'order_number', 'status', 'total', 'currency', 'created_at'
            ]);
            
        return [
            'orders' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total' => $order->total,
                    'currency' => $order->currency,
                    'created_at' => $order->created_at->toISOString(),
                ];
            })->toArray(),
            'total' => $orders->count(),
        ];
    }
}
```

## Authorization and Security

### Basic Authorization

```php
class PrivateResource extends McpResource
{
    protected bool $requiresAuth = true;
    
    protected function authorize(string $action, array $params): bool
    {
        if (!auth()->check()) {
            return false;
        }
        
        // Additional authorization logic
        return match ($action) {
            'read' => $this->authorizeRead($params),
            'list' => $this->authorizeList($params),
            'subscribe' => $this->authorizeSubscribe($params),
            default => false,
        };
    }
    
    private function authorizeRead(array $params): bool
    {
        // Check if user can read specific resource
        if (isset($params['id'])) {
            $resource = MyModel::find($params['id']);
            return $resource && $resource->user_id === auth()->id();
        }
        
        return true;
    }
}
```

### Role-Based Authorization

```php
protected function authorize(string $action, array $params): bool
{
    if (!parent::authorize($action, $params)) {
        return false;
    }
    
    return match ($action) {
        'read' => auth()->user()->can('read-resource'),
        'list' => auth()->user()->can('list-resources'),
        'subscribe' => auth()->user()->can('subscribe-resources'),
        default => false,
    };
}
```

## Caching Strategies

### Basic Caching

```php
protected function customRead(array $params): mixed
{
    $cacheKey = 'resource.' . $this->getName() . '.' . md5(serialize($params));
    
    return Cache::remember($cacheKey, 300, function () use ($params) {
        return $this->expensiveDataRetrieval($params);
    });
}
```

### Cache Invalidation

```php
<?php

namespace App\Mcp\Resources;

use App\Models\Product;
use JTD\LaravelMCP\Abstracts\McpResource;
use Illuminate\Support\Facades\Cache;

class ProductResource extends McpResource
{
    protected function boot(): void
    {
        // Listen for model events to invalidate cache
        Product::updated(function ($product) {
            $this->invalidateCache($product->id);
        });
        
        Product::deleted(function ($product) {
            $this->invalidateCache($product->id);
        });
    }
    
    protected function customRead(array $params): mixed
    {
        if (isset($params['id'])) {
            return $this->getCachedProduct($params['id']);
        }
        
        return $this->getCachedProductList($params);
    }
    
    private function getCachedProduct(int $id): array
    {
        return Cache::remember(
            "product.{$id}",
            3600,
            fn() => Product::with(['category', 'reviews'])->findOrFail($id)->toArray()
        );
    }
    
    private function getCachedProductList(array $params): array
    {
        $cacheKey = 'products.list.' . md5(serialize($params));
        
        return Cache::remember($cacheKey, 600, function () use ($params) {
            $query = Product::with(['category']);
            
            if (isset($params['category_id'])) {
                $query->where('category_id', $params['category_id']);
            }
            
            return $query->paginate($params['per_page'] ?? 15)->toArray();
        });
    }
    
    private function invalidateCache(int $productId): void
    {
        Cache::forget("product.{$productId}");
        Cache::tags(['products.list'])->flush();
    }
}
```

## Testing Resources

### Unit Testing

```php
<?php

namespace Tests\Feature\Mcp\Resources;

use App\Models\Article;
use App\Models\User;
use App\Mcp\Resources\ArticleResource;
use Tests\TestCase;

class ArticleResourceTest extends TestCase
{
    public function test_read_single_article()
    {
        $user = User::factory()->create();
        $article = Article::factory()->create(['user_id' => $user->id]);
        
        $resource = new ArticleResource();
        $result = $resource->read(['id' => $article->id]);
        
        $this->assertEquals($article->id, $result['id']);
        $this->assertEquals($article->title, $result['title']);
        $this->assertArrayHasKey('author', $result);
    }
    
    public function test_list_articles_with_pagination()
    {
        Article::factory()->count(25)->create();
        
        $resource = new ArticleResource();
        $result = $resource->list(['per_page' => 10]);
        
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(10, $result['data']);
        $this->assertArrayHasKey('pagination', $result);
    }
    
    public function test_authorization_prevents_unauthorized_access()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException::class);
        
        $resource = new ArticleResource();
        $resource->requiresAuth = true;
        
        // No authenticated user
        $resource->read(['id' => 1]);
    }
}
```

## Performance Optimization

### Database Query Optimization

```php
protected function customList(array $params): array
{
    $query = Article::query()
        ->select(['id', 'title', 'slug', 'excerpt', 'published_at', 'user_id'])
        ->with(['author:id,name'])
        ->withCount(['comments', 'likes']);
        
    // Use index-friendly filters
    if (isset($params['published_after'])) {
        $query->where('published_at', '>=', $params['published_after']);
    }
    
    // Limit expensive operations
    if (isset($params['search'])) {
        $query->whereFullText(['title', 'content'], $params['search']);
    }
    
    return $query->paginate(min($params['per_page'] ?? 15, 100));
}
```

### Eager Loading

```php
protected function customRead(array $params): mixed
{
    $article = Article::query()
        ->with([
            'author:id,name,email',
            'tags:id,name',
            'comments' => function ($query) {
                $query->latest()->limit(5)->with('author:id,name');
            },
        ])
        ->findOrFail($params['id']);
        
    return $this->transformArticle($article);
}
```

## Best Practices

### 1. Consistent Data Structure

```php
protected function formatArticle(Article $article): array
{
    return [
        'id' => $article->id,
        'title' => $article->title,
        'slug' => $article->slug,
        'content' => $article->content,
        'excerpt' => $article->excerpt,
        'published_at' => $article->published_at?->toISOString(),
        'meta' => [
            'word_count' => str_word_count(strip_tags($article->content)),
            'read_time' => ceil(str_word_count(strip_tags($article->content)) / 200),
        ],
    ];
}
```

### 2. Proper Error Handling

```php
protected function customRead(array $params): mixed
{
    try {
        if (isset($params['id'])) {
            $item = MyModel::findOrFail($params['id']);
            return $this->transformItem($item);
        }
        
        return $this->getDefaultItems();
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        throw new \Exception('Resource not found: ' . $params['id']);
    } catch (\Exception $e) {
        logger()->error('Resource read error', [
            'resource' => $this->getName(),
            'params' => $params,
            'error' => $e->getMessage(),
        ]);
        
        throw new \Exception('Unable to read resource');
    }
}
```

### 3. Input Validation

```php
protected function customList(array $params): array
{
    // Validate pagination parameters
    $perPage = max(1, min($params['per_page'] ?? 15, 100));
    $page = max(1, $params['page'] ?? 1);
    
    // Validate sort parameters
    $allowedSorts = ['id', 'title', 'created_at', 'updated_at'];
    $sortBy = in_array($params['sort'] ?? 'id', $allowedSorts) ? $params['sort'] : 'id';
    $sortDir = in_array($params['direction'] ?? 'asc', ['asc', 'desc']) ? $params['direction'] : 'asc';
    
    // Continue with query...
}
```

## Troubleshooting

### Common Issues

1. **Resource not found**: Check directory structure and namespace
2. **Authorization failures**: Verify auth logic and user permissions  
3. **Performance issues**: Add caching and optimize database queries
4. **Memory errors**: Implement pagination and limit result sets

### Debugging

```php
protected function customRead(array $params): mixed
{
    if (config('app.debug')) {
        logger()->debug('Resource access', [
            'resource' => $this->getName(),
            'params' => $params,
            'user_id' => auth()->id(),
            'timestamp' => now()->toISOString(),
        ]);
    }
    
    // Resource logic...
}
```

---

**Next Steps:**
- Learn about [Prompts](prompts.md) for AI interactions
- Explore [Tools](tools.md) for executable functions
- Check the [API Reference](../api-reference.md) for detailed method documentation