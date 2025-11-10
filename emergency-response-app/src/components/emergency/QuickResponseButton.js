/**
 * Quick Response Button Component
 * Yobe State University Emergency Response System
 */

import React from 'react';
import { TouchableOpacity, Text, StyleSheet, View, Animated } from 'react-native';
import { LinearGradient } from 'react-native-linear-gradient';
import { Icon } from 'react-native-vector-icons/MaterialCommunityIcons';
import * as Haptics from 'expo-haptics';

// Theme
import { colors, spacing, typography, borderRadius, shadows } from '../../utils/theme';

const QuickResponseButton = ({ onPress, disabled = false }) => {
  const scaleValue = React.useRef(new Animated.Value(1)).current;

  const handlePressIn = () => {
    Animated.spring(scaleValue, {
      toValue: 0.95,
      useNativeDriver: true,
    }).start();
  };

  const handlePressOut = () => {
    Animated.spring(scaleValue, {
      toValue: 1,
      useNativeDriver: true,
    }).start();
  };

  const handlePress = () => {
    if (disabled) return;

    // Trigger haptic feedback
    Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);

    // Call the onPress handler
    onPress();
  };

  return (
    <View style={styles.container}>
      {/* SOS Button */}
      <TouchableOpacity
        activeOpacity={0.8}
        onPressIn={handlePressIn}
        onPressOut={handlePressOut}
        onPress={handlePress}
        disabled={disabled}
        style={[styles.button, disabled && styles.buttonDisabled]}
      >
        <Animated.View style={[styles.buttonInner, { transform: [{ scale: scaleValue }] }]}>
          <LinearGradient
            colors={[colors.error, colors.secondaryDark]}
            style={styles.gradient}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 1 }}
          >
            {/* SOS Text */}
            <Text style={styles.sosText}>SOS</Text>

            {/* Emergency Icon */}
            <Icon name="alert-octagram" size={40} color={colors.white} />

            {/* Subtitle */}
            <Text style={styles.subtitle}>Quick Response</Text>
          </LinearGradient>
        </Animated.View>
      </TouchableOpacity>

      {/* Instructions */}
      <View style={styles.instructions}>
        <Text style={styles.instructionText}>
          Press in case of emergency
        </Text>
        <Text style={styles.instructionSubtext}>
          Report incidents quickly to the appropriate response team
        </Text>
      </View>

      {/* Status Indicators */}
      <View style={styles.statusIndicators}>
        <View style={styles.statusIndicator}>
          <View style={[styles.statusDot, { backgroundColor: colors.health }]} />
          <Text style={styles.statusText}>Health</Text>
        </View>
        <View style={styles.statusIndicator}>
          <View style={[styles.statusDot, { backgroundColor: colors.fire }]} />
          <Text style={styles.statusText}>Fire</Text>
        </View>
        <View style={styles.statusIndicator}>
          <View style={[styles.statusDot, { backgroundColor: colors.security }]} />
          <Text style={styles.statusText}>Security</Text>
        </View>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    alignItems: 'center',
    paddingVertical: spacing.lg,
  },
  button: {
    width: 180,
    height: 180,
    borderRadius: 90,
    ...shadows.xl,
    marginBottom: spacing.lg,
  },
  buttonDisabled: {
    opacity: 0.5,
  },
  buttonInner: {
    width: '100%',
    height: '100%',
    borderRadius: 90,
    overflow: 'hidden',
  },
  gradient: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 4,
    borderColor: colors.white,
  },
  sosText: {
    fontSize: typography.fontSize['4xl'],
    fontWeight: typography.fontWeight.black,
    color: colors.white,
    marginBottom: spacing.sm,
  },
  subtitle: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: colors.white,
    marginTop: spacing.sm,
  },
  instructions: {
    alignItems: 'center',
    marginBottom: spacing.lg,
  },
  instructionText: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: colors.text,
    marginBottom: spacing.xs,
  },
  instructionSubtext: {
    fontSize: typography.fontSize.base,
    color: colors.textSecondary,
    textAlign: 'center',
    maxWidth: 250,
  },
  statusIndicators: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    width: '100%',
    paddingHorizontal: spacing.lg,
  },
  statusIndicator: {
    alignItems: 'center',
  },
  statusDot: {
    width: 12,
    height: 12,
    borderRadius: 6,
    marginBottom: spacing.xs,
  },
  statusText: {
    fontSize: typography.fontSize.sm,
    color: colors.textSecondary,
    fontWeight: typography.fontWeight.medium,
  },
});

export default QuickResponseButton;