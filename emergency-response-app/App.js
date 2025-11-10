/**
 * Emergency Response System - Main App Component
 * Yobe State University Emergency Response System
 */

import React, { useEffect, useState } from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { StatusBar, StyleSheet, View, LogBox } from 'react-native';
import { Provider as PaperProvider } from 'react-native-paper';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { SafeAreaProvider } from 'react-native-safe-area-context';

// Navigation
import AppNavigator from './src/navigation/AppNavigator';

// Services
import { initAPI } from './src/services/api';
import { initStorage } from './src/services/storage';

// Theme
import { theme } from './src/utils/theme';

// Ignore specific warnings
LogBox.ignoreLogs([
  'VirtualizedLists should never be nested',
  'Each child in a list should have a unique "key" prop',
]);

const App = () => {
  const [isReady, setIsReady] = useState(false);
  const [initialRoute, setInitialRoute] = useState('Auth');

  // Initialize app services
  useEffect(() => {
    const initializeApp = async () => {
      try {
        // Initialize storage
        await initStorage();

        // Initialize API configuration
        initAPI();

        // Check for existing authentication
        const userToken = await checkAuthStatus();

        setInitialRoute(userToken ? 'Main' : 'Auth');
        setIsReady(true);
      } catch (error) {
        console.error('App initialization error:', error);
        setIsReady(true);
      }
    };

    initializeApp();
  }, []);

  // Check authentication status
  const checkAuthStatus = async () => {
    try {
      const token = await AsyncStorage.getItem('access_token');
      return token;
    } catch (error) {
      console.error('Auth check error:', error);
      return null;
    }
  };

  if (!isReady) {
    return (
      <View style={styles.loadingContainer}>
        <StatusBar barStyle="light-content" backgroundColor="#1e3a8a" />
        {/* You can add a loading screen component here */}
      </View>
    );
  }

  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <SafeAreaProvider>
        <PaperProvider theme={theme}>
          <NavigationContainer>
            <StatusBar
              barStyle="light-content"
              backgroundColor="#1e3a8a"
              translucent={false}
            />
            <AppNavigator initialRoute={initialRoute} />
          </NavigationContainer>
        </PaperProvider>
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
};

const styles = StyleSheet.create({
  loadingContainer: {
    flex: 1,
    backgroundColor: '#1e3a8a',
    justifyContent: 'center',
    alignItems: 'center',
  },
});

export default App;