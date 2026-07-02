# Structured Product Attributes — Design

**Approved by user 2026-07-02.**

## Goal
Extract the attribute data trapped in `products.product_details` HTML into real, indexed, filterable MySQL columns; expose them in the admin Create/Edit forms; accept them as filter params in the shop listing endpoint. Matches the live-site product card (Stock ID / Make / Model / Year / Engine / Transmission / Mileage / Fuel).

## Data reality (measured over all 453,375 products)
`product_details` is `<ul><li><strong>Key:</strong> value</li>…</ul>` HTML. Coverage: condition 346K, fuel 250K, mileage 237K, transmission 189K, steering 188K, seats 181K, drive type 180K, exterior color 172K + colour 71K, engine capacity 172K, model code 172K, registration year/month 172K, model 159K, year of manufacture 115K, first registration 80K.

## Decisions (user-selected)
- **Extended 12 column set**: `model`, `model_code`, `year`, `engine_cc`, `mileage_km`, `fuel`, `transmission`, `condition`, `color`, `steering`, `seats`, `drive_type` — all nullable, filterables indexed.
- **Stock ID generated**: `stock_code` = `'SM' . id` (e.g. SM23009), unique, populated for all rows; shown read-only in admin Edit.

## Components

### 1. Migration `add_attribute_columns_to_products_table`
Nullable columns as above + `stock_code` VARCHAR(20) unique. Indexes: `year`, `mileage_km`, `engine_cc`, `fuel`, `transmission`, `condition`, `steering`, `drive_type`, composite `(make_id, year)`. `seats`/`color`/`model`/`model_code` unindexed (low filter value; add later if needed).

### 2. `App\Support\ProductDetailsParser` (pure, unit-tested)
`parse(string $html): array` → returns the 12 keys (nullable values).
- Key/value regex: `<strong>([^<:]{1,40}):?</strong>:?\s*([^<]{0,120})`, keys lowercased.
- Junk values → NULL: `-`, ``, `Confirm with the Seller`, `N/A`, `Unspecified` (case-insensitive).
- `mileage_km`: digits from e.g. `3,000 km`; reject if no digits; if value contains `miles`, convert ×1.609 rounded.
- `engine_cc`: digits from e.g. `2,000cc`; accept `L` form (`2.0L` → 2000).
- `year`: priority `registration year / month` → `year of manufacture` → `first registration`; first 4-digit match; sanity 1950–2027 else NULL.
- `color`: `exterior color` else `colour`; Title Case.
- `fuel`/`transmission`/`condition`/`steering`/`drive_type`: Title Case (`diesel` → `Diesel`).
- `seats`: int 1–99 from `number of seats`.
- `model`: from `model`; `model_code` from `model code`.

### 3. Command `products:extract-attributes`
- Chunks products by id (`chunkById`, 1000/batch), selecting only `id, product_details`.
- Parses, batch-updates via per-row UPDATE inside transactions per chunk (only when at least one field non-null).
- Always sets `stock_code = 'SM'.id` for every row (separate single UPDATE statement, no parsing needed).
- Re-runnable (idempotent overwrite). Reports per-field fill counts at end.

### 4. Admin forms (Create.vue / Edit.vue)
Add the 12 fields: selects with common options + free-text fallback for fuel (Petrol/Diesel/Hybrid/Electric/LPG/CNG), transmission (Automatic/Manual/CVT/Semi-Automatic), condition (Used/New), steering (Right/Left/Center), drive_type (2WD/4WD/AWD/4wheel drive); number inputs for year/engine_cc/mileage_km/seats; text for model/model_code/color. Edit shows read-only stock_code. AdminController validation + `$fillable` updated.

### 5. Shop listing filters (`ShopController::listing`)
New optional params (both request branches): `year_from`, `year_to` (now real column — the legacy REGEXP path is replaced), `mileage_min`, `mileage_max` (replaces the mileage-marker LIKE), `engine_min`, `engine_max`, `fuel`, `transmission`, `condition`, `steering`, `drive_type` (comma-separated lists → `whereIn`), `seats`. Frontend filter UI is out of scope (user builds it).

## Out of scope
Filter UI components; re-import from mongodb-json; changing existing filters' request contract (legacy `year_from/year_to`, `mileage_min/max` param names keep working — same names, better implementation).

## Testing
- Unit: ProductDetailsParser (each normalization rule + junk handling).
- Feature: extraction command on seeded rows (sqlite); listing endpoint filter params.
- Manual: run extraction on full 453K, verify fill-rate ≈ scan coverage, EXPLAIN a filter query, admin form save round-trip.
