import { useEffect, useState } from 'react';
import { Button, Card, CardContent, MenuItem, Stack, TextField } from '@mui/material';
import FileDownloadIcon from '@mui/icons-material/FileDownload';
import PictureAsPdfIcon from '@mui/icons-material/PictureAsPdf';
import TableViewIcon from '@mui/icons-material/TableView';
import PageHeader from '../components/PageHeader.jsx';
import DataTable from '../components/DataTable.jsx';
import { api, downloadReport } from '../services/api.js';

const reports = [
  ['pending-list', 'Pending List'],
  ['paid-list', 'Paid List'],
  ['expense-report', 'Expense Report'],
  ['income-report', 'Income Report'],
  ['cash-book', 'Cash Book'],
  ['outstanding-aging', 'Outstanding Aging']
];

export default function Reports() {
  const [type, setType] = useState('pending-list');
  const [rows, setRows] = useState([]);
  useEffect(() => { api.get(`/reports/${type}`).then((res) => setRows(res.data)); }, [type]);
  const columns = rows[0] ? Object.keys(rows[0]).map((key) => ({ key, label: key.replaceAll('_', ' ') })) : [];

  return (
    <>
      <PageHeader title="Reports" subtitle="Owner, collection, finance, ledger, cash book, and outstanding reports." />
      <Card sx={{ mb: 2 }}><CardContent>
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
          <TextField label="Report" select value={type} onChange={(e) => setType(e.target.value)} sx={{ minWidth: 240 }}>
            {reports.map(([value, label]) => <MenuItem key={value} value={value}>{label}</MenuItem>)}
          </TextField>
          <Button variant="outlined" startIcon={<FileDownloadIcon />} onClick={() => downloadReport(type, 'excel')}>Excel</Button>
          <Button variant="outlined" startIcon={<PictureAsPdfIcon />} onClick={() => downloadReport(type, 'pdf')}>PDF</Button>
          <Button variant="outlined" startIcon={<TableViewIcon />} onClick={() => downloadReport(type, 'csv')}>CSV</Button>
        </Stack>
      </CardContent></Card>
      <DataTable rows={rows} columns={columns} />
    </>
  );
}
