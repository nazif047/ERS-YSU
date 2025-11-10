/**
 * Main App Navigator
 * Yobe State University Emergency Response System
 */

import React from 'react';
import { createStackNavigator } from '@react-navigation/stack';

// Auth Screens
import LoginScreen from '../screens/Auth/LoginScreen';
import RegisterScreen from '../screens/Auth/RegisterScreen';

// Main Screens
import UserDashboardScreen from '../screens/Dashboard/UserDashboardScreen';
import HealthAdminScreen from '../screens/Dashboard/HealthAdminScreen';
import FireAdminScreen from '../screens/Dashboard/FireAdminScreen';
import SecurityAdminScreen from '../screens/Dashboard/SecurityAdminScreen';

// Emergency Screens
import ReportEmergencyScreen from '../screens/Emergency/ReportEmergencyScreen';
import EmergencyDetailScreen from '../screens/Emergency/EmergencyDetailScreen';
import EmergencyTypeSelectionScreen from '../screens/Emergency/EmergencyTypeSelectionScreen';
import LocationSelectionScreen from '../screens/Emergency/LocationSelectionScreen';

// Profile and Settings
import ProfileScreen from '../screens/Profile/ProfileScreen';
import SettingsScreen from '../screens/Settings/SettingsScreen';

const Stack = createStackNavigator();

const AppNavigator = ({ initialRoute = 'Auth' }) => {
  return (
    <Stack.Navigator
      initialRouteName={initialRoute}
      screenOptions={{
        headerShown: false,
        cardStyle: { backgroundColor: '#ffffff' },
      }}
    >
      {/* Authentication Stack */}
      <Stack.Screen name="Login" component={LoginScreen} />
      <Stack.Screen name="Register" component={RegisterScreen} />

      {/* Main Dashboard Stack */}
      <Stack.Screen
        name="UserDashboard"
        component={UserDashboardScreen}
        options={{ gestureEnabled: false }}
      />
      <Stack.Screen
        name="HealthAdmin"
        component={HealthAdminScreen}
        options={{ gestureEnabled: false }}
      />
      <Stack.Screen
        name="FireAdmin"
        component={FireAdminScreen}
        options={{ gestureEnabled: false }}
      />
      <Stack.Screen
        name="SecurityAdmin"
        component={SecurityAdminScreen}
        options={{ gestureEnabled: false }}
      />

      {/* Emergency Management Stack */}
      <Stack.Screen
        name="EmergencyTypeSelection"
        component={EmergencyTypeSelectionScreen}
        options={{
          presentation: 'modal',
          gestureEnabled: true,
        }}
      />
      <Stack.Screen
        name="LocationSelection"
        component={LocationSelectionScreen}
        options={{
          presentation: 'modal',
          gestureEnabled: true,
        }}
      />
      <Stack.Screen
        name="ReportEmergency"
        component={ReportEmergencyScreen}
        options={{
          presentation: 'modal',
          gestureEnabled: false,
        }}
      />
      <Stack.Screen
        name="EmergencyDetail"
        component={EmergencyDetailScreen}
        options={{
          presentation: 'card',
          gestureEnabled: true,
        }}
      />

      {/* Profile and Settings Stack */}
      <Stack.Screen name="Profile" component={ProfileScreen} />
      <Stack.Screen name="Settings" component={SettingsScreen} />
    </Stack.Navigator>
  );
};

export default AppNavigator;