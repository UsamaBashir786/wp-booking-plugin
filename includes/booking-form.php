<?php
// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

function houses_admin_booking_form_shortcode($atts)
{
  // Extract shortcode attributes (e.g., house_id)
  $atts = shortcode_atts(['house_id' => ''], $atts);
  $house_id = absint($atts['house_id']);

  // Fix: If not set in shortcode, get from URL
  if (!$house_id && isset($_GET['house_id'])) {
    $house_id = absint($_GET['house_id']);
  }

  // Validate house_id
  $house = houses_admin_get_house($house_id);
  if (!$house) {
    return '<p class="alert alert-danger">Invalid house ID.</p>';
  }

  // Check for form submission
  $errors = [];
  $success = '';
  if (isset($_POST['houses_booking_submit'])) {
    // Verify nonce
    if (!isset($_POST['houses_booking_nonce_field']) || !wp_verify_nonce($_POST['houses_booking_nonce_field'], 'houses_booking_nonce')) {
      $errors[] = 'Security check failed.';
    } else {
      // Validate dates
      $from_date = isset($_POST['from_date']) ? sanitize_text_field($_POST['from_date']) : '';
      $to_date = isset($_POST['to_date']) ? sanitize_text_field($_POST['to_date']) : '';
      if (empty($from_date) || empty($to_date)) {
        $errors[] = 'Both From Date and To Date are required.';
      } elseif (strtotime($from_date) >= strtotime($to_date)) {
        $errors[] = 'To Date must be after From Date.';
      } elseif (strtotime($from_date) < strtotime(date('Y-m-d'))) {
        $errors[] = 'From Date cannot be in the past.';
      }

      // Check availability (prevent overlapping bookings)
      if (empty($errors)) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bookings';
        $overlap = $wpdb->get_var($wpdb->prepare(
          "SELECT COUNT(*) FROM $table_name 
          WHERE house_id = %d 
          AND (
            (from_date <= %s AND to_date >= %s) OR 
            (from_date <= %s AND to_date >= %s) OR 
            (from_date >= %s AND to_date <= %s)
          )",
          $house_id,
          $from_date,
          $from_date,
          $to_date,
          $to_date,
          $from_date,
          $to_date
        ));

        if ($overlap > 0) {
          $errors[] = 'The selected dates are not available. Please choose different dates.';
        }
      }

      // Validate guests
      $adults = isset($_POST['adults']) ? absint($_POST['adults']) : 0;
      $children = isset($_POST['children']) ? absint($_POST['children']) : 0;
      $infants = isset($_POST['infants']) ? absint($_POST['infants']) : 0;
      $total_guests = $adults + $children + $infants;
      if ($total_guests === 0) {
        $errors[] = 'At least one guest is required.';
      } elseif ($total_guests > 2) {
        $errors[] = 'Maximum 2 guests allowed (adults + children + infants).';
      }

      // Get user details
      $user_id = 0;
      $first_name = '';
      $last_name = '';
      $email = '';
      $phone = '';
      if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        $first_name = $current_user->first_name;
        $last_name = $current_user->last_name;
        $email = $current_user->user_email;
        $phone = get_user_meta($user_id, 'phone', true) ?: '';
      } else {
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? preg_replace('/[^0-9\+\-\(\)\s]/', '', sanitize_text_field($_POST['phone'])) : '';

        if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
          $errors[] = 'All user details (First Name, Last Name, Email, Phone) are required for non-logged-in users.';
        }
      }

      // Validate message
      $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

      // If no errors, save the booking
      if (empty($errors)) {
        $data = [
          'house_id' => $house_id,
          'user_id' => $user_id,
          'first_name' => $first_name,
          'last_name' => $last_name,
          'email' => $email,
          'phone' => $phone,
          'from_date' => $from_date,
          'to_date' => $to_date,
          'adults' => $adults,
          'children' => $children,
          'infants' => $infants,
          'message' => $message,
        ];

        if (houses_admin_save_booking($data)) {
          $success = 'Booking submitted successfully!';
        } else {
          $errors[] = 'Failed to save booking. Please try again.';
        }
      }
    }
  }

  // Get current user details for pre-filling if logged in
  $current_user = wp_get_current_user();
  $is_logged_in = is_user_logged_in();
  $default_first_name = $is_logged_in ? $current_user->first_name : '';
  $default_last_name = $is_logged_in ? $current_user->last_name : '';
  $default_email = $is_logged_in ? $current_user->user_email : '';
  $default_phone = $is_logged_in ? get_user_meta($current_user->ID, 'phone', true) : '';

  // Output the form
  ob_start();
