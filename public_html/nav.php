<?php
if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'parking.cweb.com.au') {
    echo '<div class="dev-banner">DEVELOPMENT VERSION</div>';
}
?>

<!-- NAVIGATION MENU -->
<nav>
  <ul>
    <li><a href="offend.php">Weekly Offenders</a></li>
    <li><a href="all.php">All Photos</a></li>
    <li><a href="survey.php">Survey Photos</a></li>
    <li><a href="offender_photos.php">Offender Photos</a></li>
    <li><a href="manage_permissions.php">Manage Permissions</a></li>
    <li><a href="manage_templates.php">Manage Templates</a></li>
  </ul>
  <div class="logout-link">
      <a href="logout.php">Logout</a>
  </div>
</nav>

<!-- SCRIPT TO HIGHLIGHT ACTIVE MENU LINK -->
<script>
  // Get the current page filename (e.g., "offend.php", "all.php", etc.)
  const currentPage = window.location.pathname.split('/').pop();

  // Select all <a> elements inside the navigation
  const menuLinks = document.querySelectorAll('nav ul li a');

  // Compare the filename in each link to the currentPage and add "active" if they match
  menuLinks.forEach(link => {
    if (link.getAttribute('href') === currentPage) {
      link.classList.add('active');
    }
  });

  // Wait until the DOM is fully loaded
  document.addEventListener('DOMContentLoaded', function() {

      // Query all elements with class .plate
      var plateCells = document.querySelectorAll('.plate td');

      // Convert NodeList to an array, or just iterate directly
      plateCells.forEach(function(cell) {

          // Mouse enters the cell
          cell.addEventListener('mouseover', function() {
              // Hide all images
              var images = document.querySelectorAll('#image-preview img');
              images.forEach(function(img) {
                  img.style.display = 'none';
              });

              // Show the matching image
              var plateId = cell.parentElement.getAttribute('data-plate');  // e.g. "one"
              var targetImage = document.getElementById('image-' + plateId); // e.g. "image-one"
              if (targetImage) {
                  targetImage.style.display = 'block';
              }
          });

          // Mouse leaves the cell (optional)

      });

      // Query all unit number cells
      var unitnumberCells = document.querySelectorAll('.unitnumber');

      // Convert NodeList to an array, or just iterate directly
      unitnumberCells.forEach(function(cell) {

          // Mouse enters the cell
          cell.addEventListener('mouseover', function() {

          // Hide all images
          var images = document.querySelectorAll('#image-preview img');
          images.forEach(function(img) {
              img.style.display = 'none';
          });

          // Show the matching image
          var unitnumberId = cell.getAttribute('data-unitnumber');  // e.g. "one"
          console.log('unit-' + unitnumberId);
          var targetImage = document.getElementById('image-unit-' + unitnumberId); // e.g. "image-one"
          if (targetImage) {
              targetImage.style.display = 'block';
          }
          });

          // Mouse leaves the cell (optional)

      });

  });

</script>
