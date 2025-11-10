/**
 * Emergency Type Selection Dialog Component
 * Yobe State University Emergency Response System
 */

import React from 'react';
import { View, Text, StyleSheet, ScrollView, Dimensions } from 'react-native';
import { Modal, Button, Title, Paragraph } from 'react-native-paper';
import { TouchableOpacity } from 'react-native-gesture-handler';
import * as Haptics from 'expo-haptics';

// Theme
import { colors, spacing, typography, borderRadius } from '../../utils/theme';
import { emergencyTypeTheme } from '../../utils/theme';

const { width: screenWidth } = Dimensions.get('window');

const EmergencyTypeDialog = ({ emergencyTypes, onSelect, onCancel }) => {
  // Handle emergency type selection
  const handleTypeSelect = (type, department) => {
    // Trigger haptic feedback
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);

    // Call onSelect with type and department info
    onSelect({
      id: type.id,
      name: type.name,
      department: department,
      icon: type.icon,
      color: type.color,
    });
  };

  // Get department styling
  const getDepartmentStyle = (department) => {
    const style = emergencyTypeTheme[department] || emergencyTypeTheme.health;
    return {
      backgroundColor: style.color,
      borderColor: style.color,
    };
  };

  // Render emergency type item
  const renderEmergencyType = (type, department) => {
    const departmentStyle = getDepartmentStyle(department);
    const emergencyIcon = type.icon || 'alert';

    return (
      <TouchableOpacity
        key={type.id}
        style={styles.emergencyTypeItem}
        onPress={() => handleTypeSelect(type, department)}
        activeOpacity={0.7}
      >
        <View style={[styles.emergencyTypeIcon, { backgroundColor: departmentStyle.backgroundColor }]}>
          <Text style={styles.emergencyTypeIconText}>
            {type.icon || getDepartmentIcon(department)}
          </Text>
        </View>

        <View style={styles.emergencyTypeContent}>
          <Text style={styles.emergencyTypeName}>{type.name}</Text>
          {type.description && (
            <Text style={styles.emergencyTypeDescription} numberOfLines={2}>
              {type.description}
            </Text>
          )}
        </View>

        <View style={styles.emergencyTypeArrow}>
          <Text style={[styles.emergencyTypeArrowText, { color: departmentStyle.backgroundColor }]}>
            →
          </Text>
        </View>
      </TouchableOpacity>
    );
  };

  // Get department icon
  const getDepartmentIcon = (department) => {
    const icons = {
      health: '🏥',
      fire: '🔥',
      security: '🚔',
    };

    return icons[department] || '🚨';
  };

  // Render department section
  const renderDepartmentSection = (departmentKey, departmentData) => {
    const types = emergencyTypes[departmentKey] || [];
    if (types.length === 0) return null;

    return (
      <View key={departmentKey} style={styles.departmentSection}>
        <View style={[styles.departmentHeader, { backgroundColor: departmentData.backgroundColor }]}>
          <Text style={styles.departmentHeaderIcon}>{departmentData.icon}</Text>
          <Text style={styles.departmentHeaderText}>{departmentData.name}</Text>
          <Text style={styles.departmentHeaderCount}>{types.length} types</Text>
        </View>

        <View style={styles.departmentContent}>
          {types.map((type) => renderEmergencyType(type, departmentKey))}
        </View>
      </View>
    );
  };

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <Title style={styles.title}>Select Emergency Type</Title>
        <Paragraph style={styles.subtitle}>
          Choose the type of emergency you want to report
        </Paragraph>
      </View>

      {/* Emergency Types */}
      <ScrollView
        style={styles.scrollContent}
        showsVerticalScrollIndicator={false}
        contentContainerStyle={styles.scrollContainer}
      >
        {Object.entries(emergencyTypes).map(([departmentKey, departmentData]) =>
          renderDepartmentSection(departmentKey, departmentData)
        )}
      </ScrollView>

      {/* Actions */}
      <View style={styles.actions}>
        <Button
          mode="outlined"
          onPress={onCancel}
          style={styles.cancelButton}
          contentStyle={styles.buttonContent}
        >
          Cancel
        </Button>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    backgroundColor: colors.white,
    borderRadius: borderRadius.lg,
    maxHeight: screenWidth * 1.4, // Responsive max height
  },
  header: {
    padding: spacing.lg,
    borderBottomWidth: 1,
    borderBottomColor: colors.gray200,
    alignItems: 'center',
  },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.semibold,
    color: colors.text,
    marginBottom: spacing.sm,
    textAlign: 'center',
  },
  subtitle: {
    fontSize: typography.fontSize.base,
    color: colors.textSecondary,
    textAlign: 'center',
    lineHeight: typography.lineHeight.normal,
  },
  scrollContent: {
    flex: 1,
  },
  scrollContainer: {
    padding: spacing.md,
  },
  departmentSection: {
    marginBottom: spacing.lg,
  },
  departmentHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    borderRadius: borderRadius.md,
    marginBottom: spacing.md,
  },
  departmentHeaderIcon: {
    fontSize: typography.fontSize.lg,
    marginRight: spacing.sm,
  },
  departmentHeaderText: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: colors.white,
    flex: 1,
  },
  departmentHeaderCount: {
    fontSize: typography.fontSize.sm,
    color: colors.white,
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    borderRadius: borderRadius.sm,
  },
  departmentContent: {
    marginLeft: spacing.xs,
  },
  emergencyTypeItem: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: spacing.md,
    backgroundColor: colors.gray50,
    borderRadius: borderRadius.md,
    marginBottom: spacing.sm,
    borderWidth: 1,
    borderColor: colors.gray200,
  },
  emergencyTypeIcon: {
    width: 50,
    height: 50,
    borderRadius: 25,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: spacing.md,
  },
  emergencyTypeIconText: {
    fontSize: 24,
    color: colors.white,
  },
  emergencyTypeContent: {
    flex: 1,
  },
  emergencyTypeName: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: colors.text,
    marginBottom: spacing.xs,
  },
  emergencyTypeDescription: {
    fontSize: typography.fontSize.sm,
    color: colors.textSecondary,
    lineHeight: typography.lineHeight.normal,
  },
  emergencyTypeArrow: {
    marginLeft: spacing.sm,
  },
  emergencyTypeArrowText: {
    fontSize: 20,
    fontWeight: typography.fontWeight.bold,
  },
  actions: {
    padding: spacing.lg,
    borderTopWidth: 1,
    borderTopColor: colors.gray200,
  },
  cancelButton: {
    borderRadius: borderRadius.md,
  },
  buttonContent: {
    paddingVertical: spacing.sm,
  },
});

export default EmergencyTypeDialog;