import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  RefreshControl,
  TouchableOpacity,
} from 'react-native';
import { useTheme } from '@react-navigation/native';
import Icon from 'react-native-vector-icons/MaterialIcons';
import Card from '../common/Card';
import Button from '../common/Button';
import LoadingSpinner from '../common/LoadingSpinner';
import { useEmergency } from '../../hooks/useEmergency';
import EmergencyStatusCard from '../emergency/EmergencyStatusCard';

const AdminDashboard = ({ navigation, user }) => {
  const theme = useTheme();
  const colors = theme.colors;
  const { getDepartmentEmergencies, updateEmergencyStatus, loading } = useEmergency();

  const [emergencies, setEmergencies] = useState([]);
  const [analytics, setAnalytics] = useState(null);
  const [refreshing, setRefreshing] = useState(false);
  const [selectedTab, setSelectedTab] = useState('active');

  const userDepartment = user?.role?.replace('_admin', '');

  useEffect(() => {
    loadDashboardData();
  }, []);

  const loadDashboardData = useCallback(async () => {
    try {
      const [emergenciesData] = await Promise.all([
        getDepartmentEmergencies(),
      ]);
      setEmergencies(emergenciesData || []);

      // Generate mock analytics based on department
      const mockAnalytics = {
        totalEmergencies: emergenciesData?.length || 0,
        activeEmergencies: emergenciesData?.filter(e => e.status === 'pending' || e.status === 'in_progress').length || 0,
        resolvedEmergencies: emergenciesData?.filter(e => e.status === 'resolved').length || 0,
        avgResponseTime: 15.5,
        criticalEmergencies: emergenciesData?.filter(e => e.severity === 'critical').length || 0,
        highPriorityEmergencies: emergenciesData?.filter(e => e.severity === 'high').length || 0,
      };
      setAnalytics(mockAnalytics);
    } catch (error) {
      console.error('Error loading dashboard data:', error);
    }
  }, [getDepartmentEmergencies]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await loadDashboardData();
    setRefreshing(false);
  }, [loadDashboardData]);

  const handleEmergencyUpdate = async (emergency, newStatus) => {
    try {
      await updateEmergencyStatus(emergency.id, newStatus);
      loadDashboardData(); // Refresh data
    } catch (error) {
      console.error('Error updating emergency:', error);
    }
  };

  const handleViewAllEmergencies = () => {
    navigation.navigate('AdminEmergencyList');
  };

  const handleViewAnalytics = () => {
    navigation.navigate('AdminAnalytics');
  };

  const handleViewUsers = () => {
    navigation.navigate('AdminUsers');
  };

  const getActiveEmergencies = () => {
    return emergencies.filter(e => e.status === 'pending' || e.status === 'in_progress');
  };

  const getCriticalEmergencies = () => {
    return emergencies.filter(e => e.severity === 'critical');
  };

  const getDepartmentName = () => {
    const departmentNames = {
      health: 'Health Services',
      fire: 'Fire Safety',
      security: 'Security',
    };
    return departmentNames[userDepartment] || 'Administration';
  };

  const getDepartmentIcon = () => {
    const icons = {
      health: 'local-hospital',
      fire: 'local-fire-department',
      security: 'security',
    };
    return icons[userDepartment] || 'admin-panel-settings';
  };

  const getDepartmentColor = () => {
    const colors = {
      health: '#F44336',
      fire: '#FF9800',
      security: '#2196F3',
    };
    return colors[userDepartment] || '#9C27B0';
  };

  const filteredEmergencies = emergencies.filter(emergency => {
    switch (selectedTab) {
      case 'active':
        return emergency.status === 'pending' || emergency.status === 'in_progress';
      case 'critical':
        return emergency.severity === 'critical';
      case 'resolved':
        return emergency.status === 'resolved';
      default:
        return true;
    }
  });

  return (
    <ScrollView
      style={[styles.container, { backgroundColor: colors.background }]}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }
      showsVerticalScrollIndicator={false}
    >
      {/* Header */}
      <View style={[styles.header, { borderBottomColor: colors.border }]}>
        <View style={styles.headerTop}>
          <View style={styles.userInfo}>
            <View style={[styles.departmentIcon, { backgroundColor: getDepartmentColor() }]}>
              <Icon name={getDepartmentIcon()} size={24} color="#FFFFFF" />
            </View>
            <View style={styles.userText}>
              <Text style={[styles.departmentName, { color: colors.text }]}>
                {getDepartmentName()}
              </Text>
              <Text style={[styles.userName, { color: colors.placeholder }]}>
                {user?.fullName || 'Admin'}
              </Text>
            </View>
          </View>
          <TouchableOpacity onPress={() => navigation.openDrawer()}>
            <Icon name="menu" size={24} color={colors.text} />
          </TouchableOpacity>
        </View>
      </View>

      {/* Analytics Overview */}
      {analytics && (
        <Card style={styles.analyticsCard}>
          <Text style={[styles.sectionTitle, { color: colors.text }]}>Overview</Text>

          <View style={styles.statsGrid}>
            <View style={styles.statCard}>
              <Icon name="assignment" size={24} color={colors.primary} />
              <Text style={[styles.statNumber, { color: colors.primary }]}>{analytics.totalEmergencies}</Text>
              <Text style={[styles.statLabel, { color: colors.placeholder }]}>Total</Text>
            </View>

            <View style={styles.statCard}>
              <Icon name="pending-actions" size={24} color={colors.error} />
              <Text style={[styles.statNumber, { color: colors.error }]}>{analytics.activeEmergencies}</Text>
              <Text style={[styles.statLabel, { color: colors.placeholder }]}>Active</Text>
            </View>

            <View style={styles.statCard}>
              <Icon name="check-circle" size={24} color={colors.success} />
              <Text style={[styles.statNumber, { color: colors.success }]}>{analytics.resolvedEmergencies}</Text>
              <Text style={[styles.statLabel, { color: colors.placeholder }]}>Resolved</Text>
            </View>

            <View style={styles.statCard}>
              <Icon name="timer" size={24} color={colors.warning} />
              <Text style={[styles.statNumber, { color: colors.warning }]}>{analytics.avgResponseTime}m</Text>
              <Text style={[styles.statLabel, { color: colors.placeholder }]}>Avg Time</Text>
            </View>
          </View>

          {/* Priority Emergencies */}
          {(analytics.criticalEmergencies > 0 || analytics.highPriorityEmergencies > 0) && (
            <View style={styles.priorityAlerts}>
              {analytics.criticalEmergencies > 0 && (
                <View style={[styles.priorityAlert, { backgroundColor: colors.error }]}>
                  <Icon name="warning" size={20} color="#FFFFFF" />
                  <Text style={styles.priorityAlertText}>
                    {analytics.criticalEmergencies} Critical Emergency{analytics.criticalEmergencies > 1 ? 's' : ''}
                  </Text>
                </View>
              )}

              {analytics.highPriorityEmergencies > 0 && (
                <View style={[styles.priorityAlert, { backgroundColor: colors.warning }]}>
                  <Icon name="priority-high" size={20} color="#FFFFFF" />
                  <Text style={styles.priorityAlertText}>
                    {analytics.highPriorityEmergencies} High Priority Emergency{analytics.highPriorityEmergencies > 1 ? 's' : ''}
                  </Text>
                </View>
              )}
            </View>
          )}
        </Card>
      )}

      {/* Quick Actions */}
      <Card style={styles.quickActionsCard}>
        <Text style={[styles.sectionTitle, { color: colors.text }]}>Quick Actions</Text>

        <View style={styles.quickActionsGrid}>
          <TouchableOpacity
            style={[styles.quickActionButton, { backgroundColor: colors.primary }]}
            onPress={() => navigation.navigate('AdminAnalytics')}
          >
            <Icon name="analytics" size={24} color="#FFFFFF" />
            <Text style={styles.quickActionText}>Analytics</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.quickActionButton, { backgroundColor: colors.success }]}
            onPress={handleViewUsers}
          >
            <Icon name="people" size={24} color="#FFFFFF" />
            <Text style={styles.quickActionText}>Users</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.quickActionButton, { backgroundColor: colors.warning }]}
            onPress={() => navigation.navigate('ManageLocations')}
          >
            <Icon name="location-on" size={24} color="#FFFFFF" />
            <Text style={styles.quickActionText}>Locations</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.quickActionButton, { backgroundColor: colors.error }]}
            onPress={() => navigation.navigate('SystemSettings')}
          >
            <Icon name="settings" size={24} color="#FFFFFF" />
            <Text style={styles.quickActionText}>Settings</Text>
          </TouchableOpacity>
        </View>
      </Card>

      {/* Tabs */}
      <View style={styles.tabContainer}>
        <TouchableOpacity
          style={[styles.tab, selectedTab === 'active' && styles.activeTab, { borderBottomColor: colors.primary }]}
          onPress={() => setSelectedTab('active')}
        >
          <Text style={[styles.tabText, { color: selectedTab === 'active' ? colors.primary : colors.placeholder }]}>
            Active ({getActiveEmergencies().length})
          </Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.tab, selectedTab === 'critical' && styles.activeTab, { borderBottomColor: colors.primary }]}
          onPress={() => setSelectedTab('critical')}
        >
          <Text style={[styles.tabText, { color: selectedTab === 'critical' ? colors.primary : colors.placeholder }]}>
            Critical ({getCriticalEmergencies().length})
          </Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.tab, selectedTab === 'resolved' && styles.activeTab, { borderBottomColor: colors.primary }]}
          onPress={() => setSelectedTab('resolved')}
        >
          <Text style={[styles.tabText, { color: selectedTab === 'resolved' ? colors.primary : colors.placeholder }]}>
            Resolved ({analytics?.resolvedEmergencies || 0})
          </Text>
        </TouchableOpacity>
      </View>

      {/* Emergencies List */}
      <Card style={styles.emergenciesCard}>
        <View style={styles.emergenciesHeader}>
          <Text style={[styles.sectionTitle, { color: colors.text }]}>
            {selectedTab === 'active' && 'Active Emergencies'}
            {selectedTab === 'critical' && 'Critical Emergencies'}
            {selectedTab === 'resolved' && 'Resolved Emergencies'}
          </Text>
          <TouchableOpacity onPress={handleViewAllEmergencies}>
            <Text style={[styles.viewAllText, { color: colors.primary }]}>View All</Text>
          </TouchableOpacity>
        </View>

        {filteredEmergencies.length === 0 ? (
          <View style={styles.emptyState}>
            <Icon name="check-circle" size={48} color={colors.success} />
            <Text style={[styles.emptyText, { color: colors.placeholder }]}>
              No {selectedTab} emergencies
            </Text>
            <Text style={[styles.emptySubtext, { color: colors.placeholder }]}>
              {selectedTab === 'active' && 'All active emergencies have been resolved'}
              {selectedTab === 'critical' && 'No critical emergencies at this time'}
              {selectedTab === 'resolved' && 'No resolved emergencies yet'}
            </Text>
          </View>
        ) : (
          filteredEmergencies.map((emergency) => (
            <EmergencyStatusCard
              key={emergency.id}
              emergency={emergency}
              userRole={user?.role}
              onStatusUpdate={handleEmergencyUpdate}
              style={styles.emergencyCard}
            />
          ))
        )}
      </Card>

      {/* Performance Metrics */}
      <Card style={styles.metricsCard}>
        <Text style={[styles.sectionTitle, { color: colors.text }]}>Performance Metrics</Text>

        <View style={styles.metricsList}>
          <View style={styles.metricItem}>
            <Text style={[styles.metricLabel, { color: colors.placeholder }]}>Average Response Time</Text>
            <Text style={[styles.metricValue, { color: colors.success }]}>
              {analytics?.avgResponseTime || 0} minutes
            </Text>
          </View>

          <View style={styles.metricItem}>
            <Text style={[styles.metricLabel, { color: colors.placeholder }]}>Resolution Rate</Text>
            <Text style={[styles.metricValue, { color: colors.primary }]}>
              {analytics && analytics.totalEmergencies > 0
                ? Math.round((analytics.resolvedEmergencies / analytics.totalEmergencies) * 100)
                : 0}%
            </Text>
          </View>

          <View style={styles.metricItem}>
            <Text style={[styles.metricLabel, { color: colors.placeholder }]}>Critical Response Rate</Text>
            <Text style={[styles.metricValue, { color: colors.error }]}>
              95%
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
    padding: 16,
    paddingBottom: 12,
    borderBottomWidth: 1,
  },
  headerTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  userInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  departmentIcon: {
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
  departmentName: {
    fontSize: 18,
    fontWeight: '600',
    marginBottom: 2,
  },
  userName: {
    fontSize: 14,
  },
  analyticsCard: {
    margin: 16,
    marginBottom: 8,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    marginBottom: 16,
  },
  statsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
    marginBottom: 16,
  },
  statCard: {
    flex: 1,
    minWidth: '45%',
    alignItems: 'center',
    padding: 16,
    borderRadius: 12,
    backgroundColor: 'rgba(0, 0, 0, 0.02)',
  },
  statNumber: {
    fontSize: 24,
    fontWeight: '700',
    marginTop: 8,
    marginBottom: 4,
  },
  statLabel: {
    fontSize: 12,
    textTransform: 'uppercase',
  },
  priorityAlerts: {
    gap: 8,
  },
  priorityAlert: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderRadius: 8,
  },
  priorityAlertText: {
    color: '#FFFFFF',
    fontSize: 14,
    fontWeight: '600',
    marginLeft: 8,
    flex: 1,
  },
  quickActionsCard: {
    margin: 16,
    marginBottom: 8,
  },
  quickActionsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  quickActionButton: {
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 16,
    borderRadius: 12,
    minWidth: '45%',
  },
  quickActionText: {
    color: '#FFFFFF',
    fontSize: 12,
    fontWeight: '600',
    marginTop: 8,
  },
  tabContainer: {
    flexDirection: 'row',
    backgroundColor: 'rgba(0, 0, 0, 0.02)',
    margin: 16,
    marginBottom: 8,
    borderRadius: 8,
  },
  tab: {
    flex: 1,
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: 2,
    borderBottomColor: 'transparent',
  },
  activeTab: {
    borderBottomColor: '#2196F3',
  },
  tabText: {
    fontSize: 14,
    fontWeight: '500',
  },
  emergenciesCard: {
    margin: 16,
    marginBottom: 8,
  },
  emergenciesHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  viewAllText: {
    fontSize: 14,
    fontWeight: '500',
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
  },
  metricsCard: {
    margin: 16,
    marginBottom: 8,
  },
  metricsList: {
    gap: 12,
  },
  metricItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
  },
  metricLabel: {
    fontSize: 14,
    fontWeight: '500',
  },
  metricValue: {
    fontSize: 16,
    fontWeight: '600',
  },
});

export default AdminDashboard;