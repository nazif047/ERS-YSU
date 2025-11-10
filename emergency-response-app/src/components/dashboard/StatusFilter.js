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

const StatusFilter = ({ selectedStatuses, onStatusChange, visible }) => {
  const theme = useTheme();
  const colors = theme.colors;

  if (!visible) return null;

  const statusOptions = [
    {
      id: 'pending',
      name: 'Pending',
      icon: 'schedule',
      color: '#FFA726',
      description: 'Awaiting response',
    },
    {
      id: 'in_progress',
      name: 'In Progress',
      icon: 'directions-run',
      color: '#42A5F5',
      description: 'Being handled',
    },
    {
      id: 'resolved',
      name: 'Resolved',
      icon: 'check-circle',
      color: '#66BB6A',
      description: 'Issue resolved',
    },
    {
      id: 'closed',
      name: 'Closed',
      icon: 'archive',
      color: '#78909C',
      description: 'Case closed',
    },
  ];

  const handleStatusToggle = (statusId) => {
    const newSelections = selectedStatuses.includes(statusId)
      ? selectedStatuses.filter(id => id !== statusId)
      : [...selectedStatuses, statusId];
    onStatusChange?.(newSelections);
  };

  const handleClearAll = () => {
    onStatusChange?.([]);
  };

  const handleSelectAll = () => {
    onStatusChange?.( statusOptions.map(status => status.id));
  };

  return (
    <Card style={[styles.container, { backgroundColor: colors.card }]}>
      <View style={styles.header}>
        <Text style={[styles.title, { color: colors.text }]}>Filter by Status</Text>
        <View style={styles.headerActions}>
          <TouchableOpacity
            style={[styles.actionButton, selectedStatuses.length > 0 && styles.clearButtonActive]}
            onPress={handleClearAll}
            disabled={selectedStatuses.length === 0}
          >
            <Icon name="clear" size={20} color={selectedStatuses.length > 0 ? colors.error : colors.placeholder} />
            <Text
              style={[
                styles.actionButtonText,
                {
                  color: selectedStatuses.length > 0 ? colors.error : colors.placeholder,
                },
              ]}
            >
              Clear
            </Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.actionButton, styles.selectAllButton]}
            onPress={handleSelectAll}
          >
            <Icon name="select-all" size={20} color={colors.primary} />
            <Text style={[styles.actionButtonText, { color: colors.primary }]}>
              All
            </Text>
          </TouchableOpacity>
        </View>
      </View>

      <ScrollView style={styles.scrollContainer} showsVerticalScrollIndicator={false}>
        {statusOptions.map((status) => {
          const isSelected = selectedStatuses.includes(status.id);
          return (
            <TouchableOpacity
              key={status.id}
              style={[
                styles.statusItem,
                {
                  backgroundColor: isSelected ? status.color + '20' : 'transparent',
                  borderColor: isSelected ? status.color : colors.border,
                },
              ]}
              onPress={() => handleStatusToggle(status.id)}
            >
              <View style={[styles.statusIcon, { backgroundColor: isSelected ? status.color : colors.border }]}>
                <Icon
                  name={status.icon}
                  size={20}
                  color={isSelected ? '#FFFFFF' : status.color}
                />
              </View>
              <View style={styles.statusContent}>
                <Text style={[styles.statusName, { color: colors.text }]}>
                  {status.name}
                </Text>
                <Text style={[styles.statusDescription, { color: colors.placeholder }]}>
                  {status.description}
                </Text>
              </View>
              <View style={[styles.checkbox, isSelected && styles.checkboxSelected]}>
                <Icon
                  name={isSelected ? 'check-circle' : 'radio-button-unchecked'}
                  size={20}
                  color={isSelected ? colors.primary : colors.placeholder}
                />
              </View>
            </TouchableOpacity>
          );
        })}
      </ScrollView>

      <View style={styles.footer}>
        <Text style={[styles.footerText, { color: colors.placeholder }]}>
          {selectedStatuses.length} status{selectedStatuses.length === 1 ? '' : 'es'} selected
        </Text>
        <Text style={[styles.footerText, { color: colors.placeholder }]}>
          • {statusOptions.length - selectedStatuses.length} available
        </Text>
      </View>
    </Card>
  );
};

const styles = StyleSheet.create({
  container: {
    margin: 16,
    padding: 16,
    borderRadius: 12,
    elevation: 2,
    shadowOpacity: 0.1,
    shadowRadius: 4,
    shadowOffset: {
      width: 0,
      height: 2,
    },
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  title: {
    fontSize: 18,
    fontWeight: '600',
  },
  headerActions: {
    flexDirection: 'row',
    gap: 12,
  },
  actionButton: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 4,
    paddingHorizontal: 8,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#E0E0E0',
  },
  clearButtonActive: {
    borderColor: '#FF5252',
    backgroundColor: 'rgba(255, 82, 82, 0.1)',
  },
  selectAllButton: {
    borderColor: '#2196F3',
    backgroundColor: 'rgba(33, 150, 243, 0.1)',
  },
  actionButtonText: {
    fontSize: 12,
    fontWeight: '500',
    marginLeft: 4,
  },
  scrollContainer: {
    maxHeight: 300,
  },
  statusItem: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderRadius: 8,
    borderWidth: 1,
    marginBottom: 8,
  },
  statusIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  statusContent: {
    flex: 1,
  },
  statusName: {
    fontSize: 16,
    fontWeight: '600',
    marginBottom: 2,
  },
  statusDescription: {
    fontSize: 12,
  },
  checkbox: {
    padding: 4,
  },
  checkboxSelected: {
    transform: [{ rotate: '360deg' }],
  },
  footer: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: '#E0E0E0',
    marginTop: 8,
  },
  footerText: {
    fontSize: 12,
    textAlign: 'center',
  },
});

export default StatusFilter;