<?php
$conn = new mysqli("localhost", "root", "", "blog_site");
if ($conn->connect_error) die("DB Connection Failed");

/* ---------------- SEARCH SUGGESTIONS ---------------- */
if (isset($_GET['suggest'])) {
    $q = trim($conn->real_escape_string($_GET['suggest']));

    $res = $conn->query("
        SELECT title 
        FROM blog 
        WHERE title LIKE '%$q%' 
        LIMIT 5
    ");

    $suggestions = [];
    while ($row = $res->fetch_assoc()) {
        $suggestions[] = $row['title'];
    }

    header('Content-Type: application/json');
    echo json_encode($suggestions);
    exit;
}

/* ---------------- VIEW BLOG ---------------- */
$viewBlog = null;

if (isset($_GET['view'])) {

    $id = (int)$_GET['view'];

    $conn->query("UPDATE blog SET view_count = view_count + 1 WHERE blog_id = $id");

    $viewBlog = $conn->query("
        SELECT blog.*, authors.name AS author_name
        FROM blog
        LEFT JOIN authors
        ON blog.author_id = authors.author_id
        WHERE blog.blog_id = $id
    ")->fetch_assoc();
}

/* ---------------- FILTER + SEARCH ---------------- */
$filter = $_GET['filter'] ?? 'all';
$search = trim($conn->real_escape_string($_GET['search'] ?? ''));

$sql = "
SELECT blog.*, authors.name AS author_name
FROM blog
LEFT JOIN authors
ON blog.author_id = authors.author_id
WHERE 1
";

if ($search != '') {
    $sql .= "
    AND (
        blog.title LIKE '%$search%' OR
        blog.subtitle LIKE '%$search%' OR
        authors.name LIKE '%$search%'
    )";
}

if ($filter == "popular") {
    $sql .= " ORDER BY view_count DESC";
} elseif ($filter == "recent") {
    $sql .= " ORDER BY upload_time DESC";
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

<style>
#suggestBox {
    position: absolute;
    background: white;
    width: 260px;
    border: 1px solid #ddd;
    border-radius: 8px;
    z-index: 1000;
}

#suggestBox div {
    padding: 8px;
    cursor: pointer;
}

#suggestBox div:hover {
    background: #f3f4f6;
}

.clear-btn {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    font-size: 18px;
    color: gray;
    display: none;
}
</style>
</head>

<body class="bg-slate-100 min-h-screen">

<!-- NAVBAR -->
<header class="bg-white shadow-sm border-b sticky top-0 z-50">
<div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">

    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl overflow-hidden">
            <img src="logo.gif" class="w-full h-full object-cover">
        </div>
        <h1 class="text-2xl font-bold text-slate-800">
            <a href="index.php">PageDrop</a>
        </h1>
    </div>

    <form method="GET" class="flex items-center gap-3 relative">

        <select name="filter"
            onchange="this.form.submit()"
            class="border px-3 py-2 rounded-lg text-sm">

            <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>All Blogs</option>
            <option value="recent" <?= $filter == 'recent' ? 'selected' : '' ?>>Recent</option>
            <option value="popular" <?= $filter == 'popular' ? 'selected' : '' ?>>Popular</option>

        </select>

        <!-- SEARCH BOX -->
        <div style="position:relative;">
            <input type="text"
                id="searchInput"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="Search blogs..."
                class="border px-3 py-2 rounded-lg w-64 pr-8">

            <span id="clearBtn" class="clear-btn">×</span>

            <div id="suggestBox"></div>
        </div>

        <button class="bg-blue-600 text-white px-4 py-2 rounded-lg">
            Search
        </button>

    </form>

</div>
</header>

<script>
const input = document.getElementById("searchInput");
const box = document.getElementById("suggestBox");
const clearBtn = document.getElementById("clearBtn");

/* INPUT EVENT */
input.addEventListener("input", function () {

    clearBtn.style.display = this.value.length > 0 ? "block" : "none";

    if (this.value.length < 1) {
        box.innerHTML = "";
        return;
    }

    fetch("?suggest=" + this.value)
        .then(res => res.json())
        .then(data => {
            box.innerHTML = "";

            data.forEach(item => {
                let div = document.createElement("div");
                div.innerText = item;

                div.onclick = function () {
                    input.value = item;
                    box.innerHTML = "";
                    clearBtn.style.display = "block";
                };

                box.appendChild(div);
            });
        });
});

/* CLEAR BUTTON */
clearBtn.addEventListener("click", function () {
    input.value = "";
    box.innerHTML = "";
    this.style.display = "none";
    input.focus();
});
</script>

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
            <span>By <?= $viewBlog['author_name'] ?? 'Unknown' ?></span>
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

    <div class="bg-white rounded-2xl shadow-md overflow-hidden">

        <img src="uploads/<?= $row['cover_image'] ?>"
            class="h-48 w-full object-cover">

        <div class="p-5">

            <h2 class="font-bold text-lg"><?= $row['title'] ?></h2>

            <p class="text-sm text-gray-500"><?= $row['subtitle'] ?></p>

            <p class="text-xs text-gray-400 mt-2">
                By <?= $row['author_name'] ?? 'Unknown' ?>
            </p>

            <a href="?view=<?= $row['blog_id'] ?>"
                class="block mt-4 text-center bg-blue-600 text-white py-2 rounded-lg">
                Read More
            </a>

        </div>
    </div>

<?php } ?>

</div>

<?php } ?>

</body>
</html>