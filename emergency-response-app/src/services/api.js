/**
 * API Service
 * Yobe State University Emergency Response System
 */

import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';

// API Configuration
const API_BASE_URL = 'http://localhost/emergency-response-server'; // Update with your server URL

// Create axios instance
const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Request interceptor for adding auth token
api.interceptors.request.use(
  async (config) => {
    try {
      const token = await AsyncStorage.getItem('access_token');
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
    } catch (error) {
      console.error('Error getting auth token:', error);
    }

    console.log(`API Request: ${config.method?.toUpperCase()} ${config.url}`, config.data);
    return config;
  },
  (error) => {
    console.error('API Request Error:', error);
    return Promise.reject(error);
  }
);

// Response interceptor for handling auth errors
api.interceptors.response.use(
  (response) => {
    console.log(`API Response: ${response.config.method?.toUpperCase()} ${response.config.url}`, response.data);
    return response;
  },
  async (error) => {
    const originalRequest = error.config;

    // Handle 401 Unauthorized errors
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true;

      try {
        // Try to refresh the token
        const refreshToken = await AsyncStorage.getItem('refresh_token');
        if (refreshToken) {
          const response = await api.post('/api/auth/refresh', {
            refresh_token: refreshToken,
          });

          const { access_token } = response.data.data;
          await AsyncStorage.setItem('access_token', access_token);

          // Retry the original request with new token
          originalRequest.headers.Authorization = `Bearer ${access_token}`;
          return api(originalRequest);
        }
      } catch (refreshError) {
        console.error('Token refresh failed:', refreshError);
        // Clear tokens and redirect to login
        await AsyncStorage.multiRemove(['access_token', 'refresh_token', 'user_data']);
        // You can navigate to login screen here or handle it globally
      }
    }

    console.error('API Response Error:', error.response?.data || error.message);
    return Promise.reject(error);
  }
);

// Initialize API configuration
export const initAPI = () => {
  console.log('API initialized with base URL:', API_BASE_URL);
};

// API Methods
export const apiService = {
  // Health check
  healthCheck: () => api.get('/api/health'),

  // Authentication
  login: (credentials) => api.post('/api/auth/login', credentials),
  register: (userData) => api.post('/api/auth/register', userData),
  refreshToken: (refreshToken) => api.post('/api/auth/refresh', { refresh_token: refreshToken }),
  getProfile: () => api.get('/api/auth/profile'),
  updateProfile: (profileData) => api.put('/api/auth/profile', profileData),

  // Emergency Management
  createEmergency: (emergencyData) => api.post('/api/emergencies/create', emergencyData),
  getEmergencies: (params = {}) => api.get('/api/emergencies', { params }),
  getEmergencyDetails: (id) => api.get(`/api/emergencies/${id}`),
  getUserEmergencies: (userId, params = {}) => api.get(`/api/emergencies/user/${userId}`, { params }),
  updateEmergencyStatus: (id, statusData) => api.put(`/api/emergencies/${id}/status`, statusData),
  getEmergencyTypes: (params = {}) => api.get('/api/emergencies/types', { params }),

  // Location Management
  getLocations: (params = {}) => api.get('/api/locations', { params }),
  getLocation: (id) => api.get(`/api/locations/${id}`),
  addLocation: (locationData) => api.post('/api/locations/add', locationData),
  updateLocation: (id, locationData) => api.put(`/api/locations/${id}`, locationData),
  deleteLocation: (id) => api.delete(`/api/locations/${id}`),

  // Admin Dashboard
  getDashboard: (params = {}) => api.get('/api/admins/dashboard', { params }),
  getAnalytics: (params = {}) => api.get('/api/admins/analytics', { params }),
  getUsers: (params = {}) => api.get('/api/admins/users', { params }),

  // Notifications
  getNotifications: (params = {}) => api.get('/api/notifications', { params }),
  sendNotification: (notificationData) => api.post('/api/notifications/send', notificationData),
  markNotificationRead: (id) => api.put(`/api/notifications/${id}/read`),
};

// Utility functions
export const apiUtils = {
  // Handle API errors
  handleError: (error) => {
    if (error.response) {
      // Server responded with error status
      const { data, status } = error.response;
      return {
        message: data.message || 'Server error',
        status,
        data: data,
      };
    } else if (error.request) {
      // Request made but no response received
      return {
        message: 'Network error. Please check your connection.',
        status: 0,
      };
    } else {
      // Something else happened
      return {
        message: error.message || 'Unknown error occurred',
        status: 0,
      };
    }
  },

  // Format API response
  formatResponse: (response) => {
    return {
      success: response.data.success,
      message: response.data.message,
      data: response.data.data || null,
      status: response.status,
    };
  },

  // Validate API response
  isValidResponse: (response) => {
    return response && response.data && typeof response.data.success === 'boolean';
  },

  // Get base URL
  getBaseURL: () => API_BASE_URL,

  // Set base URL (for development/testing)
  setBaseURL: (url) => {
    api.defaults.baseURL = url;
  },
};

export default api;