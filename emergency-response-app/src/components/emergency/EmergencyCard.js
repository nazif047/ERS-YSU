/**
 * Emergency Card Component
 * Yobe State University Emergency Response System
 */

import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { Card, Chip, Avatar } from 'react-native-paper';
import { Icon } from 'react-native-vector-icons/MaterialCommunityIcons';
import { format } from 'date-fns';

// Theme
import { colors, spacing, typography, borderRadius, shadows } from '../../utils/theme';
import { statusTheme, emergencyTypeTheme, severityTheme } from '../../utils/theme';

const EmergencyCard = ({ emergency, onPress, showActions = true }) => {
  // Get status styling
  const getStatusStyle = (status) => {
    const style = statusTheme[status] || statusTheme.pending;
    return {
      backgroundColor: style.backgroundColor,
      color: style.color,
      borderColor: style.color,
    };
  };

  // Get severity styling
  const getSeverityStyle = (severity) => {
    const style = severityTheme[severity] || severityTheme.medium;
    return {
      backgroundColor: style.backgroundColor,
      color: style.color,
    };
  };

  // Get emergency type styling
  const getEmergencyTypeStyle = (department) => {
    const style = emergencyTypeTheme[department] || emergencyTypeTheme.health;
    return {
      backgroundColor: style.backgroundColor,
      color: style.color,
    };
  };

  // Format relative time
  const formatRelativeTime = (timestamp) => {
    if (!timestamp) return 'Unknown time';

    try {
      const date = new Date(timestamp);
      const now = new Date();
      const diffInMinutes = Math.floor((now - date) / 60000);

      if (diffInMinutes < 1) return 'Just now';
      if (diffInMinutes < 60) return `${diffInMinutes} min ago`;

      const diffInHours = Math.floor(diffInMinutes / 60);
      if (diffInHours < 24) return `${diffInHours} hour${diffInHours > 1 ? 's' : ''} ago`;

      const diffInDays = Math.floor(diffInHours / 24);
      return `${diffInDays} day${diffInDays > 1 ? 's' : ''} ago`;
    } catch (error) {
      return 'Unknown time';
    }
  };

  // Get emergency icon
  const getEmergencyIcon = (icon, department) => {
    if (icon) return icon;

    const icons = {
      health: 'medical-bag',
      fire: 'fire',
      security: 'shield-account',
    };

    return icons[department] || 'alert';
  };

  // Get severity icon
  const getSeverityIcon = (severity) => {
    const icons = {
      low: 'check-circle',
      medium: 'alert-circle',
      high: 'alert',
      critical: 'alert-octagon',
    };

    return icons[severity] || 'alert-circle';
  };

  return (
    <TouchableOpacity onPress={onPress} disabled={!onPress}>
      <Card style={[styles.card, shadows.md]}>
        <Card.Content>
          {/* Header */}
          <View style={styles.header}>
            <View style={styles.headerLeft}>
              <Avatar.Icon
                size={40}
                icon={getEmergencyIcon(emergency.emergency_icon, emergency.emergency_department)}
                style={[
                  styles.typeIcon,
                  getEmergencyTypeStyle(emergency.emergency_department)
                ]}
                color={getEmergencyTypeStyle(emergency.emergency_department).color}
              />
              <View style={styles.titleContainer}>
                <Text style={styles.emergencyType}>{emergency.emergency_type}</Text>
                <Text style={styles.location}>{emergency.location_name}</Text>
              </View>
            </View>
            <View style={styles.statusContainer}>
              <Chip
                style={[styles.statusChip, getStatusStyle(emergency.status)]}
                textStyle={[
                  styles.statusText,
                  { color: getStatusStyle(emergency.status).color }
                ]}
              >
                {emergency.status.replace('_', ' ').toUpperCase()}
              </Chip>
            </View>
          </View>

          {/* Description */}
          <Text style={styles.description} numberOfLines={2}>
            {emergency.description}
          </Text>

          {/* Metadata */}
          <View style={styles.metadata}>
            <View style={styles.metadataLeft}>
              <Chip
                style={[styles.severityChip, getSeverityStyle(emergency.severity)]}
                textStyle={[
                  styles.severityText,
                  { color: getSeverityStyle(emergency.severity).color }
                ]}
              >
                <Icon
                  name={getSeverityIcon(emergency.severity)}
                  size={12}
                  color={getSeverityStyle(emergency.severity).color}
                  style={styles.severityIcon}
                />
                {emergency.severity.toUpperCase()}
              </Chip>
              <Text style={styles.reportedBy}>
                By {emergency.reporter_name}
              </Text>
            </View>
            <View style={styles.metadataRight}>
              <Text style={styles.time}>
                {formatRelativeTime(emergency.reported_at)}
              </Text>
            </View>
          </View>

          {/* Footer */}
          {(emergency.assigned_to_name || emergency.resolved_at) && (
            <View style={styles.footer}>
              {emergency.assigned_to_name && (
                <View style={styles.assignedTo}>
                  <Icon name="account-check" size={16} color={colors.info} />
                  <Text style={styles.assignedToText}>
                    Assigned to {emergency.assigned_to_name}
                  </Text>
                </View>
              )}
              {emergency.resolved_at && (
                <View style={styles.resolvedAt}>
                  <Icon name="check-circle" size={16} color={colors.success} />
                  <Text style={styles.resolvedAtText}>
                    Resolved {formatRelativeTime(emergency.resolved_at)}
                  </Text>
                </View>
              )}
            </View>
          )}
        </Card.Content>
      </Card>
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  card: {
    marginBottom: spacing.md,
    borderRadius: borderRadius.lg,
    backgroundColor: colors.white,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: spacing.md,
  },
  headerLeft: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    flex: 1,
  },
  typeIcon: {
    marginRight: spacing.md,
  },
  titleContainer: {
    flex: 1,
  },
  emergencyType: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: colors.text,
    marginBottom: spacing.xs,
  },
  location: {
    fontSize: typography.fontSize.sm,
    color: colors.textSecondary,
  },
  statusContainer: {
    marginLeft: spacing.sm,
  },
  statusChip: {
    height: 28,
  },
  statusText: {
    fontSize: typography.fontSize.xs,
    fontWeight: typography.fontWeight.bold,
  },
  description: {
    fontSize: typography.fontSize.sm,
    color: colors.text,
    lineHeight: typography.lineHeight.normal,
    marginBottom: spacing.md,
  },
  metadata: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.sm,
  },
  metadataLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  severityChip: {
    height: 24,
    marginRight: spacing.sm,
  },
  severityText: {
    fontSize: typography.fontSize.xs,
    fontWeight: typography.fontWeight.bold,
  },
  severityIcon: {
    marginRight: 2,
  },
  reportedBy: {
    fontSize: typography.fontSize.xs,
    color: colors.textSecondary,
    flex: 1,
  },
  metadataRight: {
    alignItems: 'flex-end',
  },
  time: {
    fontSize: typography.fontSize.xs,
    color: colors.textLight,
  },
  footer: {
    borderTopWidth: 1,
    borderTopColor: colors.gray200,
    paddingTop: spacing.sm,
    marginTop: spacing.sm,
  },
  assignedTo: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: spacing.xs,
  },
  assignedToText: {
    fontSize: typography.fontSize.xs,
    color: colors.info,
    marginLeft: spacing.xs,
  },
  resolvedAt: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  resolvedAtText: {
    fontSize: typography.fontSize.xs,
    color: colors.success,
    marginLeft: spacing.xs,
  },
});

export default EmergencyCard;