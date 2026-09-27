# FacturaFlow

**FacturaFlow** is a SaaS for freelancers. It keeps track of **clients**, **projects** (with a budget calculated automatically) and **invoices** with Spanish taxes (IVA 21 % and IRPF 15 % withholding). Each user sees only their own data. An **administrator** role manages all accounts.

Web Development assignment built on the **TALL stack**: Tailwind CSS 4, Alpine.js, Laravel 13 and Livewire 4, using the official **Livewire starter kit** (Flux UI + Fortify).

---

## Features

| Area | What it does |
|---|---|
| **Dashboard** | Invoiced this year, pending collections, active portfolio, late deliveries, a 6‑month chart, top clients and projects by status |
| **Clients** | Full CRUD, live search, type filter (company / self-employed / individual), sortable columns, detail page with projects and invoices |
| **Projects** | Full CRUD, budget calculated while you type, combinable filters (text + status + client + tag + "late only" + sort), inline status change |
| **Kanban board** | Drag and drop between columns (Alpine.js) to change a project's status |
| **Tags** (N:M) | Tag CRUD in a modal; each project can have several tags and each tag belongs to several projects |
| **Invoices** | Drafts with dynamic lines, sequential numbering on issue (`FF-2026-0001`), payment tracking, overdue detection, email delivery, print/PDF, CSV export |
| **Tax details** | Tax ID (NIF), address, IBAN and logo upload (required before issuing an invoice) |
| **Administration** | Global summary plus user management: change role, block or unblock, delete (only for `admin`) |
| **Authentication** | Registration, login, password reset and logout (Fortify). Blocked accounts cannot sign in |
| **Extras** | Dark mode, Spanish/English language switch, reminder emails for overdue invoices on a daily schedule, file uploads, CSV export |

---

## How it meets the assignment

| Requirement | Where |
|---|---|
| Tailwind responsive | All views (`resources/views`). Collapsible sidebar on mobile, `sm:`/`lg:` grids |
| Alpine.js | Kanban (`pages/proyectos/⚡tablero`), bar chart (`components/grafico-barras`), character counter (`pages/clientes/⚡form`), print and copy IBAN (`pages/facturas/⚡show`) |
| ≥ 3 models + relations | `User`, `Cliente`, `Proyecto`, `Factura`, `FacturaLinea`, `Etiqueta` (`app/Models`) |
| 1:N / N:1 | User → Clientes → Proyectos → Facturas → Lineas (`hasMany` / `belongsTo`) |
| **N:M** | `Proyecto` ↔ `Etiqueta` through the `etiqueta_proyecto` pivot table (`belongsToMany`, `sync()`) |
| Full CRUD from the front end | Clients, projects, invoices, tags and users (admin) |
| Routing: groups, names, middleware | `routes/web.php`: `auth`+`verified` group, `prefix`/`name` per resource, `admin` group with its own middleware |
| ≥ 5 full-page Livewire components | 14 pages in `resources/views/pages` (dashboard, clientes ×3, proyectos ×4, facturas ×3, etiquetas, admin ×2, settings/fiscal) |
| ≥ 5 reusable components | `encabezado`, `metrica`, `etiqueta`, `estado-factura`, `etiquetas-proyecto`, `importes`, `estado-vacio`, `confirmar-borrado`, `grafico-barras`, `selector-idioma` + the Livewire component `⚡cliente-rapido` |
| Migrations | `database/migrations` (users with role, clients, projects, tags + pivot, invoices + lines) |
| Seeders / factories | `DatabaseSeeder`: 7 users, clients, projects, tags and about 65 invoices with realistic data |
| Server-side validation | `rules()` in every form component, Spanish messages in `lang/es/validation.php` |
| Authentication | Livewire starter kit (Fortify): register, login, reset password |
| ≥ 2 roles | `App\Enums\Rol` (`freelancer`, `admin`), `EnsureUserIsAdmin` middleware, `UserPolicy` and data policies (`ClientePolicy`, `ProyectoPolicy`, `FacturaPolicy`, `EtiquetaPolicy`) |
| Search + multi-filter | Projects (5 combinable filters), clients, invoices (tabs + search), users |
| Extras | Dark theme, i18n ES/EN (`lang/es.json`), Mailable `FacturaEnviada`, `facturas:recordatorios` command scheduled at 09:00, logo upload, CSV export, drag and drop |

### Architecture

- **Models** (`app/Models`): relations and scopes only.
- **Services** (`app/Services`): business logic, such as `CalculadoraPresupuesto` (VAT and income tax withholding), `FacturaService` (create, issue, pay), `NumeradorFacturas` (sequential numbering) and `ClienteService`.
- **Value object** `Presupuesto` (`app/ValueObjects`): amounts are always handled in **cents** (integers) so there are no rounding errors.
- **Enums** (`app/Enums`): statuses and types with a translated `label()` and a `color()`.
- **Policies**: each user can only see and edit their own data.

---

## Installation

Requirements: PHP 8.3+, Composer, Node 20+, and SQLite (or [Laravel Herd](https://herd.laravel.com)).

```bash
git clone <repo-url> facturaflow
cd facturaflow
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
# no storage:link needed: the "public" disk writes to public/storage
npm run build        # or: npm run dev while developing
```

With Herd the app opens at `http://facturaflow.test`. Without Herd, run `composer run dev`, which starts the server, Vite and the queue together, then open `http://localhost:8000`.

Recommended `.env` values:

```
APP_NAME=FacturaFlow
APP_URL=http://facturaflow.test
APP_LOCALE=es
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=es_ES
MAIL_MAILER=log
```

With `MAIL_MAILER=log`, emails are written to `storage/logs/laravel.log`.

### Tests and code quality

```bash
php artisan test                                   # 109 Pest tests
vendor/bin/pint                                    # code style
php vendor/bin/phpstan analyse --memory-limit=1G   # static analysis (Larastan)
```

### Scheduled tasks

`routes/console.php` runs `facturas:recordatorios` every day at 09:00. It emails a reminder for every overdue invoice. To try it by hand:

```bash
php artisan facturas:recordatorios
php artisan schedule:list
```

---

## Test users

All passwords are **`password`**.

| Email | Role | Notes |
|---|---|---|
| `admin@facturaflow.test` | Administrator | Sees the *Administration* section (summary + users) |
| `demo@facturaflow.test` | Freelancer | Laura Martín, the account with the most data |
| `javier@facturaflow.test` | Freelancer | |
| `lucia@facturaflow.test` | Freelancer | |
| `marcos@facturaflow.test` | Freelancer | |
| `sofia@facturaflow.test` | Freelancer | |
| `bloqueado@facturaflow.test` | Freelancer | Blocked account: login is rejected |

---

## Use of AI

This project was developed with help from AI assistants (Claude and Cursor), which I used to design the architecture, generate code and review errors. I have reviewed, tested and understood all of the code. The Pest tests (`tests/`) cover the main features.
