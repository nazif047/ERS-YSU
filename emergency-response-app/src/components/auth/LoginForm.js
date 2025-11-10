import React, { useState, useRef } from 'react';
import {
  View,
  StyleSheet,
  Alert,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
} from 'react-native';
import { useTheme } from '@react-navigation/native';
import Button from '../common/Button';
import Input from '../common/Input';
import LoadingSpinner from '../common/LoadingSpinner';
import Icon from 'react-native-vector-icons/MaterialIcons';
import { useAuth } from '../../hooks/useAuth';

const LoginForm = ({ onLoginSuccess, onRegisterPress, onForgotPasswordPress }) => {
  const theme = useTheme();
  const colors = theme.colors;
  const { login, loading } = useAuth();

  const [formData, setFormData] = useState({
    login: '',
    password: '',
  });

  const [errors, setErrors] = useState({});
  const [showPassword, setShowPassword] = useState(false);
  const [rememberMe, setRememberMe] = useState(false);

  const passwordRef = useRef(null);

  const validateForm = () => {
    const newErrors = {};

    if (!formData.login.trim()) {
      newErrors.login = 'Please enter your email or school ID';
    } else if (formData.login.includes('@')) {
      // Email validation
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(formData.login)) {
        newErrors.login = 'Please enter a valid email address';
      }
    } else {
      // School ID validation
      if (formData.login.length < 3) {
        newErrors.login = 'School ID must be at least 3 characters';
      }
    }

    if (!formData.password) {
      newErrors.password = 'Please enter your password';
    } else if (formData.password.length < 6) {
      newErrors.password = 'Password must be at least 6 characters';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleLogin = async () => {
    if (!validateForm()) return;

    try {
      const response = await login(formData, rememberMe);
      if (response.success) {
        onLoginSuccess?.(response.data);
      } else {
        Alert.alert('Login Failed', response.message || 'Invalid credentials');
      }
    } catch (error) {
      Alert.alert('Login Error', 'An error occurred during login. Please try again.');
    }
  };

  const handleInputChange = (field, value) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    // Clear error for this field
    if (errors[field]) {
      setErrors(prev => ({ ...prev, [field]: '' }));
    }
  };

  const handleForgotPassword = () => {
    onForgotPasswordPress?.();
  };

  const handleRegister = () => {
    onRegisterPress?.();
  };

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <ScrollView
        style={styles.scrollView}
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        <View style={[styles.form, { backgroundColor: colors.background }]}>
          <View style={styles.header}>
            <Icon name="emergency" size={60} color={colors.primary} />
            <Text style={[styles.title, { color: colors.text }]}>
              Emergency Response
            </Text>
            <Text style={[styles.subtitle, { color: colors.placeholder }]}>
              Sign in to report emergencies
            </Text>
          </View>

          <View style={styles.formFields}>
            <Input
              label="Email or School ID"
              value={formData.login}
              onChangeText={(value) => handleInputChange('login', value)}
              error={errors.login}
              leftIcon={
                <Icon name="person" size={20} color={colors.placeholder} />
              }
              autoCapitalize="none"
              autoCorrect={false}
              keyboardType="email-address"
              returnKeyType="next"
              onSubmitEditing={() => passwordRef.current?.focus()}
              editable={!loading}
            />

            <Input
              ref={passwordRef}
              label="Password"
              value={formData.password}
              onChangeText={(value) => handleInputChange('password', value)}
              error={errors.password}
              leftIcon={
                <Icon name="lock" size={20} color={colors.placeholder} />
              }
              secureTextEntry
              autoCapitalize="none"
              returnKeyType="done"
              onSubmitEditing={handleLogin}
              editable={!loading}
            />

            <View style={styles.options}>
              <TouchableOpacity
                style={styles.rememberMe}
                onPress={() => setRememberMe(!rememberMe)}
                disabled={loading}
              >
                <Icon
                  name={rememberMe ? 'check-box' : 'check-box-outline-blank'}
                  size={20}
                  color={rememberMe ? colors.primary : colors.placeholder}
                />
                <Text style={[styles.rememberMeText, { color: colors.text }]}>
                  Remember me
                </Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={styles.forgotPassword}
                onPress={handleForgotPassword}
                disabled={loading}
              >
                <Text style={[styles.forgotPasswordText, { color: colors.primary }]}>
                  Forgot Password?
                </Text>
              </TouchableOpacity>
            </View>
          </View>

          <View style={styles.actions}>
            <Button
              title="Sign In"
              onPress={handleLogin}
              loading={loading}
              disabled={loading}
              size="large"
              style={styles.loginButton}
            />

            <View style={styles.registerContainer}>
              <Text style={[styles.registerText, { color: colors.placeholder }]}>
                Don't have an account?{' '}
              </Text>
              <TouchableOpacity onPress={handleRegister} disabled={loading}>
                <Text style={[styles.registerLink, { color: colors.primary }]}>
                  Sign Up
                </Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </ScrollView>

      <LoadingSpinner visible={loading} text="Signing in..." overlay />
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  scrollView: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
    justifyContent: 'center',
  },
  form: {
    margin: 20,
    padding: 24,
    borderRadius: 16,
  },
  header: {
    alignItems: 'center',
    marginBottom: 32,
  },
  title: {
    fontSize: 28,
    fontWeight: 'bold',
    marginTop: 16,
    marginBottom: 8,
    textAlign: 'center',
  },
  subtitle: {
    fontSize: 16,
    textAlign: 'center',
  },
  formFields: {
    marginBottom: 24,
  },
  options: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 24,
  },
  rememberMe: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  rememberMeText: {
    fontSize: 14,
    marginLeft: 8,
  },
  forgotPassword: {
    padding: 4,
  },
  forgotPasswordText: {
    fontSize: 14,
    fontWeight: '500',
  },
  actions: {
    gap: 16,
  },
  loginButton: {
    marginBottom: 8,
  },
  registerContainer: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
  },
  registerText: {
    fontSize: 14,
  },
  registerLink: {
    fontSize: 14,
    fontWeight: '500',
  },
});

export default LoginForm;