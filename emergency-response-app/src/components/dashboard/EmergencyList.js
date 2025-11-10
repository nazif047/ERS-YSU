import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  RefreshControl,
} from 'react-native';
import { useTheme } from '@react-navigation/native';
import Icon from 'react-native-vector-icons/MaterialIcons';
import Card from '../common/Card';
import Button from '../common/Button';
import LoadingSpinner from '../common/LoadingSpinner';
import EmergencyStatusCard from '../emergency/EmergencyStatusCard';
import Input from '../common/Input';

const EmergencyList = ({ navigation, userRole }) => {
  const theme = useTheme();
  const colors = theme.colors;
  const { getUserEmergencies, loading } = useEmergency();

  const [emergencies, setEmergencies] = useState([]);
  const [refreshing, setRefreshing] = useState(false);
  const [filters, setFilters] = useState({
    status: 'all',
    severity: 'all',
    dateRange: 'all',
    search: '',
  });
  const [showFilters, setShowFilters] = useState(false);

  const statuses = [
    { id: 'all', name: 'All Status', color: colors.placeholder },
    { id: 'pending', name: 'Pending', color: '#FFA726' },
    { id: 'in_progress', name: 'In Progress', color: '#42A5F5' },
    { id: 'resolved', name: 'Resolved', color: '#66BB6A' },
    { id: 'closed', name: 'Closed', color: '#78909C' },
  ];

  const severities = [
    { id: 'all', name: 'All Severities', color: colors.placeholder },
    { id: 'low', name: 'Low', color: '#66BB6A' },
    { id: 'medium', name: 'Medium', color: '#FFA726' },
    { id: 'high', name: 'High', color: '#EF5350' },
    { id: 'critical', name: 'Critical', color: '#D32F2F' },
  ];

  useEffect(() => {
    loadEmergencies();
  }, []);

  const loadEmergencies = useCallback(async () => {
    try {
      const emergenciesData = await getUserEmergencies(filters);
      setEmergencies(emergenciesData || []);
    } catch (error) {
      console.error('Error loading emergencies:', error);
    }
  }, [getUserEmergencies, filters]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await loadEmergencies();
    setRefreshing(false);
  }, [loadEmergencies]);

  const handleFilterChange = (key, value) => {
    setFilters(prev => ({ ...prev, [key]: value }));
  };

  const clearFilters = () => {
    setFilters({
      status: 'all',
      severity: 'all',
      dateRange: 'all',
      search: '',
    });
  };

  const filteredEmergencies = emergencies.filter(emergency => {
    if (filters.status !== 'all' && emergency.status !== filters.status) {
      return false;
    }
    if (filters.severity !== 'all' && emergency.severity !== filters.severity) {
      return false;
    }
    if (filters.search) {
      const searchLower = filters.search.toLowerCase();
      if (
        !emergency.description.toLowerCase().includes(searchLower) &&
        !emergency.type?.name?.toLowerCase().includes(searchLower) &&
        !emergency.location?.name?.toLowerCase().includes(searchLower) &&
        !emergency.reporter?.name?.toLowerCase().includes(searchLower)
      ) {
        return false;
      }
    }
    return true;
  });

  const renderEmergencyItem = ({ item }) => (
    <EmergencyStatusCard
      emergency={item}
      userRole={userRole}
      style={styles.emergencyItem}
    />
  );

  const renderHeader = () => (
    <Card style={styles.headerCard}>
      <View style={styles.headerTop}>
        <Text style={[styles.headerTitle, { color: colors.text }]}>
          Emergencies ({filteredEmergencies.length})
        </Text>
        <TouchableOpacity
          style={[styles.filterButton, { backgroundColor: colors.card }]}
          onPress={() => setShowFilters(!showFilters)}
        >
          <Icon name="filter-list" size={20} color={colors.primary} />
        </TouchableOpacity>
      </View>

      {showFilters && (
        <View style={styles.filtersContainer}>
          {/* Search */}
          <Input
            placeholder="Search emergencies..."
            value={filters.search}
            onChangeText={(value) => handleFilterChange('search', value)}
            leftIcon={<Icon name="search" size={20} color={colors.placeholder} />}
            style={styles.searchInput}
          />

          {/* Status Filter */}
          <View style={styles.filterRow}>
            <Text style={[styles.filterLabel, { color: colors.text }]}>Status:</Text>
            <ScrollView horizontal showsHorizontalScrollIndicator={false}>
              {statuses.map((status) => (
                <TouchableOpacity
                  key={status.id}
                  style={[
                    styles.filterChip,
                    {
                      backgroundColor: filters.status === status.id ? status.color : colors.card,
                      borderColor: colors.border,
                    },
                  ]}
                  onPress={() => handleFilterChange('status', status.id)}
                >
                  <Text
                    style={[
                      styles.filterChipText,
                      {
                        color: filters.status === status.id ? '#FFFFFF' : colors.text,
                      },
                    ]}
                  >
                    {status.name}
                  </Text>
                </TouchableOpacity>
              ))}
            </ScrollView>
          </View>

          {/* Severity Filter */}
          <View style={styles.filterRow}>
            <Text style={[styles.filterLabel, { color: colors.text }]}>Severity:</Text>
            <ScrollView horizontal showsHorizontalScrollIndicator={false}>
              {severities.map((severity) => (
                <TouchableOpacity
                  key={severity.id}
                  style={[
                    styles.filterChip,
                    {
                      backgroundColor: filters.severity === severity.id ? severity.color : colors.card,
                      borderColor: colors.border,
                    },
                  ]}
                  onPress={() => handleFilterChange('severity', severity.id)}
                >
                  <Text
                    style={[
                      styles.filterChipText,
                      {
                        color: filters.severity === severity.id ? '#FFFFFF' : colors.text,
                      },
                    ]}
                  >
                    {severity.name}
                  </Text>
                </TouchableOpacity>
              ))}
            </ScrollView>
          </View>

          {/* Clear Filters */}
          <View style={styles.filterActions}>
            <Button
              title="Clear Filters"
              onPress={clearFilters}
              variant="outline"
              size="small"
              style={styles.clearButton}
            />
            <TouchableOpacity
              style={styles.closeFilters}
              onPress={() => setShowFilters(false)}
            >
              <Icon name="close" size={20} color={colors.text} />
            </TouchableOpacity>
          </View>
        </View>
      )}

      {/* Active Filters Display */}
      {!showFilters && (filters.status !== 'all' || filters.severity !== 'all' || filters.search) && (
        <View style={styles.activeFilters}>
          <ScrollView horizontal showsHorizontalScrollIndicator={false}>
            {filters.status !== 'all' && (
              <View style={[styles.activeFilterChip, { backgroundColor: '#E3F2FD' }]}>
                <Icon name="circle" size={8} color="#1976D2" />
                <Text style={[styles.activeFilterText, { color: '#1976D2' }]}>
                  Status: {statuses.find(s => s.id === filters.status)?.name}
                </Text>
                <TouchableOpacity onPress={() => handleFilterChange('status', 'all')}>
                  <Icon name="close" size={14} color="#1976D2" />
                </TouchableOpacity>
              </View>
            )}

            {filters.severity !== 'all' && (
              <View style={[styles.activeFilterChip, { backgroundColor: '#FFF3E0' }]}>
                <Icon name="circle" size={8} color="#FF9800" />
                <Text style={[styles.activeFilterText, { color: '#F57C00' }]}>
                  Severity: {severities.find(s => s.id === filters.severity)?.name}
                </Text>
                <TouchableOpacity onPress={() => handleFilterChange('severity', 'all')}>
                  <Icon name="close" size={14} color="#F57C00" />
                </TouchableOpacity>
              </View>
            )}

            {filters.search && (
              <View style={[styles.activeFilterChip, { backgroundColor: '#E8F5E8' }]}>
                <Icon name="search" size={8} color="#4CAF50" />
                <Text style={[styles.activeFilterText, { color: '#2E7D32' }]} numberOfLines={1}>
                  Search: {filters.search}
                </Text>
                <TouchableOpacity onPress={() => handleFilterChange('search', '')}>
                  <Icon name="close" size={14} color="#2E7D32" />
                </TouchableOpacity>
              </View>
            )}
          </ScrollView>
        </View>
      )}
    </Card>
  );

  const renderEmptyState = () => (
    <View style={styles.emptyState}>
      <Icon name="emergency-off" size={48} color={colors.placeholder} />
      <Text style={[styles.emptyText, { color: colors.placeholder }]}>
        No emergencies found
      </Text>
      <Text style={[styles.emptySubtext, { color: colors.placeholder }]}>
        {filteredEmergencies.length === 0 && emergencies.length > 0
          ? 'Try adjusting your filters'
          : 'No emergencies have been reported yet'}
      </Text>
    </View>
  );

  const getDepartmentColor = (department) => {
    const colors = {
      health: '#F44336',
      fire: '#FF9800',
      security: '#2196F3',
    };
    return colors[department] || '#9C27B0';
  };

  return (
    <View style={[styles.container, { backgroundColor: colors.background }]}>
      {renderHeader()}

      <FlatList
        data={filteredEmergencies}
        renderItem={renderEmergencyItem}
        keyExtractor={(item) => item.id.toString()}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={renderEmptyState}
        contentContainerStyle={styles.listContent}
        showsVerticalScrollIndicator={false}
      />

      <LoadingSpinner visible={loading} text="Loading emergencies..." overlay />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  headerCard: {
    margin: 16,
    marginBottom: 8,
  },
  headerTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '600',
  },
  filterButton: {
    padding: 8,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#E0E0E0',
  },
  filtersContainer: {
    gap: 12,
  },
  searchInput: {
    marginBottom: 0,
  },
  filterRow: {
    alignItems: 'center',
    marginBottom: 8,
  },
  filterLabel: {
    fontSize: 14,
    fontWeight: '500',
    marginRight: 12,
    minWidth: 60,
  },
  filterChip: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
    borderWidth: 1,
    marginRight: 8,
  },
  filterChipText: {
    fontSize: 12,
    fontWeight: '500',
  },
  filterActions: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 8,
  },
  clearButton: {
    flex: 1,
    marginRight: 12,
  },
  closeFilters: {
    padding: 8,
  },
  activeFilters: {
    marginTop: 8,
  },
  activeFilterChip: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
    marginRight: 8,
  },
  activeFilterText: {
    fontSize: 12,
    fontWeight: '500',
    marginHorizontal: 4,
    flex: 1,
  },
  emergencyItem: {
    marginHorizontal: 16,
    marginBottom: 8,
  },
  listContent: {
    paddingBottom: 80,
  },
  emptyState: {
    alignItems: 'center',
    paddingVertical: 60,
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
    paddingHorizontal: 40,
  },
});

export default EmergencyList;