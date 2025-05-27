<?php
// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

function houses_admin_page()
{
  // Check user capability
  if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
  }

  // Handle form submission
  if (isset($_POST['houses_admin_submit'])) {
    houses_admin_handle_form();
  }

  // Handle delete action
  if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    check_admin_referer('houses_admin_delete_' . $_GET['id']);
    houses_admin_delete_house(absint($_GET['id']));
    echo '<div class="alert alert-success">House deleted successfully.</div>';
  }

  // Handle edit action
  $edit_house = null;
  if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_house = houses_admin_get_house(absint($_GET['id']));
  }
?>
  <div class="wrap">
    <h1 class="wp-heading-inline"><?php echo $edit_house ? 'Edit House' : 'Add New House'; ?></h1>
    <div class="container mt-4">
      <form method="post" enctype="multipart/form-data" class="card p-4">
        <?php wp_nonce_field('houses_admin_nonce', 'houses_admin_nonce_field'); ?>
        <div class="mb-3">
          <label for="image" class="form-label">Image (JPG/PNG)</label>
          <input type="file" class="form-control" id="image" name="image" accept=".jpg,.png" <?php echo $edit_house ? '' : 'required'; ?>>
          <?php if ($edit_house && $edit_house['image']) : ?>
            <p>Current image: <?php echo esc_html($edit_house['image']); ?></p>
          <?php endif; ?>
        </div>
        <div class="mb-3">
          <label for="star_rating" class="form-label">Star Rating (1-5)</label>
          <select class="form-select" id="star_rating" name="star_rating" required>
            <?php for ($i = 1; $i <= 5; $i++) : ?>
              <option value="<?php echo $i; ?>" <?php echo $edit_house && $edit_house['star_rating'] == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="mb-3">
          <label for="title" class="form-label">Title</label>
          <input type="text" class="form-control" id="title" name="title" value="<?php echo $edit_house ? esc_attr($edit_house['title']) : ''; ?>" required>
        </div>
        <div class="mb-3">
          <label for="description" class="form-label">Description</label>
          <textarea class="form-control" id="description" name="description" rows="4" required><?php echo $edit_house ? esc_textarea($edit_house['description']) : ''; ?></textarea>
        </div>
        <div class="mb-3">
          <label for="price_per_night" class="form-label">Price per Night</label>
          <input type="number" class="form-control" id="price_per_night" name="price_per_night" min="1" value="<?php echo $edit_house ? esc_attr($edit_house['price_per_night']) : ''; ?>" required>
        </div>
        <div class="mb-3">
          <label for="type" class="form-label">Type</label>
          <select class="form-select" id="type" name="type" required>
            <option value="Villa" <?php echo $edit_house && $edit_house['type'] === 'Villa' ? 'selected' : ''; ?>>Villa</option>
            <option value="Entire Home" <?php echo $edit_house && $edit_house['type'] === 'Entire Home' ? 'selected' : ''; ?>>Entire Home</option>
          </select>
        </div>
        <div class="mb-3">
          <label for="price_details" class="form-label">Price Details (Optional)</label>
          <input type="text" class="form-control" id="price_details" name="price_details" value="<?php echo $edit_house ? esc_attr($edit_house['price_details']) : ''; ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Features</label>
          <?php
          $features = ['Wifi', 'Pool', 'AC', 'Parking', 'Kitchen'];
          $selected_features = $edit_house ? explode(',', $edit_house['features']) : [];
          foreach ($features as $feature) :
          ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="features[]" value="<?php echo esc_attr($feature); ?>" id="feature_<?php echo esc_attr($feature); ?>" <?php echo in_array($feature, $selected_features) ? 'checked' : ''; ?>>
              <label class="form-check-label" for="feature_<?php echo esc_attr($feature); ?>"><?php echo esc_html($feature); ?></label>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="mb-3">
          <label for="location" class="form-label">Location</label>
          <input type="text" class="form-control" id="location" name="location" value="<?php echo $edit_house ? esc_attr($edit_house['location']) : ''; ?>" required>
        </div>
        <?php if ($edit_house) : ?>
          <input type="hidden" name="house_id" value="<?php echo esc_attr($edit_house['id']); ?>">
        <?php endif; ?>
        <button type="submit" name="houses_admin_submit" class="btn btn-primary"><?php echo $edit_house ? 'Update House' : 'Add House'; ?></button>
      </form>

      <!-- Houses Listing -->
      <h2 class="mt-5">All Houses</h2>
      <?php $houses = houses_admin_get_houses(); ?>
      <?php if ($houses) : ?>
        <table class="table table-striped mt-3">
          <thead>
            <tr>
              <th>ID</th>
              <th>Title</th>
              <th>Type</th>
              <th>Price/Night</th>
              <th>Location</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($houses as $house) : ?>
              <tr>
                <td><?php echo esc_html($house['id']); ?></td>
                <td><?php echo esc_html($house['title']); ?></td>
                <td><?php echo esc_html($house['type']); ?></td>
                <td><?php echo esc_html($house['price_per_night']); ?></td>
                <td><?php echo esc_html($house['location']); ?></td>
                <td>
                  <a href="<?php echo esc_url(add_query_arg(['action' => 'edit', 'id' => $house['id']])); ?>" class="btn btn-sm btn-warning">Edit</a>
                  <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'delete', 'id' => $house['id']]), 'houses_admin_delete_' . $house['id'])); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this house?');">Delete</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else : ?>
        <p>No houses found.</p>
      <?php endif; ?>
    </div>
  </div>
<?php
}
?>