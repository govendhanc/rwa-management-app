import { useEffect, useState } from 'react';
import { Alert, Button, Card, CardContent, IconButton, MenuItem, Snackbar, Stack, TextField, Tooltip } from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import CloseIcon from '@mui/icons-material/Close';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import PageHeader from '../components/PageHeader.jsx';
import DataTable from '../components/DataTable.jsx';
import { api } from '../services/api.js';

const categories = ['Security Salary', 'Housekeeping', 'Electricity', 'Water', 'Park Maintenance', 'Office Expense', 'Festival Expense', 'Repair', 'Construction', 'Legal', 'Miscellaneous'];
const paymentModes = ['Cash', 'UPI', 'Bank Transfer', 'Cheque'];
const emptyForm = { expense_date: '', category: 'Security Salary', vendor_name: '', amount: '', payment_mode: 'UPI', invoice_number: '', description: '' };
const toDateInput = (value) => (value ? String(value).slice(0, 10) : '');

export default function Expenses() {
  const [rows, setRows] = useState([]);
  const [form, setForm] = useState(emptyForm);
  const [editingId, setEditingId] = useState('');
  const [message, setMessage] = useState('');
  const [severity, setSeverity] = useState('success');

  const load = () => api.get('/finance/expenses').then((res) => setRows(res.data));
  useEffect(() => { load(); }, []);

  function notify(text, type = 'success') {
    setMessage(text);
    setSeverity(type);
  }

  function reset() {
    setEditingId('');
    setForm(emptyForm);
  }

  function edit(row) {
    setEditingId(row.id);
    setForm({
      expense_date: toDateInput(row.expense_date),
      category: row.category || 'Security Salary',
      vendor_name: row.vendor_name || '',
      amount: row.amount || '',
      payment_mode: row.payment_mode || 'UPI',
      invoice_number: row.invoice_number || '',
      description: row.description || ''
    });
  }

  async function save() {
    try {
      if (editingId) {
        await api.put(`/finance/expenses/${editingId}`, form);
        notify('Expense updated');
      } else {
        await api.post('/finance/expenses', form);
        notify('Expense added');
      }
      reset();
      load();
    } catch (error) {
      notify(error.response?.data?.message || 'Unable to save expense', 'error');
    }
  }

  async function remove(row) {
    if (!window.confirm(`Delete expense ${row.category} - ₹${row.amount}?`)) return;
    try {
      await api.delete(`/finance/expenses/${row.id}`);
      notify('Expense deleted');
      if (editingId === row.id) reset();
      load();
    } catch (error) {
      notify(error.response?.data?.message || 'Unable to delete expense', 'error');
    }
  }

  return (
    <>
      <PageHeader title="Expenses" subtitle="Add, edit, delete, and track bills, categories, payment modes, and vendor history." />
      <Card sx={{ mb: 2 }}><CardContent>
        <Stack direction={{ xs: 'column', lg: 'row' }} spacing={1.5}>
          <TextField label="Date" type="date" InputLabelProps={{ shrink: true }} value={form.expense_date} onChange={(e) => setForm({ ...form, expense_date: e.target.value })} />
          <TextField label="Category" select value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} sx={{ minWidth: 190 }}>{categories.map((c) => <MenuItem key={c} value={c}>{c}</MenuItem>)}</TextField>
          <TextField label="Vendor" value={form.vendor_name} onChange={(e) => setForm({ ...form, vendor_name: e.target.value })} />
          <TextField label="Amount" type="number" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} />
          <TextField label="Mode" select value={form.payment_mode} onChange={(e) => setForm({ ...form, payment_mode: e.target.value })} sx={{ minWidth: 160 }}>{paymentModes.map((mode) => <MenuItem key={mode} value={mode}>{mode}</MenuItem>)}</TextField>
          <TextField label="Invoice" value={form.invoice_number} onChange={(e) => setForm({ ...form, invoice_number: e.target.value })} />
          <Button variant="contained" startIcon={<AddIcon />} onClick={save}>{editingId ? 'Update' : 'Add'}</Button>
          {editingId && <Button variant="outlined" startIcon={<CloseIcon />} onClick={reset}>Cancel</Button>}
        </Stack>
        <TextField sx={{ mt: 2 }} label="Description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} fullWidth multiline minRows={2} />
      </CardContent></Card>
      <DataTable rows={rows} columns={[
        { key: 'expense_date', label: 'Date', render: (r) => toDateInput(r.expense_date) },
        { key: 'category', label: 'Category' },
        { key: 'vendor_name', label: 'Vendor' },
        { key: 'amount', label: 'Amount', render: (r) => `₹${Number(r.amount).toLocaleString('en-IN')}` },
        { key: 'payment_mode', label: 'Mode' },
        { key: 'invoice_number', label: 'Invoice' },
        { key: 'actions', label: 'Actions', render: (row) => (
          <Stack direction="row" spacing={0.5}>
            <Tooltip title="Edit"><IconButton size="small" onClick={() => edit(row)}><EditIcon fontSize="small" /></IconButton></Tooltip>
            <Tooltip title="Delete"><IconButton size="small" color="error" onClick={() => remove(row)}><DeleteIcon fontSize="small" /></IconButton></Tooltip>
          </Stack>
        ) }
      ]} />
      <Snackbar open={Boolean(message)} autoHideDuration={3500} onClose={() => setMessage('')}>
        <Alert severity={severity} onClose={() => setMessage('')}>{message}</Alert>
      </Snackbar>
    </>
  );
}
