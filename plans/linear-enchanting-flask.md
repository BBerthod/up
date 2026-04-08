# Plan: Optimize Topelio Cold Page Render Performance

## Context

Topelio's server is at 1191% CPU. When pages are not in ResponseCache (cold), they take 20-50s to render instead of <5s. The cache warmer hits all URLs, and each cold render is too slow, saturating PHP-FPM workers and causing a cascading failure. The fix must make cold page renders fast (<3s), not just work around caching.

**Root causes identified:**
1. Critical database indexes were never applied (migration `.skip` file)
2. TopList show page loads 90 days of price history for ALL products in one eager load
3. 50+ sequential Redis roundtrips in `getAggregatedPriceDataForProducts` (individual get/put per product)
4. No cache stampede protection — concurrent cold requests all rebuild the same cache simultaneously

## Files to Modify

All changes are in `~/Dev/Topela/`.

### Fix 1: Apply skipped performance indexes

**File:** `database/migrations/2025_11_15_100000_add_critical_performance_indexes.php.skip`
- Rename to `.php` (remove `.skip` extension)
- These indexes cover the critical query patterns:
  - `idx_products_locale_active_rank` — product listings
  - `idx_products_locale_active_category` — category filtering on 600K+ rows
  - `idx_products_price_filtering` — MIN/MAX price stats
  - `idx_price_history_product_date` — price trend lookups
  - `idx_products_rating` — rating sort/filter

**Impact:** Reduces query time from 2-5s to <100ms per query. Single biggest win.

### Fix 2: Reduce TopList priceHistory eager loading

**File:** `app/Http/Controllers/TopListController.php:183-191`

Current: loads 90 days of `priceHistory` for ALL products in one eager load.
```php
'products.priceHistory' => fn ($q) => $q->where('checked_at', '>=', now()->subDays(90))
    ->orderBy('checked_at', 'desc')
    ->limit(90),
```

Change: reduce to 30 days (sufficient for trend display), and note that `limit()` in eager loading doesn't work per-parent in Laravel — it limits the total result set. Remove the misleading `limit(90)`.

```php
'products.priceHistory' => fn ($q) => $q->where('checked_at', '>=', now()->subDays(30))
    ->orderBy('checked_at', 'desc'),
```

### Fix 3: Batch Redis operations in PriceMonitoringService

**File:** `app/Services/Pricing/PriceMonitoringService.php` — `getAggregatedPriceDataForProducts()`

Current: 50 individual `Cache::get()` + 50 individual `Cache::put()` = 100 Redis roundtrips.

Change: Use `Cache::many()` for batch get and `Cache::putMany()` for batch write. Reduces 100 roundtrips to 2.

```php
// Step 1: Batch get all cache keys
$cacheKeys = $products->mapWithKeys(fn ($p) => ["price_aggregated:{$locale}:{$p->id}" => null])->all();
$cached = Cache::many(array_keys($cacheKeys));

// Step 2: Identify misses
$uncachedProducts = $products->filter(fn ($p) => $cached["price_aggregated:{$locale}:{$p->id}"] === null);

// ... compute stats for uncached ...

// Step 3: Batch put
Cache::putMany($newEntries, self::CACHE_TTL);
```

### Fix 4: Cache stampede protection for expensive operations

**File:** `app/Http/Controllers/TopListController.php:178`

Current: `Cache::remember()` — multiple concurrent requests all trigger the same rebuild.

Change: Use atomic lock pattern for the main TopList query:
```php
$topList = Cache::remember($cacheKey, 21600, function () use (...) {
    return Cache::lock("lock:{$cacheKey}", 30)->block(15, function () use (...) {
        // Double-check: another process may have filled the cache while we waited
        if ($cached = Cache::get($cacheKey)) return $cached;
        return TopList::...->firstOrFail();
    });
});
```

Apply same pattern to `getAggregatedPriceDataForProducts` and `getTrendAnalysis`.

## Execution Order

1. Fix 1 (indexes) — rename migration, deploy, run `php artisan migrate`
2. Fix 2 (priceHistory) — code change in controller
3. Fix 3 (batch Redis) — code change in service
4. Fix 4 (stampede protection) — code change in controller + service

Fixes 2-4 can be in one commit. Fix 1 is a separate deploy.

## Verification

1. After Fix 1: check `SHOW INDEX FROM products` confirms indexes exist
2. After Fix 5: check Dokploy monitoring shows CPU <400%
3. After Fixes 2-4: test cold render of `/top/{slug}` via origin bypass:
   ```bash
   curl -sk --resolve "fr.topelio.com:443:162.55.199.249" \
     -w "TTFB: %{time_starttransfer}s\n" \
     "https://fr.topelio.com/top/raclette-grill"
   ```
   Target: TTFB <3s on cold cache
4. Monitor Dokploy CPU after cache warmer runs — should stay <600%
