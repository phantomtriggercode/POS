# Motambarea Farmers — Buying Management (POS)

Internal system for the cocoa and coffee buying operation in Kumba, Cameroon.
It is separate from the public website (`phantomtriggercode/motambeareafarmers`). The website shows information; this system records the business.

Built in plain **PHP 8.1+ and MySQL/MariaDB**, with no framework, Composer or build step. It runs on any Hostinger web hosting plan.

## What it does

| Area | Features |
|---|---|
| **Farmers** | Register suppliers with code, phone, village, ID number, and farm location, size and GPS (captured from the clerk's phone). Search them and view each farmer's full history. |
| **Purchases** | Weigh-in form: gross weight − tare = net kg × official price. Optional moisture %. Deducts advance repayments. Pays by cash, MTN MoMo, Orange Money, bank or cheque. Prints numbered receipts sized for 80 mm thermal or A4 paper. |
| **Prices** | Managers set a price per kg for each produce and grade, with a start date and time. Full price history is kept. Clerks cannot change the price. A manager can apply a special price only with a written note. |
| **Advances** | Pre-finance to farmers. The balance is recovered automatically from later purchases. Includes a list of who owes what. |
| **Stock** | Live stock per grade (bought − dispatched), with bags and average cost per kg. |
| **Dispatches** | Loads sent to exporters or depots, with vehicle, driver and optional sale price. A dispatch cannot exceed the stock. |
| **Reports** | Any date range, broken down by produce and grade, payment method, clerk and top farmers, plus dispatches. CSV export opens in Excel. |
| **Control** | Three roles. Nothing is deleted: mistakes are **voided with a reason**. Every action is written to an audit log. |
| **Website link** | `api/prices.php` publishes only the prices a manager marks as public, so the website's price board can show real figures. |

### Roles

| | Clerk | Manager | Admin |
|---|:-:|:-:|:-:|
| Register farmers, record purchases, print receipts | ✓ | ✓ | ✓ |
| View stock, prices, advances | ✓ | ✓ | ✓ |
| Set prices, give advances, void receipts, dispatch stock, reports and CSV export | | ✓ | ✓ |
| Users, settings, produce and grades, audit log | | | ✓ |

## Hosting on Hostinger

The public website and this system can share **one Hostinger web hosting plan** (Premium or Business):

```
motambeareafarmers.com        → public website (static HTML, the other repo)
pos.motambeareafarmers.com    → this system (PHP + MySQL)
```

### 1. Prepare hPanel
1. **PHP version:** *Websites → Manage → Advanced → PHP Configuration* → choose **PHP 8.2 or 8.3**.
2. **Database:** *Databases → MySQL Databases*. Create a database and a user, for example `u123456789_pos`. Write down the name, user and password.
3. **Subdomain:** *Domains → Subdomains*. Create `pos` and tick **Custom folder**, for example `public_html/pos-app/public`.

### 2. Upload the code
Use either method:
- **Git (recommended):** *Advanced → Git*. Repository `https://github.com/phantomtriggercode/POS.git`, branch `main`, directory `public_html/pos-app`. Later updates are one click on **Deploy**.
- **File Manager:** download this repository as a ZIP, upload it to `public_html/pos-app/` and extract it there.

The subdomain must point at the **`public/`** folder. The `app/`, `config/` and `storage/` folders each contain an `.htaccess` file that blocks web access, as a second layer of protection.

### 3. SSL and install
1. *Security → SSL* → install the free SSL certificate for `pos.motambeareafarmers.com`. `public/.htaccess` forces HTTPS.
2. **Right away**, open `https://pos.motambeareafarmers.com/install.php`. Enter the database details (host `localhost`), the company details and your administrator account. The installer creates the tables and `config/config.php`, then locks itself.
3. Sign in. Under **Prices**, set today's buying prices. Under **Settings**, rename or disable the starter grades to match how you buy, then add users for your clerks.

### 4. Backups
Business plans include daily backups and Premium plans weekly. Download a database backup regularly as well: *Databases → phpMyAdmin → Export*, or *Files → Backups*. Purchase and payment records are financial records.

> This repository is **public** on GitHub. Real credentials live only in `config/config.php` on the server, and that file is git-ignored. Never commit it. You may want to make the repository private.

## Showing live prices on the public website

When a manager ticks **"Show this price on the public website"**, the price appears at
`https://pos.motambeareafarmers.com/api/prices.php`:

```json
{ "currency": "XAF", "unit": "kg", "last_updated": "2026-10-07T07:00:00+01:00",
  "prices": [ { "produce": "Cocoa", "grade": "Grade I", "price_per_kg": 1450, "effective": "…" } ] }
```

Only `https://motambeareafarmers.com` and `www.` may read it from a browser (CORS); change this in `config/config.php`.
To use it, the website's `js/prices.js` would fetch this URL and fill the table. Until verified prices are published, it should keep its demo notice. That change belongs to the website repository, so coordinate it there.

## Local development

```bash
php -S 127.0.0.1:8000 -t public      # then open http://127.0.0.1:8000/install.php and pick "SQLite"
php tests/domain_test.php            # business-rule tests (SQLite)
bash tests/http_smoke.sh             # end-to-end: installer, login, every page, permissions, API

# Same tests against MySQL/MariaDB (empty database):
POS_TEST_MYSQL="dbname;user;password" php tests/domain_test.php
POS_TEST_MYSQL="dbname;user;password" bash tests/http_smoke.sh
```

## Project layout

```
public/            web root (point the subdomain here)
  index.php        front controller: ?p=<page>
  install.php      one-time installer
  api/prices.php   public price feed (JSON)
  assets/          app.css, app.js (no external CDNs, so it loads fast on mobile data)
app/
  bootstrap.php    config, session, security headers
  lib/             db, schema, auth, domain (business rules), helpers, layout
  pages/           one file per screen
config/            config.example.php (installer writes config.php)
storage/           logs and SQLite file (when testing), not web-accessible
tests/             domain_test.php, http_smoke.sh
```

Business rules live in `app/lib/domain.php`. Money is stored as whole XAF and weights in kg with 2 decimals. Balances and stock are always calculated from the records, never stored, so they cannot drift.

## Before going live
- Replace the starter produce and grades (Settings) with your real grading.
- Set prices, and set the company phone and receipt footer (Settings).
- Create one account per person. Do not share logins: the audit log records who did what.
- Test-print a receipt on the station printer (the browser's print dialog works with 80 mm thermal printers).

## Possible next steps
- SMS or WhatsApp receipt to the farmer
- Offline mode for buying stations with weak network
- Cash-box reconciliation per clerk per day
- Multiple buying stations or warehouses
- Lot and batch traceability for EUDR due diligence (farm GPS is already captured)
- French interface
