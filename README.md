# Studio Booking

[Leggi in italiano](README.it.md)

A booking system for a studio with services and operators (hair salon, physiotherapist, gym with trainers). Customers book a free time online, staff see their agenda, and the admin manages the catalogue, the working hours and the holidays.

The interesting part is not the CRUD but the guarantees behind it: **no double bookings under concurrent requests**, **correct times across daylight saving changes**, and **emails that are sent exactly when they should be**.

Built with Laravel 13, Livewire 4, Flux UI and Tailwind CSS. PHP 8.4, MySQL or SQLite.

## What each role can do

| Role | Features |
|---|---|
| **Customer** | Picks a service, an operator and a day, sees only the times that are really free, books, gets a confirmation email and a reminder 24 hours before, cancels for free up to 24 hours before. |
| **Staff** | Sees their own daily agenda and marks appointments as completed or no-show once they have started. |
| **Admin** | Manages services (price, duration, active), operators (services offered, weekly hours with lunch breaks, holidays and closures), sees every agenda and books on behalf of customers. Creating an operator with an email creates a staff account and sends a link to set the password. |

## Architecture

```mermaid
flowchart LR
    UI["Livewire pages<br/>customer · staff · admin"] -->|authorize| P[Policies]
    UI --> BS[BookingService]
    BS -->|"SELECT … FOR UPDATE<br/>on the operator row"| DB[(MySQL)]
    BS --> AS[AvailabilityService]
    AS -->|"working hours, time off,<br/>open bookings"| DB
    BS -->|after commit| Q[[Queue]]
    S[Scheduler<br/>every 15 min] -->|claim due reminders| DB
    S --> Q
    Q --> M[Mail]
```

- **`AvailabilityService`** computes the free start times of a service with an operator on a day. It is pure scheduling logic on top of a few queries, which makes it the most thoroughly tested class.
- **`BookingService`** owns every state change: creating, cancelling, completing, no-show. It enforces the business invariants whoever calls it; policies decide *who* may call it.
- **Livewire components** are thin: they validate input, authorize with the policies and turn domain exceptions into messages.

## Key decisions

### Preventing double bookings

Two customers clicking the same time at the same moment must not both succeed. `BookingService::book()` opens a transaction, locks the operator row with `SELECT … FOR UPDATE`, re-checks availability and only then inserts. Concurrent bookings for the same operator are serialized; bookings for different operators never wait for each other.

Alternatives considered:

- **PostgreSQL exclusion constraint** (`EXCLUDE USING gist` on a time range): the most elegant guarantee, enforced by the database itself, but it ties the project to PostgreSQL.
- **A slot table with a unique index**: simple, but rigid when services have different durations (a 90-minute service spans several slots).

The lock is proven by a real race, not a mock: [`DoubleBookingTest`](tests/Feature/Concurrency/DoubleBookingTest.php) starts two PHP processes that book the same time against MySQL. Each process deliberately slows down the availability check, so both check before either inserts: without the lock the test produces two bookings, with it exactly one. A detail that matters on MySQL: the lock is the first read of the transaction, so the InnoDB snapshot is taken *after* the lock is acquired and the second request sees the first booking.

### Time zones and daylight saving time

Every timestamp is stored in UTC. Working hours are wall-clock times in the business timezone (`APP_BUSINESS_TIMEZONE`, default `Europe/Rome`) and are converted to UTC day by day. A 09:00 shift is 08:00 UTC in winter and 07:00 UTC in summer, and a test pins the week the clocks change.

### Booking lifecycle

`pending → confirmed → completed`, or `cancelled` / `no_show`. The allowed transitions live in the `BookingStatus` enum (`canTransitionTo()`). Every change is written to `booking_status_histories` with who made it. Status changes lock the booking row, so two concurrent changes cannot both pass the check. Completed and no-show are only accepted once the appointment has started.

### Notifications that are sent once

- Confirmation and cancellation emails are queued **after the transaction commits**: a rolled-back booking never sends an email.
- The reminder time (`reminder_due_at`) is computed when the booking is made; bookings made less than a day ahead get none.
- The scheduler claims each reminder with a conditional `UPDATE … WHERE reminder_sent_at IS NULL` before queueing it, so overlapping runs never remind a customer twice. A reminder still waiting in the queue is dropped if the booking is cancelled in the meantime.

### Smaller choices

- **Price snapshot**: the price is copied onto the booking, so changing the catalogue never rewrites history.
- **Deactivate, never delete**: services and operators with bookings are deactivated; foreign keys forbid deleting them.
- **Roles are not mass-assignable**: a registration request cannot make itself admin, and a test proves it.
- **Money as integer cents**: prices typed as `19,99` are converted with string arithmetic, never through floats.

## Getting started

Requirements: PHP 8.4, Composer, Node 22.

```bash
composer setup            # install, create .env, migrate, build assets (SQLite by default)
php artisan db:seed       # demo studio: services, operators, hours, bookings
composer run dev          # server, queue worker, logs and Vite
```

Open http://localhost:8000. Every demo account uses the password `password`:

| Account | Role |
|---|---|
| `cliente@example.com` | Customer |
| `giulia@example.com`, `marco@example.com` | Staff |
| `admin@example.com` | Admin |

Reminders need the scheduler: `php artisan schedule:work`.

### With Docker (Laravel Sail: app, MySQL 8.4, Mailpit)

After `composer install` and `cp .env.example .env`, set these values in `.env` and start the containers:

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_USERNAME=sail
DB_PASSWORD=password
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

If MySQL already runs on your machine, port 3306 is taken: also add `FORWARD_DB_PORT=3307`.

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm run dev
./vendor/bin/sail artisan queue:work      # in another terminal: sends the emails
./vendor/bin/sail artisan schedule:work   # in another terminal: sends the reminders
```

The app runs on http://localhost and Mailpit shows every email on http://localhost:8025.

## Tests

```bash
composer test             # Pint, PHPStan (level 7) and the PHPUnit suite
```

The suite runs on in-memory SQLite. The double-booking race needs real row locks, so it skips itself on SQLite and runs in CI against MySQL. To run it locally:

```bash
# with Sail
./vendor/bin/sail exec -e DB_CONNECTION=mysql -e DB_DATABASE=testing laravel.test php artisan test --group=concurrency

# with a local MySQL and an empty database
DB_CONNECTION=mysql DB_DATABASE=studio_booking_test DB_USERNAME=root DB_PASSWORD=secret php artisan test --group=concurrency
```

GitHub Actions runs two jobs on every push: lint, static analysis and tests on SQLite, and the full suite on MySQL 8.4, race included.

## Project structure

```
app/
├── Enums/            BookingStatus (lifecycle), UserRole, Weekday
├── Services/         AvailabilityService, BookingService
├── Livewire/         Bookings/ (customer), Staff/Agenda, Admin/
├── Policies/         BookingPolicy, OperatorPolicy, ServicePolicy
├── Notifications/    BookingConfirmed, BookingCancelled, BookingReminder
├── Console/Commands/ SendBookingReminders
└── Exceptions/       domain errors shown to the user
tests/
├── Feature/          services, pages, policies, commands, notifications
└── Concurrency/      the script the double-booking race runs in two processes
```

## Known limitations and next steps

- The `pending` status is reserved for online payments or manual approval; today bookings are confirmed at once.
- The admin picks the customer from a plain list; a searchable field is needed beyond a few hundred customers.
- Working hours that start inside the hour skipped by the spring clock change (02:00–03:00) are not supported; a studio never has them.
- Ideas: waiting list, statistics for the admin, CSV export, iCal links for the staff agenda.
