# Performance (Phase 5)

Light load test of the TechNest store before and after the Magento performance settings, measured on 2026-11-02.

## Summary

| Metric (20 virtual users) | Before: developer mode | After: production mode | Change |
|---|---|---|---|
| Error rate | 0% | 0% | same |
| Completed purchase flows | 67 | **102** | +52% |
| Page TTFB p95 | 2.08 s | **1.25 s** | −40% |
| All requests p95 | 3.80 s | **2.44 s** | −36% |
| All requests median | 1.64 s | **0.59 s** | −64% |

- **Met:** no errors at 20 virtual users.
- **Not met:** page TTFB p95 < 500 ms. Pages served from the Full Page Cache answer in about 0.3 s (median), but the p95 is higher. See [Limits](#limits-and-remaining-work).

## Test scenario

[`tests/perf/checkout.js`](../tests/perf/checkout.js) runs with [k6](https://k6.io/) from the `grafana/k6` Docker image, so nothing is installed on the host. Each virtual user repeats this flow:

1. Opens a category page.
2. Searches for a term such as `laptop`, `iphone` or `mouse`.
3. Opens a product page.
4. Signs in as its own customer `perf-NN@technest.test` (REST token, taken once in `setup()`) and adds the product to the cart.
5. Picks the `technest_regional` shipping method for an address in Hanoi.
6. Places a Cash on Delivery order.

Each step has a think time of 1–2 s.

- **Load:** ramps up to 20 virtual users over 20 s, holds 20 for 40 s, then ramps down over 10 s.
- **Cache warm-up:** `setup()` requests every page once before the measurement starts.
- **Pass criteria:** no failed requests, all checks pass, and page TTFB (`http_req_waiting{type:page}`) p95 < 500 ms.

Steps 1–3 are plain page requests, which measures the Full Page Cache. Steps 4–6 use the customer REST API (`/V1/carts/mine/...`), the same calls the checkout makes.

## Results by step

Times in milliseconds, shown as median / p95:

| Step | Before | After |
|---|---|---|
| Category page | 1158 / 2001 | 318 / 1369 |
| Search results | 1219 / 2384 | 331 / 1679 |
| Product page | 1320 / 2202 | 337 / 1414 |
| Create or get cart | 1747 / 2517 | 640 / 1681 |
| Add to cart | 2104 / 3865 | 985 / 1878 |
| Shipping information | 2558 / 3752 | 1139 / 2441 |
| Place order | 2923 / 4326 | 1639 / 2885 |

The raw k6 summaries are in [`tests/perf/results/`](../tests/perf/results/).

## What was changed

Already in place before Phase 5:

- Cache and sessions in Valkey (databases 0/1/2).
- The built-in Full Page Cache.
- Every indexer on "Update by Schedule".

Changes for the "after" run, all applied by `scripts/perf-mode.sh prod`:

| Setting | Value |
|---|---|
| Application mode | `production` (compiled DI, pre-deployed static files) |
| Static content | `en_US` and `vi_VN` for `TechNest/minimal` and `Magento/backend` |
| Minify HTML / JS / CSS | on |
| Merge CSS | on |
| Merge JS | off: it works against RequireJS, which loads modules on demand |
| JS bundling | off: Magento's basic bundling builds JS files of several MB that each page downloads, so it was not adopted |
| Signed static URLs | on |

The front-end settings are written to `app/etc/env.php` with `config:set --lock-env`, not to the database. `scripts/perf-mode.sh dev` sets them back to off, so theme work in developer mode is not affected.

Not done:

- **Varnish:** the dev machine has about 1 GB of free memory; the built-in Full Page Cache in Valkey is used instead.
- **WebP:** Magento core does not convert images to WebP. Converting them would mean re-importing every product image. Product images are already resized per view by `view.xml`.

## Limits and remaining work

- **The FPC is flushed by orders.** Every order changes the salable quantity of a product. Magento then cleans the cache tags of that product and of the categories and search results that show it, so the next visitor gets an uncached page. The test buys from a small set of accessories, which makes this happen much more often than in real traffic.
- **PHP-FPM allows only 5 workers, and OPcache is too small.** The PHP image uses its defaults: `pm.max_children = 5`, and OPcache with 128 MB and 10,000 files, while the code base has about 34,000 PHP files. With 20 users, requests queue behind the 5 workers. Tuning these settings is outside this phase (decision of 2026-11-02) and is the next thing to try when the TTFB target matters, for example on the demo server.
- **The dev machine is small.** It has 6 GB of RAM and 4 CPUs shared by all containers, k6 and the browser. With cron on, each cron group starts a PHP process of about 200 MB every minute; the machine swaps and the timings mostly reflect cron. Both runs were therefore made with cron stopped (`scripts/perf.sh` does this itself). Real production traffic runs with cron on.

## How to run again

```bash
scripts/perf-mode.sh dev        # developer mode, optimisations off
scripts/perf.sh before-developer

scripts/perf-mode.sh prod       # production mode, takes 6-8 minutes and a lot of memory
scripts/perf.sh after-production

scripts/perf-mode.sh dev        # back to developer mode for code work
```

`scripts/perf.sh <label> [k6 options]`:

- Creates `env/perf.env` with the password of the perf customers on the first run. This file is not committed.
- Picks accessories with at least 20 salable units.
- Turns off email sending (`system/smtp/disable`) and cron during the run, then turns them back on.
- Writes `tests/perf/results/<label>.json`.

Environment variables:

| Variable | Default | Purpose |
|---|---|---|
| `VUS` | 20 | Number of virtual users (and perf customers) |
| `BASE_URL` | `https://technest.test` | Store URL |

Each run places real COD orders: about 300 orders were left from the test runs on 2026-11-02 (customers `perf-01` to `perf-20@technest.test`), and stock is reduced accordingly.
