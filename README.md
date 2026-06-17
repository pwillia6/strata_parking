# Car Parking Enforcement App

This application is a comprehensive tool for managing and enforcing parking regulations in a residential or commercial car park. It consists of a mobile-first Progressive Web App (PWA) for on-site photo capture and a web-based administrative dashboard for reviewing offenses, managing permissions, and issuing notices.

## Features

- **Mobile PWA for Photo Capture**: A lightweight, installable web app for taking photos of vehicles.
- **Offline Capability**: Photos are stored locally on the device using IndexedDB and can be uploaded when a stable internet connection is available.
- **AI-Powered Data Extraction**: Utilizes the OpenAI GPT-4o Vision API to automatically extract license plates and other contextual information from photos (e.g., parking bay numbers, presence of notices).
- **Multiple Capture Modes**:
  - **Visitors**: Log vehicles in visitor parking.
  - **Survey**: Map vehicles to specific parking unit numbers.
  - **Notice**: Document when a physical notice has been placed on a vehicle.
- **Admin Dashboard**: A comprehensive web interface for managing the system.
- **Offender Tracking**: Automatically identifies and flags repeat offenders based on configurable rules.
- **Permission Management**: Grant temporary or permanent visitor parking permissions to specific vehicles.
- **Automated Notice Generation**:
  - Generates `.docx` breach notices from a template, populated with offender details and photographic evidence.
  - Generates `.pdf` reports containing all photos of a specific vehicle.
- **User Authentication**: Secures the admin dashboard with OAuth (Google/Microsoft).

---

## Technology Stack

- **Backend**: PHP (5.6+), MySQL
- **Frontend**: HTML, CSS, JavaScript (ES5/ES6)
- **Key Libraries**:
  - **Dexie.js**: For simplified IndexedDB operations in the PWA.
  - **TinyButStrong (with OpenTBS)**: For generating `.docx` files from templates.
  - **FPDF**: For generating `.pdf` files.
- **APIs**: OpenAI GPT-4 Vision API

---

## Application Flow

1.  **Capture (Mobile PWA - `index.html`)**:
    - An operator uses the PWA on a mobile device to take photos of vehicles.
    - The operator selects a mode (Visitor, Survey, or Notice).
    - Each photo is timestamped and stored locally in the browser's IndexedDB.
    - The PWA automatically attempts to upload pending photos when an internet connection is stable, or the operator can trigger a manual upload.

2.  **Process (Backend APIs - `api2.php`, `api3.php`, `api4.php`)**:
    - The backend API receives the photo and its metadata.
    - It sends the image to the OpenAI API to extract the license plate and other relevant details based on the capture mode.
    - The photo is saved to the server in the `/var/uploads/` or `/var/survey/` directory.
    - The extracted data and file path are stored in the appropriate MySQL table (`parking_records`, `survey`, or `notice`).

3.  **Administer (Web Dashboard)**:
    - Administrators access the web dashboard from a desktop browser.
    - **`offend.php`**: The main "Weekly Offenders" page shows vehicles that have violated parking rules (e.g., overstaying in visitor parking). From here, admins can issue notices, grant permissions, or view more details.
    - **`all.php`**: A searchable and paginated gallery of all photos taken.
    - **`manage_permissions.php`**: A dedicated interface to grant, revoke, and view visitor parking permissions.
    - **`send_notices.php`**: A script that generates `.docx` files for selected offenders and emails them to a pre-configured printer address.

---

## Setup and Installation

1.  **Prerequisites**:
    - A web server with PHP (5.6 or newer).
    - A MySQL database server.
    - Composer for PHP dependencies.

2.  **Clone Repository**:
    ```bash
    git clone <repository-url>
    ```

3.  **Install Dependencies**:
    - The project uses `TinyButStrong`, which is included in the `vendor` directory. If managing dependencies with Composer, run:
    ```bash
    composer install
    ```

4.  **Database Setup**:
    - Create a MySQL database (e.g., `parking`).
    - Create the following tables. A `schema.sql` file should be created for a formal setup.
      - `parking_records`
      - `survey`
      - `notice`
      - `permission`
      - `vehicles`
      - `weekly_notices_issued`

5.  **Configuration**:
    - Copy or rename `lib/config.php.example` to `lib/config.php`.
    - Edit `lib/config.php` and fill in the following constants:
      - **Database Credentials**: `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` (Note: This needs to be refactored into the config).
      - **OAuth Credentials**: `OAUTH_GOOGLE_CLIENT_ID`, `OAUTH_GOOGLE_CLIENT_SECRET`, etc.
      - **OpenAI API Key**: `OPENAI_API_KEY`.

6.  **Directory Permissions**:
    - Ensure the web server has write permissions for the following directories:
      - `/var/` (for the semaphore lock file)
      - `/var/uploads/`
      - `/var/survey/`

7.  **Accessing the App**:
    - **Admin Panel**: Navigate to `https://your-domain/offend.php` on a desktop browser.
    - **PWA**: Navigate to `https://your-domain/index.html` on a mobile device. You should be prompted to "Install App" to add it to your home screen.

---

## Key Files and Directories

- `/public_html/`: The web root containing all user-facing PHP scripts and the PWA.
- `/public_html/index.html`: The entry point for the mobile PWA.
- `/public_html/offend.php`: The main entry point for the admin dashboard.
- `/public_html/api{2,3,4}.php`: API endpoints for handling photo uploads.
- `/public_html/nav.php`: The shared navigation menu for the admin dashboard.
- `/lib/`: Contains shared PHP libraries and configuration.
- `/lib/config.php`: Stores all secrets and configuration variables.
- `/var/`: Used for storing runtime data, including uploaded photos and lock files.
- `/vendor/`: Contains Composer dependencies (e.g., TinyButStrong).
- `template.docx`: The Microsoft Word template used for generating breach notices.