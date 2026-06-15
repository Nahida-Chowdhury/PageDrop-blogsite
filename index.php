<?php
$conn = new mysqli("localhost", "root", "", "blog_site");
if ($conn->connect_error) die("DB Connection Failed");

// ---------------- CREATE / UPDATE ----------------
if (isset($_POST['save'])) {

    $id = $_POST['blog_id'];
    $author = $_POST['author_name'];
    $title = $_POST['title'];
    $subtitle = $_POST['subtitle'];
    $content = $_POST['content'];

    $imageName = $_FILES['cover_image']['name'] ?? '';
    $tmp = $_FILES['cover_image']['tmp_name'] ?? '';

    if ($imageName) {
        move_uploaded_file($tmp, "uploads/" . $imageName);
    }

    if ($id == "") {
        // CREATE
        $sql = "INSERT INTO blog(author_name,title,subtitle,content,cover_image)
                VALUES('$author','$title','$subtitle','$content','$imageName')";
    } else {
        // UPDATE
        if ($imageName) {
            $sql = "UPDATE blog SET 
                author_name='$author',
                title='$title',
                subtitle='$subtitle',
                content='$content',
                cover_image='$imageName'
                WHERE blog_id=$id";
        } else {
            $sql = "UPDATE blog SET 
                author_name='$author',
                title='$title',
                subtitle='$subtitle',
                content='$content'
                WHERE blog_id=$id";
        }
    }

    $conn->query($sql);
    header("Location: index.php");
}

// ---------------- DELETE ----------------
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM blog WHERE blog_id=$id");
    header("Location: index.php");
}

// ---------------- EDIT LOAD ----------------
$edit = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $edit = $conn->query("SELECT * FROM blog WHERE blog_id=$id")->fetch_assoc();
}

// ---------------- VIEW BLOG ----------------
$viewBlog = null;
if (isset($_GET['view'])) {
    $id = $_GET['view'];
    $conn->query("UPDATE blog SET view_count=view_count+1 WHERE blog_id=$id");
    $viewBlog = $conn->query("SELECT * FROM blog WHERE blog_id=$id")->fetch_assoc();
}

// ---------------- FILTER + SEARCH ----------------
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM blog WHERE 1";

if ($search != '') {
    $sql .= " AND (title LIKE '%$search%' OR subtitle LIKE '%$search%' OR author_name LIKE '%$search%')";
}

if ($filter == "recent") {
    $sql .= " ORDER BY upload_time DESC";
} elseif ($filter == "popular") {
    $sql .= " ORDER BY view_count DESC";
} else {
    $sql .= " ORDER BY upload_time DESC";
}

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Blog Site</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">

    <!-- NAVBAR -->
    <header class="bg-white shadow p-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-blue-600">PageDrop</h1>

        <form method="GET" class="flex gap-3 items-center">

            <!-- FILTER -->
            <select name="filter" onchange="this.form.submit()"
                class="border p-2 rounded">
                <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>All</option>
                <option value="recent" <?= $filter == 'recent' ? 'selected' : '' ?>>Recent</option>
                <option value="popular" <?= $filter == 'popular' ? 'selected' : '' ?>>Popular</option>
            </select>

            <!-- SEARCH -->
            <input type="text" name="search" value="<?= $search ?>"
                placeholder="Search blogs..."
                class="border p-2 rounded">

            <button class="bg-blue-500 text-white px-3 py-2 rounded">
                Search
            </button>
        </form>
    </header>

    <!-- MAIN -->
    <div class="max-w-6xl mx-auto grid grid-cols-4 gap-6 p-6">

        <!-- FORM -->
        <div class="bg-white p-4 rounded shadow">

            <h2 class="font-bold mb-3">Create / Edit Blog</h2>

            <form method="POST" enctype="multipart/form-data">

                <input type="hidden" name="blog_id" value="<?= $edit['blog_id'] ?? '' ?>">

                <input name="author_name" placeholder="Author"
                    value="<?= $edit['author_name'] ?? '' ?>"
                    class="w-full border p-2 mb-2 rounded">

                <input name="title" placeholder="Title"
                    value="<?= $edit['title'] ?? '' ?>"
                    class="w-full border p-2 mb-2 rounded">

                <input name="subtitle" placeholder="Subtitle"
                    value="<?= $edit['subtitle'] ?? '' ?>"
                    class="w-full border p-2 mb-2 rounded">

                <textarea name="content" placeholder="Content"
                    class="w-full border p-2 mb-2 rounded"><?= $edit['content'] ?? '' ?></textarea>

                <input type="file" name="cover_image" class="mb-2">

                <button name="save" class="w-full bg-blue-600 text-white p-2 rounded">
                    Save Blog
                </button>

            </form>
        </div>

        <!-- BLOG LIST -->
        <div class="col-span-3 grid grid-cols-3 gap-4">

            <?php while ($row = $result->fetch_assoc()) { ?>

                <div class="bg-white rounded shadow overflow-hidden">

                    <img src="uploads/<?= $row['cover_image'] ?>"
                        class="h-32 w-full object-cover">

                    <div class="p-3">

                        <h2 class="font-bold"><?= $row['title'] ?></h2>
                        <p class="text-sm text-gray-500"><?= $row['subtitle'] ?></p>

                        <p class="text-xs text-gray-400">By <?= $row['author_name'] ?></p>

                        <p class="text-xs">Views: <?= $row['view_count'] ?></p>

                        <div class="flex gap-2 mt-2 text-sm">

                            <a href="?view=<?= $row['blog_id'] ?>" class="text-blue-500">View</a>
                            <a href="?edit=<?= $row['blog_id'] ?>" class="text-green-500">Edit</a>
                            <a href="?delete=<?= $row['blog_id'] ?>" class="text-red-500">Delete</a>

                        </div>

                    </div>
                </div>

            <?php } ?>

        </div>
    </div>

    <!-- VIEW MODAL -->
    <?php if ($viewBlog) { ?>
        <div class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center">
            <div class="bg-white p-6 max-w-xl w-full">

                <h2 class="text-xl font-bold"><?= $viewBlog['title'] ?></h2>
                <p class="text-gray-500"><?= $viewBlog['subtitle'] ?></p>

                <p class="mt-3"><?= $viewBlog['content'] ?></p>

                <p class="text-sm text-gray-500 mt-2">
                    By <?= $viewBlog['author_name'] ?>
                </p>

                <a href="index.php" class="text-red-500 mt-4 inline-block">Close</a>

            </div>
        </div>
    <?php } ?>

</body>

</html>