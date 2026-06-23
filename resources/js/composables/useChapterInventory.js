import { ref } from 'vue';
import api from '../lib/api';

export function useChapterInventory() {
  const loading = ref({
    chapters: false,
    inventory: false,
    transfer: false,
    recommendations: false,
    nearby: false,
    apiKeys: false,
    createApiKey: false,
    revokeApiKey: false,
  });

  const getErrorMessage = (error, fallback) => {
    return error?.response?.data?.message || fallback;
  };

  const fetchChapters = async () => {
    loading.value.chapters = true;
    try {
      const response = await api.get('/admin/chapters');
      return response.data?.data || { chapters: [], kpis: null };
    } finally {
      loading.value.chapters = false;
    }
  };

  const fetchChapterInventory = async (chapterId, filters = {}) => {
    loading.value.inventory = true;
    try {
      const response = await api.get(`/admin/chapters/${chapterId}/inventory`, {
        params: {
          blood_type: filters.blood_type || undefined,
          status: filters.status || undefined,
        },
      });
      return response.data?.data || { inventory: [] };
    } finally {
      loading.value.inventory = false;
    }
  };

  const fetchRecommendations = async ({ blood_type, units, destination_chapter_id }) => {
    loading.value.recommendations = true;
    try {
      const response = await api.get('/admin/inventory/recommendations', {
        params: {
          blood_type,
          units,
          destination_chapter_id,
        },
      });
      const payload = response.data?.data;
      return payload?.recommended_sources || payload || [];
    } finally {
      loading.value.recommendations = false;
    }
  };

  const createTransfer = async (payload) => {
    loading.value.transfer = true;
    try {
      const response = await api.post('/admin/transfers', payload);
      return response.data?.data;
    } finally {
      loading.value.transfer = false;
    }
  };

  const fetchNearbyChapters = async (chapterId, radius = 100) => {
    loading.value.nearby = true;
    try {
      const response = await api.get('/admin/chapters/nearby', {
        params: {
          chapter_id: chapterId,
          radius,
        },
      });
      const payload = response.data?.data;
      return payload?.nearby_chapters || payload || [];
    } finally {
      loading.value.nearby = false;
    }
  };

  const fetchApiKeys = async (chapterId) => {
    loading.value.apiKeys = true;
    try {
      const response = await api.get(`/admin/chapters/${chapterId}/api-keys`);
      return response.data?.data || [];
    } finally {
      loading.value.apiKeys = false;
    }
  };

  const generateApiKey = async (chapterId, label = '') => {
    loading.value.createApiKey = true;
    try {
      const response = await api.post(`/admin/chapters/${chapterId}/api-keys`, { label });
      return response.data?.data;
    } finally {
      loading.value.createApiKey = false;
    }
  };

  const revokeApiKey = async (chapterId, keyId) => {
    loading.value.revokeApiKey = true;
    try {
      await api.delete(`/admin/chapters/${chapterId}/api-keys/${keyId}`);
      return true;
    } finally {
      loading.value.revokeApiKey = false;
    }
  };

  return {
    loading,
    getErrorMessage,
    fetchChapters,
    fetchChapterInventory,
    fetchRecommendations,
    createTransfer,
    fetchNearbyChapters,
    fetchApiKeys,
    generateApiKey,
    revokeApiKey,
  };
}
