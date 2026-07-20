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

$message = '';
if (isset($_SESSION['template_message'])) {
    $message = $_SESSION['template_message'];
    unset($_SESSION['template_message']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Parking - Manage Templates</title>
    <?php include 'tailwind-theme.php'; ?>
</head>
<body>

<div class="container">
<?php include 'nav.php'; ?>
    <h1>Manage Document Templates</h1>

    <?php echo $message; ?>

    <p>Here you can download the current templates or upload new versions. When you upload a new version, the old one will be automatically backed up.</p>

    <table class="md:w-2/3">
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
                    <td class="whitespace-nowrap"><strong><?php echo htmlspecialchars($filename); ?></strong></td>
                    <td class="whitespace-nowrap"><?php echo htmlspecialchars($description); ?></td>
                    <td class="w-2/5">
                        <div class="flex items-center justify-between gap-3">
                            <a href="<?php echo htmlspecialchars($filename); ?>" download>
                                <button type="button">Download Current</button>
                            </a>
                            <form method="POST" enctype="multipart/form-data" class="flex-grow flex items-center gap-2">
                                <input type="hidden" name="template_name" value="<?php echo htmlspecialchars($filename); ?>">
                                <input type="file" name="new_template" required>
                                <button type="submit">Upload New</button>
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
                    <td colspan="3" class="pl-8 bg-slate-50">
                        <h4 class="mt-2 mb-1">Available Backups:</h4>
                        <ul class="list-none pl-0">
                            <?php foreach ($backups as $backup_path):
                                $backup_filename = basename($backup_path);
                            ?>
                                <li class="mb-1 flex items-center gap-2">
                                    <form method="POST" class="inline m-0">
                                        <input type="hidden" name="restore_template" value="1">
                                        <input type="hidden" name="template_name" value="<?php echo htmlspecialchars($filename); ?>">
                                        <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup_filename); ?>">
                                        <button type="submit" class="text-xs px-2 py-1" onclick="return confirm('Are you sure you want to restore this version? The current live version will be backed up.');">Restore</button>
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