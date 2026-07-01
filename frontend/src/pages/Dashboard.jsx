import { useEffect, useState } from 'react';
import { Card, CardContent, Typography } from '@mui/material';
import Grid from '@mui/material/Grid2';
import HomeWorkIcon from '@mui/icons-material/HomeWork';
import GroupsIcon from '@mui/icons-material/Groups';
import PaidIcon from '@mui/icons-material/Paid';
import WarningIcon from '@mui/icons-material/Warning';
import AccountBalanceWalletIcon from '@mui/icons-material/AccountBalanceWallet';
import TrendingDownIcon from '@mui/icons-material/TrendingDown';
import SavingsIcon from '@mui/icons-material/Savings';
import AssessmentIcon from '@mui/icons-material/Assessment';
import { Bar, BarChart, CartesianGrid, Legend, Line, LineChart, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis, Cell } from 'recharts';
import { api } from '../services/api.js';
import PageHeader from '../components/PageHeader.jsx';
import MetricCard from '../components/MetricCard.jsx';

const colors = ['#16834a', '#2d6cdf', '#b7791f', '#c2410c', '#6b7280'];
const money = (n) => `₹${Number(n || 0).toLocaleString('en-IN')}`;

export default function Dashboard() {
  const [data, setData] = useState({ cards: {}, charts: {} });

  useEffect(() => {
    api.get('/dashboard').then((res) => setData(res.data));
  }, []);

  const c = data.cards || {};
  const charts = data.charts || {};
  const metrics = [
    ['Total Plots', c.total_plots || 0, <HomeWorkIcon />, 'primary'],
    ['Total Owners', c.total_owners || 0, <GroupsIcon />, 'secondary'],
    ['Paid This Month', money(c.paid_this_month), <PaidIcon />, 'success'],
    ['Pending This Month', money(c.pending_this_month), <WarningIcon />, 'warning'],
    ['Total Collection', money(c.total_collection), <AccountBalanceWalletIcon />, 'primary'],
    ['Total Expenses', money(c.total_expenses), <TrendingDownIcon />, 'error'],
    ['Available Balance', money(c.available_balance), <SavingsIcon />, 'success'],
    ['Total Outstanding', money(c.total_outstanding), <AssessmentIcon />, 'warning']
  ];

  return (
    <>
      <PageHeader title="Dashboard" subtitle="Collections, dues, expenses, and cash position at a glance." />
      <Grid container spacing={2}>
        {metrics.map(([label, value, icon, tone]) => (
          <Grid key={label} size={{ xs: 12, sm: 6, lg: 3 }}><MetricCard label={label} value={value} icon={icon} tone={tone} /></Grid>
        ))}
        <Grid size={{ xs: 12, lg: 7 }}>
          <Card><CardContent>
            <Typography variant="h6" gutterBottom>Monthly Collection Trend</Typography>
            <ResponsiveContainer width="100%" height={300}>
              <LineChart data={charts.monthlyCollectionTrend || []}>
                <CartesianGrid strokeDasharray="3 3" /><XAxis dataKey="month" /><YAxis /><Tooltip /><Line dataKey="collection" stroke="#16834a" strokeWidth={3} />
              </LineChart>
            </ResponsiveContainer>
          </CardContent></Card>
        </Grid>
        <Grid size={{ xs: 12, lg: 5 }}>
          <Card><CardContent>
            <Typography variant="h6" gutterBottom>Expense by Category</Typography>
            <ResponsiveContainer width="100%" height={300}>
              <PieChart>
                <Pie data={charts.expenseByCategory || []} dataKey="amount" nameKey="category" outerRadius={95} label>
                  {(charts.expenseByCategory || []).map((_, i) => <Cell key={i} fill={colors[i % colors.length]} />)}
                </Pie>
                <Tooltip /><Legend />
              </PieChart>
            </ResponsiveContainer>
          </CardContent></Card>
        </Grid>
        <Grid size={{ xs: 12, md: 6 }}>
          <Card><CardContent>
            <Typography variant="h6" gutterBottom>Paid vs Pending</Typography>
            <ResponsiveContainer width="100%" height={260}>
              <BarChart data={charts.paidVsPending || []}><CartesianGrid strokeDasharray="3 3" /><XAxis dataKey="status" /><YAxis /><Tooltip /><Bar dataKey="count" fill="#2d6cdf" /></BarChart>
            </ResponsiveContainer>
          </CardContent></Card>
        </Grid>
        <Grid size={{ xs: 12, md: 6 }}>
          <Card><CardContent>
            <Typography variant="h6" gutterBottom>Income vs Expense</Typography>
            <ResponsiveContainer width="100%" height={260}>
              <BarChart data={charts.incomeVsExpense || []}><CartesianGrid strokeDasharray="3 3" /><XAxis dataKey="label" /><YAxis /><Tooltip /><Bar dataKey="amount" fill="#16834a" /></BarChart>
            </ResponsiveContainer>
          </CardContent></Card>
        </Grid>
      </Grid>
    </>
  );
}
