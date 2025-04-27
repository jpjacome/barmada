<?php
// Symlink creator for Laravel in subdirectory
// This script helps create symbolic links to ensure assets are found correctly

// Security check - don't run this in production without protection
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    // Uncomment the line below to allow this to run on your server
    // die("For security reasons, this script can only be run locally. Edit the script to enable remote execution.");
}

// Function to create symlink with proper error handling
function createSymlink($target, $link) {
    // Make sure target exists
    if (!file_exists($target)) {
        return [
            'success' => false,
            'message' => "Target does not exist: $target"
        ];
    }
    
    // Make sure link doesn't already exist
    if (file_exists($link)) {
        // If it's already a symlink pointing to our target, that's fine
        if (is_link($link) && readlink($link) === $target) {
            return [
                'success' => true,
                'message' => "Link already exists and points to the correct target: $link → $target"
            ];
        }
        
        // Otherwise, we need to remove it first
        if (is_dir($link)) {
            // Use recursive directory deletion - BE CAREFUL!
            if (!rmdir($link)) {
                return [
                    'success' => false,
                    'message' => "Failed to remove existing directory: $link"
                ];
            }
        } else {
            if (!unlink($link)) {
                return [
                    'success' => false,
                    'message' => "Failed to remove existing file: $link"
                ];
            }
        }
    }
    
    // Create the symlink
    if (symlink($target, $link)) {
        return [
            'success' => true,
            'message' => "Successfully created symlink: $link → $target"
        ];
    } else {
        return [
            'success' => false,
            'message' => "Failed to create symlink: $link → $target"
        ];
    }
}

// Determine paths
$app_path = dirname($_SERVER['SCRIPT_FILENAME']);
$doc_root = $_SERVER['DOCUMENT_ROOT'];

// Paths for assets that need to be linked
$links = [
    // Link the js directory to document root
    [
        'target' => "$app_path/js",
        'link' => "$doc_root/js"
    ],
    // Link the vendor directory to document root
    [
        'target' => "$app_path/vendor",
        'link' => "$doc_root/vendor"
    ],
    // Link the css directory to document root
    [
        'target' => "$app_path/css",
        'link' => "$doc_root/css"
    ],
];

// Perform the operation if requested
$results = [];
if (isset($_GET['create']) && $_GET['create'] === 'true') {
    foreach ($links as $link) {
        $results[] = [
            'target' => $link['target'],
            'link' => $link['link'],
            'result' => createSymlink($link['target'], $link['link'])
        ];
    }
}

// Output HTML
?>
<!DOCTYPE html>
<html>
<head>
    <title>Laravel Symlink Creator</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1 { color: #333; }
        .info { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .success { color: green; }
        .error { color: red; }
        .warning { background: #fff3cd; padding: 10px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        .button { display: inline-block; padding: 10px 15px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Laravel Symlink Creator</h1>
    
    <div class="warning">
        <strong>Warning:</strong> This tool creates symbolic links to help with asset discovery.
        Use with caution as it modifies your file system. Backup your files first if unsure.
    </div>
    
    <div class="info">
        <p>This tool helps your Laravel application work correctly in a subdirectory by creating
        symbolic links from your application's asset directories to the document root.</p>
        
        <p>Current paths:</p>
        <ul>
            <li><strong>Document Root:</strong> <?php echo $doc_root; ?></li>
            <li><strong>Application Path:</strong> <?php echo $app_path; ?></li>
        </ul>
    </div>
    
    <h2>Proposed Symbolic Links</h2>
    <table>
        <tr>
            <th>Target (Real Directory)</th>
            <th>Link (Symbolic Link)</th>
        </tr>
        <?php foreach ($links as $link): ?>
        <tr>
            <td><?php echo $link['target']; ?></td>
            <td><?php echo $link['link']; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <?php if (!empty($results)): ?>
    <h2>Results</h2>
    <table>
        <tr>
            <th>Target</th>
            <th>Link</th>
            <th>Status</th>
            <th>Message</th>
        </tr>
        <?php foreach ($results as $result): ?>
        <tr>
            <td><?php echo $result['target']; ?></td>
            <td><?php echo $result['link']; ?></td>
            <td class="<?php echo $result['result']['result']['success'] ? 'success' : 'error'; ?>">
                <?php echo $result['result']['result']['success'] ? 'Success' : 'Error'; ?>
            </td>
            <td><?php echo $result['result']['result']['message']; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
    
    <p>
        <a href="?create=true" class="button" onclick="return confirm('This will create symbolic links. Continue?')">Create Symbolic Links</a>
    </p>
    
    <div class="info">
        <p><strong>Alternative solution:</strong> Instead of creating symbolic links, you could also:</p>
        <ol>
            <li>Update your .env to set ASSET_URL correctly</li>
            <li>Make sure all asset references use the asset() helper</li>
            <li>Clear Laravel's cache with artisan commands</li>
        </ol>
    </div>
</body>
</html> 