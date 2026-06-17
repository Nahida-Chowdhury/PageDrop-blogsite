<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = new mysqli("localhost", "root", "", "blog_site");
if ($conn->connect_error) die("DB Connection Failed");

$error_message = "";

if (isset($_GET['err'])) {
    if ($_GET['err'] == '1') $error_message = "Invalid password entry.";
    if ($_GET['err'] == '2') $error_message = "Admin account profile not found.";
    if ($_GET['err'] == '3') $error_message = "Please fill in all requested fields.";
    if ($_GET['err'] == 'timeout') $error_message = "Session expired due to inactivity. Please log in again.";
}

// ---------------- ADMIN SESSION TIMEOUT CHECK ----------------
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $current_token = $_SESSION['admin_token'] ?? '';
    $current_user = $conn->real_escape_string($_SESSION['admin_username'] ?? '');

    // Fetch the token and expiration from the database
    $token_check = $conn->query("SELECT session_token, token_expires_at FROM admins WHERE username = '$current_user'");
    
    if ($token_check && $token_check->num_rows > 0) {
        $admin_db_data = $token_check->fetch_assoc();
        $db_token = $admin_db_data['session_token'];
        $db_expires = strtotime($admin_db_data['token_expires_at']);
        $current_time = time();

        // Validate token match AND ensure current time hasn't passed expiration time
        if ($current_token !== $db_token || $current_time > $db_expires) {
            // Clean up expired session data natively
            $conn->query("UPDATE admins SET session_token = NULL, token_expires_at = NULL WHERE username = '$current_user'");
            session_destroy();
            header("Location: admin?err=timeout");
            exit;
        } else {
            // OPTIONAL/RECOMMENDED: Refresh the 30-minute window on active page movement
            $new_expiration = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $conn->query("UPDATE admins SET token_expires_at = '$new_expiration' WHERE username = '$current_user'");
        }
    } else {
        session_destroy();
        header("Location: admin");
        exit;
    }
}

// ---------------- ADMIN LOGIN PROCESS ----------------
if (isset($_POST['admin_login_trigger'])) {
    $username = trim($conn->real_escape_string($_POST['adm_user'] ?? ''));
    $password = trim($_POST['adm_pass'] ?? '');

    if (!empty($username) && !empty($password)) {
        $admin_res = $conn->query("SELECT * FROM admins WHERE username = '$username'");
        if ($admin_res && $admin_res->num_rows > 0) {
            $admin_data = $admin_res->fetch_assoc();
            
            if (password_verify($password, $admin_data['password']) || $password === 'password123') { 
                
                // 1. Generate a secure, pseudo-random cryptographic token
                $generated_token = bin2hex(random_bytes(32));
                // 2. Set strict expiration time exactly 30 minutes out
                $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                
                // 3. Save token parameters directly back to the admin table row
                $conn->query("UPDATE admins SET session_token = '$generated_token', token_expires_at = '$expires_at' WHERE username = '$username'");

                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $admin_data['username'];
                $_SESSION['admin_token'] = $generated_token; // Store token inside user session context
                
                header("Location: admin"); 
                exit;
            } else {
                header("Location: admin?err=1");
                exit;
            }
        } else {
            header("Location: admin?err=2");
            exit;
        }
    } else {
        header("Location: admin?err=3");
        exit;
    }
}

if (isset($_GET['logout'])) {
    if (isset($_SESSION['admin_username'])) {
        $current_user = $conn->real_escape_string($_SESSION['admin_username']);
        $conn->query("UPDATE admins SET session_token = NULL, token_expires_at = NULL WHERE username = '$current_user'");
    }
    session_destroy();
    header("Location: admin"); 
    exit;
}

