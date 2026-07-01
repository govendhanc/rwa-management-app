import { useEffect, useState } from 'react';
import { Button, Card, CardContent, MenuItem, Stack, TextField } from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import PageHeader from '../components/PageHeader.jsx';
import DataTable from '../components/DataTable.jsx';
import { api } from '../services/api.js';

const categories = ['Security Salary', 'Housekeeping', 'Electricity', 'Water', 'Park Maintenance', 'Office Expense', 'Festival Expense', 'Repair', 'Construction', 'Legal', 'Miscellaneous'];

export default function Expenses() {
  const [rows, setRows] = useState([]);
  const [form, setForm] = useState({ expense_date: '', category: 'Security Salary', vendor_name: '', amount: '', payment_mode: 'UPI', invoice_number: '', description: '' });
  const load = () => api.get('/finance/expenses').then((res) => setRows(res.data));
  useEffect(() => { load(); }, []);

  async function save() {
    await api.post('/finance/expenses', form);
    setForm({ ...form, amount: '', vendor_name: '', invoice_number: '', description: '' });
    load();
  }

  return (
    <>
      <PageHeader title="Expenses" subtitle="Capture bills, categories, payment modes, and vendor history." />
      <Card sx={{ mb: 2 }}><CardContent>
        <Stack direction={{ xs: 'column', lg: 'row' }} spacing={1.5}>
          <TextField label="Date" type="date" InputLabelProps={{ shrink: true }} value={form.expense_date} onChange={(e) => setForm({ ...form, expense_date: e.target.value })} />
          <TextField label="Category" select value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} sx={{ minWidth: 190 }}>{categories.map((c) => <MenuItem key={c} value={c}>{c}</MenuItem>)}</TextField>
          <TextField label="Vendor" value={form.vendor_name} onChange={(e) => setForm({ ...form, vendor_name: e.target.value })} />
          <TextField label="Amount" type="number" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} />
          <Button variant="contained" startIcon={<AddIcon />} onClick={save}>Add</Button>
        </Stack>
      </CardContent></Card>
      <DataTable rows={rows} columns={[
        { key: 'expense_date', label: 'Date' },
        { key: 'category', label: 'Category' },
        { key: 'vendor_name', label: 'Vendor' },
        { key: 'amount', label: 'Amount', render: (r) => `₹${Number(r.amount).toLocaleString('en-IN')}` },
        { key: 'payment_mode', label: 'Mode' },
        { key: 'invoice_number', label: 'Invoice' }
      ]} />
    </>
  );
}
