import { useEffect, useState } from 'react';
import { Button, Card, CardContent, MenuItem, Stack, TextField } from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import PageHeader from '../components/PageHeader.jsx';
import DataTable from '../components/DataTable.jsx';
import { api } from '../services/api.js';

const sources = ['Corpus Fund', 'Donation', 'Interest', 'Penalty', 'Membership Fee', 'Other Income'];

export default function Income() {
  const [rows, setRows] = useState([]);
  const [form, setForm] = useState({ income_date: '', source: 'Donation', amount: '', remarks: '' });
  const load = () => api.get('/finance/income').then((res) => setRows(res.data));
  useEffect(() => { load(); }, []);
  async function save() { await api.post('/finance/income', form); setForm({ ...form, amount: '', remarks: '' }); load(); }

  return (
    <>
      <PageHeader title="Income" subtitle="Track corpus, donation, interest, penalty, membership fee, and other income." />
      <Card sx={{ mb: 2 }}><CardContent>
        <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5}>
          <TextField label="Date" type="date" InputLabelProps={{ shrink: true }} value={form.income_date} onChange={(e) => setForm({ ...form, income_date: e.target.value })} />
          <TextField label="Source" select value={form.source} onChange={(e) => setForm({ ...form, source: e.target.value })}>{sources.map((s) => <MenuItem key={s} value={s}>{s}</MenuItem>)}</TextField>
          <TextField label="Amount" type="number" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} />
          <TextField label="Remarks" value={form.remarks} onChange={(e) => setForm({ ...form, remarks: e.target.value })} />
          <Button variant="contained" startIcon={<AddIcon />} onClick={save}>Add</Button>
        </Stack>
      </CardContent></Card>
      <DataTable rows={rows} columns={[
        { key: 'income_date', label: 'Date' },
        { key: 'source', label: 'Source' },
        { key: 'amount', label: 'Amount', render: (r) => `₹${Number(r.amount).toLocaleString('en-IN')}` },
        { key: 'remarks', label: 'Remarks' }
      ]} />
    </>
  );
}