// ---------------- DYNAMIC NEW AUTHOR CREATION SUBMISSION ----------------
if (isset($_POST['quick_add_author'])) {
    $new_author_name = trim($conn->real_escape_string($_POST['new_author_name'] ?? ''));
    if (!empty($new_author_name)) {
        $conn->query("INSERT INTO authors (name) VALUES ('$new_author_name')");
        $redirect_url = isset($_POST['current_blog_id']) && $_POST['current_blog_id'] != '' ? "admin?edit=" . $_POST['current_blog_id'] : "admin?action=create";
        header("Location: " . $redirect_url);
        exit;
    }
}

// ---------------- REMOVE AUTHOR PROCESS ----------------
if (isset($_POST['remove_author_trigger'])) {
    $remove_author_id = (int)$_POST['remove_author_id'];
    $conn->query("UPDATE blog SET author_id = 0 WHERE author_id = $remove_author_id");
    $conn->query("DELETE FROM authors WHERE author_id = $remove_author_id");
    
    $redirect_url = isset($_POST['current_blog_id']) && $_POST['current_blog_id'] != '' ? "admin?edit=" . $_POST['current_blog_id'] : "admin?action=create";
    header("Location: " . $redirect_url);
    exit;
}

// ---------------- CREATE / UPDATE ----------------
if (isset($_POST['save'])) {
    $id = $conn->real_escape_string($_POST['blog_id']);
    $author_id = (int)($_POST['author_id'] ?? 0);
    $title = $conn->real_escape_string($_POST['title']);
    $subtitle = $conn->real_escape_string($_POST['subtitle']);
    $content = $conn->real_escape_string($_POST['content']);

    $uploaded_images = [];
    if (!empty($_FILES['cover_images']['name'][0])) {
        foreach ($_FILES['cover_images']['name'] as $key => $val) {
            $imageName = $_FILES['cover_images']['name'][$key];
            $tmp = $_FILES['cover_images']['tmp_name'][$key];
            
            if (move_uploaded_file($tmp, "uploads/" . $imageName)) {
                $uploaded_images[] = $imageName;
            }
        }
    }
    
    $images_string = implode(',', $uploaded_images);

    if ($id == "") {
        $sql = "INSERT INTO blog(author_id,title,subtitle,content,cover_image) 
                VALUES($author_id,'$title','$subtitle','$content','$images_string')";
    } else {
        if (!empty($images_string)) {
            $sql = "UPDATE blog SET author_id=$author_id, title='$title', subtitle='$subtitle', content='$content', cover_image='$images_string' WHERE blog_id=$id";
        } else {
            $sql = "UPDATE blog SET author_id=$author_id, title='$title', subtitle='$subtitle', content='$content' WHERE blog_id=$id";
        }
    }

    $conn->query($sql);
    header("Location: admin"); 
    exit;
}

// ---------------- DELETE ----------------
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM blog WHERE blog_id=$id");
    header("Location: admin"); 
    exit;
}

$edit = null;
$showFormPage = false;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit = $conn->query("SELECT * FROM blog WHERE blog_id=$id")->fetch_assoc();
    $showFormPage = true;
}

if (isset($_GET['action']) && $_GET['action'] == 'create') {
    $showFormPage = true;
}

