<?php
// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

function houses_admin_handle_form()
{
  // Verify nonce
  if (!isset($_POST['houses_admin_nonce_field']) || !wp_verify_nonce($_POST['houses_admin_nonce_field'], 'houses_admin_nonce')) {
    wp_die('Security check failed.');
  }

  // Check user capability
  if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access.');
  }

  // Initialize errors
  $errors = [];

  // Validate required fields
  $required_fields = ['title', 'description', 'price_per_night', 'type', 'location'];
  foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
      $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
    }
  }

  // Validate star rating
  $star_rating = isset($_POST['star_rating']) ? intval($_POST['star_rating']) : 0;
  if ($star_rating < 1 || $star_rating > 5) {
    $errors[] = 'Star rating must be between 1 and 5.';
  }

  // Validate price per night
  $price_per_night = isset($_POST['price_per_night']) ? intval($_POST['price_per_night']) : 0;
  if ($price_per_night <= 0) {
    $errors[] = 'Price per night must be a positive number.';
  }

  // Validate type
  $valid_types = ['Villa', 'Entire Home'];
  $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
  if (!in_array($type, $valid_types)) {
    $errors[] = 'Invalid type selected.';
  }

  // Handle image upload
  $image_path = '';
  $is_edit = isset($_POST['house_id']) && !empty($_POST['house_id']);
  if (!$is_edit && empty($_FILES['image']['name'])) {
    $errors[] = 'Image is required for new houses.';
  } elseif (!empty($_FILES['image']['name'])) {
    $allowed_types = ['image/jpeg', 'image/png'];
    if (!in_array($_FILES['image']['type'], $allowed_types)) {
      $errors[] = 'Image must be JPG or PNG.';
    } else {
      $upload = wp_handle_upload($_FILES['image'], ['test_form' => false]);
      if ($upload && !isset($upload['error'])) {
        $image_path = $upload['url'];
      } else {
        $errors[] = 'Image upload failed: ' . $upload['error'];
      }
    }
  }

  // Sanitize inputs
  $title = sanitize_text_field($_POST['title']);
  $description = sanitize_textarea_field($_POST['description']);
  $price_details = isset($_POST['price_details']) ? sanitize_text_field($_POST['price_details']) : '';
  $features = isset($_POST['features']) ? implode(',', array_map('sanitize_text_field', $_POST['features'])) : '';
  $location = sanitize_text_field($_POST['location']);

  // If there are errors, display them
  if (!empty($errors)) {
    echo '<div class="alert alert-danger">';
    foreach ($errors as $error) {
      echo '<p>' . esc_html($error) . '</p>';
    }
    echo '</div>';
    return;
  }

  // Prepare data for database
  $data = [
    'image' => $image_path ?: (isset($_POST['current_image']) ? sanitize_text_field($_POST['current_image']) : ''),
    'star_rating' => $star_rating,
    'title' => $title,
    'description' => $description,
    'price_per_night' => $price_per_night,
    'type' => $type,
    'price_details' => $price_details,
    'features' => $features,
    'location' => $location,
  ];

  // Save or update house
  if ($is_edit) {
    $house_id = absint($_POST['house_id']);
    houses_admin_update_house($house_id, $data);
    echo '<div class="alert alert-success">House updated successfully.</div>';
  } else {
    houses_admin_save_house($data);
    echo '<div class="alert alert-success">House added successfully.</div>';
  }
}
