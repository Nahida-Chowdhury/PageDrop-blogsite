<?php
$conn = new mysqli("localhost", "root", "", "blog_site");
if ($conn->connect_error) die("DB Connection Failed");

// ---------------- VIEW BLOG ----------------
$viewBlog = null;

if (isset($_GET['view'])) {

    $id = (int)$_GET['view'];

    // increase view count
    $conn->query("UPDATE blog SET view_count = view_count + 1 WHERE blog_id = $id");

    // get full blog
    $viewBlog = $conn->query("SELECT * FROM blog WHERE blog_id = $id")->fetch_assoc();
}

// ---------------- FILTER + SEARCH ----------------
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM blog WHERE 1";

if ($search != '') {
    $sql .= " AND (
        title LIKE '%$search%' OR
        subtitle LIKE '%$search%' OR
        author_name LIKE '%$search%'
    )";
}

if ($filter == "popular") {
    $sql .= " ORDER BY view_count DESC";
} else {
    $sql .= " ORDER BY upload_time DESC";
}

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PageDrop Blog</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-100 min-h-screen">

    <!-- NAVBAR -->
    <header class="bg-white shadow-sm border-b sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">

            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl overflow-hidden">
                    <img src="logo.gif"
                        alt="Logo"
                        class="w-full h-full object-cover">
                </div>
                <h1 class="text-2xl font-bold text-slate-800">PageDrop</h1>
            </div>

            <form method="GET" class="flex items-center gap-3">

                <select name="filter"
                    onchange="this.form.submit()"
                    class="border px-3 py-2 rounded-lg text-sm">

                    <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>All Blogs</option>
                    <option value="recent" <?= $filter == 'recent' ? 'selected' : '' ?>>Recent</option>
                    <option value="popular" <?= $filter == 'popular' ? 'selected' : '' ?>>Popular</option>

                </select>

                <input type="text"
                    name="search"
                    value="<?= $search ?>"
                    placeholder="Search blogs..."
                    class="border px-3 py-2 rounded-lg w-64">

                <button class="bg-blue-600 text-white px-4 py-2 rounded-lg">
                    Search
                </button>

            </form>

        </div>
    </header>

    <?php if ($viewBlog) { ?>

        <!-- FULL BLOG VIEW -->
        <div class="max-w-4xl mx-auto my-8 bg-white rounded-2xl shadow-lg overflow-hidden">

            <img src="uploads/<?= $viewBlog['cover_image'] ?>"
                class="w-full h-[450px] object-cover">

            <div class="p-8">

                <h1 class="text-4xl font-bold mb-3">
                    <?= $viewBlog['title'] ?>
                </h1>

                <h2 class="text-xl text-gray-500 mb-4">
                    <?= $viewBlog['subtitle'] ?>
                </h2>

                <div class="flex justify-between text-sm text-gray-500 mb-6">
                    <span>By <?= $viewBlog['author_name'] ?></span>
                    <span><?= $viewBlog['upload_time'] ?></span>
                </div>

                <div class="text-gray-700 leading-8 whitespace-pre-line">
                    <?= $viewBlog['content'] ?>
                </div>

                <div class="mt-6 text-sm text-gray-500">
                    👁 <?= $viewBlog['view_count'] ?> Views
                </div>

                <a href="index.php"
                    class="inline-block mt-6 bg-blue-600 text-white px-5 py-2 rounded-lg">
                    ← Back to Blogs
                </a>

            </div>
        </div>

    <?php } else { ?>

        <!-- BLOG GRID -->
        <div class="max-w-7xl mx-auto p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

            <?php while ($row = $result->fetch_assoc()) { ?>

                <div class="bg-white rounded-2xl shadow-md overflow-hidden hover:shadow-lg transition">

                    <img src="uploads/<?= $row['cover_image'] ?>"
                        class="h-48 w-full object-cover">

                    <div class="p-5">

                        <span class="text-xs px-3 py-1 rounded-full bg-blue-100 text-blue-600">
                            Blog
                        </span>

                        <h2 class="font-bold text-lg mt-2">
                            <?= $row['title'] ?>
                        </h2>

                        <p class="text-sm text-gray-500 mt-1">
                            <?= $row['subtitle'] ?>
                        </p>

                        <p class="text-xs text-gray-400 mt-2">
                            By <?= $row['author_name'] ?>
                        </p>

                        <div class="flex justify-between text-xs text-gray-400 mt-3">
                            <span><?= $row['view_count'] ?> Views</span>
                            <span><?= $row['upload_time'] ?></span>
                        </div>

                        <a href="?view=<?= $row['blog_id'] ?>"
                            class="block mt-4 text-center bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">
                            Read More
                        </a>

                    </div>
                </div>

            <?php } ?>

        </div>

    <?php } ?>

    <!-- FOOTER -->
    <footer class="bg-white border-t mt-10">
        <div class="max-w-7xl mx-auto py-5 text-center text-sm text-slate-500">
            © 2026 PageDrop | Simple Blog System
        </div>
    </footer>

</body>

</html>