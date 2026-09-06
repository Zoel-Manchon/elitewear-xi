<h1 align="center">Elitewear XI</h1>

<p align="center">
  Retro football shirt ecommerce.<br>
  Laravel 13 · MySQL · Bootstrap 5 over Sass · PayPal sandbox · versioned REST API
</p>

<p align="center">
  <a href="https://github.com/Zoel-Manchon/elitewear-xi/actions/workflows/ci.yml"><img src="https://github.com/Zoel-Manchon/elitewear-xi/actions/workflows/ci.yml/badge.svg?branch=main" alt="CI"></a>
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/PHP-8.3-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.3">
  <img src="https://img.shields.io/badge/MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/Bootstrap-5-7952B3?style=flat-square&logo=bootstrap&logoColor=white" alt="Bootstrap 5">
  <img src="https://img.shields.io/badge/PayPal-sandbox-00457C?style=flat-square&logo=paypal&logoColor=white" alt="PayPal sandbox">
  <img src="https://img.shields.io/badge/license-MIT-2A3340?style=flat-square" alt="MIT licence">
</p>

![Home](docs/screenshots/01-home.png)

---

## At a glance

|  |  |
| --- | --- |
| **What it is** | A complete period-shirt shop: catalogue imported from public APIs, persistent cart, checkout with a payment gateway, admin panel and a documented REST API. |
| **The one idea** | The test suite does not check that the buttons work. **It checks that the attacks fail.** An IDOR against someone else's cart line, a sort with injected SQL, a registration carrying `is_admin=1`, an order for more units than exist, a single-use coupon redeemed twice, a polyglot with a valid PNG header followed by PHP. |
| **Built with** | Laravel 13 · PHP 8.3 · MySQL · Bootstrap 5 over Sass · Vite · PayPal Orders v2 |
| **Money** | Integer cents, never `float`. Orders snapshot their own prices, so history cannot be rewritten by editing the catalogue. |
| **Concurrency** | `lockForUpdate()` on variants and coupons: two buyers going for the last size M are serialised, not both sold. |
| **The browser** | Never sees or sends an amount. It can only manipulate a PayPal order id, and the server checks that against the order before treating anything as paid. |
| **Run it** | `docker compose up -d` → `http://localhost:8000` · `admin@retroshop.test` / `password` |

