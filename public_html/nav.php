
  <style>

    .row {
      display: flex;          /* Place table and preview side by side */
      align-items: flex-start;
    }
    
    .table-container {
      margin-right: 20px;     /* Spacing between the table and image preview */
    }
    
    #image-preview {
        margin: 1em 0; 
      width: 400px;           /* Adjust as needed */
      border: 2px solid #4e54c8;
      padding:1px;
      display: flex;
      justify-content: center;
      align-items: center;
      background-color: #fafafa;
      overflow: hidden;       /* If images are large, this hides overflow */
      position: relative;
    }

    #image-preview img {
      display: none;          /* Hide images by default */
      max-width: 100%;
      max-height: 100%;
    }

    .plate:hover {
      background-color: #eee;
    }

    /* Basic reset for margins and padding */
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    
    /* BODY STYLING */
    body {
      font-family: Arial, sans-serif;
      background: #f0f0f0; /* Subtle background color */
      margin: 20px;
      color: #333;         /* Default text color */
      line-height: 1.6;    /* Improve readability */
    }

    /* NAVIGATION BAR STYLES (Same decorative menu as before) */
    nav {
      background: linear-gradient(to right, #4e54c8, #8f94fb);
      border-radius: 8px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
      margin-bottom: 20px; /* Space under menu */
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-right: 20px;
    }
    nav ul {
      display: flex;      /* Horizontal layout */
      list-style: none;  
    }
    nav ul li a {
      display: inline-block;
      padding: 15px 20px;
      text-decoration: none;
      color: #fff;
      font-weight: 600;
      transition: background 0.3s, color 0.3s;
      border-radius: 4px;
    }
    nav ul li a:hover {
      background: rgba(255, 255, 255, 0.2);
    }
    nav ul li a.active {
      background: #fff; 
      color: #4e54c8;
      font-weight: bold;
    }
    .logout-link a {
        color: white;
        text-decoration: none;
        font-weight: 600;
        padding: 15px 0;
    }

    /* HEADINGS STYLING */
    h1, h2, h3, h4, h5, h6 {
      margin-bottom: 0.6em;  /* Spacing under headings */
      font-weight: 700;      /* Make headings bold */
      color: #4e54c8;        /* Accent color for headings */
    }

    button {
      background-color: #4e54c8;   
      width:100%;
      height:1.5em;
      color:white;
      font-size:14px;
    }

    /* Specific style for the edit button on the all.php page */
    .edit-btn {
        width: 100%; /* It should be contained within .offence now */
        height: 1.5em;
        font-size: 14px;
    }

    /* Placeholder to keep alignment consistent when no button is shown */
    .edit-btn-placeholder {
        width: 100%;
        height: 1.5em;
    }

    /* PARAGRAPH STYLING */
    p {
      margin-bottom: 1em;    /* Spacing after paragraphs */
    }

    /* TABLE STYLING */
    table {
      width: 100%;
      border-collapse: collapse; /* Merge borders for a cleaner look */
      margin: 1em 0;            /* Space above and below the table */
      background-color: #fff;   /* White background for clarity */
      border-radius: 5px;       /* Slight rounding of corners (requires some overflow handling if you want to see rounded edges) */
      overflow: hidden;
    }
    th, td {
      border: 1px solid #ddd;
      padding: 0.75em 1em;      /* Spacing inside cells */
      text-align: left;
    }
    th {
      background-color: #4e54c8;
      color: #fff;
    }
    /* Alternate row coloring for readability */
    tbody tr:nth-child(even) {
      background-color: #f9f9f9;
    }
  </style>
</head>
<body>

<?php
if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'parking.cweb.com.au') {
    echo '<div style="background-color: red; color: white; text-align: center; padding: 10px; font-weight: bold;">DEVELOPMENT VERSION</div>';
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

</body>
</html>
