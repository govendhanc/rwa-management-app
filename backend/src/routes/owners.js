import express from 'express';
import { randomUUID } from 'crypto';
import { pool, query } from '../config/db.js';
import { authorize } from '../middleware/auth.js';
import { audit } from '../middleware/audit.js';

const router = express.Router();

function normalizePlots(payload) {
  const plots = Array.isArray(payload.plots) && payload.plots.length
    ? payload.plots
    : [{
        plot_number: payload.plot_number,
        block: payload.block,
        street: payload.street,
        plot_size: payload.plot_size,
        water_connection: payload.water_connection,
        eb_connection: payload.eb_connection
      }];

  return plots
    .map((plot) => ({
      id: plot.id,
      plot_number: String(plot.plot_number || '').trim().toUpperCase(),
      block: plot.block || '',
      street: plot.street || '',
      plot_size: plot.plot_size || '',
      water_connection: Boolean(plot.water_connection),
      eb_connection: Boolean(plot.eb_connection)
    }))
    .filter((plot) => plot.plot_number);
}

function assertUniquePlotNumbers(plots) {
  const seen = new Set();
  const duplicates = [];
  plots.forEach((plot) => {
    if (seen.has(plot.plot_number)) duplicates.push(plot.plot_number);
    seen.add(plot.plot_number);
  });
  if (duplicates.length) {
    const error = new Error(`Duplicate plot number in request: ${duplicates.join(', ')}`);
    error.status = 400;
    throw error;
  }
}

async function assertPlotsAvailable(plots, excludedPlotIds = []) {
  if (!plots.length) {
    const error = new Error('At least one plot is required');
    error.status = 400;
    throw error;
  }
  assertUniquePlotNumbers(plots);
  const placeholders = plots.map((_, index) => `$${index + 1}`).join(',');
  const params = plots.map((plot) => plot.plot_number);
  const { rows } = await query(
    `SELECT id, plot_number FROM plots WHERE plot_number IN (${placeholders})`,
    params
  );
  const excluded = new Set(excludedPlotIds.filter(Boolean));
  const conflicts = rows.filter((row) => !excluded.has(row.id));
  if (conflicts.length) {
    const error = new Error(`Plot number already exists: ${conflicts.map((row) => row.plot_number).join(', ')}`);
    error.status = 409;
    throw error;
  }
}

router.get('/', async (req, res) => {
  const search = `%${req.query.search || ''}%`;
  const { rows } = await query(
    `SELECT p.id AS plot_id, p.plot_number, p.block, p.street, p.plot_size,
            p.water_connection, p.eb_connection, p.document_url,
            o.id AS owner_id, o.owner_name, o.father_or_husband_name, o.mobile_number,
            o.email, o.address, o.occupancy_status, o.tenant_name, o.tenant_mobile, o.remarks,
            (SELECT COUNT(*) FROM plots op WHERE op.owner_id = o.id) AS owner_plot_count
     FROM plots p JOIN owners o ON o.id = p.owner_id
     WHERE p.plot_number LIKE $1 OR o.owner_name LIKE $1 OR o.mobile_number LIKE $1
     ORDER BY p.plot_number LIMIT 500`,
    [search]
  );
  res.json(rows);
});

router.get('/:ownerId/plots', async (req, res) => {
  const { rows } = await query(
    `SELECT p.*, o.owner_name, o.mobile_number
     FROM plots p JOIN owners o ON o.id = p.owner_id
     WHERE p.owner_id = $1
     ORDER BY p.plot_number`,
    [req.params.ownerId]
  );
  res.json(rows);
});

