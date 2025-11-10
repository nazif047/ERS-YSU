import React from 'react';
import { TouchableOpacity, Text, StyleSheet, ActivityIndicator, ViewStyle, TextStyle } from 'react-native';
import { useTheme } from '@react-navigation/native';

const Button = ({
  title,
  onPress,
  variant = 'primary',
  size = 'medium',
  loading = false,
  disabled = false,
  icon,
  iconPosition = 'left',
  style,
  textStyle,
  ...props
}) => {
  const theme = useTheme();
  const colors = theme.colors;

  const getButtonStyle = () => {
    const baseStyle: ViewStyle = {
      borderRadius: 8,
      alignItems: 'center',
      justifyContent: 'center',
      flexDirection: 'row',
    };

    // Size styles
    const sizeStyles = {
      small: {
        paddingHorizontal: 12,
        paddingVertical: 8,
        minHeight: 36,
      },
      medium: {
        paddingHorizontal: 16,
        paddingVertical: 12,
        minHeight: 44,
      },
      large: {
        paddingHorizontal: 24,
        paddingVertical: 16,
        minHeight: 52,
      },
    };

    // Variant styles
    const variantStyles = {
      primary: {
        backgroundColor: colors.primary,
        borderWidth: 0,
      },
      secondary: {
        backgroundColor: 'transparent',
        borderWidth: 1,
        borderColor: colors.primary,
      },
      danger: {
        backgroundColor: colors.error || '#FF5252',
        borderWidth: 0,
      },
      outline: {
        backgroundColor: 'transparent',
        borderWidth: 1,
        borderColor: colors.border,
      },
      ghost: {
        backgroundColor: 'transparent',
        borderWidth: 0,
      },
    };

    return {
      ...baseStyle,
      ...sizeStyles[size],
      ...variantStyles[variant],
      opacity: disabled || loading ? 0.6 : 1,
    };
  };

  const getTextStyle = () => {
    const baseStyle: TextStyle = {
      fontWeight: '600',
      textAlign: 'center',
    };

    // Size styles
    const sizeStyles = {
      small: {
        fontSize: 14,
        lineHeight: 20,
      },
      medium: {
        fontSize: 16,
        lineHeight: 24,
      },
      large: {
        fontSize: 18,
        lineHeight: 26,
      },
    };

    // Variant styles
    const variantStyles = {
      primary: {
        color: colors.text || '#FFFFFF',
      },
      secondary: {
        color: colors.primary,
      },
      danger: {
        color: colors.text || '#FFFFFF',
      },
      outline: {
        color: colors.text,
      },
      ghost: {
        color: colors.primary,
      },
    };

    return {
      ...baseStyle,
      ...sizeStyles[size],
      ...variantStyles[variant],
    };
  };

  const renderContent = () => {
    if (loading) {
      return (
        <>
          <ActivityIndicator
            size="small"
            color={variant === 'primary' || variant === 'danger' ? '#FFFFFF' : colors.primary}
          />
          <Text style={[getTextStyle(), { marginLeft: 8 }, textStyle]}>
            {title}
          </Text>
        </>
      );
    }

    const content = [
      icon && iconPosition === 'left' && (
        <View key="left-icon" style={{ marginRight: 8 }}>
          {icon}
        </View>
      ),
      <Text key="text" style={[getTextStyle(), textStyle]}>
        {title}
      </Text>,
      icon && iconPosition === 'right' && (
        <View key="right-icon" style={{ marginLeft: 8 }}>
          {icon}
        </View>
      ),
    ].filter(Boolean);

    return content;
  };

  return (
    <TouchableOpacity
      style={[getButtonStyle(), style]}
      onPress={onPress}
      disabled={disabled || loading}
      activeOpacity={0.8}
      {...props}
    >
      {renderContent()}
    </TouchableOpacity>
  );
};

export default Button;