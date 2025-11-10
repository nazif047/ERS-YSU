/**
 * User Dashboard Screen
 * Yobe State University Emergency Response System
 */

import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  RefreshControl,
  Alert,
  TouchableOpacity,
} from 'react-native';
import {
  Card,
  Title,
  Paragraph,
  Button,
  Avatar,
  Divider,
  Chip,
  Badge,
  FAB,
  Portal,
  Modal,
} from 'react-native-paper';
import { LinearGradient } from 'react-native-linear-gradient';
import { useFocusEffect } from '@react-navigation/native';

// Components
import QuickResponseButton from '../../components/emergency/QuickResponseButton';
import EmergencyCard from '../../components/emergency/EmergencyCard';
import LoadingOverlay from '../../components/common/LoadingOverlay';
import EmergencyTypeDialog from '../../components/emergency/EmergencyTypeDialog';

// Services
import { apiService, apiUtils } from '../../services/api';
import { authStorage } from '../../services/storage';

// Theme
import { colors, spacing, typography, borderRadius, shadows } from '../../utils/theme';
import { statusTheme, emergencyTypeTheme, severityTheme } from '../../utils/theme';

const UserDashboardScreen = ({ navigation }) => {
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [userData, setUserData] = useState(null);
  const [statistics, setStatistics] = useState(null);
  const [recentEmergencies, setRecentEmergencies] = useState([]);
  const [emergencyTypes, setEmergencyTypes] = useState([]);
  const [showEmergencyDialog, setShowEmergencyDialog] = useState(false);

  // Load dashboard data
  const loadDashboardData = useCallback(async () => {
    try {
      // Get user profile with statistics
      const profileResponse = await apiService.getProfile();
      const { success, data } = apiUtils.formatResponse(profileResponse);

      if (success) {
        setUserData(data.user);
        setStatistics(data.statistics);
        setRecentEmergencies(data.recent_emergencies || []);
      }

      // Get emergency types
      const typesResponse = await apiService.getEmergencyTypes();
      const typesResult = apiUtils.formatResponse(typesResponse);

      if (typesResult.success) {
        setEmergencyTypes(typesResult.data.emergency_types);
      }
    } catch (error) {
      const errorInfo = apiUtils.handleError(error);
      console.error('Dashboard loading error:', errorInfo);
      Alert.alert('Error', 'Failed to load dashboard data');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  // Initial load and focus effect
  useFocusEffect(
    useCallback(() => {
      setLoading(true);
      loadDashboardData();
    }, [loadDashboardData])
  );

  // Pull to refresh
  const handleRefresh = useCallback(() => {
    setRefreshing(true);
    loadDashboardData();
  }, [loadDashboardData]);

  // Handle emergency report
  const handleEmergencyReport = () => {
    setShowEmergencyDialog(true);
  };

  // Handle emergency type selection
  const handleEmergencyTypeSelect = (emergencyType) => {
    setShowEmergencyDialog(false);
    navigation.navigate('LocationSelection', {
      emergencyType,
    });
  };

  // Navigate to emergency details
  const handleEmergencyPress = (emergency) => {
    navigation.navigate('EmergencyDetail', { emergencyId: emergency.id });
  };

  // Get emergency status style
  const getStatusStyle = (status) => {
    return statusTheme[status] || statusTheme.pending;
  };

  // Get emergency severity style
  const getSeverityStyle = (severity) => {
    return severityTheme[severity] || severityTheme.medium;
  };

  // Handle logout
  const handleLogout = () => {
    Alert.alert(
      'Logout',
      'Are you sure you want to logout?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Logout',
          style: 'destructive',
          onPress: async () => {
            try {
              await authStorage.clearAuthData();
              navigation.reset({
                index: 0,
                routes: [{ name: 'Login' }],
              });
            } catch (error) {
              console.error('Logout error:', error);
              Alert.alert('Error', 'Failed to logout');
            }
          },
        },
      ]
    );
  };

  // Navigate to profile
  const handleProfile = () => {
    navigation.navigate('Profile');
  };

  if (loading) {
    return <LoadingOverlay message="Loading dashboard..." />;
  }

  return (
    <View style={styles.container}>
      {/* Header */}
      <LinearGradient
        colors={[colors.primary, colors.primaryDark]}
        style={styles.header}
      >
        <View style={styles.headerContent}>
          <View style={styles.userInfo}>
            <Avatar.Text
              size={60}
              label={userData?.full_name?.charAt(0)?.toUpperCase() || 'U'}
              style={styles.avatar}
              labelStyle={styles.avatarLabel}
            />
            <View style={styles.userDetails}>
              <Text style={styles.userName}>{userData?.full_name}</Text>
              <Text style={styles.userRole}>
                {userData?.role?.replace('_', ' ').toUpperCase()}
              </Text>
              <Text style={styles.userDepartment}>
                {userData?.department || 'No Department'}
              </Text>
            </View>
          </View>
          <View style={styles.headerActions}>
            <TouchableOpacity onPress={handleProfile} style={styles.actionButton}>
              <Avatar.Icon size={40} icon="account" style={styles.actionAvatar} />
            </TouchableOpacity>
            <TouchableOpacity onPress={handleLogout} style={styles.actionButton}>
              <Avatar.Icon size={40} icon="logout" style={styles.actionAvatar} />
            </TouchableOpacity>
          </View>
        </View>
      </LinearGradient>

      {/* Main Content */}
      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={handleRefresh}
            colors={[colors.primary]}
          />
        }
      >
        {/* Quick Response Button */}
        <QuickResponseButton onPress={handleEmergencyReport} />

        {/* Statistics Cards */}
        {statistics && (
          <View style={styles.statsContainer}>
            <Title style={styles.sectionTitle}>Your Statistics</Title>
            <View style={styles.statsGrid}>
              <Card style={styles.statCard}>
                <Card.Content style={styles.statContent}>
                  <Text style={styles.statNumber}>{statistics.pending_emergencies}</Text>
                  <Text style={styles.statLabel}>Pending</Text>
                </Card.Content>
              </Card>
              <Card style={styles.statCard}>
                <Card.Content style={styles.statContent}>
                  <Text style={styles.statNumber}>{statistics.active_emergencies}</Text>
                  <Text style={styles.statLabel}>Active</Text>
                </Card.Content>
              </Card>
              <Card style={styles.statCard}>
                <Card.Content style={styles.statContent}>
                  <Text style={styles.statNumber}>{statistics.resolved_today}</Text>
                  <Text style={styles.statLabel}>Resolved Today</Text>
                </Card.Content>
              </Card>
              <Card style={styles.statCard}>
                <Card.Content style={styles.statContent}>
                  <Text style={styles.statNumber}>{statistics.total_emergencies}</Text>
                  <Text style={styles.statLabel}>Total</Text>
                </Card.Content>
              </Card>
            </View>
          </View>
        )}

        {/* Recent Emergencies */}
        {recentEmergencies.length > 0 && (
          <View style={styles.recentContainer}>
            <Title style={styles.sectionTitle}>Recent Emergencies</Title>
            {recentEmergencies.map((emergency) => (
              <EmergencyCard
                key={emergency.id}
                emergency={emergency}
                onPress={() => handleEmergencyPress(emergency)}
                showActions={false}
              />
            ))}
          </View>
        )}

        {/* Quick Actions */}
        <View style={styles.actionsContainer}>
          <Title style={styles.sectionTitle}>Quick Actions</Title>
          <View style={styles.actionsGrid}>
            <TouchableOpacity
              style={styles.actionCard}
              onPress={() => navigation.navigate('EmergencyTypeSelection')}
            >
              <View style={[styles.actionIcon, { backgroundColor: colors.error }]}>
                <Text style={styles.actionIconText}>🚨</Text>
              </View>
              <Text style={styles.actionTitle}>Report Emergency</Text>
              <Text style={styles.actionSubtitle}>Quick emergency reporting</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.actionCard}
              onPress={() => navigation.navigate('EmergencyList')}
            >
              <View style={[styles.actionIcon, { backgroundColor: colors.info }]}>
                <Text style={styles.actionIconText}>📋</Text>
              </View>
              <Text style={styles.actionTitle}>View History</Text>
              <Text style={styles.actionSubtitle}>Emergency reports history</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.actionCard}
              onPress={() => navigation.navigate('LocationsList')}
            >
              <View style={[styles.actionIcon, { backgroundColor: colors.success }]}>
                <Text style={styles.actionIconText}>📍</Text>
              </View>
              <Text style={styles.actionTitle}>Campus Map</Text>
              <Text style={styles.actionSubtitle}>View campus locations</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.actionCard}
              onPress={() => navigation.navigate('Settings')}
            >
              <View style={[styles.actionIcon, { backgroundColor: colors.warning }]}>
                <Text style={styles.actionIconText}>⚙️</Text>
              </View>
              <Text style={styles.actionTitle}>Settings</Text>
              <Text style={styles.actionSubtitle}>App preferences</Text>
            </TouchableOpacity>
          </View>
        </View>

        {/* Bottom padding */}
        <View style={styles.bottomPadding} />
      </ScrollView>

      {/* Emergency Type Dialog */}
      <Portal>
        <Modal
          visible={showEmergencyDialog}
          onDismiss={() => setShowEmergencyDialog(false)}
          contentContainerStyle={styles.modalContent}
        >
          <EmergencyTypeDialog
            emergencyTypes={emergencyTypes}
            onSelect={handleEmergencyTypeSelect}
            onCancel={() => setShowEmergencyDialog(false)}
          />
        </Modal>
      </Portal>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  header: {
    paddingTop: spacing.lg,
    paddingBottom: spacing.xl,
    paddingHorizontal: spacing.lg,
  },
  headerContent: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  userInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  avatar: {
    backgroundColor: colors.white,
    marginRight: spacing.md,
  },
  avatarLabel: {
    color: colors.primary,
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.bold,
  },
  userDetails: {
    flex: 1,
  },
  userName: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.bold,
    color: colors.white,
    marginBottom: spacing.xs,
  },
  userRole: {
    fontSize: typography.fontSize.sm,
    color: colors.white,
    fontWeight: typography.fontWeight.medium,
    marginBottom: 2,
  },
  userDepartment: {
    fontSize: typography.fontSize.sm,
    color: colors.white,
    opacity: 0.8,
  },
  headerActions: {
    flexDirection: 'row',
  },
  actionButton: {
    marginLeft: spacing.sm,
  },
  actionAvatar: {
    backgroundColor: colors.white,
  },
  content: {
    flex: 1,
    backgroundColor: colors.background,
  },
  statsContainer: {
    paddingHorizontal: spacing.lg,
    marginBottom: spacing.lg,
  },
  sectionTitle: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.semibold,
    color: colors.text,
    marginBottom: spacing.md,
  },
  statsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  statCard: {
    width: '48%',
    marginBottom: spacing.md,
    ...shadows.sm,
  },
  statContent: {
    alignItems: 'center',
    paddingVertical: spacing.md,
  },
  statNumber: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: colors.primary,
    marginBottom: spacing.xs,
  },
  statLabel: {
    fontSize: typography.fontSize.sm,
    color: colors.textSecondary,
    fontWeight: typography.fontWeight.medium,
  },
  recentContainer: {
    paddingHorizontal: spacing.lg,
    marginBottom: spacing.lg,
  },
  actionsContainer: {
    paddingHorizontal: spacing.lg,
    marginBottom: spacing.lg,
  },
  actionsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  actionCard: {
    width: '48%',
    backgroundColor: colors.white,
    borderRadius: borderRadius.lg,
    padding: spacing.md,
    alignItems: 'center',
    marginBottom: spacing.md,
    ...shadows.md,
  },
  actionIcon: {
    width: 50,
    height: 50,
    borderRadius: 25,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: spacing.sm,
  },
  actionIconText: {
    fontSize: 24,
  },
  actionTitle: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: colors.text,
    textAlign: 'center',
    marginBottom: spacing.xs,
  },
  actionSubtitle: {
    fontSize: typography.fontSize.xs,
    color: colors.textSecondary,
    textAlign: 'center',
  },
  bottomPadding: {
    height: spacing.xl,
  },
  modalContent: {
    backgroundColor: colors.white,
    padding: spacing.lg,
    margin: spacing.lg,
    borderRadius: borderRadius.lg,
    maxHeight: '80%',
  },
});

export default UserDashboardScreen;