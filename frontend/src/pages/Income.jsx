import { useEffect, useState } from 'react';
import { Alert, Button, Card, CardContent, IconButton, MenuItem, Snackbar, Stack, TextField, Tooltip } from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import CloseIcon from '@mui/icons-material/Close';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import PageHeader from '../components/PageHeader.jsx';
import DataTable from '../components/DataTable.jsx';
import { api } from '../services/api.js';

const sources = ['Corpus Fund', 'Donation', 'Interest', 'Penalty', 'Membership Fee', 'Other Income'];
const emptyForm = { income_date: '', source: 'Donation', amount: '', remarks: '' };
const toDateInput = (value) => (value ? String(value).slice(0, 10) : '');

export default function Income() {
  const [rows, setRows] = useState([]);
  const [form, setForm] = useState(emptyForm);
  const [editingId, setEditingId] = useState('');
  const [message, setMessage] = useState('');
  const [severity, setSeverity] = useState('success');

  const load = () => api.get('/finance/income').then((res) => setRows(res.data));
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
      income_date: toDateInput(row.income_date),
      source: row.source || 'Donation',
      amount: row.amount || '',
      remarks: row.remarks || ''
    });
  }

  async function save() {
    try {
      if (editingId) {
        await api.put(`/finance/income/${editingId}`, form);
        notify('Income updated');
      } else {
        await api.post('/finance/income', form);
        notify('Income added');
      }
      reset();
      load();
    } catch (error) {
      notify(error.response?.data?.message || 'Unable to save income', 'error');
    }
  }

  async function remove(row) {
    if (!window.confirm(`Delete income ${row.source} - ₹${row.amount}?`)) return;
    try {
      await api.delete(`/finance/income/${row.id}`);
      notify('Income deleted');
      if (editingId === row.id) reset();
      load();
    } catch (error) {
      notify(error.response?.data?.message || 'Unable to delete income', 'error');
    }
  }

  return (
    <>
      <PageHeader title="Income" subtitle="Add, edit, delete, and track corpus, donation, interest, penalty, membership fee, and other income." />
      <Card sx={{ mb: 2 }}><CardContent>
        <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
          <TextField label="Date" type="date" InputLabelProps={{ shrink: true }} value={form.income_date} onChange={(e) => setForm({ ...form, income_date: e.target.value })} />
          <TextField label="Source" select value={form.source} onChange={(e) => setForm({ ...form, source: e.target.value })}>{sources.map((s) => <MenuItem key={s} value={s}>{s}</MenuItem>)}</TextField>
          <TextField label="Amount" type="number" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} />
          <TextField label="Remarks" value={form.remarks} onChange={(e) => setForm({ ...form, remarks: e.target.value })} />
          <Button variant="contained" startIcon={<AddIcon />} onClick={save}>{editingId ? 'Update' : 'Add'}</Button>
          {editingId && <Button variant="outlined" startIcon={<CloseIcon />} onClick={reset}>Cancel</Button>}
        </Stack>
      </CardContent></Card>
      <DataTable rows={rows} columns={[
        { key: 'income_date', label: 'Date', render: (r) => toDateInput(r.income_date) },
        { key: 'source', label: 'Source' },
        { key: 'amount', label: 'Amount', render: (r) => `₹${Number(r.amount).toLocaleString('en-IN')}` },
        { key: 'remarks', label: 'Remarks' },
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
