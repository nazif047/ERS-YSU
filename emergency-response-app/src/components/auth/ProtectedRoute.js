import React, { useEffect, useState } from 'react';
import { View, StyleSheet, ActivityIndicator } from 'react-native';
import { useTheme } from '@react-navigation/native';
import { useAuth } from '../../hooks/useAuth';
import LoadingSpinner from '../common/LoadingSpinner';

const ProtectedRoute = ({ children, requiredRole, children: Component, ...props }) => {
  const theme = useTheme();
  const colors = theme.colors;
  const { user, isAuthenticated, isLoading, checkAuth } = useAuth();
  const [authChecked, setAuthChecked] = useState(false);

  useEffect(() => {
    const verifyAuth = async () => {
      if (!isLoading) {
        const isValid = await checkAuth();
        setAuthChecked(true);
      }
    };

    verifyAuth();
  }, [isLoading, checkAuth]);

  // Show loading screen while checking authentication
  if (isLoading || !authChecked) {
    return (
      <View style={[styles.loadingContainer, { backgroundColor: colors.background }]}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  // Not authenticated
  if (!isAuthenticated) {
    return (
      <View style={[styles.loadingContainer, { backgroundColor: colors.background }]}>
        <LoadingSpinner
          visible={true}
          text="Redirecting to login..."
          overlay={false}
        />
      </View>
    );
  }

  // Check role requirements
  if (requiredRole) {
    const hasRequiredRole = checkUserRole(user?.role, requiredRole);
    if (!hasRequiredRole) {
      return (
        <View style={[styles.loadingContainer, { backgroundColor: colors.background }]}>
          <LoadingSpinner
            visible={true}
            text="Access denied. Insufficient permissions."
            overlay={false}
          />
        </View>
      );
    }
  }

  // User is authenticated and has required permissions
  return typeof Component === 'function' ? <Component {...props} /> : children;
};

// Helper function to check if user has required role
const checkUserRole = (userRole, requiredRole) => {
  if (!userRole || !requiredRole) {
    return false;
  }

  // If specific role is required
  if (typeof requiredRole === 'string') {
    return userRole === requiredRole;
  }

  // If array of roles is provided
  if (Array.isArray(requiredRole)) {
    return requiredRole.includes(userRole);
  }

  // If role levels are provided
  if (typeof requiredRole === 'object') {
    const { minimum, allowed } = requiredRole;

    // Check minimum role level
    if (minimum) {
      const roleHierarchy = [
        'student',
        'staff',
        'health_admin',
        'fire_admin',
        'security_admin',
        'super_admin'
      ];

      const userLevel = roleHierarchy.indexOf(userRole);
      const minLevel = roleHierarchy.indexOf(minimum);

      if (userLevel < minLevel) {
        return false;
      }
    }

    // Check allowed roles
    if (allowed && !allowed.includes(userRole)) {
      return false;
    }

    return true;
  }

  return false;
};

const styles = StyleSheet.create({
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
});

export default ProtectedRoute;