import { Card, CardContent, Stack, Typography } from '@mui/material';

export default function MetricCard({ label, value, icon, tone = 'primary' }) {
  return (
    <Card>
      <CardContent>
        <Stack direction="row" alignItems="center" justifyContent="space-between" spacing={2}>
          <Stack spacing={0.5}>
            <Typography variant="body2" color="text.secondary">{label}</Typography>
            <Typography variant="h5" fontWeight={800}>{value}</Typography>
          </Stack>
          <Stack alignItems="center" justifyContent="center" sx={{ width: 44, height: 44, borderRadius: 2, bgcolor: `${tone}.light`, color: `${tone}.main` }}>
            {icon}
          </Stack>
        </Stack>
      </CardContent>
    </Card>
  );
}
