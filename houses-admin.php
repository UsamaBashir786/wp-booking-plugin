<?php
/*
Plugin Name: Houses Admin
Description: A custom WordPress plugin to manage house/villa rental listings with an admin panel and booking functionality.
Version: 1.1
Author: Grok
*/

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'includes/db-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/form-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/booking-form.php';

// Register activation hook to create database tables
register_activation_hook(__FILE__, 'houses_admin_create_table');
register_activation_hook(__FILE__, 'houses_admin_create_bookings_table');

// Add admin menu
add_action('admin_menu', 'houses_admin_menu');
function houses_admin_menu()
{
  add_menu_page(
    'Houses',
    'Houses',
    'manage_options',
    'houses-admin',
    'houses_admin_page',
    'dashicons-admin-home',
    20
  );
}

function display_house_listings() {
  global $wpdb;

  // Force fresh data - Clear WordPress object cache (safe for frontend)
  wp_cache_flush();

  $table = $wpdb->prefix . 'houses';

  // Make sure you only fetch rows that really exist
  $results = $wpdb->get_results("SELECT * FROM $table WHERE status = 'active' ORDER BY id DESC");

  if (!$results) {
      return "<p>No houses found.</p>";
  }

  $output = '<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">';

  foreach ($results as $house) {
    $output .= '
    <div class="col">
      <div class="card h-100">
        <img src="' . esc_url($house->image) . '" class="card-img-top" alt="House Image">
        <div class="card-body">
          <h5 class="card-title">' . esc_html($house->title) . '</h5>
          <p class="card-text">' . esc_html($house->description) . '</p>
          <p class="card-text"><strong>Price/Night:</strong> $' . esc_html($house->price_per_night) . '</p>
          <p class="card-text"><strong>Type:</strong> ' . esc_html($house->type) . '</p>
          <p class="card-text"><strong>Location:</strong> ' . esc_html($house->location) . '</p>
          <p class="card-text"><strong>⭐ Rating:</strong> ' . esc_html($house->star_rating) . '/5</p>
          <a href="' . site_url('/booking-page') . '?house_id=' . esc_attr($house->id) . '" class="btn btn-primary mt-2">Book Now</a>
        </div>
      </div>
    </div>';
  }

  $output .= '</div>';

  return $output;
}
add_shortcode('show_houses', 'display_house_listings');



// Enqueue Bootstrap 5 and custom scripts/styles for admin
add_action('admin_enqueue_scripts', 'houses_admin_enqueue_scripts');
function houses_admin_enqueue_scripts($hook)
{
  if ($hook !== 'toplevel_page_houses-admin') {
    return;
  }
  // Bootstrap 5 CSS
  wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
  // Custom CSS
  wp_enqueue_style('houses-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css');
}goo

// Enqueue styles for front-end booking form
add_action('wp_enqueue_scripts', 'houses_admin_enqueue_front_scripts');
function houses_admin_enqueue_front_scripts()
{
  // Bootstrap 5 CSS for front end
  wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
  // Custom booking CSS
  wp_enqueue_style('houses-booking-style', plugin_dir_url(__FILE__) . 'assets/css/booking-style.css');
}

// Register shortcode for booking form
add_shortcode('house_booking_form', 'houses_admin_booking_form_shortcode');
