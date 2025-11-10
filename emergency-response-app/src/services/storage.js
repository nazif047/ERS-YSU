/**
 * Local Storage Service
 * Yobe State University Emergency Response System
 */

import AsyncStorage from '@react-native-async-storage/async-storage';

// Storage Keys
export const STORAGE_KEYS = {
  ACCESS_TOKEN: 'access_token',
  REFRESH_TOKEN: 'refresh_token',
  USER_DATA: 'user_data',
  EMERGENCY_DRAFTS: 'emergency_drafts',
  USER_PREFERENCES: 'user_preferences',
  RECENT_LOCATIONS: 'recent_locations',
  APP_SETTINGS: 'app_settings',
  FIRST_LAUNCH: 'first_launch',
  NOTIFICATION_SETTINGS: 'notification_settings',
};

// Initialize storage
export const initStorage = async () => {
  try {
    console.log('Storage initialized');
    // Add any initialization logic here
    return true;
  } catch (error) {
    console.error('Storage initialization error:', error);
    return false;
  }
};

// Generic storage methods
export const storageService = {
  // Store data
  storeData: async (key, value) => {
    try {
      const jsonValue = JSON.stringify(value);
      await AsyncStorage.setItem(key, jsonValue);
      console.log(`Data stored for key: ${key}`);
      return true;
    } catch (error) {
      console.error(`Error storing data for key ${key}:`, error);
      return false;
    }
  },

  // Retrieve data
  getData: async (key) => {
    try {
      const jsonValue = await AsyncStorage.getItem(key);
      return jsonValue != null ? JSON.parse(jsonValue) : null;
    } catch (error) {
      console.error(`Error retrieving data for key ${key}:`, error);
      return null;
    }
  },

  // Remove data
  removeData: async (key) => {
    try {
      await AsyncStorage.removeItem(key);
      console.log(`Data removed for key: ${key}`);
      return true;
    } catch (error) {
      console.error(`Error removing data for key ${key}:`, error);
      return false;
    }
  },

  // Clear all data
  clearAll: async () => {
    try {
      await AsyncStorage.clear();
      console.log('All storage data cleared');
      return true;
    } catch (error) {
      console.error('Error clearing storage:', error);
      return false;
    }
  },

  // Get all keys
  getAllKeys: async () => {
    try {
      const keys = await AsyncStorage.getAllKeys();
      return keys;
    } catch (error) {
      console.error('Error getting storage keys:', error);
      return [];
    }
  },

  // Store multiple key-value pairs
  storeMultiple: async (keyValuePairs) => {
    try {
      const entries = Object.entries(keyValuePairs).map(([key, value]) => [
        key,
        JSON.stringify(value),
      ]);
      await AsyncStorage.multiSet(entries);
      console.log('Multiple data items stored');
      return true;
    } catch (error) {
      console.error('Error storing multiple items:', error);
      return false;
    }
  },

  // Remove multiple keys
  removeMultiple: async (keys) => {
    try {
      await AsyncStorage.multiRemove(keys);
      console.log('Multiple data items removed');
      return true;
    } catch (error) {
      console.error('Error removing multiple items:', error);
      return false;
    }
  },
};

// Authentication storage helpers
export const authStorage = {
  // Store authentication tokens
  storeTokens: async (tokens) => {
    const { access_token, refresh_token, user } = tokens;
    return await storageService.storeMultiple({
      [STORAGE_KEYS.ACCESS_TOKEN]: access_token,
      [STORAGE_KEYS.REFRESH_TOKEN]: refresh_token,
      [STORAGE_KEYS.USER_DATA]: user,
    });
  },

  // Get authentication tokens
  getTokens: async () => {
    try {
      const [access_token, refresh_token, user_data] = await AsyncStorage.multiGet([
        STORAGE_KEYS.ACCESS_TOKEN,
        STORAGE_KEYS.REFRESH_TOKEN,
        STORAGE_KEYS.USER_DATA,
      ]);

      return {
        access_token: access_token[1],
        refresh_token: refresh_token[1],
        user: user_data[1] ? JSON.parse(user_data[1]) : null,
      };
    } catch (error) {
      console.error('Error getting auth tokens:', error);
      return {
        access_token: null,
        refresh_token: null,
        user: null,
      };
    }
  },

  // Clear authentication data
  clearAuthData: async () => {
    return await storageService.removeMultiple([
      STORAGE_KEYS.ACCESS_TOKEN,
      STORAGE_KEYS.REFRESH_TOKEN,
      STORAGE_KEYS.USER_DATA,
    ]);
  },

  // Check if user is authenticated
  isAuthenticated: async () => {
    try {
      const token = await AsyncStorage.getItem(STORAGE_KEYS.ACCESS_TOKEN);
      return !!token;
    } catch (error) {
      console.error('Error checking authentication status:', error);
      return false;
    }
  },

  // Get current user data
  getCurrentUser: async () => {
    return await storageService.getData(STORAGE_KEYS.USER_DATA);
  },

  // Update current user data
  updateCurrentUser: async (userData) => {
    return await storageService.storeData(STORAGE_KEYS.USER_DATA, userData);
  },
};

