# HTTP 502 Bad Gateway Investigation Summary - fr.topelio.com/top

## Executive Summary

The HTTP 502 Bad Gateway errors on `https://fr.topelio.com/top` are caused by uncaught PHP TypeErrors occurring when the `SchemaOrgService::getProductImages()` method attempts to call `array_slice()` on the `images` field of a Product model. The images field is being stored and retrieved as a double-encoded JSON string literal instead of a proper array, causing the type mismatch.

**Error Evidence:**
```
array_slice(): Argument #1 ($array) must be of type array, string given
```

**Example of corrupted data:**
```
array_slice('[\"https:\\/\\/via...', 0, 5)  // String passed instead of array
```

---

## Root Cause Analysis

### The Core Problem
The images field in the Product model is being stored as a JSON-encoded string literal rather than a proper JSON array. When retrieved from the database, Laravel's 'array' cast attempts to decode this string, but it's already been double-encoded, resulting in a string type instead of an array.

### Architectural Inconsistency Discovered

The Product model (`/Users/billyberthod/Dev/up/app/Models/Product.php`) reveals a critical inconsistency in how JSON fields are handled:

**Fields WITH custom mutators (lines 197-237):**
- `metadata`
- `features`
- `specifications`

All three use a custom setter method that explicitly calls `sanitizeAndEncodeJson()`:
```php
public function setMetadataAttribute(mixed $value): void
{
    $this->attributes['metadata'] = $this->sanitizeAndEncodeJson($value);
}
```

**Fields WITHOUT custom mutators:**
- `images` (line 134: `'images' => 'array'` cast)

Despite being defined in the `$casts` array as 'array' type, the `images` field has no custom mutator. It relies solely on Laravel's automatic array casting mechanism.

### The Inconsistency Impact

The `sanitizeAndEncodeJson()` method (lines 221-237):
```php
protected function sanitizeAndEncodeJson(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = $decoded !== null ? $decoded : $value;
    }
    if (is_array($value)) {
        $value = $this->sanitizeArrayUtf8($value);
    }
    return is_array($value) ? 
        json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : 
        $value;
}
```

This method:
1. Detects if the value is already a JSON string
2. Decodes it if necessary
3. Sanitizes UTF-8 characters
4. Re-encodes the array back to JSON with proper flags

**The images field never goes through this sanitization process.**

---

## Data Flow Analysis

### Correct Data Transformation (Source → Model)

1. **AmazonResponseParser** (`/Users/billyberthod/Dev/up/app/Services/Amazon/AmazonResponseParser.php`)
   - Returns images as a proper PHP array of URLs
   - Example: `['https://example.com/img1.jpg', 'https://example.com/img2.jpg']`

2. **AmazonService** (`/Users/billyberthod/Dev/up/app/Services/AmazonService.php`)
   - Returns properly formatted product data with images as arrays
   - No JSON encoding applied at this layer

3. **ProcessKeywordJob** → **FetchProductsJob**
   - Receives array data in `$productData` property
   - Line 24: `public array $productData`
   - Uses `SerializesModels` trait for Redis queue serialization
   - Passes array to `Product::update()` or `Product::updateOrCreate()`

4. **Product Model Storage**
   - Line 109 in FetchProductsJob: `'images' => $this->productData['images'] ?? []`
   - Should store as proper JSON array in database

### The Problem Occurs Here

**Hypothesis 1: Job Serialization Issue**
The `SerializesModels` trait with Redis serialization may be double-encoding the images array. When the job is serialized and deserialized through Redis, the array might be getting JSON-encoded twice.

**Hypothesis 2: Missing Mutator Issue**
Unlike metadata/features/specifications, the images field has no custom `setImagesAttribute()` mutator. The automatic 'array' cast may not handle all edge cases that occur with Redis serialization and model state transitions.

**Hypothesis 3: Retrieval-Time Corruption**
The images field might be stored correctly in the database but corrupted during retrieval/deserialization.

---

## Code Locations

### Critical Files

