import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  ActivityIndicator,
  Modal,
  ViewStyle,
} from 'react-native';
import { useTheme } from '@react-navigation/native';

const LoadingSpinner = ({
  visible = false,
  text,
  size = 'large',
  overlay = false,
  color,
  style,
  textStyle,
}) => {
  const theme = useTheme();
  const colors = theme.colors;

  const spinnerColor = color || colors.primary;

  if (overlay) {
    return (
      <Modal
        transparent={true}
        animationType="fade"
        visible={visible}
        statusBarTranslucent={true}
      >
        <View style={styles.overlay}>
          <View style={[styles.container, style]}>
            <ActivityIndicator
              size={size}
              color={spinnerColor}
              style={styles.spinner}
            />
            {text && (
              <Text style={[styles.text, { color: colors.text }, textStyle]}>
                {text}
              </Text>
            )}
          </View>
        </View>
      </Modal>
    );
  }

  if (!visible) return null;

  return (
    <View style={[styles.container, style]}>
      <ActivityIndicator
        size={size}
        color={spinnerColor}
        style={styles.spinner}
      />
      {text && (
        <Text style={[styles.text, { color: colors.text }, textStyle]}>
          {text}
        </Text>
      )}
    </View>
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
    backgroundColor: 'rgba(255, 255, 255, 0.9)',
    borderRadius: 12,
    padding: 20,
    alignItems: 'center',
    justifyContent: 'center',
    minWidth: 120,
    minHeight: 120,
  },
  spinner: {
    marginBottom: 12,
  },
  text: {
    fontSize: 16,
    textAlign: 'center',
    fontWeight: '500',
  },
});

export default LoadingSpinner;