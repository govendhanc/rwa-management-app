import express from 'express';
import bcrypt from 'bcryptjs';
import { randomUUID } from 'crypto';
import { query } from '../config/db.js';
import { authorize } from '../middleware/auth.js';
import { audit } from '../middleware/audit.js';

const router = express.Router();

router.get('/users', authorize('Admin'), async (_req, res) => {
  const { rows } = await query(
    `SELECT u.id, u.name, u.email, u.mobile, u.is_active, u.last_login_at, r.name AS role
     FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.created_at DESC`
  );
  res.json(rows);
});

router.post('/users', authorize('Admin'), audit('Create User', 'users'), async (req, res) => {
  const p = req.body;
  const hash = await bcrypt.hash(p.password, 12);
  const id = randomUUID();
  await query(
    `INSERT INTO users (id, role_id, name, email, password_hash, mobile)
     SELECT $1, id, $2, $3, $4, $5 FROM roles WHERE name=$6`,
    [id, p.name, p.email, hash, p.mobile, p.role]
  );
  const { rows } = await query('SELECT id, name, email FROM users WHERE id=$1', [id]);
  res.status(201).json(rows[0]);
});

router.patch('/users/:id/status', authorize('Admin'), audit('Update User Status', 'users'), async (req, res) => {
  await query('UPDATE users SET is_active=$1 WHERE id=$2', [req.body.is_active, req.params.id]);
  const { rows } = await query('SELECT id, is_active FROM users WHERE id=$1', [req.params.id]);
  res.json(rows[0]);
});

router.get('/audit-logs', authorize('Admin'), async (_req, res) => {
  const { rows } = await query(
    `SELECT a.*, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id
     ORDER BY a.created_at DESC LIMIT 1000`
  );
  res.json(rows);
});

export default router;
