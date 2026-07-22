# Hair Clinic Pro

Hair Clinic Pro is a full-stack clinic management system built with a Laravel 12 JSON API and a React 19 single-page application. It covers reception, doctors, patients, appointments, payments, prescriptions, pharmacy sales, inventory, laboratory work, reporting, and administration.

## Technology stack

| Layer | Technology |
| --- | --- |
| Frontend | React 19, Vite 7, Tailwind CSS 4, Axios, Lucide Icons |
| Backend | PHP 8.2+, Laravel 12, Sanctum |
| Database | MySQL/MariaDB; SQLite is used by automated tests |
| Payments | WaafiPay API for EVC Plus, Zaad, and Sahal |
| Notifications | Laravel Mail and environment-configured HTTP SMS provider |

## Main features

- Role-based access for Administrator, Receptionist, Doctor, Inventory Officer, Pharmacy User, and Lab User.
- Patient registration with DOB-derived, read-only age.
- Doctor profiles linked to Doctor user accounts without entering the doctor's name twice.
- Admin-managed doctor schedules with working days, Morning/Afternoon shifts, slot durations, and blocked dates.
- Schedule-aware appointment dates and available time slots.
- Patient dropdown on appointment booking with phone, gender, age, and address autofill.
- Full Paid and Partial Paid appointment payments with backend-calculated balances.
- Cash, Card, bank transfer, EVC Plus, Zaad, and Sahal payment methods.
- Persistent payment receipts and WaafiPay gateway audit history.
- Idempotent appointment and payment notification logs with email/SMS delivery support.
- Multi-medicine prescriptions with quantity, frequency, instructions, and dispensing progress.
- Pharmacy POS integration that loads a selected patient's pending prescription.
- Transactional pharmacy sales, stock deductions, returns, and detailed multi-medicine receipts.
- Inventory, laboratory, treatment, follow-up, reporting, audit-log, and notification modules.
- Accessible password Eye/Eye-Off controls.
- Responsive Purple/Violet interface with light and dark themes.

## Repository structure

```text
Hair-clinic-pro/
├── backend/      Laravel API, migrations, models, services, and tests
├── frontend/     React SPA
├── database.sql  Legacy/reference SQL schema
└── setup.sh      Linux setup helper
```

## Local installation

### Backend

```powershell
cd backend
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
```

Configure the database connection in `backend/.env` before running migrations.

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hair_clinic_system
DB_USERNAME=root
DB_PASSWORD=
```

### Frontend

Open another terminal:

```powershell
cd frontend
npm install
npm run dev
```

The frontend runs at <http://127.0.0.1:5174> and proxies `/api` and `/storage` requests to <http://127.0.0.1:8000>.

## WaafiPay configuration

Add credentials issued by WaafiPay to `backend/.env`:

```env
WAAFI_ENDPOINT=https://api.waafipay.net/asm
WAAFI_MERCHANT_UID=your-merchant-uid
WAAFI_API_USER_ID=your-api-user-id
WAAFI_API_KEY=your-api-key
```

Never commit real gateway credentials. Mobile payment attempts are recorded in `payment_gateway_logs` with masked account numbers.

## Email and SMS notifications

Email uses Laravel's standard `MAIL_*` settings. SMS delivery uses a provider-neutral HTTP adapter:

```env
SMS_DRIVER=http
SMS_ENDPOINT=https://your-provider.example/messages
SMS_API_TOKEN=your-provider-token
SMS_SENDER_ID=HairClinic
```

The configured endpoint must accept a Bearer token and a JSON payload containing `to`, `message`, and `sender_id`. If no provider is configured, the appointment still succeeds and the notification is recorded as `Skipped` in `notification_logs`. Delivery failures are logged without rolling back a successfully saved appointment.

Do not commit SMS or email credentials.

## Database migrations

Apply pending schema changes after pulling updates:

```powershell
cd backend
php artisan migrate
```

The clinical workflow migration adds:

- doctor schedule shifts and day/shift uniqueness;
- total, paid, and remaining appointment payment values;
- prescription frequency and dispensing progress;
- pharmacy paid/remaining values and prescription item references;
- persistent notification delivery logs.

The migration is additive and preserves existing records.

## Testing and validation

Run backend tests:

```powershell
cd backend
php artisan test
```

Run the frontend production build:

```powershell
cd frontend
npm run build
```

The current regression suite covers authentication and permissions, doctor schedule uniqueness, receptionist schedule access, doctor-name synchronization, patient DOB/age validation, full/partial payment calculations, WaafiPay logging, SMS delivery/idempotency, multi-medicine prescriptions, patient-specific pharmacy dispensing, stock-safe sales, and receipts.

Current verified result: **21 backend tests, 73 assertions, and a successful frontend production build**.

## Production deployment

1. Set `APP_ENV=production`, `APP_DEBUG=false`, and a production `APP_URL`.
2. Configure MySQL, mail, SMS, and WaafiPay credentials through environment variables.
3. Run `composer install --no-dev --optimize-autoloader` in `backend`.
4. Run `php artisan migrate --force`, `php artisan storage:link`, and `php artisan optimize`.
5. Point the web server to `backend/public`.
6. Run `npm ci && npm run build` in `frontend` and serve `frontend/dist` with SPA fallback enabled.
7. Use HTTPS and ensure API/storage proxy rules match the production backend URL.

## Security notes

- Authentication uses Laravel Sanctum bearer tokens.
- Permissions are enforced by backend middleware, not only by the UI.
- Passwords and provider credentials are never returned by APIs.
- Payment account numbers are masked in gateway logs.
- Multi-step prescriptions, payments, appointments, stock changes, and pharmacy sales use database transactions where applicable.

## License

This repository is intended for the Hair Clinic Pro project. Add the organization's chosen license before public distribution.
