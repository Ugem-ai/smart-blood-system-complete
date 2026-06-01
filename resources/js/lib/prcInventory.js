import api from './api';

export const fetchChapters = () => api.get('/prc/chapters').then((response) => response.data.data);
export const fetchChapterDetails = (chapterId) => api.get(`/prc/chapters/${chapterId}`).then((response) => response.data.data);
export const searchInventory = (filters) => api.get('/prc/inventory/search', { params: filters }).then((response) => response.data.data);
export const fetchNearbyInventory = (chapterId, radiusKm = 100) => api.get(`/prc/chapters/${chapterId}/nearby`, { params: { radius_km: radiusKm } }).then((response) => response.data.data);
export const fetchRecommendations = (chapterId, params) => api.get(`/prc/chapters/${chapterId}/recommend-transfers`, { params }).then((response) => response.data.data);
export const createInventoryTransfer = (payload) => api.post('/prc/transfers', payload).then((response) => response.data.data);
