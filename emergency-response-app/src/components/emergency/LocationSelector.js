import React, { useState, useEffect, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Modal,
  FlatList,
  TextInput,
  Alert,
  PermissionsAndroid,
  Platform,
  ActivityIndicator,
} from 'react-native';
import { useTheme } from '@react-navigation/native';
import Icon from 'react-native-vector-icons/MaterialIcons';
import * as Location from 'expo-location';
import Card from '../common/Card';
import Button from '../common/Button';
import Input from '../common/Input';

const LocationSelector = ({ selectedLocation, onLocationSelect, visible, onClose }) => {
  const theme = useTheme();
  const colors = theme.colors;

  const [locations, setLocations] = useState([]);
  const [filteredLocations, setFilteredLocations] = useState([]);
  const [searchText, setSearchText] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('all');
  const [currentLocation, setCurrentLocation] = useState(null);
  const [gettingLocation, setGettingLocation] = useState(false);
  const [customLocation, setCustomLocation] = useState({
    name: '',
    description: '',
  });
  const [showCustomForm, setShowCustomForm] = useState(false);
  const searchInputRef = useRef(null);

  const categories = [
    { id: 'all', name: 'All Locations', icon: 'location-on' },
    { id: 'academic', name: 'Academic', icon: 'school' },
    { id: 'hostel', name: 'Hostels', icon: 'hotel' },
    { id: 'admin', name: 'Administrative', icon: 'business' },
    { id: 'recreational', name: 'Recreational', icon: 'sports-soccer' },
    { id: 'medical', name: 'Medical', icon: 'local-hospital' },
    { id: 'other', name: 'Other', icon: 'more-horiz' },
  ];

  useEffect(() => {
    if (visible) {
      loadLocations();
      requestLocationPermission();
    }
  }, [visible]);

  useEffect(() => {
    filterLocations();
  }, [searchText, selectedCategory, locations]);

  const loadLocations = async () => {
    try {
      // This would be an API call in a real app
      const mockLocations = [
        { id: 1, name: 'Main Library', category: 'academic', latitude: 12.4567, longitude: 10.1234, description: 'University main library' },
        { id: 2, name: 'Science Laboratory', category: 'academic', latitude: 12.4578, longitude: 10.1245, description: 'Science faculty laboratories' },
        { id: 3, name: 'Student Hostel A', category: 'hostel', latitude: 12.4589, longitude: 10.1256, description: 'Male student accommodation' },
        { id: 4, name: 'Student Hostel B', category: 'hostel', latitude: 12.4600, longitude: 10.1267, description: 'Female student accommodation' },
        { id: 5, name: 'Administrative Block', category: 'admin', latitude: 12.4611, longitude: 10.1278, description: 'University administrative offices' },
        { id: 6, name: 'University Health Center', category: 'medical', latitude: 12.4622, longitude: 10.1289, description: 'Campus medical facility' },
        { id: 7, name: 'Sports Complex', category: 'recreational', latitude: 12.4633, longitude: 10.1300, description: 'Sports and recreation facilities' },
        { id: 8, name: 'Main Gate', category: 'other', latitude: 12.4644, longitude: 10.1311, description: 'University main entrance' },
      ];
      setLocations(mockLocations);
    } catch (error) {
      console.error('Error loading locations:', error);
      Alert.alert('Error', 'Failed to load locations. Please try again.');
    }
  };

  const requestLocationPermission = async () => {
    if (Platform.OS === 'android') {
      const granted = await PermissionsAndroid.request(
        PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION
      );
      return granted === PermissionsAndroid.RESULTS.GRANTED;
    } else {
      const { status } = await Location.requestForegroundPermissionsAsync();
      return status === 'granted';
    }
  };

  const getCurrentLocation = async () => {
    setGettingLocation(true);
    try {
      const hasPermission = await requestLocationPermission();
      if (!hasPermission) {
        Alert.alert('Permission Required', 'Location permission is required to get your current location.');
        setGettingLocation(false);
        return;
      }

      const location = await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.High,
      });

      const currentLoc = {
        latitude: location.coords.latitude,
        longitude: location.coords.longitude,
        name: 'Current Location',
        description: `GPS: ${location.coords.latitude.toFixed(6)}, ${location.coords.longitude.toFixed(6)}`,
        category: 'current',
        id: 'current',
      };

      setCurrentLocation(currentLoc);
      setGettingLocation(false);
    } catch (error) {
      console.error('Error getting location:', error);
      Alert.alert('Error', 'Failed to get your current location. Please select a location manually.');
      setGettingLocation(false);
    }
  };

  const filterLocations = () => {
    let filtered = locations;

    // Filter by category
    if (selectedCategory !== 'all') {
      filtered = filtered.filter(loc => loc.category === selectedCategory);
    }

    // Filter by search text
    if (searchText) {
      filtered = filtered.filter(loc =>
        loc.name.toLowerCase().includes(searchText.toLowerCase()) ||
        loc.description.toLowerCase().includes(searchText.toLowerCase())
      );
    }

    setFilteredLocations(filtered);
  };

  const handleLocationSelect = (location) => {
    onLocationSelect?.(location);
    onClose();
  };

  const handleCustomLocationSubmit = () => {
    if (!customLocation.name.trim()) {
      Alert.alert('Error', 'Please enter a location name.');
      return;
    }

    const customLoc = {
      id: 'custom',
      name: customLocation.name,
      description: customLocation.description || 'Custom location',
      category: 'custom',
      latitude: null,
      longitude: null,
    };

    handleLocationSelect(customLoc);
  };

  const calculateDistance = (lat1, lon1, lat2, lon2) => {
    const R = 6371; // Earth's radius in km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(lat1 * Math.PI / 180) *
      Math.cos(lat2 * Math.PI / 180) *
      Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c; // Distance in km
  };

  const renderLocationItem = ({ item }) => {
    const distance = currentLocation && item.latitude && item.longitude
      ? calculateDistance(
          currentLocation.latitude,
          currentLocation.longitude,
          item.latitude,
          item.longitude
        )
      : null;

    return (
      <TouchableOpacity
        style={styles.locationItem}
        onPress={() => handleLocationSelect(item)}
      >
        <View style={styles.locationInfo}>
          <View style={styles.locationHeader}>
            <Icon
              name={categories.find(cat => cat.id === item.category)?.icon || 'location-on'}
              size={20}
              color={colors.primary}
            />
            <Text style={[styles.locationName, { color: colors.text }]}>{item.name}</Text>
            {distance && (
              <Text style={[styles.distance, { color: colors.placeholder }]}>
                {distance < 1 ? `${(distance * 1000).toFixed(0)}m` : `${distance.toFixed(1)}km`}
              </Text>
            )}
          </View>
          <Text style={[styles.locationDescription, { color: colors.placeholder }]}>
            {item.description}
          </Text>
        </View>
        <Icon name="chevron-right" size={20} color={colors.placeholder} />
      </TouchableOpacity>
    );
  };

  return (
    <Modal
      visible={visible}
      animationType="slide"
      presentationStyle="page"
      onRequestClose={onClose}
    >
      <View style={[styles.container, { backgroundColor: colors.background }]}>
        <View style={[styles.header, { borderBottomColor: colors.border }]}>
          <TouchableOpacity onPress={onClose} style={styles.closeButton}>
            <Icon name="close" size={24} color={colors.text} />
          </TouchableOpacity>
          <Text style={[styles.title, { color: colors.text }]}>Select Location</Text>
          <View style={styles.placeholder} />
        </View>

        <View style={styles.content}>
          {/* Current Location */}
          <Card style={styles.currentLocationCard}>
            <TouchableOpacity
              style={styles.currentLocationButton}
              onPress={getCurrentLocation}
              disabled={gettingLocation}
            >
              <View style={styles.currentLocationContent}>
                {gettingLocation ? (
                  <ActivityIndicator size="small" color={colors.primary} />
                ) : (
                  <Icon name="my-location" size={24} color={colors.primary} />
                )}
                <View style={styles.currentLocationText}>
                  <Text style={[styles.currentLocationTitle, { color: colors.text }]}>
                    Use Current Location
                  </Text>
                  <Text style={[styles.currentLocationSubtitle, { color: colors.placeholder }]}>
                    {gettingLocation ? 'Getting location...' : 'GPS-based location detection'}
                  </Text>
                </View>
              </View>
            </TouchableOpacity>
          </Card>

          {/* Search */}
          <Input
            ref={searchInputRef}
            placeholder="Search locations..."
            value={searchText}
            onChangeText={setSearchText}
            leftIcon={<Icon name="search" size={20} color={colors.placeholder} />}
            style={styles.searchInput}
          />

          {/* Categories */}
          <View style={styles.categoriesContainer}>
            <FlatList
              data={categories}
              horizontal
              showsHorizontalScrollIndicator={false}
              keyExtractor={(item) => item.id}
              renderItem={({ item }) => (
                <TouchableOpacity
                  style={[
                    styles.categoryButton,
                    {
                      backgroundColor: selectedCategory === item.id ? colors.primary : colors.card,
                      borderColor: colors.border,
                    },
                  ]}
                  onPress={() => setSelectedCategory(item.id)}
                >
                  <Icon
                    name={item.icon}
                    size={16}
                    color={selectedCategory === item.id ? '#FFFFFF' : colors.primary}
                  />
                  <Text
                    style={[
                      styles.categoryButtonText,
                      {
                        color: selectedCategory === item.id ? '#FFFFFF' : colors.primary,
                      },
                    ]}
                  >
                    {item.name}
                  </Text>
                </TouchableOpacity>
              )}
            />
          </View>

          {/* Locations List */}
          <FlatList
            data={currentLocation ? [currentLocation, ...filteredLocations] : filteredLocations}
            keyExtractor={(item) => item.id}
            renderItem={renderLocationItem}
            style={styles.locationsList}
            showsVerticalScrollIndicator={false}
            ListEmptyComponent={
              <View style={styles.emptyContainer}>
                <Icon name="location-off" size={48} color={colors.placeholder} />
                <Text style={[styles.emptyText, { color: colors.placeholder }]}>
                  No locations found
                </Text>
              </View>
            }
          />

          {/* Custom Location Button */}
          <Card style={styles.customLocationCard}>
            <TouchableOpacity
              style={styles.customLocationButton}
              onPress={() => setShowCustomForm(true)}
            >
              <Icon name="add-location" size={24} color={colors.primary} />
              <Text style={[styles.customLocationText, { color: colors.primary }]}>
                Add Custom Location
              </Text>
            </TouchableOpacity>
          </Card>
        </View>

        {/* Custom Location Form Modal */}
        <Modal
          visible={showCustomForm}
          animationType="fade"
          transparent={true}
          onRequestClose={() => setShowCustomForm(false)}
        >
          <View style={styles.customLocationModal}>
            <View style={[styles.customLocationForm, { backgroundColor: colors.card }]}>
              <Text style={[styles.customLocationTitle, { color: colors.text }]}>
                Add Custom Location
              </Text>
              <Input
                label="Location Name"
                value={customLocation.name}
                onChangeText={(value) => setCustomLocation(prev => ({ ...prev, name: value }))}
                placeholder="e.g., Classroom 101"
                style={styles.customInput}
              />
              <Input
                label="Description (Optional)"
                value={customLocation.description}
                onChangeText={(value) => setCustomLocation(prev => ({ ...prev, description: value }))}
                placeholder="Additional details about this location"
                multiline
                numberOfLines={3}
                style={styles.customInput}
              />
              <View style={styles.customLocationActions}>
                <Button
                  title="Cancel"
                  onPress={() => setShowCustomForm(false)}
                  variant="outline"
                  style={styles.customCancelButton}
                />
                <Button
                  title="Add Location"
                  onPress={handleCustomLocationSubmit}
                  style={styles.customAddButton}
                />
              </View>
            </View>
          </View>
        </Modal>
      </View>
    </Modal>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: 1,
  },
  closeButton: {
    padding: 4,
  },
  title: {
    fontSize: 18,
    fontWeight: '600',
  },
  placeholder: {
    width: 24,
  },
  content: {
    flex: 1,
    padding: 16,
  },
  currentLocationCard: {
    marginBottom: 16,
  },
  currentLocationButton: {
    padding: 16,
  },
  currentLocationContent: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  currentLocationText: {
    marginLeft: 12,
    flex: 1,
  },
  currentLocationTitle: {
    fontSize: 16,
    fontWeight: '600',
    marginBottom: 2,
  },
  currentLocationSubtitle: {
    fontSize: 14,
  },
  searchInput: {
    marginBottom: 16,
  },
  categoriesContainer: {
    marginBottom: 16,
  },
  categoryButton: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 20,
    borderWidth: 1,
    marginRight: 8,
  },
  categoryButtonText: {
    fontSize: 12,
    fontWeight: '500',
    marginLeft: 4,
  },
  locationsList: {
    flex: 1,
  },
  locationItem: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  locationInfo: {
    flex: 1,
  },
  locationHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 4,
  },
  locationName: {
    fontSize: 16,
    fontWeight: '600',
    marginLeft: 8,
    flex: 1,
  },
  distance: {
    fontSize: 12,
    marginLeft: 8,
  },
  locationDescription: {
    fontSize: 14,
    marginLeft: 28,
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 40,
  },
  emptyText: {
    fontSize: 16,
    marginTop: 12,
    textAlign: 'center',
  },
  customLocationCard: {
    marginTop: 16,
  },
  customLocationButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 16,
  },
  customLocationText: {
    fontSize: 16,
    fontWeight: '600',
    marginLeft: 12,
  },
  customLocationModal: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  customLocationForm: {
    borderRadius: 12,
    padding: 20,
    width: '100%',
    maxWidth: 400,
  },
  customLocationTitle: {
    fontSize: 18,
    fontWeight: '600',
    marginBottom: 16,
    textAlign: 'center',
  },
  customInput: {
    marginBottom: 16,
  },
  customLocationActions: {
    flexDirection: 'row',
    gap: 12,
  },
  customCancelButton: {
    flex: 1,
  },
  customAddButton: {
    flex: 1,
  },
});

export default LocationSelector;