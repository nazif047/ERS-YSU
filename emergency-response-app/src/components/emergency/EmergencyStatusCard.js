import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
} from 'react-native';
import { useTheme } from '@react-navigation/native';
import Icon from 'react-native-vector-icons/MaterialIcons';
import Card from '../common/Card';
import Button from '../common/Button';

const EmergencyStatusCard = ({ emergency, onStatusUpdate, userRole, style }) => {
  const theme = useTheme();
  const colors = theme.colors;

  const getStatusColor = (status) => {
    switch (status) {
      case 'pending':
        return '#FFA726'; // Orange
      case 'in_progress':
        return '#42A5F5'; // Blue
      case 'resolved':
        return '#66BB6A'; // Green
      case 'closed':
        return '#78909C'; // Grey
      default:
        return colors.placeholder;
    }
  };

  const getStatusIcon = (status) => {
    switch (status) {
      case 'pending':
        return 'schedule';
      case 'in_progress':
        return 'directions-run';
      case 'resolved':
        return 'check-circle';
      case 'closed':
        return 'archive';
      default:
        return 'help';
    }
  };

  const getSeverityColor = (severity) => {
    switch (severity) {
      case 'low':
        return '#66BB6A'; // Green
      case 'medium':
        return '#FFA726'; // Orange
      case 'high':
        return '#EF5350'; // Red
      case 'critical':
        return '#D32F2F'; // Dark Red
      default:
        return colors.placeholder;
    }
  };

  const canUpdateStatus = () => {
    return userRole && ['health_admin', 'fire_admin', 'security_admin', 'super_admin'].includes(userRole);
  };

  const getNextStatus = (currentStatus) => {
    switch (currentStatus) {
      case 'pending':
        return 'in_progress';
      case 'in_progress':
        return 'resolved';
      case 'resolved':
        return 'closed';
      default:
        return null;
    }
  };

  const formatTime = (timeString) => {
    if (!timeString) return 'Not set';
    const date = new Date(timeString);
    return date.toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const formatResponseTime = (minutes) => {
    if (!minutes) return 'Not recorded';
    if (minutes < 1) return 'Less than 1 minute';
    return `${minutes} minute${minutes > 1 ? 's' : ''}`;
  };

  return (
    <Card style={[styles.container, style]}>
      {/* Header */}
      <View style={styles.header}>
        <View style={styles.headerLeft}>
          <View style={[styles.typeIcon, { backgroundColor: emergency.type?.color || colors.primary }]}>
            <Icon name={emergency.type?.icon || 'emergency'} size={24} color="#FFFFFF" />
          </View>
          <View style={styles.headerInfo}>
            <Text style={[styles.typeText, { color: colors.text }]}>
              {emergency.type?.name || 'Emergency'}
            </Text>
            <Text style={[styles.locationText, { color: colors.placeholder }]}>
              {emergency.location?.name || 'Unknown Location'}
            </Text>
          </View>
        </View>
        <View style={[styles.statusBadge, { backgroundColor: getStatusColor(emergency.status) }]}>
          <Icon name={getStatusIcon(emergency.status)} size={16} color="#FFFFFF" />
          <Text style={styles.statusText}>{emergency.status?.replace('_', ' ') || 'Unknown'}</Text>
        </View>
      </View>

      {/* Description */}
      <View style={styles.section}>
        <Text style={[styles.sectionTitle, { color: colors.text }]}>Description</Text>
        <Text style={[styles.description, { color: colors.text }]}>
          {emergency.description || 'No description provided'}
        </Text>
      </View>

      {/* Severity */}
      <View style={styles.section}>
        <Text style={[styles.sectionTitle, { color: colors.text }]}>Severity</Text>
        <View style={[styles.severityBadge, { backgroundColor: getSeverityColor(emergency.severity) }]}>
          <Text style={styles.severityText}>{emergency.severity?.toUpperCase() || 'UNKNOWN'}</Text>
        </View>
      </View>

      {/* Reporter Information */}
      <View style={styles.section}>
        <Text style={[styles.sectionTitle, { color: colors.text }]}>Reported By</Text>
        <View style={styles.reporterInfo}>
          <Icon name="person" size={16} color={colors.placeholder} />
          <Text style={[styles.reporterName, { color: colors.text }]}>
            {emergency.reporter?.name || 'Unknown'}
          </Text>
          {emergency.reporter?.phone && (
            <Text style={[styles.reporterPhone, { color: colors.placeholder }]}>
              {emergency.reporter.phone}
            </Text>
          )}
        </View>
      </View>

      {/* Timeline */}
      <View style={styles.section}>
        <Text style={[styles.sectionTitle, { color: colors.text }]}>Timeline</Text>
        <ScrollView style={styles.timeline} horizontal showsHorizontalScrollIndicator={false}>
          <View style={styles.timelineItem}>
            <View style={[styles.timelineDot, { backgroundColor: colors.primary }]}>
              <Icon name="report" size={12} color="#FFFFFF" />
            </View>
            <View style={styles.timelineContent}>
              <Text style={[styles.timelineTitle, { color: colors.text }]}>Reported</Text>
              <Text style={[styles.timelineTime, { color: colors.placeholder }]}>
                {formatTime(emergency.reportedAt)}
              </Text>
            </View>
          </View>

          {emergency.timeline?.map((update, index) => (
            <View key={update.id} style={styles.timelineItem}>
              <View style={[styles.timelineDot, { backgroundColor: getStatusColor(update.status) }]}>
                <Icon name={getStatusIcon(update.status)} size={12} color="#FFFFFF" />
              </View>
              <View style={styles.timelineContent}>
                <Text style={[styles.timelineTitle, { color: colors.text }]}>{update.title}</Text>
                <Text style={[styles.timelineTime, { color: colors.placeholder }]}>
                  {formatTime(update.time)}
                </Text>
                {update.text && (
                  <Text style={[styles.timelineText, { color: colors.text }]}>{update.text}</Text>
                )}
              </View>
            </View>
          ))}
        </ScrollView>
      </View>

      {/* Response Information */}
      {canUpdateStatus() && (
        <View style={styles.section}>
          <Text style={[styles.sectionTitle, { color: colors.text }]}>Response Information</Text>
          <View style={styles.responseInfo}>
            <View style={styles.responseItem}>
              <Text style={[styles.responseLabel, { color: colors.placeholder }]}>Assigned To:</Text>
              <Text style={[styles.responseValue, { color: colors.text }]}>
                {emergency.assignedResponder?.name || 'Unassigned'}
              </Text>
            </View>
            <View style={styles.responseItem}>
              <Text style={[styles.responseLabel, { color: colors.placeholder }]}>Response Time:</Text>
              <Text style={[styles.responseValue, { color: colors.text }]}>
                {formatResponseTime(emergency.responseTime)}
              </Text>
            </View>
            <View style={styles.responseItem}>
              <Text style={[styles.responseLabel, { color: colors.placeholder }]}>Estimated Resolution:</Text>
              <Text style={[styles.responseValue, { color: colors.text }]}>
                {emergency.estimatedResolution || 'Not set'}
              </Text>
            </View>
          </View>
        </View>
      )}

      {/* Actions */}
      <View style={styles.actions}>
        {canUpdateStatus() && getNextStatus(emergency.status) && (
          <Button
            title={`Mark as ${getNextStatus(emergency.status)?.replace('_', ' ')}`}
            onPress={() => onStatusUpdate?.(emergency, getNextStatus(emergency.status))}
            style={styles.actionButton}
          />
        )}
        <Button
          title="View Details"
          variant="outline"
          onPress={() => {
            // Navigate to detailed view
          }}
          style={styles.actionButton}
        />
      </View>
    </Card>
  );
};

const styles = StyleSheet.create({
  container: {
    margin: 16,
    padding: 16,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  headerLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  typeIcon: {
    width: 48,
    height: 48,
    borderRadius: 24,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  headerInfo: {
    flex: 1,
  },
  typeText: {
    fontSize: 18,
    fontWeight: '600',
    marginBottom: 2,
  },
  locationText: {
    fontSize: 14,
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 20,
  },
  statusText: {
    color: '#FFFFFF',
    fontSize: 12,
    fontWeight: '600',
    marginLeft: 4,
  },
  section: {
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    marginBottom: 8,
  },
  description: {
    fontSize: 14,
    lineHeight: 20,
  },
  severityBadge: {
    alignSelf: 'flex-start',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
  },
  severityText: {
    color: '#FFFFFF',
    fontSize: 12,
    fontWeight: '600',
  },
  reporterInfo: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  reporterName: {
    fontSize: 14,
    fontWeight: '500',
    marginLeft: 6,
    marginRight: 12,
  },
  reporterPhone: {
    fontSize: 14,
  },
  timeline: {
    flexDirection: 'row',
  },
  timelineItem: {
    alignItems: 'flex-start',
    marginRight: 24,
    minWidth: 120,
  },
  timelineDot: {
    width: 24,
    height: 24,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 4,
  },
  timelineContent: {
    flex: 1,
  },
  timelineTitle: {
    fontSize: 12,
    fontWeight: '600',
    marginBottom: 2,
  },
  timelineTime: {
    fontSize: 10,
    marginBottom: 2,
  },
  timelineText: {
    fontSize: 11,
    lineHeight: 14,
  },
  responseInfo: {
    gap: 8,
  },
  responseItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  responseLabel: {
    fontSize: 14,
    fontWeight: '500',
  },
  responseValue: {
    fontSize: 14,
    textAlign: 'right',
    flex: 1,
  },
  actions: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 8,
  },
  actionButton: {
    flex: 1,
  },
});

export default EmergencyStatusCard;