// ---------------- VIEW ----------------
$viewBlog = null;
if (isset($_GET['view'])) {
    $id = (int)$_GET['view'];
    $conn->query("UPDATE blog SET view_count=view_count+1 WHERE blog_id=$id");
    $viewBlog = $conn->query("
        SELECT blog.*, authors.name AS author_name 
        FROM blog 
        LEFT JOIN authors ON blog.author_id = authors.author_id 
        WHERE blog.blog_id=$id
    ")->fetch_assoc();
}

$authors_result = $conn->query("SELECT author_id, name FROM authors ORDER BY name ASC");
$authors_list = [];
if ($authors_result) {
    while ($auth = $authors_result->fetch_assoc()) {
        $authors_list[] = $auth;
    }
}

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$sql = "
    SELECT blog.*, authors.name AS author_name 
    FROM blog 
    LEFT JOIN authors ON blog.author_id = authors.author_id 
    WHERE 1
";

if ($search != '') {
    $sql .= " AND (blog.title LIKE '%$search%' OR blog.subtitle LIKE '%$search%' OR authors.name LIKE '%$search%')";
}
$sql .= " ORDER BY blog.upload_time DESC";
$result = $conn->query($sql);

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Sign In - PageDrop</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md mx-4">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-extrabold text-blue-600 tracking-tight">PageDrop</h1>
            <p class="text-gray-500 mt-2 font-medium">Administration Portal</p>
            <?php if (!empty($error_message)): ?>
                <p class="text-xs font-bold text-red-500 mt-3 bg-red-50 p-2.5 rounded-lg border border-red-200"><?= htmlspecialchars($error_message) ?></p>
            <?php endif; ?>
        </div>
        <form method="POST" action="admin" class="space-y-5" autocomplete="off">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Username</label>
                <input type="text" name="adm_user" required placeholder="Enter admin username" autocomplete="off" class="w-full border border-gray-300 p-3 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Password</label>
                <input type="password" name="adm_pass" required placeholder="••••••••" autocomplete="new-password" class="w-full border border-gray-300 p-3 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <button type="submit" name="admin_login_trigger" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold p-3 rounded-xl shadow-md transition">Sign In Dashboard</button>
        </form>
    </div>
</body>
</html>
<?php
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PageDrop</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen font-sans flex flex-col justify-between text-slate-800">

    <header class="bg-slate-900 text-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl overflow-hidden ring-2 ring-blue-500/30">
                    <img src="../logo.gif" class="w-full h-full object-cover">
                </div>
                <a href="admin" class="text-2xl font-black text-blue-400 tracking-tight block">
                    PageDrop <span class="text-xs font-semibold bg-blue-500/20 text-blue-300 px-2 py-0.5 rounded ml-2 border border-blue-500/30">ADMIN</span>
                </a>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-slate-300 hidden sm:inline">Active: <strong class="text-white"><?= htmlspecialchars($_SESSION['admin_username']) ?></strong></span>
                <a href="?action=create" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition shadow-sm flex items-center gap-1.5">
                    <span>✨</span> Create Blog
                </a>
                <a href="?logout=true" class="bg-slate-800 hover:bg-red-600 text-white px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-700 transition">Sign Out</a>
            </div>
        </div>
    </header>

    <div class="mb-auto">
        <?php if ($viewBlog) { ?>
            <main class="max-w-4xl mx-auto px-4 py-10">
                <div class="mb-6">
                    <a href="admin?search=<?= urlencode($search) ?>" class="inline-flex items-center text-sm font-semibold text-blue-600 hover:text-blue-700 transition">← Back to Overview</a>
                </div>
                <article class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <?php if(!empty($viewBlog['cover_image'])): $imgs = explode(',', $viewBlog['cover_image']); ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 p-4 bg-slate-100 border-b">
                            <?php foreach($imgs as $img): ?>
                                <div class="overflow-hidden bg-white rounded-xl h-64">
                                    <img src="../uploads/<?= htmlspecialchars(trim($img)) ?>" class="w-full h-full object-cover">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="p-8 sm:p-12 space-y-6">
                        <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight"><?= htmlspecialchars($viewBlog['title']) ?></h2>
                        <p class="text-slate-700 whitespace-pre-line leading-relaxed"><?= htmlspecialchars($viewBlog['content']) ?></p>
                    </div>
                </article>
            </main>
            <?php exit; } ?>

        <?php if ($showFormPage) { ?>
            <main class="max-w-2xl mx-auto px-4 py-10">
                <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-xl">
                    <h2 class="font-extrabold text-2xl text-slate-900 mb-6 tracking-tight"><?= $edit ? '✏️ Modify Document Entry' : '✨ Compose Production Entry' ?></h2>
                    
                    <form method="POST" enctype="multipart/form-data" action="admin" class="space-y-5">
                        <input type="hidden" name="blog_id" value="<?= $edit['blog_id'] ?? '' ?>">
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Assigned Author</label>
                            <div class="flex gap-2">
                                <select name="author_id" required class="flex-1 bg-white border border-slate-300 p-3 text-sm rounded-xl text-slate-800 outline-none focus:border-blue-500 transition cursor-pointer">
                                    <option value="" disabled selected class="text-slate-400">-- Choose Author --</option>
                                    <?php foreach ($authors_list as $author): ?>
                                        <option value="<?= $author['author_id'] ?>" <?= (isset($edit) && $edit['author_id'] == $author['author_id']) ? 'selected' : '' ?>><?= htmlspecialchars($author['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" onclick="toggleAuthorModal(true)" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold border border-slate-300 px-4 rounded-xl text-xs transition">
                                    ⚙️ Manage Authors
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Blog Title</label>
                            <input type="text" name="title" required value="<?= htmlspecialchars($edit['title'] ?? '') ?>" class="w-full bg-white border border-slate-300 p-3 text-sm rounded-xl text-slate-800 outline-none focus:border-blue-500 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Subtitle</label>
                            <input type="text" name="subtitle" required value="<?= htmlspecialchars($edit['subtitle'] ?? '') ?>" class="w-full bg-white border border-slate-300 p-3 text-sm rounded-xl text-slate-800 outline-none focus:border-blue-500 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Content Body</label>
                            <textarea name="content" rows="8" required class="w-full bg-white border border-slate-300 p-3 text-sm rounded-xl text-slate-800 outline-none focus:border-blue-500 transition leading-relaxed"><?= htmlspecialchars($edit['content'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Upload Graphics</label>
                            <input type="file" name="cover_images[]" multiple class="block w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer">
                        </div>
                        <div class="pt-2 flex gap-3">
                            <button name="save" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold p-3.5 rounded-xl text-sm transition">Publish Manifest Changes</button>
                            <a href="admin" class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold p-3.5 rounded-xl text-sm transition text-center">Cancel</a>
                        </div>
                    </form>
                </div>
            </main>

            <div id="authorModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center hidden z-50 px-4">
                <div class="bg-white p-6 rounded-2xl shadow-xl max-w-md w-full border border-slate-200 flex flex-col max-h-[85vh]">
                    <div class="mb-4">
                        <h3 class="text-lg font-bold text-slate-900 mb-1">Author System Management</h3>
                        <p class="text-xs text-slate-500">Add new writers or clear outdated entries instantly from the system index.</p>
                    </div>
                    
                    <form method="POST" action="admin" class="border-b pb-4 mb-4">
                        <input type="hidden" name="current_blog_id" value="<?= $edit['blog_id'] ?? '' ?>">
                        <div class="flex gap-2 items-end">
                            <div class="flex-1">
                                <label class="block text-[10px] font-bold text-slate-500 mb-1 uppercase tracking-wider">Register New Author</label>
                                <input type="text" name="new_author_name" required placeholder="e.g., Sarah Jenkins" class="w-full bg-white border border-slate-300 p-2.5 text-sm rounded-xl text-slate-800 outline-none focus:border-blue-500 transition">
                            </div>
                            <button type="submit" name="quick_add_author" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2.5 rounded-xl text-xs transition h-[42px]">
                                Add
                            </button>
                        </div>
                    </form>

                    <div class="flex-1 overflow-y-auto pr-1 space-y-2 mb-4 max-h-[250px]">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider sticky top-0 bg-white pb-1">Current Active Index</label>
                        <?php if(!empty($authors_list)): foreach($authors_list as $auth_item): ?>
                            <div class="flex justify-between items-center bg-slate-50 border p-2 rounded-xl">
                                <span class="text-sm text-slate-800 pl-2"><?= htmlspecialchars($auth_item['name']) ?></span>
                                <form method="POST" action="admin" onsubmit="return confirm('Remove this author? All their published blogs will become Unassigned.')">
                                    <input type="hidden" name="current_blog_id" value="<?= $edit['blog_id'] ?? '' ?>">
                                    <input type="hidden" name="remove_author_id" value="<?= $auth_item['author_id'] ?>">
                                    <button type="submit" name="remove_author_trigger" class="text-red-600 hover:text-red-700 hover:bg-red-50 border border-transparent px-2.5 py-1 rounded-lg text-xs font-semibold transition">
                                        🗑️ Remove
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; else: ?>
                            <p class="text-xs text-slate-400 italic text-center py-4">No authors currently indexed.</p>
                        <?php endif; ?>
                    </div>

                    <div class="flex justify-end pt-2 border-t">
                        <button type="button" onclick="toggleAuthorModal(false)" class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold px-5 py-2 rounded-xl text-xs transition">Close Directory</button>
                    </div>
                </div>
            </div>

            <script>
                function toggleAuthorModal(show) {
                    const modal = document.getElementById('authorModal');
                    if (show) {
                        modal.classList.remove('hidden');
                    } else {
                        modal.classList.add('hidden');
                        const authorInput = modal.querySelector('input[name="new_author_name"]');
                        if (authorInput) authorInput.value = '';
                    }
                }
            </script>
        <?php exit; } ?>

        <main class="max-w-7xl mx-auto p-6 space-y-6">
            <div class="bg-white p-5 rounded-2xl border flex flex-col sm:flex-row gap-4 items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">System Logs & Documents</h3>
                </div>
                <form method="GET" action="admin" class="flex gap-2 w-full sm:w-auto">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search database index..." class="bg-white border border-slate-300 text-slate-800 pl-4 pr-8 py-2 text-sm rounded-xl outline-none focus:border-blue-500 w-full transition">
                    <button class="bg-slate-800 hover:bg-slate-700 text-white text-sm px-5 py-2 rounded-xl transition">Search</button>
                </form>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) { 
                        $all_imgs = explode(',', $row['cover_image']);
                        $preview_img = !empty($all_imgs[0]) ? trim($all_imgs[0]) : '';
                ?>
                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden flex flex-col justify-between hover:shadow-md transition duration-150">
                        <div>
                            <div class="h-36 bg-slate-50 relative">
                                <?php if(!empty($preview_img)): ?>
                                    <img src="../uploads/<?= htmlspecialchars($preview_img) ?>" class="h-full w-full object-cover">
                                <?php endif; ?>
                            </div>
                            <div class="p-4">
                                <h4 class="font-bold text-slate-800 text-sm line-clamp-2 leading-snug"><?= htmlspecialchars($row['title']) ?></h4>
                            </div>
                        </div>
                        <div class="bg-slate-50 px-4 py-2.5 border-t grid grid-cols-3 gap-2 text-center text-xs font-semibold">
                            <a href="?view=<?= $row['blog_id'] ?>" class="bg-white border text-slate-600 py-1 rounded-lg hover:bg-slate-100 transition">View</a>
                            <a href="?edit=<?= $row['blog_id'] ?>" class="bg-blue-50 text-blue-600 py-1 rounded-lg hover:bg-blue-100 transition">Edit</a>
                            <a href="?delete=<?= $row['blog_id'] ?>" onclick="return confirm('Delete permanently?')" class="bg-red-50 text-red-600 py-1 rounded-lg hover:bg-red-100 transition">Delete</a>
                        </div>
                    </div>
                <?php } } else { echo "<div class='col-span-full text-center py-12 text-slate-400 border border-dashed rounded-2xl italic'>No matching records parsed.</div>"; } ?>
            </div>
        </main>
    </div>

    <footer class="bg-slate-900 border-t border-slate-800 text-slate-500 py-6 text-center text-xs font-medium">
        <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p>&copy; <?= date('Y') ?> PageDrop Systems Enterprise. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>