import React, { useState, forwardRef, useImperativeHandle } from 'react';
import {
  TextInput,
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ViewStyle,
  TextInputProps,
} from 'react-native';
import { useTheme } from '@react-navigation/native';
import Icon from 'react-native-vector-icons/MaterialIcons';

const Input = forwardRef(
  (
    {
      label,
      error,
      helperText,
      leftIcon,
      rightIcon,
      onRightIconPress,
      secureTextEntry,
      style,
      containerStyle,
      errorStyle,
      helperTextStyle,
      ...props
    },
    ref
  ) => {
    const theme = useTheme();
    const colors = theme.colors;
    const [isFocused, setIsFocused] = useState(false);
    const [showPassword, setShowPassword] = useState(false);

    const inputRef = React.useRef(null);

    useImperativeHandle(ref, () => ({
      focus: () => inputRef.current?.focus(),
      blur: () => inputRef.current?.blur(),
      clear: () => inputRef.current?.clear(),
      isFocused: () => isFocused,
    }));

    const getInputStyle = (): ViewStyle => {
      return {
        borderWidth: 1,
        borderRadius: 8,
        paddingHorizontal: 16,
        paddingVertical: 12,
        fontSize: 16,
        color: colors.text,
        backgroundColor: colors.background,
        borderColor: error
          ? colors.error
          : isFocused
          ? colors.primary
          : colors.border,
        flexDirection: 'row',
        alignItems: 'center',
        minHeight: 48,
      };
    };

    const handleFocus = () => {
      setIsFocused(true);
      props.onFocus?.();
    };

    const handleBlur = () => {
      setIsFocused(false);
      props.onBlur?.();
    };

    const togglePasswordVisibility = () => {
      setShowPassword(!showPassword);
    };

    const renderRightIcon = () => {
      if (secureTextEntry) {
        return (
          <TouchableOpacity
            onPress={togglePasswordVisibility}
            style={styles.iconButton}
            hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
          >
            <Icon
              name={showPassword ? 'visibility-off' : 'visibility'}
              size={20}
              color={colors.placeholder}
            />
          </TouchableOpacity>
        );
      }

      if (rightIcon) {
        return (
          <TouchableOpacity
            onPress={onRightIconPress}
            style={styles.iconButton}
            disabled={!onRightIconPress}
            hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
          >
            {rightIcon}
          </TouchableOpacity>
        );
      }

      return null;
    };

    return (
      <View style={[styles.container, containerStyle]}>
        {label && (
          <Text style={[styles.label, { color: colors.text }]}>{label}</Text>
        )}

        <View style={getInputStyle()}>
          {leftIcon && (
            <View style={styles.leftIcon}>
              {leftIcon}
            </View>
          )}

          <TextInput
            ref={inputRef}
            style={[
              styles.input,
              {
                color: colors.text,
                flex: 1,
                marginLeft: leftIcon ? 8 : 0,
                marginRight: (secureTextEntry || rightIcon) ? 8 : 0,
              },
            ]}
            placeholderTextColor={colors.placeholder}
            onFocus={handleFocus}
            onBlur={handleBlur}
            secureTextEntry={secureTextEntry && !showPassword}
            {...props}
          />

          {renderRightIcon()}
        </View>

        {error && (
          <Text style={[styles.errorText, { color: colors.error }, errorStyle]}>
            {error}
          </Text>
        )}

        {helperText && !error && (
          <Text style={[styles.helperText, { color: colors.placeholder }, helperTextStyle]}>
            {helperText}
          </Text>
        )}
      </View>
    );
  }
);

const styles = StyleSheet.create({
  container: {
    marginBottom: 16,
  },
  label: {
    fontSize: 14,
    fontWeight: '500',
    marginBottom: 6,
  },
  input: {
    fontSize: 16,
    padding: 0,
  },
  leftIcon: {
    marginRight: 8,
  },
  iconButton: {
    padding: 4,
  },
  errorText: {
    fontSize: 12,
    marginTop: 4,
  },
  helperText: {
    fontSize: 12,
    marginTop: 4,
  },
});

Input.displayName = 'Input';

export default Input;