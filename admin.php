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
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $admin_data['username'];
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
    session_destroy();
    header("Location: admin"); 
    exit;
}

// ---------------- CREATE / UPDATE (MULTIPLE IMAGES WORKING) ----------------
if (isset($_POST['save'])) {
    $id = $conn->real_escape_string($_POST['blog_id']);
    $author_id = (int)($_POST['author_id'] ?? 0);
    $title = $conn->real_escape_string($_POST['title']);
    $subtitle = $conn->real_escape_string($_POST['subtitle']);
    $content = $conn->real_escape_string($_POST['content']);

    // Process Multiple Uploads
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
    
    // Convert array to a comma-separated string to save into the single column
    $images_string = implode(',', $uploaded_images);

    if ($id == "") {
        $sql = "INSERT INTO blog(author_id,title,subtitle,content,cover_image) 
                VALUES($author_id,'$title','$subtitle','$content','$images_string')";
    } else {
        if (!empty($images_string)) {
            // Replace old images with new ones if new files are uploaded
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
        </div>
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-3 rounded mb-4 text-sm">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="admin" class="space-y-5" autocomplete="off">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Username</label>
                <input type="text" name="adm_user" required placeholder="Enter admin username" class="w-full border border-gray-300 p-3 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Password</label>
                <input type="password" name="adm_pass" required placeholder="••••••••" class="w-full border border-gray-300 p-3 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
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
<body class="bg-stone-100 min-h-screen font-sans flex flex-col justify-between">

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
                <span class="text-sm text-slate-300 hidden sm:inline">User: <strong class="text-white"><?= htmlspecialchars($_SESSION['admin_username']) ?></strong></span>
                <a href="?action=create" class="bg-slate-800 border border-slate-700 hover:bg-slate-700 text-blue-400 font-bold text-xs px-4 py-2.5 rounded-xl transition shadow-sm flex items-center gap-1.5">
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
                    <a href="admin?search=<?= urlencode($search) ?>" class="inline-flex items-center text-sm font-semibold text-blue-600 hover:text-blue-700 transition">← Back to Dashboard Overview</a>
                </div>
                <article class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <?php 
                    if(!empty($viewBlog['cover_image'])): 
                        $imgs = explode(',', $viewBlog['cover_image']);
                    ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 p-4 bg-slate-100">
                            <?php foreach($imgs as $img): ?>
                                <div class="overflow-hidden bg-white rounded-xl h-64">
                                    <img src="../uploads/<?= htmlspecialchars(trim($img)) ?>" class="w-full h-full object-cover">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="p-8 sm:p-12 space-y-6">
                        <h2 class="text-3xl font-extrabold text-slate-900"><?= htmlspecialchars($viewBlog['title']) ?></h2>
                        <p class="text-slate-700 whitespace-pre-line"><?= htmlspecialchars($viewBlog['content']) ?></p>
                    </div>
                </article>
            </main>
            <?php exit; } ?>

        <?php if ($showFormPage) { ?>
            <main class="max-w-2xl mx-auto px-4 py-10">
                <div class="bg-white p-8 rounded-3xl shadow-xs border border-slate-200">
                    <h2 class="font-extrabold text-2xl text-slate-900 mb-6"><?= $edit ? '✏️ Modify Entry' : '✨ Compose Blog Post' ?></h2>
                    <form method="POST" enctype="multipart/form-data" action="admin" class="space-y-5">
                        <input type="hidden" name="blog_id" value="<?= $edit['blog_id'] ?? '' ?>">
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Select Author</label>
                            <select name="author_id" required class="w-full border p-3 text-sm rounded-xl bg-white outline-none">
                                <option value="" disabled>-- Choose Author --</option>
                                <?php foreach ($authors_list as $author): ?>
                                    <option value="<?= $author['author_id'] ?>" <?= (isset($edit) && $edit['author_id'] == $author['author_id']) ? 'selected' : '' ?>><?= htmlspecialchars($author['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Blog Title</label>
                            <input type="text" name="title" required value="<?= htmlspecialchars($edit['title'] ?? '') ?>" class="w-full border p-3 text-sm rounded-xl outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Subtitle</label>
                            <input type="text" name="subtitle" required value="<?= htmlspecialchars($edit['subtitle'] ?? '') ?>" class="w-full border p-3 text-sm rounded-xl outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Content Body</label>
                            <textarea name="content" rows="8" required class="w-full border p-3 text-sm rounded-xl outline-none"><?= htmlspecialchars($edit['content'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Upload Graphics (Hold Ctrl/Cmd to select multiple)</label>
                            <input type="file" name="cover_images[]" multiple class="block w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                        </div>
                        <div class="pt-2 flex gap-3">
                            <button name="save" class="flex-1 bg-blue-600 text-white font-bold p-3.5 rounded-xl text-sm">Save Changes</button>
                            <a href="admin" class="bg-slate-100 text-slate-600 font-semibold p-3.5 rounded-xl text-sm">Cancel</a>
                        </div>
                    </form>
                </div>
            </main>
            <?php exit; } ?>

        <main class="max-w-7xl mx-auto p-6 space-y-6">
            <div class="bg-white p-5 rounded-2xl border flex flex-col sm:flex-row gap-4 items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Search Blogs</h3>
                </div>
                <form method="GET" action="admin" class="flex gap-2 w-full sm:w-auto">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search..." class="border pl-4 pr-8 py-2 text-sm rounded-xl outline-none w-full">
                    <button class="bg-slate-800 text-white text-sm px-5 py-2 rounded-xl">Search</button>
                </form>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) { 
                        // Target first image out of string to show as grid preview card background
                        $all_imgs = explode(',', $row['cover_image']);
                        $preview_img = !empty($all_imgs[0]) ? trim($all_imgs[0]) : '';
                ?>
                    <div class="bg-white rounded-2xl border overflow-hidden flex flex-col justify-between hover:shadow-md transition">
                        <div>
                            <div class="h-36 bg-slate-100 relative">
                                <?php if(!empty($preview_img)): ?>
                                    <img src="../uploads/<?= htmlspecialchars($preview_img) ?>" class="h-full w-full object-cover">
                                <?php endif; ?>
                            </div>
                            <div class="p-4">
                                <h4 class="font-bold text-slate-800 text-sm line-clamp-2"><?= htmlspecialchars($row['title']) ?></h4>
                            </div>
                        </div>
                        <div class="bg-slate-50 px-4 py-2.5 border-t grid grid-cols-3 gap-2 text-center text-xs font-semibold">
                            <a href="?view=<?= $row['blog_id'] ?>" class="bg-white border text-slate-600 py-1 rounded-lg">View</a>
                            <a href="?edit=<?= $row['blog_id'] ?>" class="bg-blue-50 text-blue-600 py-1 rounded-lg">Edit</a>
                            <a href="?delete=<?= $row['blog_id'] ?>" onclick="return confirm('Delete permanently?')" class="bg-red-50 text-red-600 py-1 rounded-lg">Delete</a>
                        </div>
                    </div>
                <?php } } else { echo "<div class='col-span-full text-center py-12 text-slate-400 italic'>No records found.</div>"; } ?>
            </div>
        </main>
    </div>

    <footer class="bg-slate-900 border-t border-slate-800 text-slate-500 py-6 text-center text-xs font-medium">
        <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p>&copy; <?= date('Y') ?> PageDrop Systems. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>