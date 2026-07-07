<div align="center">
  <img src="./frontend/public/vite.svg" alt="Logo" width="80" height="80">
  <h1 align="center">Hair Clinic Pro</h1>
  <p align="center">
    A comprehensive, decoupled management system for modern hair clinics.
    <br />
    <strong>Laravel 12 API Backend • React 19 Frontend • WaafiPay Integration</strong>
  </p>
</div>

---

## 🚀 Overview

**Hair Clinic Pro** is a modern, enterprise-grade clinic management system designed to streamline day-to-day operations. The architecture has been completely migrated to a decoupled structure utilizing a robust **Laravel API** and a blazing fast **React Single Page Application (SPA)**.

### ✨ Key Features
- 🗓️ **Smart Appointments:** Schedule, track, and manage doctor and patient appointments.
- 💊 **Pharmacy POS:** Built-in Point of Sale system tailored for pharmacy use.
- 📱 **WaafiPay Integration:** Real-time mobile money integration (EVC Plus, Sahal) directly prompting the customer's phone via USSD.
- 📦 **Inventory Management:** Live tracking of medicines, auto-deductions upon sale or prescription dispensing.
- 📝 **E-Prescriptions:** Doctors can prescribe medications which instantly sync with the pharmacy.
- 📊 **Advanced Analytics:** Comprehensive reporting for daily sales, revenue, and clinical metrics.
- 🔐 **Role-Based Access Control:** Distinct interfaces and permissions for Admin, Doctor, Receptionist, and Pharmacy.
- 🌙 **Premium UI:** Fully responsive, modern, dark-mode native interface utilizing Tailwind CSS.

---

## 🛠️ Technology Stack

| Architecture Layer | Technology |
|--------------------|------------|
| **Frontend** | React 19, Vite, Tailwind CSS, Lucide Icons, Axios |
| **Backend** | Laravel 12 (JSON API), Sanctum Authentication |
| **Database** | MySQL 8.4 |
| **Payments** | WaafiPay API Sandbox/Production |

---

## 💻 Getting Started (Local Development)

The application is split into two deployable projects. Both must be running simultaneously.

### 1. Backend (Laravel API)
```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```
*Note: Configure your `DB_USERNAME`, `DB_PASSWORD`, and `WAAFI` API credentials inside the `.env` file.*

### 2. Frontend (React UI)
Open a new terminal window:
```bash
cd frontend
npm install
npm run dev
```
Navigate to `http://127.0.0.1:5174` in your browser.

### ⚡ One-Click Setup
If you are on a fresh Ubuntu environment, you can run the automated setup script from the root directory:
```bash
bash setup.sh
```

---

## 🌍 Production Deployment

1. **Backend:** Point your web server (Nginx/Apache) virtual host to the `/backend/public` directory. Ensure `APP_ENV=production`.
2. **Frontend:** Run `npm run build` inside the `/frontend` directory. Serve the resulting `/dist` folder statically, ensuring SPA fallback (catch-all route) is enabled so React Router can handle navigation. Set `VITE_API_URL` to your production backend URL.

---

<div align="center">
  <i>Engineered for Performance and Reliability.</i>
</div>
