import { useState } from 'react';
import { Outlet, NavLink, useNavigate } from 'react-router-dom';
import {
  AppBar, Avatar, Box, Divider, Drawer, IconButton, InputBase, List, ListItemButton,
  ListItemIcon, ListItemText, Menu, MenuItem, Toolbar, Typography, useMediaQuery
} from '@mui/material';
import Grid from '@mui/material/Grid2';
import DashboardIcon from '@mui/icons-material/Dashboard';
import HomeWorkIcon from '@mui/icons-material/HomeWork';
import PaymentsIcon from '@mui/icons-material/Payments';
import ReceiptLongIcon from '@mui/icons-material/ReceiptLong';
import SavingsIcon from '@mui/icons-material/Savings';
import AssessmentIcon from '@mui/icons-material/Assessment';
import PeopleIcon from '@mui/icons-material/People';
import SettingsIcon from '@mui/icons-material/Settings';
import SearchIcon from '@mui/icons-material/Search';
import NotificationsIcon from '@mui/icons-material/Notifications';
import MenuIcon from '@mui/icons-material/Menu';
import LogoutIcon from '@mui/icons-material/Logout';
import { useAuth } from '../contexts/AuthContext.jsx';

const drawerWidth = 264;
const nav = [
  ['/', 'Dashboard', <DashboardIcon />],
  ['/owners', 'Plot Owners', <HomeWorkIcon />],
  ['/maintenance', 'Maintenance', <PaymentsIcon />],
  ['/expenses', 'Expenses', <ReceiptLongIcon />],
  ['/income', 'Income', <SavingsIcon />],
  ['/reports', 'Reports', <AssessmentIcon />],
  ['/users', 'Users', <PeopleIcon />],
  ['/settings', 'Settings', <SettingsIcon />]
];

function DrawerContent() {
  return (
    <Box sx={{ height: '100%', bgcolor: 'primary.dark', color: 'white' }}>
      <Box sx={{ px: 3, py: 3 }}>
        <Typography variant="h6">Green Valley RWA</Typography>
        <Typography variant="body2" sx={{ opacity: 0.75 }}>Finance & Resident Suite</Typography>
      </Box>
      <Divider sx={{ borderColor: 'rgba(255,255,255,.14)' }} />
      <List sx={{ px: 1.5 }}>
        {nav.map(([to, label, icon]) => (
          <ListItemButton
            key={to}
            component={NavLink}
            to={to}
            end={to === '/'}
            sx={{
              color: 'rgba(255,255,255,.78)',
              borderRadius: 2,
              mb: 0.5,
              '&.active': { bgcolor: 'rgba(255,255,255,.14)', color: 'white' },
              '&:hover': { bgcolor: 'rgba(255,255,255,.10)' }
            }}
          >
            <ListItemIcon sx={{ color: 'inherit', minWidth: 38 }}>{icon}</ListItemIcon>
            <ListItemText primary={label} primaryTypographyProps={{ fontWeight: 700 }} />
          </ListItemButton>
        ))}
      </List>
    </Box>
  );
}

export default function AppShell() {
  const small = useMediaQuery('(max-width:900px)');
  const [open, setOpen] = useState(false);
  const [anchor, setAnchor] = useState(null);
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  function signOut() {
    logout();
    navigate('/login');
  }

  return (
    <Box sx={{ display: 'flex', minHeight: '100vh', bgcolor: 'background.default' }}>
      <Drawer variant={small ? 'temporary' : 'permanent'} open={open || !small} onClose={() => setOpen(false)}
        PaperProps={{ sx: { width: drawerWidth, border: 0 } }}>
        <DrawerContent />
      </Drawer>
      <Box sx={{ flex: 1, width: { md: `calc(100% - ${drawerWidth}px)` } }}>
        <AppBar position="sticky" color="inherit" elevation={0} sx={{ borderBottom: '1px solid', borderColor: 'divider' }}>
          <Toolbar sx={{ gap: 2 }}>
            {small && <IconButton onClick={() => setOpen(true)}><MenuIcon /></IconButton>}
            <Box sx={{ display: 'flex', alignItems: 'center', bgcolor: 'primary.light', px: 1.5, borderRadius: 2, flex: 1, maxWidth: 520 }}>
              <SearchIcon color="primary" />
              <InputBase placeholder="Search plot, owner, mobile, receipt" sx={{ ml: 1, flex: 1, py: 0.7 }} />
            </Box>
            <IconButton><NotificationsIcon /></IconButton>
            <IconButton onClick={(e) => setAnchor(e.currentTarget)}>
              <Avatar sx={{ bgcolor: 'primary.main' }}>{user?.name?.[0] || 'U'}</Avatar>
            </IconButton>
            <Menu anchorEl={anchor} open={Boolean(anchor)} onClose={() => setAnchor(null)}>
              <MenuItem disabled>{user?.name} · {user?.role}</MenuItem>
              <MenuItem onClick={signOut}><LogoutIcon fontSize="small" sx={{ mr: 1 }} /> Logout</MenuItem>
            </Menu>
          </Toolbar>
        </AppBar>
        <Box component="main" sx={{ p: { xs: 2, md: 3 } }}>
          <Grid container spacing={2}><Grid size={12}><Outlet /></Grid></Grid>
        </Box>
      </Box>
    </Box>
  );
}
