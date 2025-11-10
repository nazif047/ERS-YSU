/**
 * Application Theme Configuration
 * Yobe State University Emergency Response System
 */

import { DefaultTheme } from 'react-native-paper';

// Color Palette
export const colors = {
  // Primary Colors (YSU Brand)
  primary: '#1e3a8a',      // Deep Blue
  primaryDark: '#1e3a8a',  // Same as primary
  primaryLight: '#3b82f6', // Light Blue

  // Secondary Colors
  secondary: '#dc2626',    // Emergency Red
  secondaryDark: '#991b1b',
  secondaryLight: '#ef4444',

  // Emergency Type Colors
  health: '#10b981',       // Green
  fire: '#f59e0b',         // Orange/Amber
  security: '#3b82f6',     // Blue

  // Status Colors
  success: '#10b981',
  warning: '#f59e0b',
  error: '#dc2626',
  info: '#3b82f6',

  // Neutral Colors
  white: '#ffffff',
  black: '#000000',
  gray50: '#f9fafb',
  gray100: '#f3f4f6',
  gray200: '#e5e7eb',
  gray300: '#d1d5db',
  gray400: '#9ca3af',
  gray500: '#6b7280',
  gray600: '#4b5563',
  gray700: '#374151',
  gray800: '#1f2937',
  gray900: '#111827',

  // Background Colors
  background: '#ffffff',
  surface: '#f9fafb',
  card: '#ffffff',

  // Text Colors
  text: '#111827',
  textSecondary: '#6b7280',
  textLight: '#9ca3af',
  textOnDark: '#ffffff',
};

// Typography
export const typography = {
  // Font Families
  fontFamily: {
    regular: 'System',
    medium: 'System',
    bold: 'System',
    light: 'System',
  },

  // Font Sizes
  fontSize: {
    xs: 12,
    sm: 14,
    base: 16,
    lg: 18,
    xl: 20,
    '2xl': 24,
    '3xl': 30,
    '4xl': 36,
  },

  // Font Weights
  fontWeight: {
    light: '300',
    normal: '400',
    medium: '500',
    semibold: '600',
    bold: '700',
    extrabold: '800',
  },

  // Line Heights
  lineHeight: {
    tight: 1.2,
    normal: 1.4,
    relaxed: 1.6,
  },
};

// Spacing
export const spacing = {
  xs: 4,
  sm: 8,
  md: 16,
  lg: 24,
  xl: 32,
  '2xl': 48,
  '3xl': 64,
  '4xl': 96,
};

// Border Radius
export const borderRadius = {
  sm: 4,
  md: 8,
  lg: 12,
  xl: 16,
  full: 9999,
};

// Shadows
export const shadows = {
  sm: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
    elevation: 2,
  },
  md: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.15,
    shadowRadius: 4,
    elevation: 4,
  },
  lg: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.2,
    shadowRadius: 8,
    elevation: 8,
  },
  xl: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.25,
    shadowRadius: 16,
    elevation: 16,
  },
};

// React Native Paper Theme
export const theme = {
  ...DefaultTheme,
  colors: {
    ...DefaultTheme.colors,
    primary: colors.primary,
    accent: colors.secondary,
    background: colors.background,
    surface: colors.surface,
    text: colors.text,
    error: colors.error,
    onPrimary: colors.white,
    onSurface: colors.text,
    disabled: colors.gray400,
    placeholder: colors.gray400,
    backdrop: 'rgba(0, 0, 0, 0.5)',
    notification: colors.error,
  },
  fonts: {
    regular: {
      fontFamily: typography.fontFamily.regular,
    },
    medium: {
      fontFamily: typography.fontFamily.medium,
    },
    light: {
      fontFamily: typography.fontFamily.light,
    },
    thin: {
      fontFamily: typography.fontFamily.light,
    },
  },
  roundness: borderRadius.md,
};

// Component-specific themes
export const emergencyTypeTheme = {
  health: {
    color: colors.health,
    backgroundColor: '#10b98120',
    borderColor: colors.health,
  },
  fire: {
    color: colors.fire,
    backgroundColor: '#f59e0b20',
    borderColor: colors.fire,
  },
  security: {
    color: colors.security,
    backgroundColor: '#3b82f620',
    borderColor: colors.security,
  },
};

export const statusTheme = {
  pending: {
    color: colors.warning,
    backgroundColor: '#f59e0b20',
    label: 'Pending',
  },
  in_progress: {
    color: colors.info,
    backgroundColor: '#3b82f620',
    label: 'In Progress',
  },
  resolved: {
    color: colors.success,
    backgroundColor: '#10b98120',
    label: 'Resolved',
  },
  closed: {
    color: colors.gray500,
    backgroundColor: '#6b728020',
    label: 'Closed',
  },
};

export const severityTheme = {
  low: {
    color: colors.success,
    backgroundColor: '#10b98120',
    label: 'Low',
  },
  medium: {
    color: colors.warning,
    backgroundColor: '#f59e0b20',
    label: 'Medium',
  },
  high: {
    color: colors.fire,
    backgroundColor: '#f59e0b20',
    label: 'High',
  },
  critical: {
    color: colors.error,
    backgroundColor: '#dc262620',
    label: 'Critical',
  },
};

export default {
  colors,
  typography,
  spacing,
  borderRadius,
  shadows,
  theme,
  emergencyTypeTheme,
  statusTheme,
  severityTheme,
};