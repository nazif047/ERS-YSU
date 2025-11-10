import React, { useState } from 'react';
import { View, StyleSheet, Alert } from 'react-native';
import { useTheme } from '@react-navigation/native';
import RegisterForm from '../../components/auth/RegisterForm';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import { useAuth } from '../../hooks/useAuth';

const RegisterScreen = ({ navigation }) => {
  const theme = useTheme();
  const colors = theme.colors;
  const { loading } = useAuth();

  const handleRegisterSuccess = (userData) => {
    Alert.alert(
      'Registration Successful',
      'Your account has been created successfully. You can now log in to access the emergency response system.',
      [
        {
          text: 'OK',
          onPress: () => {
            navigation.replace('Login');
          },
        },
      ]
    );
  };

  const handleLoginPress = () => {
    navigation.navigate('Login');
  };

  return (
    <View style={[styles.container, { backgroundColor: colors.background }]}>
      <View style={styles.content}>
        <RegisterForm
          onRegisterSuccess={handleRegisterSuccess}
          onLoginPress={handleLoginPress}
        />
      </View>
      <LoadingSpinner visible={loading} text="Creating account..." overlay />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  content: {
    flex: 1,
  },
});

export default RegisterScreen;