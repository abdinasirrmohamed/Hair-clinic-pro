# Hair Clinic Pro

The application is split into two deployable projects:

- `backend/` — Laravel 12 JSON API, authentication, authorization, business rules, database access, reporting, payments, inventory and pharmacy transactions.
- `frontend/` — React 19 single-page application containing the complete user interface.

## Local development

### Backend

```powershell
cd backend
copy .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
```

Configure the MySQL and WaafiPay values in `backend/.env`. Secrets must never be added to source files.

### Frontend

```powershell
cd frontend
npm install
npm run dev
```

Vite serves the SPA on `http://127.0.0.1:5174` and proxies `/api` and `/storage` to Laravel.

## Production

Point the API virtual host at `backend/public`. Build the frontend with `npm run build` and serve `frontend/dist` with SPA fallback enabled. Set `VITE_API_URL` when the API is hosted on a different origin.
