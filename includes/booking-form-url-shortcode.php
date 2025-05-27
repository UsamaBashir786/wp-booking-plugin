<?php
// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

/**
 * Shortcode: [dynamic_house_booking_form]
 * Usage: Place [dynamic_house_booking_form] on any page. 
 * It will show the booking form for the house_id passed in the URL (?house_id=5).
 */
function houses_admin_dynamic_booking_form_shortcode()
{
  $house_id = isset($_GET['house_id']) ? absint($_GET['house_id']) : 0;

  if (!$house_id) {
    return '<p class="alert alert-danger">No house selected. Please choose a house to book.</p>';
  }

  // Use the existing booking form shortcode with the detected house_id
  return do_shortcode('[house_booking_form house_id="' . $house_id . '"]');
}
add_shortcode('dynamic_house_booking_form', 'houses_admin_dynamic_booking_form_shortcode');
