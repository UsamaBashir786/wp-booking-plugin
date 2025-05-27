<?php
// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

// Create houses table on plugin activation
function houses_admin_create_table()
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'houses';
  $charset_collate = $wpdb->get_charset_collate();

  $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        image VARCHAR(255) NOT NULL,
        star_rating INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        price_per_night DECIMAL(10,2) NOT NULL,
        type VARCHAR(50) NOT NULL,
        price_details TEXT,
        features TEXT,
        location VARCHAR(255) NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_type (type),
        INDEX idx_location (location),
        INDEX idx_status (status)
    ) $charset_collate;";

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  dbDelta($sql);
}

// Create bookings table on plugin activation
function houses_admin_create_bookings_table()
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'bookings';
  $charset_collate = $wpdb->get_charset_collate();

  $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        house_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED DEFAULT 0,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        from_date DATE NOT NULL,
        to_date DATE NOT NULL,
        adults INT NOT NULL DEFAULT 0,
        children INT NOT NULL DEFAULT 0,
        infants INT NOT NULL DEFAULT 0,
        total_price DECIMAL(10,2) DEFAULT 0,
        status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
        message TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_house_id (house_id),
        INDEX idx_user_id (user_id),
        INDEX idx_dates (from_date, to_date),
        INDEX idx_status (status)
    ) $charset_collate;";

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  dbDelta($sql);
}

// Save house data with validation
function houses_admin_save_house($data)
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'houses';

  // Validate required fields
  $required_fields = ['image', 'star_rating', 'title', 'description', 'price_per_night', 'type', 'location'];
  foreach ($required_fields as $field) {
    if (empty($data[$field]) && $data[$field] !== '0') {
      return false;
    }
  }

  // Ensure status is always set to 'active' by default if not provided
  if (!isset($data['status']) || empty($data['status'])) {
    $data['status'] = 'active';
  }

  $result = $wpdb->insert($table_name, $data, [
    '%s', // image
    '%d', // star_rating
    '%s', // title
    '%s', // description
    '%f', // price_per_night
    '%s', // type
    '%s', // price_details
    '%s', // features
    '%s', // location
    '%s'  // status
  ]);

  return $result !== false ? $wpdb->insert_id : false;
}

// Save booking data with validation and price calculation
function houses_admin_save_booking($data)
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'bookings';

  // Calculate total price
  if (isset($data['house_id']) && isset($data['from_date']) && isset($data['to_date'])) {
    $house = houses_admin_get_house($data['house_id']);
    if ($house) {
      $days = (strtotime($data['to_date']) - strtotime($data['from_date'])) / (60 * 60 * 24);
      $data['total_price'] = $days * $house['price_per_night'];
    }
  }

  $result = $wpdb->insert($table_name, $data, [
    '%d',
    '%d',
    '%s',
    '%s',
    '%s',
    '%s',
    '%s',
    '%s',
    '%d',
    '%d',
    '%d',
    '%f',
    '%s',
    '%s'
  ]);

  return $result !== false ? $wpdb->insert_id : false;
}

// Get all houses with optional filters
function houses_admin_get_houses($filters = [])
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'houses';

  $where_clauses = ["status = 'active'"];
  $values = [];

  if (!empty($filters['type'])) {
    $where_clauses[] = "type = %s";
    $values[] = $filters['type'];
  }

  if (!empty($filters['location'])) {
    $where_clauses[] = "location LIKE %s";
    $values[] = '%' . $wpdb->esc_like($filters['location']) . '%';
  }

  $where_sql = implode(' AND ', $where_clauses);
  $sql = "SELECT * FROM $table_name WHERE $where_sql ORDER BY created_at DESC";

  if (!empty($values)) {
    $sql = $wpdb->prepare($sql, $values);
  }

  return $wpdb->get_results($sql, ARRAY_A);
}

// Get single house by ID
function houses_admin_get_house($id)
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'houses';
  return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id), ARRAY_A);
}

// Update house data
function houses_admin_update_house($id, $data)
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'houses';

  // Add updated timestamp
  $data['updated_at'] = current_time('mysql');

  return $wpdb->update($table_name, $data, ['id' => $id], [
    '%s',
    '%d',
    '%s',
    '%s',
    '%f',
    '%s',
    '%s',
    '%s',
    '%s',
    '%s',
    '%s'
  ], ['%d']);
}

// Delete house (soft delete by setting status to inactive)
// Permanently delete house from the database
function houses_admin_delete_house($id)
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'houses';
  return $wpdb->delete($table_name, ['id' => $id], ['%d']);
}

// Get all bookings with house details
function houses_admin_get_bookings($filters = [])
{
  global $wpdb;
  $bookings_table = $wpdb->prefix . 'bookings';
  $houses_table = $wpdb->prefix . 'houses';

  $where_clauses = [];
  $values = [];

  if (!empty($filters['house_id'])) {
    $where_clauses[] = "b.house_id = %d";
    $values[] = $filters['house_id'];
  }

  if (!empty($filters['status'])) {
    $where_clauses[] = "b.status = %s";
    $values[] = $filters['status'];
  }

  $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

  $sql = "SELECT b.*, h.title as house_title, h.location as house_location 
            FROM $bookings_table b 
            LEFT JOIN $houses_table h ON b.house_id = h.id 
            $where_sql 
            ORDER BY b.created_at DESC";

  if (!empty($values)) {
    $sql = $wpdb->prepare($sql, $values);
  }

  return $wpdb->get_results($sql, ARRAY_A);
}

// Check date availability for a house
function houses_admin_check_availability($house_id, $from_date, $to_date, $exclude_booking_id = null)
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'bookings';

  $exclude_clause = $exclude_booking_id ? $wpdb->prepare("AND id != %d", $exclude_booking_id) : '';

  $sql = $wpdb->prepare("
        SELECT COUNT(*) 
        FROM $table_name 
        WHERE house_id = %d 
        AND status IN ('pending', 'confirmed')
        AND (
            (from_date <= %s AND to_date > %s) OR
            (from_date < %s AND to_date >= %s) OR
            (from_date >= %s AND to_date <= %s)
        )
        $exclude_clause
    ", $house_id, $from_date, $from_date, $to_date, $to_date, $from_date, $to_date);

  return $wpdb->get_var($sql) == 0;
}

// Update booking status
function houses_admin_update_booking_status($booking_id, $status)
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'bookings';

  $valid_statuses = ['pending', 'confirmed', 'cancelled'];
  if (!in_array($status, $valid_statuses)) {
    return false;
  }

  return $wpdb->update(
    $table_name,
    ['status' => $status, 'updated_at' => current_time('mysql')],
    ['id' => $booking_id],
    ['%s', '%s'],
    ['%d']
  );
}
