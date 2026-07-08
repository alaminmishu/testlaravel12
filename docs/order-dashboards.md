# Order Dashboards

How order data gets from MongoDB into the Filament admin dashboard, what each
widget shows, and what's next.

## Data pipeline

**Source**: a MongoDB `orders` collection (database name is env-configurable —
see `config/mongo.php` / `MONGO_DATABASE` — since snapshots get re-provisioned
under different database names over time). Each document is a full order:
customer, payment, receiver address (area/zone/division), and a `products`
array of line items (product/category/seller/warehouse/variant/discount).

**Sync**: `php artisan orders:sync-mongo` (`app/Console/Commands/SyncOrdersFromMongoCommand.php`)

1. Connects via the `mongodb/mongodb` driver using `config('mongo.uri')`.
2. Resolves a starting point:
   - `--all` — full resync, no filter.
   - `--since=<date>` — explicit cutoff.
   - default — reads the last watermark from `sync_states` (key
     `mongo_orders_last_synced_at`).
3. Queries the collection sorted by `updatedAt` ascending, filtering
   `updatedAt >= <cutoff>`, in batches (`--batch`, default 500).
4. Each document is mapped by `App\Services\Mongo\OrderDocumentMapper` into:
   - one row of `orders` attributes (`uid`, `status`, `payment_method`,
     `total_amount`, `area_name`/`zone_name`/`division_name`, `promo_code`, …
     plus the full original document in `raw`).
   - zero or more `order_items` rows (one per product in the order).
