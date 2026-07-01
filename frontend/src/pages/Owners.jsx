import { useEffect, useState } from 'react';
import {
  Alert, Box, Button, Card, CardContent, Checkbox, Dialog, DialogActions, DialogContent,
  DialogTitle, FormControlLabel, IconButton, MenuItem, Snackbar, Stack, TextField, Tooltip, Chip
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import SearchIcon from '@mui/icons-material/Search';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import PageHeader from '../components/PageHeader.jsx';
import DataTable from '../components/DataTable.jsx';
import { api } from '../services/api.js';

const emptyForm = {
  owner_id: '',
  plot_id: '',
  plot_numbers: '',
  plot_number: '',
  owner_name: '',
  father_or_husband_name: '',
  mobile_number: '',
  email: '',
  address: '',
  block: '',
  street: '',
  plot_size: '',
  occupancy_status: 'Owner Occupied',
  tenant_name: '',
  tenant_mobile: '',
  water_connection: false,
  eb_connection: false,
  remarks: ''
};

function rowToForm(row) {
  return {
    ...emptyForm,
    ...row,
    plot_numbers: row.plot_number || '',
    water_connection: Boolean(row.water_connection),
    eb_connection: Boolean(row.eb_connection)
  };
}

function buildPlots(form, editing) {
  const numbers = editing
    ? [form.plot_number]
    : form.plot_numbers.split(',').map((value) => value.trim()).filter(Boolean);
  return numbers.map((plotNumber) => ({
    id: editing ? form.plot_id : undefined,
    plot_number: plotNumber,
    block: form.block,
    street: form.street,
    plot_size: form.plot_size,
    water_connection: form.water_connection,
    eb_connection: form.eb_connection
  }));
}

export default function Owners() {
  const [rows, setRows] = useState([]);
  const [search, setSearch] = useState('');
  const [open, setOpen] = useState(false);
  const [mode, setMode] = useState('create');
  const [form, setForm] = useState(emptyForm);
  const [message, setMessage] = useState('');
  const [severity, setSeverity] = useState('success');
  const editing = mode === 'edit';
  const addPlotMode = mode === 'addPlot';

  function notify(text, type = 'success') {
    setMessage(text);
    setSeverity(type);
  }

  function load() {
    api.get('/owners', { params: { search } }).then((res) => setRows(res.data));
  }

  useEffect(() => { load(); }, []);

  function openAdd() {
    setMode('create');
    setForm(emptyForm);
    setOpen(true);
  }

  function openEdit(row) {
    setMode('edit');
    setForm(rowToForm(row));
    setOpen(true);
  }

  function openAddPlot(row) {
    setMode('addPlot');
    setForm({ ...rowToForm(row), plot_numbers: '', plot_number: '' });
    setOpen(true);
  }

  function setField(field, value) {
    setForm((current) => ({ ...current, [field]: value }));
  }

  async function save() {
    try {
      if (editing) {
        await api.put(`/owners/${form.owner_id}`, form);
        await api.put(`/owners/plots/${form.plot_id}`, { ...form, plots: buildPlots(form, true) });
        notify('Owner and plot updated');
      } else if (addPlotMode) {
        await api.post(`/owners/${form.owner_id}/plots`, { ...form, plots: buildPlots(form, false) });
        notify('Plot added to existing owner');
      } else {
        await api.post('/owners', { ...form, plots: buildPlots(form, false) });
        notify('Owner added');
      }
      setOpen(false);
      load();
    } catch (error) {
      notify(error.response?.data?.message || 'Unable to save owner details', 'error');
    }
  }

  async function removePlot(row) {
    if (!window.confirm(`Remove plot ${row.plot_number}?`)) return;
    try {
      await api.delete(`/owners/plots/${row.plot_id}`);
      notify('Plot removed');
      load();
    } catch (error) {
      notify(error.response?.data?.message || 'Unable to remove plot', 'error');
    }
  }

  return (
    <>
      <PageHeader
        title="Plot Owners"
        subtitle="Add, update, remove, and validate owners with single or multiple plots."
        actionLabel="Add Owner"
        actionIcon={<AddIcon />}
        onAction={openAdd}
      />
      <Card sx={{ mb: 2 }}><CardContent>
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
          <TextField label="Search plot, owner, mobile" value={search} onChange={(e) => setSearch(e.target.value)} fullWidth />
          <Button variant="outlined" startIcon={<SearchIcon />} onClick={load}>Search</Button>
        </Stack>
      </CardContent></Card>
      <DataTable
        rows={rows}
        columns={[
          { key: 'plot_number', label: 'Plot' },
          { key: 'owner_name', label: 'Owner', render: (row) => (
            <Stack direction="row" spacing={1} alignItems="center">
              <Box>{row.owner_name}</Box>
              {Number(row.owner_plot_count) > 1 && <Chip size="small" color="primary" label={`${row.owner_plot_count} plots`} />}
            </Stack>
          ) },
          { key: 'mobile_number', label: 'Mobile' },
          { key: 'email', label: 'Email' },
          { key: 'block', label: 'Block' },
          { key: 'street', label: 'Street' },
          { key: 'plot_size', label: 'Size' },
          { key: 'occupancy_status', label: 'Occupancy' },
          { key: 'tenant_name', label: 'Tenant' },
          { key: 'actions', label: 'Actions', render: (row) => (
            <Stack direction="row" spacing={0.5}>
              <Tooltip title="Add plot to same owner"><IconButton size="small" color="primary" onClick={() => openAddPlot(row)}><AddIcon fontSize="small" /></IconButton></Tooltip>
              <Tooltip title="Edit owner and plot"><IconButton size="small" onClick={() => openEdit(row)}><EditIcon fontSize="small" /></IconButton></Tooltip>
              <Tooltip title="Remove this plot"><IconButton size="small" color="error" onClick={() => removePlot(row)}><DeleteIcon fontSize="small" /></IconButton></Tooltip>
            </Stack>
          ) }
        ]}
      />

      <Dialog open={open} onClose={() => setOpen(false)} maxWidth="md" fullWidth>
        <DialogTitle>{editing ? 'Update Owner / Plot' : addPlotMode ? 'Add Plot To Owner' : 'Add Owner'}</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            {!editing && (
              <Alert severity="info">
                Enter multiple plot numbers separated by commas to assign them to the same owner.
              </Alert>
            )}
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
              <TextField label="Owner Name" value={form.owner_name} onChange={(e) => setField('owner_name', e.target.value)} fullWidth required InputProps={{ readOnly: addPlotMode }} />
              <TextField label="Father/Husband Name" value={form.father_or_husband_name || ''} onChange={(e) => setField('father_or_husband_name', e.target.value)} fullWidth InputProps={{ readOnly: addPlotMode }} />
            </Stack>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
              <TextField label="Mobile Number" value={form.mobile_number || ''} onChange={(e) => setField('mobile_number', e.target.value)} fullWidth required InputProps={{ readOnly: addPlotMode }} />
              <TextField label="Email" value={form.email || ''} onChange={(e) => setField('email', e.target.value)} fullWidth InputProps={{ readOnly: addPlotMode }} />
            </Stack>
            <TextField label="Address" value={form.address || ''} onChange={(e) => setField('address', e.target.value)} fullWidth multiline minRows={2} InputProps={{ readOnly: addPlotMode }} />
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
              <TextField
                label={editing ? 'Plot Number' : 'Plot Numbers'}
                value={editing ? form.plot_number : form.plot_numbers}
                onChange={(e) => setField(editing ? 'plot_number' : 'plot_numbers', e.target.value)}
                helperText={editing ? 'Plot number must be unique' : 'Example: A-001, A-003'}
                fullWidth
                required
              />
              <TextField label="Block" value={form.block || ''} onChange={(e) => setField('block', e.target.value)} />
              <TextField label="Street" value={form.street || ''} onChange={(e) => setField('street', e.target.value)} fullWidth />
            </Stack>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
              <TextField label="Plot Size" value={form.plot_size || ''} onChange={(e) => setField('plot_size', e.target.value)} />
              <TextField label="Occupancy" select value={form.occupancy_status || 'Owner Occupied'} onChange={(e) => setField('occupancy_status', e.target.value)} sx={{ minWidth: 180 }} disabled={addPlotMode}>
                <MenuItem value="Vacant">Vacant</MenuItem>
                <MenuItem value="Owner Occupied">Owner Occupied</MenuItem>
                <MenuItem value="Tenant">Tenant</MenuItem>
              </TextField>
              <FormControlLabel control={<Checkbox checked={form.water_connection} onChange={(e) => setField('water_connection', e.target.checked)} />} label="Water" />
              <FormControlLabel control={<Checkbox checked={form.eb_connection} onChange={(e) => setField('eb_connection', e.target.checked)} />} label="EB" />
            </Stack>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
              <TextField label="Tenant Name" value={form.tenant_name || ''} onChange={(e) => setField('tenant_name', e.target.value)} fullWidth InputProps={{ readOnly: addPlotMode }} />
              <TextField label="Tenant Mobile" value={form.tenant_mobile || ''} onChange={(e) => setField('tenant_mobile', e.target.value)} fullWidth InputProps={{ readOnly: addPlotMode }} />
            </Stack>
            <TextField label="Remarks" value={form.remarks || ''} onChange={(e) => setField('remarks', e.target.value)} fullWidth multiline minRows={2} InputProps={{ readOnly: addPlotMode }} />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setOpen(false)}>Cancel</Button>
          <Button variant="contained" onClick={save}>{editing ? 'Update' : addPlotMode ? 'Add Plot' : 'Add'}</Button>
        </DialogActions>
      </Dialog>

      <Snackbar open={Boolean(message)} autoHideDuration={3500} onClose={() => setMessage('')}>
        <Alert severity={severity} onClose={() => setMessage('')}>{message}</Alert>
      </Snackbar>
    </>
  );
}
