/**
 * Loading Overlay Component
 * Yobe State University Emergency Response System
 */

import React from 'react';
import { View, Text, StyleSheet, ActivityIndicator } from 'react-native';
import { Modal } from 'react-native-paper';

// Theme
import { colors, spacing, typography } from '../../utils/theme';

const LoadingOverlay = ({ visible = true, message = 'Loading...' }) => {
  return (
    <Modal
      visible={visible}
      transparent={true}
      animationType="fade"
      dismissable={false}
    >
      <View style={styles.overlay}>
        <View style={styles.container}>
          <ActivityIndicator
            size="large"
            color={colors.primary}
            style={styles.spinner}
          />
          <Text style={styles.message}>{message}</Text>
        </View>
      </View>
    </Modal>
  );
};

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  container: {
    backgroundColor: colors.white,
    borderRadius: 16,
    padding: spacing.lg,
    alignItems: 'center',
    minWidth: 200,
    maxWidth: '80%',
  },
  spinner: {
    marginBottom: spacing.md,
  },
  message: {
    fontSize: typography.fontSize.base,
    color: colors.text,
    textAlign: 'center',
    fontWeight: typography.fontWeight.medium,
  },
});

export default LoadingOverlay;