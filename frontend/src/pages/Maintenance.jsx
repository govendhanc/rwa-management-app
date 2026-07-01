import { useEffect, useState } from 'react';
import { Button, Card, CardContent, Chip, MenuItem, Stack, TextField } from '@mui/material';
import PrintIcon from '@mui/icons-material/Print';
import WhatsAppIcon from '@mui/icons-material/WhatsApp';
import EmailIcon from '@mui/icons-material/Email';
import PageHeader from '../components/PageHeader.jsx';
import DataTable from '../components/DataTable.jsx';
import { api } from '../services/api.js';

const money = (n) => `₹${Number(n || 0).toLocaleString('en-IN')}`;

export default function Maintenance() {
  const now = new Date();
  const [filters, setFilters] = useState({ month: now.getMonth() + 1, year: now.getFullYear(), status: '' });
  const [rows, setRows] = useState([]);

  function load() {
    api.get('/maintenance', { params: filters }).then((res) => setRows(res.data));
  }

  function generateMonth() {
    api.post('/maintenance/generate-month', { month: Number(filters.month), year: Number(filters.year) }).then(load);
  }

  useEffect(() => { load(); }, []);

  return (
    <>
      <PageHeader title="Maintenance Collection" subtitle="Track paid, partially paid, unpaid, dues, receipts, and carry-forward balances." actionLabel="Generate Month" actionIcon={<PrintIcon />} onAction={generateMonth} />
      <Card sx={{ mb: 2 }}><CardContent>
        <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
          <TextField label="Month" type="number" value={filters.month} onChange={(e) => setFilters({ ...filters, month: e.target.value })} />
          <TextField label="Year" type="number" value={filters.year} onChange={(e) => setFilters({ ...filters, year: e.target.value })} />
          <TextField label="Status" select value={filters.status} onChange={(e) => setFilters({ ...filters, status: e.target.value })} sx={{ minWidth: 180 }}>
            <MenuItem value="">All</MenuItem><MenuItem value="Paid">Paid</MenuItem><MenuItem value="Partially Paid">Partially Paid</MenuItem><MenuItem value="Unpaid">Unpaid</MenuItem>
          </TextField>
          <Button variant="outlined" onClick={load}>Apply</Button>
        </Stack>
      </CardContent></Card>
      <DataTable
        rows={rows}
        columns={[
          { key: 'plot_number', label: 'Plot' },
          { key: 'owner_name', label: 'Owner' },
          { key: 'month', label: 'Month' },
          { key: 'year', label: 'Year' },
          { key: 'total_amount', label: 'Total', render: (r) => money(r.total_amount) },
          { key: 'paid_amount', label: 'Paid', render: (r) => money(r.paid_amount) },
          { key: 'balance', label: 'Balance', render: (r) => money(r.balance) },
          { key: 'status', label: 'Status', render: (r) => <Chip size="small" label={r.status} color={r.status === 'Paid' ? 'success' : r.status === 'Partially Paid' ? 'warning' : 'error'} /> },
          { key: 'receipt_number', label: 'Receipt' },
          { key: 'actions', label: 'Actions', render: () => <Stack direction="row" spacing={0.5}><PrintIcon fontSize="small" /><WhatsAppIcon fontSize="small" /><EmailIcon fontSize="small" /></Stack> }
        ]}
      />
    </>
  );
}
