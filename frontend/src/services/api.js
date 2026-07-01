import axios from 'axios';

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:5000/api'
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('rwa_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

export async function downloadReport(type, format) {
  const response = await api.get(`/reports/${type}/export/${format}`, { responseType: 'blob' });
  const url = URL.createObjectURL(response.data);
  const link = document.createElement('a');
  link.href = url;
  link.download = `${type}.${format === 'excel' ? 'xlsx' : format}`;
  link.click();
  URL.revokeObjectURL(url);
}
