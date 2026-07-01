import express from 'express';
import cors from 'cors';
import helmet from 'helmet';
import morgan from 'morgan';
import rateLimit from 'express-rate-limit';
import dotenv from 'dotenv';
import authRoutes from './routes/auth.js';
import dashboardRoutes from './routes/dashboard.js';
import ownersRoutes from './routes/owners.js';
import maintenanceRoutes from './routes/maintenance.js';
import financeRoutes from './routes/finance.js';
import reportsRoutes from './routes/reports.js';
import adminRoutes from './routes/admin.js';
import settingsRoutes from './routes/settings.js';
import receiptsRoutes from './routes/receipts.js';
import notificationsRoutes from './routes/notifications.js';
import { authenticate } from './middleware/auth.js';
import { errorHandler, notFound } from './middleware/error.js';

dotenv.config();

const app = express();
app.use(helmet());
app.use(cors({ origin: process.env.FRONTEND_URL?.split(',') || '*', credentials: true }));
app.use(express.json({ limit: '2mb' }));
app.use(morgan('dev'));
app.use(rateLimit({ windowMs: 15 * 60 * 1000, max: 300 }));

app.get('/health', (_req, res) => res.json({ ok: true }));
app.use('/api/auth', authRoutes);
app.use('/api/dashboard', authenticate, dashboardRoutes);
app.use('/api/owners', authenticate, ownersRoutes);
app.use('/api/maintenance', authenticate, maintenanceRoutes);
app.use('/api/finance', authenticate, financeRoutes);
app.use('/api/reports', authenticate, reportsRoutes);
app.use('/api/admin', authenticate, adminRoutes);
app.use('/api/settings', authenticate, settingsRoutes);
app.use('/api/receipts', authenticate, receiptsRoutes);
app.use('/api/notifications', authenticate, notificationsRoutes);

app.use(notFound);
app.use(errorHandler);

const port = process.env.PORT || 5000;
app.listen(port, () => console.log(`RWA backend running on ${port}`));
