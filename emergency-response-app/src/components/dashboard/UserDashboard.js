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
import { useTheme } from '@react-navigation/native';
import Icon from 'react-native-vector-icons/MaterialIcons';
import Card from '../common/Card';
import Button from '../common/Button';
import LoadingSpinner from '../common/LoadingSpinner';
import { useEmergency } from '../../hooks/useEmergency';
import { useNotifications } from '../../hooks/useNotifications';
import EmergencyStatusCard from '../emergency/EmergencyStatusCard';

const UserDashboard = ({ navigation, user }) => {
  const theme = useTheme();
  const colors = theme.colors;
  const { getUserEmergencies, reportEmergency, loading } = useEmergency();
  const { notifications, markAsRead } = useNotifications();

  const [emergencies, setEmergencies] = useState([]);
  const [refreshing, setRefreshing] = useState(false);
  const [showQuickActions, setShowQuickActions] = useState(true);

  useEffect(() => {
    loadDashboardData();
  }, []);

  const loadDashboardData = useCallback(async () => {
    try {
      const [emergenciesData] = await Promise.all([
        getUserEmergencies(),
      ]);
      setEmergencies(emergenciesData || []);
    } catch (error) {
      console.error('Error loading dashboard data:', error);
      Alert.alert('Error', 'Failed to load dashboard data. Please try again.');
    }
  }, [getUserEmergencies]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await loadDashboardData();
    setRefreshing(false);
  }, [loadDashboardData]);

  const handleEmergencyReport = (type) => {
    navigation.navigate('ReportEmergency', { emergencyType: type });
  };

  const handleViewAllEmergencies = () => {
    navigation.navigate('EmergencyList');
  };

  const handleViewNotifications = () => {
    navigation.navigate('Notifications');
  };

  const handleViewEmergencyDetails = (emergency) => {
    navigation.navigate('EmergencyDetail', { emergencyId: emergency.id });
  };

  const getActiveEmergencies = () => {
    return emergencies.filter(e => e.status === 'pending' || e.status === 'in_progress');
  };

  const getEmergencyStats = () => {
    const total = emergencies.length;
    const active = getActiveEmergencies().length;
    const resolved = emergencies.filter(e => e.status === 'resolved').length;
    return { total, active, resolved };
  };

  const emergencyTypes = [
    {
      id: 'medical',
      name: 'Medical',
      icon: 'local-hospital',
      color: '#F44336',
      description: 'Health emergencies',
    },
    {
      id: 'fire',
      name: 'Fire',
      icon: 'local-fire-department',
      color: '#FF9800',
      description: 'Fire emergencies',
    },
    {
      id: 'security',
      name: 'Security',
      icon: 'security',
      color: '#2196F3',
      description: 'Security threats',
    },
  ];

  const stats = getEmergencyStats();

  return (
    <ScrollView
      style={[styles.container, { backgroundColor: colors.background }]}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }
      showsVerticalScrollIndicator={false}
    >
      {/* Header */}
      <View style={styles.header}>
        <View style={styles.headerTop}>
          <View style={styles.userInfo}>
            <View style={[styles.avatar, { backgroundColor: colors.primary }]}>
              <Icon name="person" size={24} color="#FFFFFF" />
            </View>
            <View style={styles.userText}>
              <Text style={[styles.userName, { color: colors.text }]}>
                Welcome, {user?.fullName || 'User'}
              </Text>
              <Text style={[styles.userRole, { color: colors.placeholder }]}>
                {user?.department || 'Department'}
              </Text>
            </View>
          </View>
          <TouchableOpacity onPress={handleViewNotifications} style={styles.notificationButton}>
            <Icon name="notifications" size={24} color={colors.text} />
            {notifications.filter(n => !n.is_read).length > 0 && (
              <View style={[styles.notificationBadge, { backgroundColor: colors.error }]}>
                <Text style={styles.notificationBadgeText}>
                  {notifications.filter(n => !n.is_read).length}
                </Text>
              </View>
            )}
          </TouchableOpacity>
        </View>

        {/* Stats Summary */}
        <View style={styles.statsRow}>
          <View style={styles.statItem}>
            <Text style={[styles.statNumber, { color: colors.primary }]}>{stats.total}</Text>
            <Text style={[styles.statLabel, { color: colors.placeholder }]}>Total</Text>
          </View>
          <View style={styles.statItem}>
            <Text style={[styles.statNumber, { color: colors.error }]}>{stats.active}</Text>
            <Text style={[styles.statLabel, { color: colors.placeholder }]}>Active</Text>
          </View>
          <View style={styles.statItem}>
            <Text style={[styles.statNumber, { color: colors.success }]}>{stats.resolved}</Text>
            <Text style={[styles.statLabel, { color: colors.placeholder }]}>Resolved</Text>
          </View>
        </View>
      </View>

      {/* Emergency Quick Actions */}
      {showQuickActions && (
        <Card style={styles.quickActionsCard}>
          <View style={styles.sectionHeader}>
            <Text style={[styles.sectionTitle, { color: colors.text }]}>Quick Actions</Text>
            <TouchableOpacity onPress={() => setShowQuickActions(false)}>
              <Icon name="expand-less" size={24} color={colors.placeholder} />
            </TouchableOpacity>
          </View>

          <View style={styles.emergencyTypes}>
            {emergencyTypes.map((type) => (
              <TouchableOpacity
                key={type.id}
                style={[styles.emergencyButton, { backgroundColor: type.color }]}
                onPress={() => handleEmergencyReport(type.id)}
              >
                <Icon name={type.icon} size={32} color="#FFFFFF" />
                <Text style={styles.emergencyButtonText}>{type.name}</Text>
                <Text style={styles.emergencyButtonDescription}>{type.description}</Text>
              </TouchableOpacity>
            ))}
          </View>

          {/* Panic Button */}
          <TouchableOpacity
            style={[styles.panicButton, { backgroundColor: colors.error }]}
            onPress={() => handleEmergencyReport('panic')}
          >
            <Icon name="warning" size={32} color="#FFFFFF" />
            <Text style={styles.panicButtonText}>PANIC BUTTON</Text>
            <Text style={styles.panicButtonDescription}>For critical emergencies only</Text>
          </TouchableOpacity>
        </Card>
      )}

      {/* Collapsed Quick Actions */}
      {!showQuickActions && (
        <Card style={styles.collapsedActionsCard}>
          <TouchableOpacity
            style={styles.collapsedActionsButton}
            onPress={() => setShowQuickActions(true)}
          >
            <Icon name="emergency" size={24} color={colors.primary} />
            <Text style={[styles.collapsedActionsText, { color: colors.text }]}>
              Quick Emergency Actions
            </Text>
            <Icon name="expand-more" size={24} color={colors.placeholder} />
          </TouchableOpacity>
        </Card>
      )}

      {/* Active Emergencies */}
      {getActiveEmergencies().length > 0 && (
        <Card style={styles.activeEmergenciesCard}>
          <View style={styles.sectionHeader}>
            <Text style={[styles.sectionTitle, { color: colors.text }]}>
              Active Emergencies ({getActiveEmergencies().length})
            </Text>
            <TouchableOpacity onPress={handleViewAllEmergencies}>
              <Text style={[styles.viewAllText, { color: colors.primary }]}>View All</Text>
            </TouchableOpacity>
          </View>

          {getActiveEmergencies().slice(0, 3).map((emergency) => (
            <EmergencyStatusCard
              key={emergency.id}
              emergency={emergency}
              userRole={user?.role}
              style={styles.emergencyCard}
            />
          ))}
        </Card>
      )}

      {/* Recent Emergencies */}
      <Card style={styles.recentEmergenciesCard}>
        <View style={styles.sectionHeader}>
          <Text style={[styles.sectionTitle, { color: colors.text }]}>Recent Emergencies</Text>
          {emergencies.length > 0 && (
            <TouchableOpacity onPress={handleViewAllEmergencies}>
              <Text style={[styles.viewAllText, { color: colors.primary }]}>View All</Text>
            </TouchableOpacity>
          )}
        </View>

        {emergencies.length === 0 ? (
          <View style={styles.emptyState}>
            <Icon name="emergency" size={48} color={colors.placeholder} />
            <Text style={[styles.emptyText, { color: colors.placeholder }]}>
              No emergencies reported yet
            </Text>
            <Text style={[styles.emptySubtext, { color: colors.placeholder }]}>
              Stay safe! Emergency resources are available if needed.
            </Text>
            <Button
              title="Learn Emergency Procedures"
              variant="outline"
              onPress={() => navigation.navigate('EmergencyProcedures')}
              style={styles.learnButton}
            />
          </View>
        ) : (
          emergencies.slice(0, 3).map((emergency) => (
            <EmergencyStatusCard
              key={emergency.id}
              emergency={emergency}
              userRole={user?.role}
              style={styles.emergencyCard}
            />
          ))
        )}
      </Card>

      {/* Emergency Contacts */}
      <Card style={styles.contactsCard}>
        <View style={styles.sectionHeader}>
          <Text style={[styles.sectionTitle, { color: colors.text }]}>Emergency Contacts</Text>
        </View>

        <View style={styles.contactsList}>
          <TouchableOpacity style={styles.contactItem}>
            <View style={[styles.contactIcon, { backgroundColor: '#2196F3' }]}>
              <Icon name="local-hospital" size={20} color="#FFFFFF" />
            </View>
            <View style={styles.contactInfo}>
              <Text style={[styles.contactName, { color: colors.text }]}>Health Center</Text>
              <Text style={[styles.contactNumber, { color: colors.placeholder }]}>080-123-45678</Text>
            </View>
            <Icon name="phone" size={20} color={colors.primary} />
          </TouchableOpacity>

          <TouchableOpacity style={styles.contactItem}>
            <View style={[styles.contactIcon, { backgroundColor: '#FF9800' }]}>
              <Icon name="local-fire-department" size={20} color="#FFFFFF" />
            </View>
            <View style={styles.contactInfo}>
              <Text style={[styles.contactName, { color: colors.text }]}>Fire Safety</Text>
              <Text style={[styles.contactNumber, { color: colors.placeholder }]}>080-123-45679</Text>
            </View>
            <Icon name="phone" size={20} color={colors.primary} />
          </TouchableOpacity>

          <TouchableOpacity style={styles.contactItem}>
            <View style={[styles.contactIcon, { backgroundColor: '#4CAF50' }]}>
              <Icon name="security" size={20} color="#FFFFFF" />
            </View>
            <View style={styles.contactInfo}>
              <Text style={[styles.contactName, { color: colors.text }]}>Security</Text>
              <Text style={[styles.contactNumber, { color: colors.placeholder }]}>080-123-45680</Text>
            </View>
            <Icon name="phone" size={20} color={colors.primary} />
          </TouchableOpacity>
        </View>
      </Card>

      {/* Safety Tips */}
      <Card style={styles.safetyTipsCard}>
        <View style={styles.sectionHeader}>
          <Text style={[styles.sectionTitle, { color: colors.text }]}>Safety Tips</Text>
        </View>

        <View style={styles.safetyTips}>
          <View style={styles.safetyTip}>
            <Icon name="check-circle" size={20} color={colors.success} />
            <Text style={[styles.safetyTipText, { color: colors.text }]}>
              Keep your phone charged and location services enabled
            </Text>
          </View>
          <View style={styles.safetyTip}>
            <Icon name="check-circle" size={20} color={colors.success} />
            <Text style={[styles.safetyTipText, { color: colors.text }]}>
              Familiarize yourself with emergency exits and assembly points
            </Text>
          </View>
          <View style={styles.safetyTip}>
            <Icon name="check-circle" size={20} color={colors.success} />
            <Text style={[styles.safetyTipText, { color: colors.text }]}>
              Save emergency contacts in your phone for quick access
            </Text>
          </View>
        </View>
      </Card>

      <LoadingSpinner visible={loading} text="Loading..." overlay />
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  header: {
    padding: 20,
    paddingBottom: 10,
  },
  headerTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  userInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  avatar: {
    width: 50,
    height: 50,
    borderRadius: 25,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  userText: {
    flex: 1,
  },
  userName: {
    fontSize: 20,
    fontWeight: '600',
    marginBottom: 2,
  },
  userRole: {
    fontSize: 14,
  },
  notificationButton: {
    position: 'relative',
  },
  notificationBadge: {
    position: 'absolute',
    right: -6,
    top: -6,
    width: 18,
    height: 18,
    borderRadius: 9,
    alignItems: 'center',
    justifyContent: 'center',
  },
  notificationBadgeText: {
    color: '#FFFFFF',
    fontSize: 10,
    fontWeight: '600',
  },
  statsRow: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    paddingVertical: 16,
  },
  statItem: {
    alignItems: 'center',
  },
  statNumber: {
    fontSize: 24,
    fontWeight: '700',
    marginBottom: 4,
  },
  statLabel: {
    fontSize: 12,
    textTransform: 'uppercase',
  },
  quickActionsCard: {
    margin: 16,
    marginBottom: 8,
  },
  collapsedActionsCard: {
    margin: 16,
    marginBottom: 8,
  },
  collapsedActionsButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 16,
  },
  collapsedActionsText: {
    fontSize: 16,
    fontWeight: '600',
    marginLeft: 12,
    flex: 1,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
  },
  viewAllText: {
    fontSize: 14,
    fontWeight: '500',
  },
  emergencyTypes: {
    gap: 12,
    marginBottom: 20,
  },
  emergencyButton: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
    borderRadius: 12,
  },
  emergencyButtonText: {
    color: '#FFFFFF',
    fontSize: 18,
    fontWeight: '600',
    marginLeft: 12,
  },
  emergencyButtonDescription: {
    color: '#FFFFFF',
    fontSize: 12,
    marginTop: 4,
    marginLeft: 12,
    flex: 1,
  },
  panicButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 20,
    borderRadius: 12,
  },
  panicButtonText: {
    color: '#FFFFFF',
    fontSize: 20,
    fontWeight: '700',
    marginLeft: 12,
  },
  panicButtonDescription: {
    color: '#FFFFFF',
    fontSize: 12,
    marginTop: 4,
    marginLeft: 12,
  },
  activeEmergenciesCard: {
    margin: 16,
    marginBottom: 8,
  },
  recentEmergenciesCard: {
    margin: 16,
    marginBottom: 8,
  },
  emergencyCard: {
    marginBottom: 12,
  },
  emptyState: {
    alignItems: 'center',
    paddingVertical: 40,
  },
  emptyText: {
    fontSize: 18,
    fontWeight: '600',
    marginTop: 16,
    marginBottom: 8,
    textAlign: 'center',
  },
  emptySubtext: {
    fontSize: 14,
    textAlign: 'center',
    marginBottom: 24,
  },
  learnButton: {
    alignSelf: 'center',
  },
  contactsCard: {
    margin: 16,
    marginBottom: 8,
  },
  contactsList: {
    gap: 12,
  },
  contactItem: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderRadius: 8,
    backgroundColor: 'rgba(0, 0, 0, 0.02)',
  },
  contactIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  contactInfo: {
    flex: 1,
  },
  contactName: {
    fontSize: 16,
    fontWeight: '600',
    marginBottom: 2,
  },
  contactNumber: {
    fontSize: 14,
  },
  safetyTipsCard: {
    margin: 16,
    marginBottom: 8,
  },
  safetyTips: {
    gap: 12,
  },
  safetyTip: {
    flexDirection: 'row',
    alignItems: 'flex-start',
  },
  safetyTipText: {
    fontSize: 14,
    marginLeft: 12,
    flex: 1,
    lineHeight: 20,
  },
});

export default UserDashboard;