// User preferences storage
export const preferencesStorage = {
  // Store user preferences
  storePreferences: async (preferences) => {
    return await storageService.storeData(STORAGE_KEYS.USER_PREFERENCES, preferences);
  },

  // Get user preferences
  getPreferences: async () => {
    const defaultPreferences = {
      language: 'en',
      theme: 'light',
      notifications: true,
      autoLocation: true,
      soundEnabled: true,
      hapticFeedback: true,
    };

    const stored = await storageService.getData(STORAGE_KEYS.USER_PREFERENCES);
    return { ...defaultPreferences, ...stored };
  },

  // Update specific preference
  updatePreference: async (key, value) => {
    const preferences = await preferencesStorage.getPreferences();
    preferences[key] = value;
    return await preferencesStorage.storePreferences(preferences);
  },
};

// Recent locations storage
export const locationsStorage = {
  // Add recent location
  addRecentLocation: async (location) => {
    const recent = await locationsStorage.getRecentLocations();
    const filtered = recent.filter(item => item.id !== location.id);
    filtered.unshift(location);
    const limited = filtered.slice(0, 10); // Keep only 10 most recent
    return await storageService.storeData(STORAGE_KEYS.RECENT_LOCATIONS, limited);
  },

  // Get recent locations
  getRecentLocations: async () => {
    return (await storageService.getData(STORAGE_KEYS.RECENT_LOCATIONS)) || [];
  },

  // Clear recent locations
  clearRecentLocations: async () => {
    return await storageService.removeData(STORAGE_KEYS.RECENT_LOCATIONS);
  },
};

// App settings storage
export const appStorage = {
  // Store app settings
  storeSettings: async (settings) => {
    return await storageService.storeData(STORAGE_KEYS.APP_SETTINGS, settings);
  },

  // Get app settings
  getSettings: async () => {
    const defaultSettings = {
      firstLaunch: true,
      onboardingCompleted: false,
      apiBaseUrl: 'http://localhost/emergency-response-server',
      debugMode: false,
      analyticsEnabled: true,
    };

    const stored = await storageService.getData(STORAGE_KEYS.APP_SETTINGS);
    return { ...defaultSettings, ...stored };
  },

  // Check if first launch
  isFirstLaunch: async () => {
    const firstLaunch = await AsyncStorage.getItem(STORAGE_KEYS.FIRST_LAUNCH);
    return firstLaunch === null;
  },

  // Mark first launch as completed
  completeFirstLaunch: async () => {
    return await storageService.storeData(STORAGE_KEYS.FIRST_LAUNCH, false);
  },
};

// Emergency draft storage
export const draftStorage = {
  // Save emergency draft
  saveDraft: async (draft) => {
    const drafts = (await storageService.getData(STORAGE_KEYS.EMERGENCY_DRAFTS)) || [];
    drafts.unshift({
      ...draft,
      id: Date.now(),
      timestamp: new Date().toISOString(),
    });
    const limited = drafts.slice(0, 5); // Keep only 5 most recent drafts
    return await storageService.storeData(STORAGE_KEYS.EMERGENCY_DRAFTS, limited);
  },

  // Get emergency drafts
  getDrafts: async () => {
    return (await storageService.getData(STORAGE_KEYS.EMERGENCY_DRAFTS)) || [];
  },

  // Remove specific draft
  removeDraft: async (draftId) => {
    const drafts = await draftStorage.getDrafts();
    const filtered = drafts.filter(draft => draft.id !== draftId);
    return await storageService.storeData(STORAGE_KEYS.EMERGENCY_DRAFTS, filtered);
  },

  // Clear all drafts
  clearDrafts: async () => {
    return await storageService.removeData(STORAGE_KEYS.EMERGENCY_DRAFTS);
  },
};

export default storageService;