5. Per batch, inside a DB transaction: `Order::updateOrCreate(['uid', 'environment' => 'mongo'], ...)`,
   then the order's existing `order_items` are deleted and recreated from the
   mapped line items (simpler than diffing, and safe since it's idempotent).
6. After the run, the highest `updated_at_external` seen is written back to
   `sync_states` as the new watermark.

Rows synced this way are tagged `environment = 'mongo'`
(`Order::ENVIRONMENT_MONGO`) to keep them separate from the older
GraphQL-based `orders:sync` pipeline, which still writes `environment =
'dev'|'prod'`. All dashboard widgets scope to `Order::fromMongo()` — mixing
sources in one aggregate would double-count orders that exist in both.

**Scheduling**: `routes/console.php` runs `orders:sync-mongo` every 5 minutes
(`Schedule::command('orders:sync-mongo')->everyFiveMinutes()->withoutOverlapping()`).

**Telescope note**: the command calls `Telescope::stopRecording()` before
processing. Every insert/update/query it makes bulk-processing tens of
thousands of orders would otherwise get logged as a Telescope entry, which
fills the database and disk within minutes (this happened during initial
backfill — a 140k-order run wrote 2.4GB / ~756k Telescope entries after
Telescope logging every single query).

## Schema

- `orders` — one row per order. Line-item-agnostic fields (status, payment,
  totals, customer email, delivery area/zone/division, promo code) plus
  `raw` (full original Mongo document as JSON, kept for audit/debugging).
- `order_items` — one row per product per order (`belongsTo(Order::class)`).
  Carries product/category/seller/warehouse names + uids, variant details,
  quantity, price, discount, commission.
- `sync_states` — generic `key`/`value` (timestamp) store for sync
  watermarks.

## Widgets (`app/Filament/Widgets/`)

All queries are scoped with `Order::query()->fromMongo()` (a model scope for
`where('environment', 'mongo')`), plus a date range from the dashboard
filters (see below).

| Widget | Chart | What it shows |
|---|---|---|
| `RevenueStats` | Stat cards | Total revenue, total orders, average order value, and unique customers (distinct `customer_email`) for the selected range. |
| `RevenuePerMonthChart` | Bar | `SUM(total_amount)` grouped by month, across the selected range. |
| `OrdersPerMonthChart` | Horizontal bar | Order count grouped by month, across the selected range. |
| `OrdersStatusDonut` | Donut | Order count grouped by `status` (DELIVERED, PROCESSING, CANCELLED, …). |
| `TotalCustomersChart` | Line | Distinct `customer_email` count per month — i.e. monthly active customers, not cumulative total. |
| `TopProductsChart` | Horizontal bar | Top 10 products by `SUM(mrp_price * quantity)`, from `order_items`. |
| `TopCategoriesChart` | Horizontal bar | Top 10 categories by revenue, from `order_items`. |
| `TopSellersChart` | Horizontal bar | Top 10 sellers by revenue, from `order_items`. |
| `RevenueByZoneChart` | Horizontal bar | Top 10 delivery zones (`orders.zone_name`) by `SUM(total_amount)`. |

Registered in `app/Providers/Filament/AdminPanelProvider.php`.

## Dashboard filters & caching

`app/Filament/Pages/Dashboard.php` extends Filament's stock dashboard page
with `HasFiltersForm`, adding two date fields (`startDate`/`endDate`,
default: trailing 12 months). Filament automatically passes these to every
widget as `$this->pageFilters` (`Filament\Widgets\Concerns\InteractsWithPageFilters`).

All 9 widgets use `App\Filament\Widgets\Concerns\ScopesToDashboardFilters`,
which provides:
- `filterStart()` / `filterEnd()` — resolve the active date range (falling
  back to trailing-12-months if the filter form hasn't been touched).
- `rememberForDashboard($suffix, $callback)` — wraps a widget's query in
  `Cache::store('redis')->remember(...)`, keyed by widget class, resolved
  date range, **and** the current `orders:sync-mongo` watermark. The
  watermark component means cache invalidates automatically the moment new
  data lands — no manual cache-busting needed after a sync.

One integration wrinkle worth knowing: the 8 chart widgets extend the
community `leandrocfe/filament-apex-charts` `ApexChartWidget`, which caches
its computed options in a **public** property set once in `mount()` — it
does not auto-recompute when a reactive prop like `pageFilters` changes
(that sync happens in Livewire's `hydrate()` phase, not `updated()`). Each
chart widget therefore defines:
```php
public function rendering(): void
{
    $this->updateOptions();
}
```
mirroring the pattern Filament's own first-party `ChartWidget` uses
internally. `RevenueStats` (a `StatsOverviewWidget`) doesn't need this — it
recomputes fresh on every render already.

## Known limitations

- **Customer counting is email-based**, and only ~38% of synced orders have a
  customer email (most orders only have a phone number). `RevenueStats` and
  `TotalCustomersChart` undercount unique customers as a result.
- **GraphQL sync (`orders:sync`) still exists** and writes to the same
  tables under `environment = 'dev'|'prod'`. It's untouched but not used by
  any widget.

## Next development roadmap

1. **Customer identity** — add a `customer_uid` column (present in the Mongo
   document as `customer.uid`) to `orders` and use it instead of/alongside
   email for accurate unique-customer counts.
2. **Sync observability** — alert (email/Slack) on `orders:sync-mongo`
   failure, and expose last-sync time / row counts somewhere in the admin
   panel (e.g. a small widget reading `sync_states`).
3. **Decide the fate of the GraphQL pipeline** — either retire
   `orders:sync`/`SyncOrdersJob` now that Mongo is the primary source, or
   define clearly when each is used, to avoid confusion about which
   `environment` value is authoritative.
4. **Telescope hygiene** — schedule `telescope:prune` regularly; the
   disk-fill incident during backfill showed the entries table grows fast
   under normal (non-bulk) usage too.
5. **Widget test coverage** — current tests cover the mapper, sync command,
   and the filter/cache-key trait; still missing tests asserting each
   widget's query returns the expected shape given seeded
   `Order`/`OrderItem` factory data.
6. **Replace the temporary admin user** — `admin@example.com` /
   `password` was created only to verify the dashboard renders; swap for
   real authentication before this goes anywhere shared.
7. **Data retention** — `orders`/`order_items` will keep growing with every
   sync; decide whether to archive/partition older orders once volume
   becomes a performance concern.
8. **Cache TTL tuning** — currently a flat 10-minute TTL on top of the
   watermark-based invalidation; revisit once real usage patterns are known.