**Contents** — [Getting started](#getting-started) · [Architecture](#architecture) ·
[Data model](#data-model) · [Order lifecycle](#order-lifecycle) ·
[Checkout and payment](#checkout-and-payment) · [Cart](#cart) ·
[Importing from external APIs](#importing-from-external-apis) ·
[Security](#security) · [Bugs found and closed](#bugs-found-and-closed) ·
[API](#api) · [Quality](#quality) · [What is next](#what-is-next)

---

## What it is

A portfolio project with a deliberate bias towards **application security**. Every
decision that closes an attack vector is commented in the code and justified here.

---

## Screenshots

### Shop

| | |
|---|---|
| ![Catalogue](docs/screenshots/02-catalogo.png)<br>**Catalogue** — combinable filters with a count per facet | ![Product](docs/screenshots/03-producto.png)<br>**Product** — gallery, sizes and real stock |
| ![Gallery](docs/screenshots/04-galeria.png)<br>**Gallery** — full-screen zoom with PhotoSwipe | ![Cart](docs/screenshots/05-carrito.png)<br>**Cart** — side drawer with free-shipping progress |
| ![Checkout](docs/screenshots/06-checkout.png)<br>**Checkout** — coupon applied to the summary | |

### Admin

| | |
|---|---|
| ![Dashboard](docs/screenshots/08-admin-dashboard.png)<br>**Dashboard** — revenue, orders and low stock | ![Product admin](docs/screenshots/09-admin-producto.png)<br>**Catalogue** — sizes, stock and images |
| ![Audit](docs/screenshots/10-admin-auditoria.png)<br>**Audit** — who changed what, when and from which IP | ![API](docs/screenshots/11-api.png)<br>**API v1** — public catalogue as JSON |

---

## Getting started

### With Docker

```bash
cp .env.example .env
npm ci
npm run build

docker compose down -v --remove-orphans   # first install, or a full reset
docker compose build --no-cache app queue
docker compose up -d mysql redis app web
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate:fresh --seed --force
docker compose exec app php artisan optimize:clear
docker compose up -d queue
docker compose exec app php artisan catalog:doctor
```

The Compose development configuration uses an internal, reproducible MySQL account
(`elitewear` / `secret`). It does not depend on whatever user or password your host
MySQL is set up with. The `app` image ships Composer, so the same checks CI runs can be
run inside the container:

```bash
docker compose exec app composer validate --strict --no-check-publish
docker compose exec app vendor/bin/phpstan analyse --no-progress
docker compose exec app vendor/bin/pint --test
docker compose exec app php artisan test
```

To rebuild everything from scratch, database included: `sh docker/reset.sh`, or
`.\docker\reset.ps1` in PowerShell. To run the CI-equivalent checks before pushing:
`.\docker\verify.ps1`.

Then `http://localhost:8000`.

> **If the page comes up unstyled**, delete `public/hot` and rebuild. That file is
> created by `npm run dev` to redirect assets to the Vite server; if it is left behind
> after stopping it, `@vite` keeps pointing at `127.0.0.1:5173` and everything fails on
> CORS.
>
> ```bash
> rm -f public/hot && npm run build && docker compose restart app
> ```

The image carries **GD compiled in**, which is what Intervention Image needs to
re-encode images downloaded from third parties. That is the main reason Docker is here
at all: the environment stops depending on how each machine happens to have PHP set up.

### Without Docker

Needs PHP 8.3+ with `gd`, `pdo_mysql`, `mbstring`, `intl`, `zip` and `exif`.

```bash
composer install
npm install && npm run build

cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link

php artisan serve
```

### Filling the catalogue from the APIs

```bash
php artisan catalog:sportsdb-full --all-countries --max-countries=15
php artisan kits:gallery --max=2      # second kit per product
php artisan teams:media --limit=40    # banner and fanart per club
php artisan players:import --max=6    # player portraits
```

### If something will not start

| Symptom | Cause | Fix |
|---|---|---|
| Unstyled page | `public/hot` left over from an earlier `npm run dev` | `rm -f public/hot && npm run build` |
| Empty catalogue | The seed never finished, or the products are unpublished | `php artisan catalog:doctor` tells the two apart, `--publish` fixes it |
| Zero images on import | `gd` missing, or the Intervention Image version does not match | The Docker image ships `gd`; the error now shows in the skipped column |
| A code change has no effect | The Dockerfile copies the code into the image | The code directories are mounted in `docker-compose.yml`; touching `composer.json` does need `docker compose build` |
| `Connection refused` to MySQL | The `.env` points at the host's MySQL | `docker-compose.yml` overrides `DB_HOST` and `DB_PORT`; check the user and password |

---

## Architecture

```mermaid
graph TD
    subgraph Inbound
        WEB[Web routes · Blade]
        API[API v1 · Sanctum]
        HOOK[PayPal webhook]
        CLI[Import commands]
    end

    subgraph Application
        CTRL[Controllers<br/>thin]
        REQ[Form Requests<br/>validation]
        ACT[Actions<br/>use cases]
        RES[Resources<br/>serialisation]
    end

    subgraph Domain
        MOD[Eloquent models]
        ENUM[Enums<br/>OrderStatus · ShirtSize · KitType]
        MONEY[Money<br/>value object]
    end

    subgraph External
        DB[(MySQL)]
        DISK[Public disk]
        PP[PayPal Orders v2]
        SDB[TheSportsDB]
        WM[Wikimedia Commons]
    end

    WEB --> CTRL
    API --> CTRL
    HOOK --> CTRL
    CLI --> ACT
    CTRL --> REQ
    CTRL --> ACT
    CTRL --> RES
    ACT --> MOD
    MOD --> ENUM
    MOD --> MONEY
    MOD --> DB
    ACT --> DISK
    ACT --> PP
    CLI --> SDB
    CLI --> WM

    style ACT fill:#d6b36a,color:#12201a
    style PP fill:#1a2f27,color:#f4f1e8
```

Controllers hold no business logic: they validate with a Form Request, delegate to an
Action and serialise with a Resource. The only interface in the project is
`PaymentGateway`, and it exists so PayPal can be swapped for a double in the tests.

**There are no repositories over Eloquent.** Eloquent *is* the repository; wrapping it
adds a layer that decouples nothing real.

---

## Data model

```mermaid
erDiagram
    TEAMS ||--o{ PRODUCTS : "makes"
    TEAMS ||--o{ TEAM_EQUIPMENTS : "kits"
    TEAMS ||--o{ PLAYERS : "squad"
    PRODUCTS ||--o{ PRODUCT_VARIANTS : "sizes"
    PRODUCTS ||--o{ PRODUCT_IMAGES : "gallery"
    CATEGORIES }o--o{ PRODUCTS : "classifies"

    USERS ||--o{ ADDRESSES : "has"
    USERS ||--o| CARTS : "owns"
    CARTS ||--o{ CART_ITEMS : "contains"
    PRODUCT_VARIANTS ||--o{ CART_ITEMS : "references"

    USERS ||--o{ ORDERS : "places"
    ORDERS ||--o{ ORDER_ITEMS : "freezes"
    ORDERS ||--o{ PAYMENTS : "records"
    COUPONS ||--o{ ORDERS : "discounts"
    PRODUCT_VARIANTS |o--o{ ORDER_ITEMS : "weak reference"

    USERS ||--o{ REVIEWS : "writes"
    USERS ||--o{ WISHLIST_ITEMS : "saves"
    PRODUCT_VARIANTS ||--o{ STOCK_ALERTS : "notifies"

    PRODUCTS {
        bigint base_price_cents "integer, never float"
        string season "1994-95"
        timestamp published_at "null = draft"
    }
    PRODUCT_VARIANTS {
        string size "the inventory unit"
        int stock
        int price_delta_cents
    }
    CART_ITEMS {
        int quantity "no price: recalculated"
    }
    ORDER_ITEMS {
        string product_name "snapshot"
        bigint unit_price_cents "snapshot"
    }
    PAYMENTS {
        string provider_order_id "unique = idempotency"
    }
```

Four rules hold up everything else:

| Rule | Why |
|---|---|
| Money as integer cents | `float` loses precision as soon as you add |
| `order_items` stores a snapshot | A past order cannot change because the catalogue did |
| `cart_items` stores **no** price | A cart is an intention; an order is a contract |
| The cart references the *variant* | Size is the inventory unit, not an attribute |

---

## Order lifecycle

```mermaid
stateDiagram-v2
    [*] --> pending: PlaceOrder<br/>(stock locked and decremented)
    pending --> paid: PayPal capture<br/>or webhook
    pending --> cancelled: customer walks away
    paid --> shipped
    paid --> refunded
    paid --> cancelled
    shipped --> delivered
    shipped --> refunded
    delivered --> refunded
    cancelled --> [*]
    refunded --> [*]
    delivered --> [*]
```

The state machine lives in `OrderStatus::canTransitionTo()`, not in the controller and
not in the form. A hand-rolled `POST` with an arbitrary status is rejected exactly like
a click in the interface.

---

## Checkout and payment

```mermaid
sequenceDiagram
    autonumber
    actor C as Customer
    participant L as Laravel
    participant DB as MySQL
    participant PP as PayPal

    C->>L: POST /checkout (address)
    L->>DB: BEGIN
    L->>DB: SELECT ... FOR UPDATE (variants and coupon)
    Note over L,DB: The lock serialises two buyers<br/>going for the last size M
    alt Not enough stock
        L->>DB: ROLLBACK
        L-->>C: "Only N left"
    else Stock available
        L->>DB: decrement stock, redeem coupon, create order and snapshot
        L->>DB: COMMIT
        L-->>C: payment page
    end

    C->>L: POST /payments/{order}/paypal
    L->>PP: create order (amount taken from the ORDER)
    PP-->>L: order id
    C->>PP: approves in the PayPal window
    C->>L: POST .../capture
    L->>PP: capture
    PP-->>L: capture id + amount
    Note over L: Checks the captured amount<br/>against the order total
    L->>DB: order = paid
    L-->>C: confirmation

    PP->>L: webhook PAYMENT.CAPTURE.COMPLETED
    Note over L: Verifies the signature.<br/>If already paid, does nothing:<br/>idempotent by design
```

The browser never sees or sends an amount. The only thing it can manipulate is the
PayPal order id, and the server checks that against the order before treating anything
as paid.

---

## Cart

```mermaid
flowchart LR
    A[Visitor] -->|adds| B{Signed in?}
    B -->|No| C[Guest cart<br/>token in the SESSION]
    B -->|Yes| D[User cart]
    C -->|signs in| E[MergeGuestCart]
    E --> D
    D --> F[Side drawer<br/>fetch, no reload]
    F --> G[Checkout]

    style C fill:#1a2f27,color:#f4f1e8
    style E fill:#d6b36a,color:#12201a
```

The guest cart token lives in the session, **never** in the URL and never in a cookie of
its own. If the client could choose it, changing that value would mean reading and
emptying somebody else's cart: a textbook IDOR, and the most common flaw in hand-rolled
carts.

---

## Importing from external APIs

The catalogue is fed by **TheSportsDB** (kits, crests, squads) and **Wikimedia Commons**
(retro archive). This is the part of the system where content we do not control gets in.

```mermaid
flowchart TD
    A[External API URL] --> B{Allowed domain?}
    B -->|No| X1[Rejected]
    B -->|Yes| C[Download with a size cap]
    C --> D{Does getimagesizefromstring<br/>recognise the bytes?}
    D -->|No| X2[Rejected]
    D -->|Yes| E{PNG, JPEG or WEBP?<br/>Sane dimensions?}
    E -->|No| X3[Rejected]
    E -->|Yes| F[Filename we generate<br/>external id sanitised]
    F --> G[Re-encoded to WEBP]
    G --> H[(Public disk)]

    style X1 fill:#c4331f,color:#fff
    style X2 fill:#c4331f,color:#fff
    style X3 fill:#c4331f,color:#fff
    style G fill:#d6b36a,color:#12201a
```

Everything goes through `RemoteImageStore`, and the principle is explicit: **an image
arriving over HTTP deserves no more trust than one a user uploads.**

There is a pixel cap as well as a byte cap: a 30 KB PNG can decompress to 40,000 ×
40,000 and exhaust memory during re-encoding.

---

## Back-in-stock alerts

```mermaid
sequenceDiagram
    actor V as Visitor
    participant L as Laravel
    participant DB as MySQL
    actor A as Admin
    participant Q as Queue

    V->>L: POST /alerts (size + email)
    L->>DB: firstOrCreate(variant, email)
    L-->>V: "We will write when it is back"
    Note over L,V: Always the identical response.<br/>A different message would turn this<br/>into an account checker.

    A->>L: restocks from the panel
    L->>DB: UPDATE stock 0 -> N
    Note over L: The observer checks the PREVIOUS<br/>value: it fires only on the<br/>0 -> positive transition
    L->>Q: queues BackInStock
    Q-->>V: email with a signed unsubscribe link
```

---

## Security

| Control | Where | What it closes |
|---|---|---|
| Cart ownership check | `CartController::authorizeItem()` | IDOR on cart lines |
| Guest token in the session | `CartResolver` | Cart theft by parameter tampering |
| Sort whitelist | `CatalogController::SORTS` | SQL injection via `orderBy()` |
| Escaping `%` and `_` | Catalogue and search | Dumping the catalogue with `?q=%` |
| `abort_unless` on `published_at` | `CatalogController::show()` | Drafts visible by guessing a URL |
| `is_admin` outside `#[Fillable]` | `User` | Privilege escalation from `POST /register` |
| 404 rather than 403 on `/admin` | `EnsureUserIsAdmin` | Panel enumeration |
| State machine in the enum | `OrderStatus` | Skipping the flow with a hand-made POST |
| `lockForUpdate()` on the order | `PlaceOrder` | Overselling under concurrency |
| `lockForUpdate()` on the coupon | `PlaceOrder` | Multiple redemption of a single-use code |
| Discount capped at the subtotal | `Coupon::discountFor()` | Negative totals |
| One message for any invalid code | `CouponController` | Brute-forcing coupons |
| Amount taken from the order | `PayPalGateway` | Client-side price tampering |
| Captured amount checked | `PaymentController` | Capturing below the total |
| `unique` on `provider_order_id` | `payments` table | Duplicate webhooks |
| Webhook signature verification | `PaymentController` | Forged webhooks |
| Sanitised filename | `RemoteImageStore` | Path traversal from an API response |
| Real byte verification | `RemoteImageStore` | `Content-Type` forged by the remote server |
| Re-encoding to WEBP | `RemoteImageStore` | Payloads behind a valid image header |
| Pixel cap | `RemoteImageStore` | Memory exhaustion on decompression |
| SVG rejected on upload | `ProductRequest` | XSS via a vector served from our own origin |
| CSP with nonce, no `unsafe-inline` | `SecurityHeaders` | Stored XSS |
| `report-uri` in the CSP | `SecurityHeaders` | A policy with no telemetry |
| `escapeHtml()` before `innerHTML` | `http.js` | XSS from the catalogue |
| Tags escaped in JSON-LD | `shop/show.blade.php` | XSS originating at TheSportsDB |
| YouTube id validated | `Team::youtubeId()` | Injection from a third-party URL |
| `Password::uncompromised()` | `RegisterController` | Already-breached credentials |
| Rate limiting per email + IP | `TokenController` | API brute force |
| One login error message | `TokenController` | User enumeration |
| Availability instead of stock | `ProductVariantResource` | Leaking business information |
| Identical response on alerts | `StockAlertController` | Account checking by email |
| Signed unsubscribe URL | `BackInStock` | Third-party forged unsubscribes |
| Audit log | `Auditable` | Changes with no identifiable author |
| Secrets excluded from the log | `Auditable::$auditExclude` | Leaking through the log itself |

The CSP only works because **there is no inline JavaScript in any view**. Gallery, cart,
search and filters all live in modules imported by Vite. Retrofitting that later is
expensive; doing it from the start is free.

---

## Bugs found and closed

The ones that teach something, with the real cause rather than the symptom.

**Filters returned nothing when combined.**
The condition was `$except !== 'tipo' && ($filters['tipo'] ?? null)`. In PHP, `&&`
**always** returns a boolean, so the `when()` closure received `true` instead of the
slug and the query compared `slug = 1`. With `decada`, `substr(true, 0, 3)` gave `'1'`
and `season LIKE '1%'` matched everything from 1970 to 1999, which is why it looked
half-working. It survived three reviews because no test checked that a filter *returned*
the right thing, only that the page loaded. `CatalogFilterTest` now covers it with eight
cases.

**Path traversal from an API response.**
The kit's external identifier was concatenated straight into the file path. An id
containing `../` in the response wrote outside the intended directory. A third party
controls it, not a user: exactly the kind of input people forget to validate.

**Stored XSS through JSON-LD.**
The structured data was serialised with `JSON_UNESCAPED_SLASHES` inside a `<script>`. A
`</script>` in a product name — and the names come from TheSportsDB — closed the block
and executed whatever followed.

**Silent mass assignment, three times.**
`notified_at`, `is_approved` and `redemptions_count` outside `$fillable` made `update()`
discard them without an error. The symptom was always the same: "this does not save and
gives no error". When an `update()` does nothing, `$fillable` is the first place to look.

**A directory where a file should have been.**
`phpstan.neon` includes `phpstan-baseline.neon`, but that name existed in the repository
as an empty directory. PHPStan aborts when it cannot read a file from `includes:`, so
the static-analysis job failed without producing a single analysis error — the message
said nothing about the code. Zips and Git do not preserve empty files, and an accidental
`mkdir` turns it into a folder.

**Tests running against the real database.**
`phpunit.xml` declared `DB_CONNECTION=sqlite`, but under Docker `env_file` injects the
`.env` as process variables and PHPUnit does not override them. The suite ran
`migrate:fresh` against MySQL and **wiped the development catalogue**. It is the same
cause that had CSRF active during tests and DNS validation making real queries: three
unrelated-looking symptoms, one configuration cause. `TestCase::refreshApplication()`
now forces in-memory SQLite before `RefreshDatabase` acts.

**Cascading catalogue deactivation.**
If the API returned no kits, the list of active ids came back empty, the filter was not
applied, and the `update` reached *every* product of the team. A temporary third-party
failure unpublished the whole catalogue with nothing flagging it.

---

## API

Full documentation in [`docs/openapi.yaml`](docs/openapi.yaml).

```bash
# Public catalogue
curl http://localhost:8000/api/v1/products?team=afc-ajax

# Token
curl -X POST http://localhost:8000/api/v1/tokens \
  -H 'Content-Type: application/json' \
  -d '{"email":"you@example.com","password":"...","device_name":"cli"}'

# Orders
curl http://localhost:8000/api/v1/orders -H 'Authorization: Bearer <token>'
```

Versioned from day one: `/api/v1` means a v2 can ship without breaking whoever already
consumes the current one. Adding the version afterwards costs far more.

---

## Quality

```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/phpstan analyse --no-progress
docker compose exec app vendor/bin/pint --test
docker compose exec app composer validate --strict --no-check-publish
docker compose exec app composer audit --locked --no-interaction
npm ci && npm run build && npm audit --audit-level=high
```

![Tests](docs/screenshots/12-tests.png)

CI on GitHub Actions: the suite over in-memory SQLite, migration validation against
MySQL 8.4, Larastan level 5 with an incremental baseline, Pint, and a PHP/npm dependency
audit. New errors not in the baseline block the push.

---

## Structure

```
app/
├── Actions/          use cases (PlaceOrder, AddItemToCart, MergeGuestCart)
├── Console/Commands/ catalogue importers
├── Contracts/        PaymentGateway
├── Enums/            OrderStatus, ShirtSize, KitType, CouponType
├── Http/
│   ├── Controllers/  web, Admin/, Api/V1/
│   ├── Middleware/   EnsureUserIsAdmin, SecurityHeaders
│   ├── Requests/     validation
│   └── Resources/    JSON serialisation
├── Models/           Eloquent
├── Notifications/    BackInStock
├── Observers/        ProductVariantObserver
├── Services/
│   ├── PayPal/       gateway client
│   └── SportsData/   TheSportsDB, Commons, RemoteImageStore
├── Support/          Money, CartResolver, CouponSession, ShirtRenderer
└── Traits/           Auditable

resources/
├── js/               http, cart, search, gallery, filters, product, toast
├── sass/             _tokens, _variables, _components, app
└── views/            shop/, account/, admin/, partials/

docker/               nginx and php.ini
docs/                 openapi.yaml and screenshots
```

---

## What is next

1. **TOTP 2FA on the admin panel**, with recovery codes.
2. **Reviews with prior moderation** rather than automatic approval.
3. **PDF invoices** with sequential numbering.
4. **Meilisearch** for the search box: `LIKE` with a leading wildcard cannot use an index
   and does not scale.

---

## License

MIT. The shirts, crests and club names belong to their owners; this project is a
technical demonstration with no commercial purpose.
