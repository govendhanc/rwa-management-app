# RWA Management Application

Modern full-stack starter application for a Residents Welfare Association managing roughly 300 plots, monthly maintenance, owners, expenses, income, receipts, reports, users, settings, and audit logs.

## Stack

- Frontend: React.js, Material UI, Recharts
- Backend: Node.js, Express
- Database: MySQL
- Auth: JWT with role-based access
- Reports: Excel and PDF export endpoints
- Hosting: Local machine only

## Local Setup

Install these locally:

- Node.js 20 or newer
- MySQL 8 or newer
- npm

Create the local database:

```sql
CREATE DATABASE rwa_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'rwa_user'@'localhost' IDENTIFIED BY 'rwa_password';
GRANT ALL PRIVILEGES ON rwa_db.* TO 'rwa_user'@'localhost';
FLUSH PRIVILEGES;
```

Load schema and sample data:

```bash
mysql -u rwa_user -p rwa_db < database/schema.sql
mysql -u rwa_user -p rwa_db < database/seed.sql
```

Create backend environment file:

```bash
cp backend/.env.example backend/.env
```

For local hosting, use this database URL in `backend/.env`:

```env
DATABASE_URL=mysql://rwa_user:rwa_password@localhost:3306/rwa_db
FRONTEND_URL=http://localhost:5173
```

Create frontend environment file:

```bash
cp frontend/.env.example frontend/.env
```

Install and start backend:

```bash
cd backend
npm install
npm run dev
```

Install and start frontend in a second terminal:

```bash
cd frontend
npm install
npm run dev
```

Open the application:

- Frontend: `http://localhost:5173`
- Backend health: `http://localhost:5000/health`

## Demo Login

- Admin: `admin@rwa.local` / `Admin@123`
- Treasurer: `treasurer@rwa.local` / `Treasurer@123`

## Local Operation Notes

- Change `JWT_SECRET`.
- Back up MySQL regularly with `mysqldump`.
- Configure real SMS, email, WhatsApp Business, UPI, and Razorpay credentials through settings or environment variables.
- Store uploaded bills, logos, and plot documents in a configured local uploads folder or network drive.