1. **Product Model** 
   - Path: `/Users/billyberthod/Dev/up/app/Models/Product.php`
   - Lines 134: `'images' => 'array'` cast definition
   - Lines 197-237: Custom mutators for metadata/features/specifications
   - Missing: `setImagesAttribute()` mutator

2. **Error Location**
   - Path: `/Users/billyberthod/Dev/up/app/Services/Seo/SchemaOrgService.php`
   - Line 767: `array_slice($product->images, 0, 5)`
   - Line 750: `if (is_array($product->images))` check that should prevent error but is being bypassed

3. **Data Entry Points**
   - Path: `/Users/billyberthod/Dev/up/app/Jobs/FetchProductsJob.php`
   - Line 24: `public array $productData` property
   - Line 135: `$product->update($productAttributes)` call
   
   - Path: `/Users/billyberthod/Dev/up/app/Jobs/GenerateTopListJob.php`
   - Passes product data to model update operations

4. **Optimized Images Trait**
   - Path: `/Users/billyberthod/Dev/up/app/Models/Traits/HasOptimizedImages.php`
   - Contains only getter methods, no setters
   - Focuses on `optimized_images`, not raw `images` field

### Related Services

- **AmazonResponseParser**: `/Users/billyberthod/Dev/up/app/Services/Amazon/AmazonResponseParser.php`
- **AmazonService**: `/Users/billyberthod/Dev/up/app/Services/AmazonService.php`

---

## Error Log Evidence

**Timestamp:** 2026-02-01 11:18:14  
**File:** `/Users/billyberthod/Dev/up/storage/logs/laravel.log` (103MB)

The error shows:
- Function: `array_slice('[\"https:\\/\\/via...', 0, 5)`
- Expected: An array of image URLs
- Received: A JSON string literal

---

## Project Context

### Multi-Locale Setup
- Supported locales: FR, US, UK, DE, ES, IT, CA, MX, BR, AU, BE, NL, PL, SE
- Development mode: `LOCALE_MODE=path` → `/fr/products/...`
- Production mode: `LOCALE_MODE=subdomain` → `fr.example.com/products/...`
- Critical rule: Use `LocaleUrlHelper::detectFromRequest()` instead of `$request->segment(1)`

### Docker Architecture
Services defined in `/Users/billyberthod/Dev/up/docker-compose.yml`:
- `app`: Main Laravel application (port 8000)
- `worker`: Queue processor (redis queue with monitors, notifications, default queues)
- `scheduler`: Task scheduler (`schedule:work`)
- `reverb`: WebSocket server (port 8080)
- `postgres`: Database (service_healthy check)
- `redis`: Cache/queue backend (service_healthy check)

### Security Requirements
- SQL injection prevention: Use `QueryHelper::likePattern()` for LIKE queries
- Input validation required at controller level
- CSRF protection on all forms
- Rate limiting on public endpoints
- Session encryption in production

---

## Investigation Status

### Confirmed
✓ Images field is being passed as an array from AmazonResponseParser  
✓ Product model receives array in update operations  
✓ Product model has 'array' cast defined  
✓ SchemaOrgService receives string instead of array  
✓ Error occurs at array_slice() call with type mismatch  
✓ Architectural inconsistency exists between images and other JSON fields  

### Not Yet Confirmed
? Exact location where array becomes double-encoded string  
? Whether double-encoding occurs at save time or retrieval time  
? Role of SerializesModels trait in JSON encoding  
? Actual database representation of images field  
? Whether adding a custom setImagesAttribute() mutator would resolve issue  

---

## Next Steps Required (In Priority Order)

### Phase 1: Diagnosis (CRITICAL)
1. **Database Query Inspection**
   - Execute raw database query to inspect actual stored values
   - Determine if images field contains:
     - Proper JSON array: `["url1", "url2", ...]`
     - Double-encoded string: `"[\"url1\", \"url2\", ...]"`
   - Sample query: `SELECT id, images FROM products WHERE images IS NOT NULL LIMIT 5;`

