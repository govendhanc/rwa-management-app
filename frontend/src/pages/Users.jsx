import { useEffect, useState } from 'react';
import { Chip } from '@mui/material';
import PageHeader from '../components/PageHeader.jsx';
import DataTable from '../components/DataTable.jsx';
import { api } from '../services/api.js';

export default function Users() {
  const [rows, setRows] = useState([]);
  useEffect(() => { api.get('/admin/users').then((res) => setRows(res.data)); }, []);
  return (
    <>
      <PageHeader title="Users" subtitle="Create users, reset passwords, disable access, and assign Admin, Treasurer, or Read Only roles." />
      <DataTable rows={rows} columns={[
        { key: 'name', label: 'Name' },
        { key: 'email', label: 'Email' },
        { key: 'mobile', label: 'Mobile' },
        { key: 'role', label: 'Role' },
        { key: 'is_active', label: 'Status', render: (r) => <Chip size="small" color={r.is_active ? 'success' : 'default'} label={r.is_active ? 'Active' : 'Disabled'} /> },
        { key: 'last_login_at', label: 'Last Login' }
      ]} />
    </>
  );
}