router.post('/', authorize('Admin'), audit('Create Owner', 'owners'), async (req, res) => {
  const payload = req.body;
  const plots = normalizePlots(payload);
  await assertPlotsAvailable(plots);

  const client = await pool.getConnection();
  const ownerId = randomUUID();
  try {
    await client.beginTransaction();
    await client.query(
      `INSERT INTO owners (id, owner_name, father_or_husband_name, mobile_number, email, address,
        occupancy_status, tenant_name, tenant_mobile, remarks)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [ownerId, payload.owner_name, payload.father_or_husband_name, payload.mobile_number, payload.email,
        payload.address, payload.occupancy_status || 'Owner Occupied', payload.tenant_name, payload.tenant_mobile, payload.remarks]
    );
    for (const plot of plots) {
      await client.query(
        `INSERT INTO plots (id, owner_id, plot_number, block, street, plot_size, water_connection, eb_connection)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
        [randomUUID(), ownerId, plot.plot_number, plot.block, plot.street, plot.plot_size, plot.water_connection, plot.eb_connection]
      );
    }
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

router.post('/:ownerId/plots', authorize('Admin'), audit('Add Owner Plot', 'plots'), async (req, res) => {
  const plots = normalizePlots(req.body);
  await assertPlotsAvailable(plots);

  const owner = await query('SELECT id FROM owners WHERE id=$1', [req.params.ownerId]);
  if (!owner.rows[0]) return res.status(404).json({ message: 'Owner not found' });

  const client = await pool.getConnection();
  try {
    await client.beginTransaction();
    for (const plot of plots) {
      await client.query(
        `INSERT INTO plots (id, owner_id, plot_number, block, street, plot_size, water_connection, eb_connection)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
        [randomUUID(), req.params.ownerId, plot.plot_number, plot.block, plot.street, plot.plot_size, plot.water_connection, plot.eb_connection]
      );
    }
    await client.commit();
    res.status(201).json({ added: plots.length });
  } catch (error) {
    await client.rollback();
    throw error;
  } finally {
    client.release();
  }
});

router.put('/:ownerId', authorize('Admin'), audit('Update Owner', 'owners'), async (req, res) => {
  const payload = req.body;
  await query(
    `UPDATE owners SET owner_name=$1, father_or_husband_name=$2, mobile_number=$3, email=$4,
      address=$5, occupancy_status=$6, tenant_name=$7, tenant_mobile=$8, remarks=$9
     WHERE id=$10`,
    [payload.owner_name, payload.father_or_husband_name, payload.mobile_number, payload.email,
      payload.address, payload.occupancy_status || 'Owner Occupied', payload.tenant_name, payload.tenant_mobile,
      payload.remarks, req.params.ownerId]
  );
  const { rows } = await query('SELECT * FROM owners WHERE id=$1', [req.params.ownerId]);
  res.json(rows[0]);
});

router.put('/plots/:plotId', authorize('Admin'), audit('Update Plot', 'plots'), async (req, res) => {
  const payload = req.body;
  const plots = normalizePlots({ ...payload, plots: undefined });
  await assertPlotsAvailable(plots, [req.params.plotId]);
  const plot = plots[0];

  await query(
    `UPDATE plots SET plot_number=$1, block=$2, street=$3, plot_size=$4,
      water_connection=$5, eb_connection=$6
     WHERE id=$7`,
    [plot.plot_number, plot.block, plot.street, plot.plot_size, plot.water_connection, plot.eb_connection, req.params.plotId]
  );
  const { rows } = await query('SELECT * FROM plots WHERE id=$1', [req.params.plotId]);
  res.json(rows[0]);
});

router.delete('/plots/:plotId', authorize('Admin'), audit('Delete Plot', 'plots'), async (req, res) => {
  try {
    const plotResult = await query('SELECT owner_id FROM plots WHERE id=$1', [req.params.plotId]);
    const ownerId = plotResult.rows[0]?.owner_id;
    await query('DELETE FROM plots WHERE id=$1', [req.params.plotId]);
    if (ownerId) {
      const remaining = await query('SELECT COUNT(*) AS plot_count FROM plots WHERE owner_id=$1', [ownerId]);
      if (Number(remaining.rows[0]?.plot_count || 0) === 0) {
        await query('DELETE FROM owners WHERE id=$1', [ownerId]);
      }
    }
    res.status(204).send();
  } catch (error) {
    if (error.code === 'ER_ROW_IS_REFERENCED_2') {
      return res.status(409).json({ message: 'This plot has maintenance records and cannot be deleted. Mark it inactive in a future enhancement or clear linked records first.' });
    }
    throw error;
  }
});

router.delete('/:ownerId', authorize('Admin'), audit('Delete Owner', 'owners'), async (req, res) => {
  const { rows } = await query('SELECT COUNT(*) AS plot_count FROM plots WHERE owner_id=$1', [req.params.ownerId]);
  if (Number(rows[0]?.plot_count || 0) > 0) {
    return res.status(409).json({ message: 'Remove or transfer all plots before deleting this owner.' });
  }
  await query('DELETE FROM owners WHERE id=$1', [req.params.ownerId]);
  res.status(204).send();
});

export default router;