2. **Job Serialization Testing**
   - Create test job with array data to trace Redis serialization behavior
   - Verify if SerializesModels + Redis double-encodes data
   - Check if unserializing the job corrupts the data

3. **Model Mutation Trace**
   - Add logging to Product model to trace images value through:
     - Save operation
     - Database storage
     - Retrieval operation
     - Casting mechanism

### Phase 2: Fix Implementation
1. **Add Custom Mutator**
   - Implement `setImagesAttribute()` method in Product model
   - Use same pattern as metadata/features/specifications
   - Ensure consistent JSON encoding with proper flags

2. **Data Migration**
   - Create migration to fix all corrupted product records
   - Decode double-encoded strings and restore proper arrays
   - Validate all products have correct images format

3. **Validation Layer**
   - Add model validation to ensure images is always array type
   - Add type casting in getImagesAttribute() getter
   - Implement fallback mechanism for corrupted data

### Phase 3: Prevention
1. **Enhanced Testing**
   - Add tests for job serialization with array data
   - Add tests for product model JSON field handling
   - Add tests for schema generation with products containing images

2. **Documentation**
   - Document JSON field handling pattern
   - Create guide for adding new JSON fields to products
   - Document multi-locale constraints

---

## Key Architectural Decisions

### JSON Field Handling Pattern
The codebase uses a consistent pattern for JSON fields:
1. Define in `$casts` array as 'array' type
2. Create custom setter with `sanitizeAndEncodeJson()`
3. Allows UTF-8 sanitization and consistent JSON encoding
4. Enables automatic decoding on retrieval

**Current Exception:** Images field does not follow this pattern.

### Service Layer Pattern
- **AmazonResponseParser**: Handles API response parsing → returns arrays
- **AmazonService**: Wraps parser with caching → returns arrays
- **Jobs**: Handle data persistence → call model methods
- **Models**: Handle data storage/retrieval → manage JSON encoding

### Queue Architecture
- Uses Redis for job queue storage
- Jobs use `SerializesModels` trait for automatic model serialization
- Worker processes: monitors, notifications, default (in priority order)

---

## Security Considerations

Per security rules in `/Users/billyberthod/Dev/Claude/rules/laravel/security.md`:
- Never commit .env files or credentials
- Always validate user input at controller level
- Use QueryHelper for SQL injection prevention
- Rate limit public endpoints
- Require METRICS_ACCESS_TOKEN for metrics endpoint

The images data flow originates from Amazon PA-API, which is trusted internal source, but the type error could be exploited if attacker can inject malformed products.

---

## Locale & Multi-Marketplace Rules

Per `/Users/billyberthod/Dev/Claude/rules/laravel/multi-locale.md`:
- The issue affects `fr.topelio.com/top` specifically
- Must use `LocaleUrlHelper::detectFromRequest()` for locale detection
- Database queries must be scoped by locale: `Product::locale('fr')->...`
- Images should be locale-specific in product data

This is critical: verify if the corruption is locale-specific or affects all locales.

---

## Implementation Confidence Assessment

**Severity:** HIGH - Causes 502 errors and service unavailability  
**Impact Scope:** All product pages using SchemaOrgService  
**Data Corruption:** Yes - existing product records affected  
**Fix Complexity:** MEDIUM - Requires code fix + data migration  

**Root Cause Confidence:** MEDIUM-HIGH
- Architectural inconsistency identified
- Type error confirmed
- Data flow verified at most points
- Exact corruption point still requires testing

---

## Session Summary

This investigation identified a critical architectural inconsistency in the Product model where the `images` field lacks a custom JSON encoder/sanitizer that other similar fields (metadata, features, specifications) possess. While the images field is passed correctly through the data flow from AmazonResponseParser through Jobs to the Product model, somewhere in the save/retrieval cycle, the array is being converted to a double-encoded JSON string.

The next session should focus on database inspection to confirm the double-encoding, followed by implementation of a custom `setImagesAttribute()` mutator consistent with the existing pattern used by other JSON fields, and a data migration to repair corrupted records.
