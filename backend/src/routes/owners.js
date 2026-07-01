import express from 'express';
import { randomUUID } from 'crypto';
import { pool, query } from '../config/db.js';
import { authorize } from '../middleware/auth.js';
import { audit } from '../middleware/audit.js';

const router = express.Router();

router.get('/', async (req, res) => {
  const search = `%${req.query.search || ''}%`;
  const { rows } = await query(
    `SELECT p.id AS plot_id, p.plot_number, p.block, p.street, p.plot_size,
            p.water_connection, p.eb_connection, o.*
     FROM plots p JOIN owners o ON o.plot_id = p.id
     WHERE p.plot_number LIKE $1 OR o.owner_name LIKE $1 OR o.mobile_number LIKE $1
     ORDER BY p.plot_number LIMIT 500`,
    [search]
  );
  res.json(rows);
});

router.post('/', authorize('Admin'), audit('Create Owner', 'owners'), async (req, res) => {
  const client = await pool.getConnection();
  const payload = req.body;
  try {
    await client.beginTransaction();
    const plotId = randomUUID();
    const ownerId = randomUUID();
    await client.query(
      `INSERT INTO plots (id, plot_number, block, street, plot_size, water_connection, eb_connection)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [plotId, payload.plot_number, payload.block, payload.street, payload.plot_size, payload.water_connection, payload.eb_connection]
    );
    await client.query(
      `INSERT INTO owners (id, plot_id, owner_name, father_or_husband_name, mobile_number, email, address,
        occupancy_status, tenant_name, tenant_mobile, remarks)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [ownerId, plotId, payload.owner_name, payload.father_or_husband_name, payload.mobile_number, payload.email,
        payload.address, payload.occupancy_status, payload.tenant_name, payload.tenant_mobile, payload.remarks]
    );
    await client.commit();
    const { rows } = await query('SELECT * FROM owners WHERE id = $1', [ownerId]);
    res.status(201).json(rows[0]);
  } catch (error) {
    await client.rollback();
    throw error;
  } finally {
    client.release();
  }
});

export default router;
