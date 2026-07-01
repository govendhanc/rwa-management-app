import mysql from 'mysql2/promise';
import dotenv from 'dotenv';

dotenv.config();

const databaseUrl = new URL(process.env.DATABASE_URL || 'mysql://rwa_user:rwa_password@localhost:3306/rwa_db');

export const pool = mysql.createPool({
  host: databaseUrl.hostname,
  port: Number(databaseUrl.port || 3306),
  user: decodeURIComponent(databaseUrl.username),
  password: decodeURIComponent(databaseUrl.password),
  database: databaseUrl.pathname.replace('/', ''),
  waitForConnections: true,
  connectionLimit: 20,
  maxIdle: 10,
  idleTimeout: 30000,
  namedPlaceholders: false
});

function toMySqlPlaceholders(text, params) {
  const values = [];
  const sql = text.replace(/\$(\d+)/g, (_match, index) => {
    values.push(params[Number(index) - 1]);
    return '?';
  });
  return { sql, values };
}

export async function query(text, params = []) {
  const start = Date.now();
  const { sql, values } = toMySqlPlaceholders(text, params);
  const [rows] = await pool.query(sql, values);
  const rowCount = Array.isArray(rows) ? rows.length : rows.affectedRows || 0;
  if (process.env.NODE_ENV !== 'production') {
    console.log('db query', { rows: rowCount, ms: Date.now() - start });
  }
  return { rows: Array.isArray(rows) ? rows : [], rowCount, result: rows };
}
