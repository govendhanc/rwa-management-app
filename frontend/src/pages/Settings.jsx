import { useEffect, useState } from 'react';
import { Card, CardContent, Stack, TextField, Typography } from '@mui/material';
import PageHeader from '../components/PageHeader.jsx';
import { api } from '../services/api.js';

export default function Settings() {
  const [data, setData] = useState({ association: {}, settings: [] });
  useEffect(() => { api.get('/settings').then((res) => setData(res.data)); }, []);
  const a = data.association || {};

  return (
    <>
      <PageHeader title="Settings" subtitle="Association details, financial year, receipt prefix, backup, logo, penalties, and payment configuration." />
      <Card>
        <CardContent>
          <Typography variant="h6" gutterBottom>Association Details</Typography>
          <Stack spacing={2}>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
              <TextField label="Association Name" value={a.name || ''} fullWidth InputProps={{ readOnly: true }} />
              <TextField label="Registration Number" value={a.registration_number || ''} fullWidth InputProps={{ readOnly: true }} />
            </Stack>
            <TextField label="Address" value={a.address || ''} fullWidth multiline minRows={2} InputProps={{ readOnly: true }} />
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
              <TextField label="Monthly Maintenance" value={a.monthly_maintenance_amount || ''} InputProps={{ readOnly: true }} />
              <TextField label="Late Fee" value={a.late_fee_amount || ''} InputProps={{ readOnly: true }} />
              <TextField label="GST %" value={a.gst_percent || ''} InputProps={{ readOnly: true }} />
            </Stack>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
              <TextField label="Bank" value={a.bank_name || ''} fullWidth InputProps={{ readOnly: true }} />
              <TextField label="IFSC" value={a.bank_ifsc || ''} InputProps={{ readOnly: true }} />
            </Stack>
          </Stack>
        </CardContent>
      </Card>
    </>
  );
}
