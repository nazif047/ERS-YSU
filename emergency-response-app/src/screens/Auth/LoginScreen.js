/**
 * Login Screen
 * Yobe State University Emergency Response System
 */

import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  KeyboardAvoidingView,
  Platform,
  Alert,
  ScrollView,
} from 'react-native';
import {
  TextInput,
  Button,
  Card,
  Title,
  Paragraph,
  ActivityIndicator,
} from 'react-native-paper';
import { LinearGradient } from 'react-native-linear-gradient';
import { useFocusEffect } from '@react-navigation/native';

// Services
import { apiService, apiUtils } from '../../services/api';
import { authStorage } from '../../services/storage';

// Components
import LoadingOverlay from '../../components/common/LoadingOverlay';

// Theme
import { colors, spacing, typography, borderRadius } from '../../utils/theme';

const LoginScreen = ({ navigation }) => {
  const [formData, setFormData] = useState({
    login: '',
    password: '',
  });
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState({});
  const [showPassword, setShowPassword] = useState(false);

  // Clear form when screen focuses
  useFocusEffect(
    React.useCallback(() => {
      setFormData({ login: '', password: '' });
      setErrors({});
    }, [])
  );

  const handleInputChange = (field, value) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    // Clear error when user starts typing
    if (errors[field]) {
      setErrors(prev => ({ ...prev, [field]: '' }));
    }
  };

  const validateForm = () => {
    const newErrors = {};

    if (!formData.login.trim()) {
      newErrors.login = 'Email or School ID is required';
    } else if (!isValidLogin(formData.login)) {
      newErrors.login = 'Please enter a valid email or School ID (YSU/YYYY/XXXX)';
    }

    if (!formData.password) {
      newErrors.password = 'Password is required';
    } else if (formData.password.length < 8) {
      newErrors.password = 'Password must be at least 8 characters';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const isValidLogin = (login) => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const schoolIdRegex = /^YSU\/\d{4}\/\d{4}$/;
    return emailRegex.test(login) || schoolIdRegex.test(login);
  };

  const handleLogin = async () => {
    if (!validateForm()) {
      return;
    }

    setLoading(true);

    try {
      const response = await apiService.login(formData);
      const { success, message, data } = apiUtils.formatResponse(response);

      if (success) {
        // Store authentication data
        await authStorage.storeTokens(data);

        Alert.alert(
          'Login Successful',
          `Welcome back, ${data.user.full_name}!`,
          [
            {
              text: 'OK',
              onPress: () => {
                // Navigate based on user role
                const userRole = data.user.role;
                if (userRole === 'health_admin') {
                  navigation.reset({
                    index: 0,
                    routes: [{ name: 'HealthAdmin' }],
                  });
                } else if (userRole === 'fire_admin') {
                  navigation.reset({
                    index: 0,
                    routes: [{ name: 'FireAdmin' }],
                  });
                } else if (userRole === 'security_admin') {
                  navigation.reset({
                    index: 0,
                    routes: [{ name: 'SecurityAdmin' }],
                  });
                } else {
                  navigation.reset({
                    index: 0,
                    routes: [{ name: 'UserDashboard' }],
                  });
                }
              },
            },
          ],
          { cancelable: false }
        );
      } else {
        Alert.alert('Login Failed', message);
      }
    } catch (error) {
      const errorInfo = apiUtils.handleError(error);
      Alert.alert(
        'Login Failed',
        errorInfo.message || 'An error occurred during login'
      );
    } finally {
      setLoading(false);
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      keyboardVerticalOffset={Platform.OS === 'ios' ? 0 : 20}
    >
      <LinearGradient
        colors={[colors.primary, colors.primaryDark]}
        style={styles.gradient}
      >
        <ScrollView
          contentContainerStyle={styles.scrollContent}
          showsVerticalScrollIndicator={false}
        >
          {/* Header */}
          <View style={styles.header}>
            <Text style={styles.logoText}>YSU</Text>
            <Text style={styles.subtitle}>Emergency Response System</Text>
          </View>

          {/* Login Form */}
          <Card style={styles.card}>
            <Card.Content>
              <Title style={styles.title}>Sign In</Title>
              <Paragraph style={styles.paragraph}>
                Access your emergency response account
              </Paragraph>

              {/* Login Field */}
              <TextInput
                label="Email or School ID"
                value={formData.login}
                onChangeText={(value) => handleInputChange('login', value)}
                error={!!errors.login}
                mode="outlined"
                style={styles.input}
                left={<TextInput.Icon icon="account" />}
                placeholder="Enter email or School ID (YSU/YYYY/XXXX)"
                autoCapitalize="none"
                autoComplete="email"
                textContentType="emailAddress"
              />
              {errors.login && (
                <Text style={styles.errorText}>{errors.login}</Text>
              )}

              {/* Password Field */}
              <TextInput
                label="Password"
                value={formData.password}
                onChangeText={(value) => handleInputChange('password', value)}
                error={!!errors.password}
                mode="outlined"
                style={styles.input}
                secureTextEntry={!showPassword}
                left={<TextInput.Icon icon="lock" />}
                right={
                  <TextInput.Icon
                    icon={showPassword ? 'eye-off' : 'eye'}
                    onPress={() => setShowPassword(!showPassword)}
                  />
                }
                placeholder="Enter your password"
                autoComplete="password"
              />
              {errors.password && (
                <Text style={styles.errorText}>{errors.password}</Text>
              )}

              {/* Login Button */}
              <Button
                mode="contained"
                onPress={handleLogin}
                loading={loading}
                disabled={loading}
                style={styles.loginButton}
                contentStyle={styles.loginButtonContent}
              >
                {loading ? 'Signing In...' : 'Sign In'}
              </Button>

              {/* Register Link */}
              <View style={styles.registerLink}>
                <Text style={styles.registerText}>Don't have an account? </Text>
                <Button
                  mode="text"
                  onPress={() => navigation.navigate('Register')}
                  compact
                  labelStyle={styles.registerButtonLabel}
                >
                  Sign Up
                </Button>
              </View>
            </Card.Content>
          </Card>

          {/* Emergency Contact Information */}
          <View style={styles.emergencyInfo}>
            <Text style={styles.emergencyTitle}>Emergency Contacts</Text>
            <Text style={styles.emergencyText}>🏥 Health: 08012345679</Text>
            <Text style={styles.emergencyText}>🔥 Fire: 08012345680</Text>
            <Text style={styles.emergencyText}>🚔 Security: 08012345678</Text>
          </View>
        </ScrollView>
      </LinearGradient>

      {loading && <LoadingOverlay message="Signing in..." />}
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  gradient: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
    justifyContent: 'center',
    padding: spacing.lg,
  },
  header: {
    alignItems: 'center',
    marginBottom: spacing.xl,
  },
  logoText: {
    fontSize: typography.fontSize['4xl'],
    fontWeight: typography.fontWeight.bold,
    color: colors.white,
    marginBottom: spacing.sm,
  },
  subtitle: {
    fontSize: typography.fontSize.lg,
    color: colors.white,
    textAlign: 'center',
  },
  card: {
    borderRadius: borderRadius.lg,
    elevation: 8,
  },
  title: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.semibold,
    color: colors.text,
    marginBottom: spacing.sm,
    textAlign: 'center',
  },
  paragraph: {
    fontSize: typography.fontSize.base,
    color: colors.textSecondary,
    textAlign: 'center',
    marginBottom: spacing.lg,
  },
  input: {
    marginBottom: spacing.md,
  },
  errorText: {
    fontSize: typography.fontSize.sm,
    color: colors.error,
    marginTop: -spacing.md,
    marginBottom: spacing.md,
    marginLeft: spacing.sm,
  },
  loginButton: {
    marginTop: spacing.lg,
    borderRadius: borderRadius.md,
  },
  loginButtonContent: {
    paddingVertical: spacing.sm,
  },
  registerLink: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: spacing.lg,
  },
  registerText: {
    fontSize: typography.fontSize.base,
    color: colors.textSecondary,
  },
  registerButtonLabel: {
    fontSize: typography.fontSize.base,
    color: colors.primary,
    fontWeight: typography.fontWeight.medium,
  },
  emergencyInfo: {
    marginTop: spacing.xl,
    alignItems: 'center',
  },
  emergencyTitle: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: colors.white,
    marginBottom: spacing.md,
  },
  emergencyText: {
    fontSize: typography.fontSize.base,
    color: colors.white,
    marginBottom: spacing.sm,
  },
});

export default LoginScreen;