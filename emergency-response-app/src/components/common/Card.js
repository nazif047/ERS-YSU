import React from 'react';
import {
  View,
  StyleSheet,
  TouchableOpacity,
  ViewStyle,
} from 'react-native';
import { useTheme } from '@react-navigation/native';

const Card = ({
  children,
  variant = 'elevated',
  style,
  onPress,
  padding = 16,
  margin = 0,
  radius = 8,
  shadow = true,
  ...props
}) => {
  const theme = useTheme();
  const colors = theme.colors;

  const getCardStyle = (): ViewStyle => {
    const baseStyle: ViewStyle = {
      backgroundColor: colors.card,
      borderRadius: radius,
      padding,
      margin,
    };

    // Variant styles
    const variantStyles = {
      elevated: {
        shadowColor: '#000',
        shadowOffset: {
          width: 0,
          height: 2,
        },
        shadowOpacity: 0.1,
        shadowRadius: 3.84,
        elevation: 5,
      },
      outlined: {
        borderWidth: 1,
        borderColor: colors.border,
      },
      filled: {
        backgroundColor: colors.background,
      },
    };

    const shadowStyle = shadow ? variantStyles[variant] : {};

    return {
      ...baseStyle,
      ...shadowStyle,
    };
  };

  const CardComponent = onPress ? TouchableOpacity : View;

  return (
    <CardComponent
      style={[getCardStyle(), style]}
      onPress={onPress}
      activeOpacity={onPress ? 0.8 : 1}
      {...props}
    >
      {children}
    </CardComponent>
  );
};

export default Card;