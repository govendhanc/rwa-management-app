import { useEffect, useState } from 'react';
import { Button, Card, CardContent, Stack, TextField } from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import SearchIcon from '@mui/icons-material/Search';
import PageHeader from '../components/PageHeader.jsx';
import DataTable from '../components/DataTable.jsx';
import { api } from '../services/api.js';

export default function Owners() {
  const [rows, setRows] = useState([]);
  const [search, setSearch] = useState('');

  function load() {
    api.get('/owners', { params: { search } }).then((res) => setRows(res.data));
  }

  useEffect(() => { load(); }, []);

  return (
    <>
      <PageHeader title="Plot Owners" subtitle="Manage around 300 plots with owner, tenant, utility, and occupancy details." actionLabel="Add Owner" actionIcon={<AddIcon />} />
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
          { key: 'owner_name', label: 'Owner' },
          { key: 'mobile_number', label: 'Mobile' },
          { key: 'email', label: 'Email' },
          { key: 'block', label: 'Block' },
          { key: 'street', label: 'Street' },
          { key: 'plot_size', label: 'Size' },
          { key: 'occupancy_status', label: 'Occupancy' },
          { key: 'tenant_name', label: 'Tenant' }
        ]}
      />
    </>
  );
}
