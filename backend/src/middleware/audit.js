import { query } from '../config/db.js';

export function audit(action, entityType) {
  return async (req, _res, next) => {
    try {
      await query(
        `INSERT INTO audit_logs (user_id, action, entity_type, ip_address, metadata)
         VALUES ($1, $2, $3, $4, $5)`,
        [req.user?.id || null, action, entityType, req.ip, JSON.stringify({ path: req.originalUrl, method: req.method })]
      );
    } catch (error) {
      console.error('audit failed', error.message);
    }
    next();
  };
}
