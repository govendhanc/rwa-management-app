import express from 'express';
import bcrypt from 'bcryptjs';
import jwt from 'jsonwebtoken';
import { query } from '../config/db.js';
import { authenticate } from '../middleware/auth.js';

const router = express.Router();

router.post('/login', async (req, res) => {
  const { email, password } = req.body;
  const { rows } = await query(
    `SELECT u.id, u.name, u.email, u.password_hash, u.is_active, r.name AS role,
            u.password_hash = SHA2($2, 256) AS sha_password_ok
     FROM users u JOIN roles r ON r.id = u.role_id
     WHERE u.email = $1`,
    [email, password]
  );
  const user = rows[0];
  const bcryptOk = user?.password_hash?.startsWith('$2') ? await bcrypt.compare(password, user.password_hash) : false;
  const ok = user && user.is_active && (bcryptOk || user.sha_password_ok);
  if (!ok) return res.status(401).json({ message: 'Invalid login details' });

  await query('UPDATE users SET last_login_at = NOW() WHERE id = $1', [user.id]);
  await query(
    `INSERT INTO audit_logs (user_id, action, entity_type, ip_address)
     VALUES ($1, 'Login', 'users', $2)`,
    [user.id, req.ip]
  );
  const token = jwt.sign({ sub: user.id, role: user.role }, process.env.JWT_SECRET, {
    expiresIn: process.env.JWT_EXPIRES_IN || '8h'
  });
  res.json({ token, user: { id: user.id, name: user.name, email: user.email, role: user.role } });
});

router.get('/me', authenticate, (req, res) => res.json({ user: req.user }));

export default router;
