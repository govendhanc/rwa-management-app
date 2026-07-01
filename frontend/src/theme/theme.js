import { createTheme } from '@mui/material/styles';

export function buildTheme(mode = 'light') {
  return createTheme({
    palette: {
      mode,
      primary: { main: '#16834a', dark: '#0f5f36', light: '#e6f4ed' },
      secondary: { main: '#2d6cdf' },
      background: { default: mode === 'dark' ? '#111814' : '#f6faf7', paper: mode === 'dark' ? '#17211b' : '#ffffff' },
      success: { main: '#16834a' },
      warning: { main: '#b7791f' },
      error: { main: '#c2410c' }
    },
    shape: { borderRadius: 8 },
    typography: {
      fontFamily: '"Inter", "Segoe UI", Arial, sans-serif',
      h4: { fontWeight: 700 },
      h6: { fontWeight: 700 },
      button: { textTransform: 'none', fontWeight: 700, letterSpacing: 0 }
    },
    components: {
      MuiButton: { styleOverrides: { root: { minHeight: 40 } } },
      MuiCard: { styleOverrides: { root: { borderRadius: 8, boxShadow: '0 8px 28px rgba(18, 71, 39, 0.08)' } } }
    }
  });
}
