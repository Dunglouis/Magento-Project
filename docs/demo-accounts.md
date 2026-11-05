# Demo accounts and data

Accounts and test data for showing the store. **Passwords are not in this file or anywhere in the repository**: each row says where the password is kept on the machine that runs the environment.

## Storefront

| Account | Created by | Password |
|---|---|---|
| `qa@technest.test` (QA TechNest, phone 0912345678, address in Hà Nội, Vietnamese store) | `bin/magento technest:seed:run --step=accounts` | `TECHNEST_QA_PASSWORD` in `env/phpfpm.env` |
| `perf-01@technest.test` … `perf-20@technest.test` (dev only) | `scripts/perf.sh` | `TECHNEST_PERF_PASSWORD` in `env/perf.env` |
| Any Google account | "Sign in with Google" on the login page | Google. On the first sign-in the store asks for a phone number |

Visitors can also register with an email address and a Vietnamese mobile number (for example `0987654321`).

## Admin

| User | Role | Password |
|---|---|---|
| Set by `MAGENTO_ADMIN_USER` (staging: `technest.admin`) | Administrators | `MAGENTO_ADMIN_PASSWORD` in `~/Sites/technest-<env>/env/magento.env` |

The admin path is `MAGENTO_ADMIN_FRONTNAME` in the same file. On staging and production, two-factor authentication is on: the first sign-in sends a setup email to `MAGENTO_ADMIN_EMAIL`.

Staff roles, created by the data patch `TechNest\Base\Setup\Patch\Data\CreateAdminRoles`:

| Role | Can use |
|---|---|
| Catalog Manager | Products, categories, attributes and attribute sets, catalog and cart price rules |
| Sales Staff | Orders (create, invoice, ship, credit memo, cancel, hold, comments), transactions, customers, warranties |
| Content Editor | CMS pages and blocks, widgets, media gallery, customer reviews |

To give a person a role: **System → Permissions → All Users → Add New User**, then the *User Role* tab.

## Test data

| What | Value |
|---|---|
| Coupon, 10% off | `WELCOME10` |
| Coupon, free shipping | `FREESHIP` |
| Free shipping without coupon | Order subtotal from $500 (12,500,000 ₫) |
| Catalog rule | Accessories −10% |
| Flash sale | 8 products with a countdown on the home page and a "Sale" label |
| Payment | Cash on Delivery, Bank Transfer (order status "Chờ xác nhận chuyển khoản", cancelled after 3 days if unpaid) |
| Shipping | Flat Rate $5, "TechNest Delivery – Regional delivery" by province: North $2, Central $4, South $5 |
| Warranty lookup | `/warranty`, serial format `TN-YYMM-XXXXXX`, created when an order is placed, cancelled with the order |
| Currencies | English store in USD, Vietnamese store in VND (1 USD = 25,000 VND) |

Seed or refresh the data in an environment:

```bash
scripts/env/compose.sh staging exec phpfpm bin/magento technest:seed:run
```
