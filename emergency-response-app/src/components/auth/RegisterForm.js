import React, { useState, useRef } from 'react';
import {
  View,
  StyleSheet,
  Alert,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  TouchableOpacity,
} from 'react-native';
import { useTheme } from '@react-navigation/native';
import Button from '../common/Button';
import Input from '../common/Input';
import LoadingSpinner from '../common/LoadingSpinner';
import Icon from 'react-native-vector-icons/MaterialIcons';
import { useAuth } from '../../hooks/useAuth';

const RegisterForm = ({ onRegisterSuccess, onLoginPress }) => {
  const theme = useTheme();
  const colors = theme.colors;
  const { register, loading } = useAuth();

  const [formData, setFormData] = useState({
    fullName: '',
    email: '',
    schoolId: '',
    phone: '',
    department: '',
    password: '',
    confirmPassword: '',
    acceptTerms: false,
  });

  const [errors, setErrors] = useState({});
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  const emailRef = useRef(null);
  const schoolIdRef = useRef(null);
  const phoneRef = useRef(null);
  const passwordRef = useRef(null);
  const confirmPasswordRef = useRef(null);

  const departments = [
    { label: 'Academic', value: 'academic' },
    { label: 'Administration', value: 'admin' },
    { label: 'Health Services', value: 'health' },
    { label: 'Security', value: 'security' },
    { label: 'Technical', value: 'technical' },
    { label: 'Other', value: 'other' },
  ];

  const validateForm = () => {
    const newErrors = {};

    if (!formData.fullName.trim()) {
      newErrors.fullName = 'Full name is required';
    } else if (formData.fullName.length < 2) {
      newErrors.fullName = 'Full name must be at least 2 characters';
    }

    if (formData.email) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(formData.email)) {
        newErrors.email = 'Please enter a valid email address';
      }
    }

    if (!formData.schoolId.trim()) {
      newErrors.schoolId = 'School ID is required';
    } else if (formData.schoolId.length < 3) {
      newErrors.schoolId = 'School ID must be at least 3 characters';
    }

    if (!formData.phone.trim()) {
      newErrors.phone = 'Phone number is required';
    } else {
      const phoneRegex = /^[\+]?[0-9]{10,15}$/;
      if (!phoneRegex.test(formData.phone.replace(/[\s\-\(\)]/g, ''))) {
        newErrors.phone = 'Please enter a valid phone number';
      }
    }

    if (!formData.department) {
      newErrors.department = 'Please select your department';
    }

    if (!formData.password) {
      newErrors.password = 'Password is required';
    } else if (formData.password.length < 8) {
      newErrors.password = 'Password must be at least 8 characters';
    } else if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/.test(formData.password)) {
      newErrors.password = 'Password must contain uppercase, lowercase, number, and special character';
    }

    if (!formData.confirmPassword) {
      newErrors.confirmPassword = 'Please confirm your password';
    } else if (formData.password !== formData.confirmPassword) {
      newErrors.confirmPassword = 'Passwords do not match';
    }

    if (!formData.acceptTerms) {
      newErrors.acceptTerms = 'You must accept the terms and conditions';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleRegister = async () => {
    if (!validateForm()) return;

    try {
      const response = await register(formData);
      if (response.success) {
        onRegisterSuccess?.(response.data);
      } else {
        Alert.alert('Registration Failed', response.message || 'Registration failed. Please try again.');
      }
    } catch (error) {
      Alert.alert('Registration Error', 'An error occurred during registration. Please try again.');
    }
  };

  const handleInputChange = (field, value) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    // Clear error for this field
    if (errors[field]) {
      setErrors(prev => ({ ...prev, [field]: '' }));
    }
  };

  const handleLogin = () => {
    onLoginPress?.();
  };

  const handleTermsPress = () => {
    Alert.alert(
      'Terms and Conditions',
      'By using this Emergency Response System, you agree to:\n\n' +
      '1. Provide accurate information during registration\n' +
      '2. Use the system only for legitimate emergency situations\n' +
      '3. Not misuse the panic button or make false reports\n' +
      '4. Follow university emergency protocols\n' +
      '5. Respect the privacy and safety of others\n\n' +
      'Violation of these terms may result in account suspension or disciplinary action.',
      [{ text: 'I Understand' }]
    );
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
              Create Account
            </Text>
            <Text style={[styles.subtitle, { color: colors.placeholder }]}>
              Join the Emergency Response System
            </Text>
          </View>

          <View style={styles.formFields}>
            <Input
              label="Full Name"
              value={formData.fullName}
              onChangeText={(value) => handleInputChange('fullName', value)}
              error={errors.fullName}
              leftIcon={
                <Icon name="person" size={20} color={colors.placeholder} />
              }
              autoCapitalize="words"
              autoCorrect={true}
              returnKeyType="next"
              onSubmitEditing={() => emailRef.current?.focus()}
              editable={!loading}
            />

            <Input
              ref={emailRef}
              label="Email (Optional)"
              value={formData.email}
              onChangeText={(value) => handleInputChange('email', value)}
              error={errors.email}
              leftIcon={
                <Icon name="email" size={20} color={colors.placeholder} />
              }
              autoCapitalize="none"
              autoCorrect={false}
              keyboardType="email-address"
              returnKeyType="next"
              onSubmitEditing={() => schoolIdRef.current?.focus()}
              editable={!loading}
            />

            <Input
              ref={schoolIdRef}
              label="School ID"
              value={formData.schoolId}
              onChangeText={(value) => handleInputChange('schoolId', value)}
              error={errors.schoolId}
              leftIcon={
                <Icon name="school" size={20} color={colors.placeholder} />
              }
              autoCapitalize="characters"
              autoCorrect={false}
              returnKeyType="next"
              onSubmitEditing={() => phoneRef.current?.focus()}
              editable={!loading}
              placeholder="e.g., YSU/2023/0001"
            />

            <Input
              ref={phoneRef}
              label="Phone Number"
              value={formData.phone}
              onChangeText={(value) => handleInputChange('phone', value)}
              error={errors.phone}
              leftIcon={
                <Icon name="phone" size={20} color={colors.placeholder} />
              }
              keyboardType="phone-pad"
              returnKeyType="next"
              onSubmitEditing={() => passwordRef.current?.focus()}
              editable={!loading}
              placeholder="+2348012345678"
            />

            <View style={styles.departmentContainer}>
              <Text style={[styles.label, { color: colors.text }]}>Department</Text>
              <View style={styles.departmentButtons}>
                {departments.map((dept) => (
                  <TouchableOpacity
                    key={dept.value}
                    style={[
                      styles.departmentButton,
                      {
                        backgroundColor: formData.department === dept.value ? colors.primary : colors.card,
                        borderColor: colors.border,
                      },
                    ]}
                    onPress={() => handleInputChange('department', dept.value)}
                    disabled={loading}
                  >
                    <Text
                      style={[
                        styles.departmentButtonText,
                        {
                          color: formData.department === dept.value ? '#FFFFFF' : colors.text,
                        },
                      ]}
                    >
                      {dept.label}
                    </Text>
                  </TouchableOpacity>
                ))}
              </View>
              {errors.department && (
                <Text style={[styles.errorText, { color: colors.error }]}>
                  {errors.department}
                </Text>
              )}
            </View>

            <Input
              ref={passwordRef}
              label="Password"
              value={formData.password}
              onChangeText={(value) => handleInputChange('password', value)}
              error={errors.password}
              leftIcon={
                <Icon name="lock" size={20} color={colors.placeholder} />
              }
              secureTextEntry={!showPassword}
              autoCapitalize="none"
              returnKeyType="next"
              onSubmitEditing={() => confirmPasswordRef.current?.focus()}
              editable={!loading}
            />

            <Input
              ref={confirmPasswordRef}
              label="Confirm Password"
              value={formData.confirmPassword}
              onChangeText={(value) => handleInputChange('confirmPassword', value)}
              error={errors.confirmPassword}
              leftIcon={
                <Icon name="lock" size={20} color={colors.placeholder} />
              }
              secureTextEntry={!showConfirmPassword}
              autoCapitalize="none"
              returnKeyType="done"
              onSubmitEditing={handleRegister}
              editable={!loading}
            />

            <View style={styles.termsContainer}>
              <TouchableOpacity
                style={styles.checkboxContainer}
                onPress={() => handleInputChange('acceptTerms', !formData.acceptTerms)}
                disabled={loading}
              >
                <Icon
                  name={formData.acceptTerms ? 'check-box' : 'check-box-outline-blank'}
                  size={24}
                  color={formData.acceptTerms ? colors.primary : colors.placeholder}
                />
                <Text style={[styles.termsText, { color: colors.text }]}>
                  I accept the{' '}
                  <Text style={[styles.termsLink, { color: colors.primary }]} onPress={handleTermsPress}>
                    Terms and Conditions
                  </Text>
                </Text>
              </TouchableOpacity>
              {errors.acceptTerms && (
                <Text style={[styles.errorText, { color: colors.error }]}>
                  {errors.acceptTerms}
                </Text>
              )}
            </View>
          </View>

          <View style={styles.actions}>
            <Button
              title="Create Account"
              onPress={handleRegister}
              loading={loading}
              disabled={loading}
              size="large"
              style={styles.registerButton}
            />

            <View style={styles.loginContainer}>
              <Text style={[styles.loginText, { color: colors.placeholder }]}>
                Already have an account?{' '}
              </Text>
              <TouchableOpacity onPress={handleLogin} disabled={loading}>
                <Text style={[styles.loginLink, { color: colors.primary }]}>
                  Sign In
                </Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </ScrollView>

      <LoadingSpinner visible={loading} text="Creating account..." overlay />
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
  departmentContainer: {
    marginBottom: 16,
  },
  label: {
    fontSize: 14,
    fontWeight: '500',
    marginBottom: 6,
  },
  departmentButtons: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginBottom: 4,
  },
  departmentButton: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 20,
    borderWidth: 1,
  },
  departmentButtonText: {
    fontSize: 12,
    fontWeight: '500',
  },
  termsContainer: {
    marginBottom: 16,
  },
  checkboxContainer: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginTop: 8,
  },
  termsText: {
    fontSize: 14,
    marginLeft: 8,
    flex: 1,
    lineHeight: 20,
  },
  termsLink: {
    textDecorationLine: 'underline',
  },
  errorText: {
    fontSize: 12,
    marginTop: 4,
  },
  actions: {
    gap: 16,
  },
  registerButton: {
    marginBottom: 8,
  },
  loginContainer: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
  },
  loginText: {
    fontSize: 14,
  },
  loginLink: {
    fontSize: 14,
    fontWeight: '500',
  },
});

export default RegisterForm;