?>
  <div class="booking-form-container">
    <h2>Book <?php echo esc_html($house['title']); ?></h2>
    <p class="booking-subtitle">Reserve your stay in just a few steps</p>
    <?php if ($success) : ?>
      <div class="alert alert-success"><?php echo esc_html($success); ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)) : ?>
      <div class="alert alert-danger">
        <?php foreach ($errors as $error) : ?>
          <p><?php echo esc_html($error); ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="booking-card">
      <form method="post" id="booking-form">
        <?php wp_nonce_field('houses_booking_nonce', 'houses_booking_nonce_field'); ?>
        <div class="form-section">
          <h3>Booking Dates</h3>
          <div class="date-inputs">
            <div class="date-input-group">
              <label for="from_date" class="form-label">From Date</label>
              <input type="date" class="form-control" id="from_date" name="from_date" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="date-input-group">
              <label for="to_date" class="form-label">To Date</label>
              <input type="date" class="form-control" id="to_date" name="to_date" required>
            </div>
          </div>
        </div>
        <div class="form-section">
          <h3>Guests (Max 2)</h3>
          <div class="guest-counter">
            <div class="guest-input-group">
              <label for="adults" class="form-label">Adults</label>
              <input type="number" class="form-control guest-input" id="adults" name="adults" min="0" max="2" value="0">
            </div>
            <div class="guest-input-group">
              <label for="children" class="form-label">Children</label>
              <input type="number" class="form-control guest-input" id="children" name="children" min="0" max="2" value="0">
            </div>
            <div class="guest-input-group">
              <label for="infants" class="form-label">Infants</label>
              <input type="number" class="form-control guest-input" id="infants" name="infants" min="0" max="2" value="0">
            </div>
          </div>
          <p class="guest-total">Total Guests: <span id="total-guests">0</span> (Max 2)</p>
        </div>
        <div class="form-section <?php echo !$is_logged_in ? 'user-details' : ''; ?>">
          <?php if (!$is_logged_in) : ?>
            <h3>User Details</h3>
            <div class="mb-3">
              <label for="first_name" class="form-label">First Name</label>
              <input type="text" class="form-control" id="first_name" name="first_name" placeholder="Enter your first name" required>
            </div>
            <div class="mb-3">
              <label for="last_name" class="form-label">Last Name</label>
              <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Enter your last name" required>
            </div>
            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
            </div>
            <div class="mb-3">
              <label for="phone" class="form-label">Phone</label>
              <input type="text" class="form-control" id="phone" name="phone" placeholder="Enter your phone number (e.g., +1234567890)" required>
            </div>
          <?php else : ?>
            <h3>User Details (Logged In)</h3>
            <div class="user-info-display">
              <h4>Booking as:</h4>
              <div class="user-info-grid">
                <div class="user-info-item">
                  <strong>First Name:</strong>
                  <span><?php echo esc_html($default_first_name); ?></span>
                </div>
                <div class="user-info-item">
                  <strong>Last Name:</strong>
                  <span><?php echo esc_html($default_last_name); ?></span>
                </div>
                <div class="user-info-item">
                  <strong>Email:</strong>
                  <span><?php echo esc_html($default_email); ?></span>
                </div>
                <div class="user-info-item">
                  <strong>Phone:</strong>
                  <span><?php echo esc_html($default_phone ?: 'Not provided'); ?></span>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
        <div class="form-section">
          <h3>Your Message</h3>
          <div class="mb-3">
            <label for="message" class="form-label">Message (Optional)</label>
            <textarea class="form-control" id="message" name="message" rows="4" placeholder="Any special requests or notes?"></textarea>
          </div>
        </div>
        <button type="submit" name="houses_booking_submit" class="btn btn-primary" id="submit-booking-btn">Submit Booking</button>
      </form>
    </div>
  </div>

  <script>
    // Dynamic guest counter
    document.addEventListener('DOMContentLoaded', function() {
      const guestInputs = document.querySelectorAll('.guest-input');
      const totalGuestsSpan = document.getElementById('total-guests');
      const submitButton = document.getElementById('submit-booking-btn');
      const form = document.getElementById('booking-form');

      function updateTotalGuests() {
        let total = 0;
        guestInputs.forEach(input => {
          total += parseInt(input.value) || 0;
        });
        totalGuestsSpan.textContent = total;
        if (total > 2) {
          totalGuestsSpan.style.color = 'red';
          submitButton.disabled = true;
        } else {
          totalGuestsSpan.style.color = 'inherit';
          submitButton.disabled = false;
        }
      }

      guestInputs.forEach(input => {
        input.addEventListener('input', updateTotalGuests);
      });

      // Add loading state to submit button
      form.addEventListener('submit', function() {
        submitButton.disabled = true;
        submitButton.textContent = 'Submitting...';
      });

      // Set min date for to_date based on from_date
      const fromDateInput = document.getElementById('from_date');
      const toDateInput = document.getElementById('to_date');
      fromDateInput.addEventListener('change', function() {
        toDateInput.min = fromDateInput.value;
        if (toDateInput.value && toDateInput.value <= fromDateInput.value) {
          toDateInput.value = '';
        }
      });
    });
  </script>
<?php
  return ob_get_clean();
}
?>