import { useEffect, useState } from 'react';
import {
  Alert, Button, Card, CardContent, Chip, Dialog, DialogActions, DialogContent,
  DialogTitle, IconButton, MenuItem, Snackbar, Stack, TextField, Tooltip
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import PrintIcon from '@mui/icons-material/Print';
import PageHeader from '../components/PageHeader.jsx';
import DataTable from '../components/DataTable.jsx';
import { api } from '../services/api.js';

const money = (n) => `₹${Number(n || 0).toLocaleString('en-IN')}`;
const today = () => new Date().toISOString().slice(0, 10);
const paymentModes = ['Cash', 'UPI', 'Bank Transfer', 'Cheque'];

const emptyForm = {
  id: '',
  plot_id: '',
  owner_id: '',
  month: new Date().getMonth() + 1,
  year: new Date().getFullYear(),
  monthly_amount: 500,
  previous_due: 0,
  late_fee: 0,
  discount: 0,
  paid_amount: 0,
  payment_date: today(),
  payment_mode: 'UPI',
  transaction_number: '',
  receipt_number: '',
  remarks: ''
};

function toDateInput(value) {
  return value ? String(value).slice(0, 10) : '';
}

export default function Maintenance() {
  const now = new Date();
  const [filters, setFilters] = useState({ month: now.getMonth() + 1, year: now.getFullYear(), status: '' });
  const [rows, setRows] = useState([]);
  const [plots, setPlots] = useState([]);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [message, setMessage] = useState('');
  const [severity, setSeverity] = useState('success');

  function notify(text, type = 'success') {
    setMessage(text);
    setSeverity(type);
  }

  function load() {
    api.get('/maintenance', { params: filters }).then((res) => setRows(res.data));
  }

  function loadPlots() {
    api.get('/owners').then((res) => setPlots(res.data));
  }

  function generateMonth() {
    api.post('/maintenance/generate-month', { month: Number(filters.month), year: Number(filters.year) }).then(() => {
      notify('Monthly maintenance generated');
      load();
    });
  }

  useEffect(() => {
    load();
    loadPlots();
  }, []);

  function setField(field, value) {
    setForm((current) => ({ ...current, [field]: value }));
  }

  function openAdd() {
    setEditing(false);
    setForm({ ...emptyForm, month: filters.month, year: filters.year });
    setOpen(true);
  }

  function openEdit(row) {
    setEditing(true);
    setForm({
      ...emptyForm,
      ...row,
      payment_date: toDateInput(row.payment_date),
      monthly_amount: row.monthly_amount ?? row.monthlyAmount,
      previous_due: row.previous_due || 0,
      late_fee: row.late_fee || 0,
      discount: row.discount || 0,
      paid_amount: row.paid_amount || 0
    });
    setOpen(true);
  }

  function selectPlot(plotId) {
    const plot = plots.find((item) => item.plot_id === plotId);
    setForm((current) => ({
      ...current,
      plot_id: plotId,
      owner_id: plot?.owner_id || ''
    }));
  }

  async function save() {
    try {
      const payload = {
        ...form,
        month: Number(form.month),
        year: Number(form.year),
        monthly_amount: Number(form.monthly_amount || 0),
        previous_due: Number(form.previous_due || 0),
        late_fee: Number(form.late_fee || 0),
        discount: Number(form.discount || 0),
        paid_amount: Number(form.paid_amount || 0)
      };
      if (editing) {
        await api.put(`/maintenance/${form.id}`, payload);
        notify('Maintenance updated');
      } else {
        await api.post('/maintenance', payload);
        notify('Maintenance payment added');
      }
      setOpen(false);
      load();
    } catch (error) {
      notify(error.response?.data?.message || 'Unable to save maintenance', 'error');
    }
  }

  async function remove(row) {
    if (!window.confirm(`Delete maintenance receipt ${row.receipt_number || row.plot_number}?`)) return;
    try {
      await api.delete(`/maintenance/${row.id}`);
      notify('Maintenance deleted');
      load();
    } catch (error) {
      notify(error.response?.data?.message || 'Unable to delete maintenance', 'error');
    }
  }

  async function printReceipt(row) {
    if (!row.receipt_number) {
      notify('Receipt number is missing', 'error');
      return;
    }
    try {
      const response = await api.get(`/receipts/${row.receipt_number}/pdf`, { responseType: 'blob' });
      const url = URL.createObjectURL(response.data);
      window.open(url, '_blank', 'noopener,noreferrer');
      setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (error) {
      notify(error.response?.data?.message || 'Unable to print receipt', 'error');
    }
  }

  const selectedPlot = plots.find((item) => item.plot_id === form.plot_id);
  const total = Number(form.monthly_amount || 0) + Number(form.previous_due || 0) + Number(form.late_fee || 0) - Number(form.discount || 0);
  const balance = Math.max(total - Number(form.paid_amount || 0), 0);

  return (
    <>
      <PageHeader
        title="Maintenance Collection"
        subtitle="Add, edit, delete, print receipts, and track paid, partially paid, unpaid, dues, and balances."
        actionLabel="Add Payment"
        actionIcon={<AddIcon />}
        onAction={openAdd}
      />
      <Card sx={{ mb: 2 }}><CardContent>
        <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
          <TextField label="Month" type="number" value={filters.month} onChange={(e) => setFilters({ ...filters, month: e.target.value })} />
          <TextField label="Year" type="number" value={filters.year} onChange={(e) => setFilters({ ...filters, year: e.target.value })} />
          <TextField label="Status" select value={filters.status} onChange={(e) => setFilters({ ...filters, status: e.target.value })} sx={{ minWidth: 180 }}>
            <MenuItem value="">All</MenuItem><MenuItem value="Paid">Paid</MenuItem><MenuItem value="Partially Paid">Partially Paid</MenuItem><MenuItem value="Unpaid">Unpaid</MenuItem>
          </TextField>
          <Button variant="outlined" onClick={load}>Apply</Button>
          <Button variant="outlined" startIcon={<PrintIcon />} onClick={generateMonth}>Generate Month</Button>
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
          { key: 'actions', label: 'Actions', render: (row) => (
            <Stack direction="row" spacing={0.5}>
              <Tooltip title="Print receipt"><IconButton size="small" onClick={() => printReceipt(row)}><PrintIcon fontSize="small" /></IconButton></Tooltip>
              <Tooltip title="Edit"><IconButton size="small" onClick={() => openEdit(row)}><EditIcon fontSize="small" /></IconButton></Tooltip>
              <Tooltip title="Delete"><IconButton size="small" color="error" onClick={() => remove(row)}><DeleteIcon fontSize="small" /></IconButton></Tooltip>
            </Stack>
          ) }
        ]}
      />

      <Dialog open={open} onClose={() => setOpen(false)} maxWidth="md" fullWidth>
        <DialogTitle>{editing ? 'Edit Maintenance' : 'Add Maintenance Payment'}</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
              <TextField label="Plot" select value={form.plot_id} onChange={(e) => selectPlot(e.target.value)} fullWidth required>
                {plots.map((plot) => (
                  <MenuItem key={plot.plot_id} value={plot.plot_id}>{plot.plot_number} - {plot.owner_name}</MenuItem>
                ))}
              </TextField>
              <TextField label="Owner" value={selectedPlot?.owner_name || form.owner_name || ''} fullWidth InputProps={{ readOnly: true }} />
            </Stack>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
              <TextField label="Month" type="number" value={form.month} onChange={(e) => setField('month', e.target.value)} />
              <TextField label="Year" type="number" value={form.year} onChange={(e) => setField('year', e.target.value)} />
              <TextField label="Payment Date" type="date" InputLabelProps={{ shrink: true }} value={form.payment_date || ''} onChange={(e) => setField('payment_date', e.target.value)} />
            </Stack>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
              <TextField label="Monthly Amount" type="number" value={form.monthly_amount} onChange={(e) => setField('monthly_amount', e.target.value)} />
              <TextField label="Previous Due" type="number" value={form.previous_due} onChange={(e) => setField('previous_due', e.target.value)} />
              <TextField label="Late Fee" type="number" value={form.late_fee} onChange={(e) => setField('late_fee', e.target.value)} />
              <TextField label="Discount" type="number" value={form.discount} onChange={(e) => setField('discount', e.target.value)} />
            </Stack>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
              <TextField label="Paid Amount" type="number" value={form.paid_amount} onChange={(e) => setField('paid_amount', e.target.value)} />
              <TextField label="Payment Mode" select value={form.payment_mode} onChange={(e) => setField('payment_mode', e.target.value)} sx={{ minWidth: 180 }}>
                {paymentModes.map((mode) => <MenuItem key={mode} value={mode}>{mode}</MenuItem>)}
              </TextField>
              <TextField label="Transaction Number" value={form.transaction_number || ''} onChange={(e) => setField('transaction_number', e.target.value)} fullWidth />
            </Stack>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
              <TextField label="Receipt Number" value={form.receipt_number || ''} onChange={(e) => setField('receipt_number', e.target.value)} fullWidth helperText="Leave blank to auto-generate for new payment" />
              <TextField label="Total" value={money(total)} InputProps={{ readOnly: true }} />
              <TextField label="Balance" value={money(balance)} InputProps={{ readOnly: true }} />
            </Stack>
            <TextField label="Remarks" value={form.remarks || ''} onChange={(e) => setField('remarks', e.target.value)} fullWidth multiline minRows={2} />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setOpen(false)}>Cancel</Button>
          <Button variant="contained" onClick={save}>{editing ? 'Update' : 'Add'}</Button>
        </DialogActions>
      </Dialog>

      <Snackbar open={Boolean(message)} autoHideDuration={3500} onClose={() => setMessage('')}>
        <Alert severity={severity} onClose={() => setMessage('')}>{message}</Alert>
      </Snackbar>
    </>
  );
}
