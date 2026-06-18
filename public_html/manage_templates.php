<?php
if (session_id() == '') {
    session_start();
}

$templates_dir = __DIR__; // Templates are in the same directory

$allowed_templates = [
    'permit.docx' => 'Parking Permit',
    'template.docx' => 'Notice (Unknown Unit)',
    'template_unitnumber.docx' => 'Notice (Known Unit)',
];

function prune_backups($template_path, $max_backups = 4, &$message) {
    $backups = glob($template_path . '.*.gitignore');
    if (count($backups) > $max_backups) {
        // Sort by modification time, oldest first
        usort($backups, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $files_to_delete_count = count($backups) - $max_backups;
        for ($i = 0; $i < $files_to_delete_count; $i++) {
            if (unlink($backups[$i])) {
                $message .= "<p style='color:orange;'>Removed old backup: " . basename($backups[$i], '.gitignore') . "</p>";
            }
        }
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['new_template']) && isset($_POST['template_name'])) {
    $_SESSION['template_message'] = '';
    $template_key = $_POST['template_name'];

    // 1. Validate template name
    if (!array_key_exists($template_key, $allowed_templates)) {
        $_SESSION['template_message'] = "<p style='color:red;'>Invalid template specified.</p>";
    }
    // 2. Check for upload errors
    elseif ($_FILES['new_template']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['template_message'] = "<p style='color:red;'>File upload failed with error code: " . $_FILES['new_template']['error'] . "</p>";
    } else {
        $target_file = $templates_dir . '/' . $template_key;
        $uploaded_file_path = $_FILES['new_template']['tmp_name'];

        // Check if file is different
        if (file_exists($target_file) && md5_file($uploaded_file_path) === md5_file($target_file)) {
            $_SESSION['template_message'] = "<p style='color:blue;'><strong>Notice:</strong> The uploaded template is identical to the current one. No changes were made.</p>";
        } else {
            // 3. Backup old file if it exists
            if (file_exists($target_file)) {
                $backup_filename = $target_file . '.' . date('Y-m-d_H-i-s') . '.gitignore';
                if (rename($target_file, $backup_filename)) {
                    $_SESSION['template_message'] .= "<p style='color:blue;'>Backed up old version to: " . basename($backup_filename, '.gitignore') . "</p>";
                } else {
                    $_SESSION['template_message'] .= "<p style='color:red;'>Failed to create a backup of the old template.</p>";
                }
            }

            // 4. Move the new file into place
            if (move_uploaded_file($uploaded_file_path, $target_file)) {
                $_SESSION['template_message'] .= "<p style='color:green;'>Template '" . htmlspecialchars($allowed_templates[$template_key]) . "' updated successfully.</p>";
                prune_backups($target_file, 4, $_SESSION['template_message']);
            } else {
                $_SESSION['template_message'] .= "<p style='color:red;'>Failed to move the uploaded file.</p>";
            }
        }
    }
    header("Location: manage_templates.php");
    exit();
}

// Handle Restore
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_template'])) {
    $_SESSION['template_message'] = '';
    $template_key = $_POST['template_name'];
    $backup_to_restore = $_POST['backup_file'];

    if (!array_key_exists($template_key, $allowed_templates)) {
        $_SESSION['template_message'] = "<p style='color:red;'>Invalid template specified for restore.</p>";
    } else {
        $current_file_path = $templates_dir . '/' . $template_key;
        $backup_file_path = $templates_dir . '/' . $backup_to_restore;

        if (!file_exists($backup_file_path)) {
            $_SESSION['template_message'] = "<p style='color:red;'>Backup file not found.</p>";
        } else {
            // 1. Backup the current live file if it exists
            $did_backup = true; // Assume success if no file to backup
            if (file_exists($current_file_path)) {
                $new_backup_path = $current_file_path . '.' . date('Y-m-d_H-i-s') . '.gitignore';
                if (rename($current_file_path, $new_backup_path)) {
                    $_SESSION['template_message'] .= "<p style='color:blue;'>Backed up current version to " . basename($new_backup_path, '.gitignore') . ".</p>";
                } else {
                    $_SESSION['template_message'] .= "<p style='color:red;'>Could not back up the current live file. Restore aborted.</p>";
                    $did_backup = false;
                }
            }

            if ($did_backup) {
                // 2. Restore the selected backup by renaming it
                if (rename($backup_file_path, $current_file_path)) {
                    $_SESSION['template_message'] .= "<p style='color:green;'>Successfully restored '" . htmlspecialchars(basename($backup_to_restore, '.gitignore')) . "'.</p>";
                    prune_backups($current_file_path, 4, $_SESSION['template_message']);
                } else {
                    $_SESSION['template_message'] .= "<p style='color:red;'>Failed to restore backup. The system might be in an inconsistent state.</p>";
                }
            }
        }
    }
    header("Location: manage_templates.php");
    exit();
}

include 'nav.php';
$message = '';
if (isset($_SESSION['template_message'])) {
    $message = $_SESSION['template_message'];
    unset($_SESSION['template_message']);
}

?>

<div class="container" style="text-align:left">
    <h1>Manage Document Templates</h1>

    <?php echo $message; ?>

    <p>Here you can download the current templates or upload new versions. When you upload a new version, the old one will be automatically backed up.</p>

    <table border="1" style="width:50%;">
        <thead>
            <tr>
                <th>Template Name</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allowed_templates as $filename => $description): ?>
                <tr>
                    <td style="white-space: nowrap;"><strong><?php echo htmlspecialchars($filename); ?></strong></td>
                    <td style="white-space: nowrap;"><?php echo htmlspecialchars($description); ?></td>
                    <td style="width: 40%;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <a href="<?php echo htmlspecialchars($filename); ?>" download>
                                <button type="button" style="width:auto; height: auto; padding: 5px 10px;">Download Current</button>
                            </a>
                            <form method="POST" enctype="multipart/form-data" style="margin-left: 15px; flex-grow: 1;">
                                <input type="hidden" name="template_name" value="<?php echo htmlspecialchars($filename); ?>">
                                <input type="file" name="new_template" required>
                                <button type="submit" style="width:auto; height: auto; padding: 5px 10px;">Upload New</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php
                $backups = glob($templates_dir . '/' . $filename . '.*.gitignore');
                if (!empty($backups)) {
                    // Sort by modification time, newest first for display
                    usort($backups, function($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
            ?>
                <tr>
                    <td colspan="3" style="padding-left: 30px; background-color: #f9f9f9;">
                        <h4 style="margin-top: 10px; margin-bottom: 5px;">Available Backups:</h4>
                        <ul style="list-style-type: none; padding-left: 0;">
                            <?php foreach ($backups as $backup_path):
                                $backup_filename = basename($backup_path);
                            ?>
                                <li style="margin-bottom: 5px; display: flex; justify-content: flex-start; align-items: center;">
                                    <form method="POST" style="display:inline; margin:0; margin-right: 10px;">
                                        <input type="hidden" name="restore_template" value="1">
                                        <input type="hidden" name="template_name" value="<?php echo htmlspecialchars($filename); ?>">
                                        <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup_filename); ?>">
                                        <button type="submit" style="width:auto; height: auto; padding: 2px 8px; font-size: 0.9em;" onclick="return confirm('Are you sure you want to restore this version? The current live version will be backed up.');">Restore</button>
                                    </form>
                                    <span>
                                        <?php echo htmlspecialchars(basename($backup_filename, '.gitignore')); ?>
                                        (<?php echo date("d-M-Y H:i", filemtime($backup_path)); ?>)
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
            <?php
                }
            ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>