<?php
// ---------------- PATH ROUTER FOR ADMIN ----------------
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($request_uri, 'index.php/admin') !== false) {
    // Secretly load the admin file and stop executing the rest of index.php
    require_once __DIR__ . '/admin.php';
    exit;
}

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
</head>

<body class="bg-stone-100 min-h-screen font-sans flex flex-col justify-between">

    <header class="bg-slate-900 text-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 py-4 flex flex-col md:flex-row gap-4 md:gap-0 items-center justify-between">

            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl overflow-hidden ring-2 ring-blue-500/30">
                    <img src="logo.gif" class="w-full h-full object-cover">
                </div>
                <h1 class="text-2xl font-black text-blue-400 tracking-tight">
                    <a href="index.php" class="hover:text-blue-300 transition">PageDrop</a>
                </h1>
            </div>

            <form method="GET" class="w-full md:w-auto flex items-center gap-3 relative">

                <select name="filter" onchange="this.form.submit()"
                    class="bg-slate-800 border border-slate-700 text-slate-300 px-3 py-2 text-xs font-bold rounded-xl outline-none cursor-pointer focus:border-blue-500 transition">
                    <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>All Blogs</option>
                    <option value="recent" <?= $filter == 'recent' ? 'selected' : '' ?>>Recent Posts</option>
                    <option value="popular" <?= $filter == 'popular' ? 'selected' : '' ?>>Most Popular</option>
                </select>

                <div class="relative w-full sm:w-64">
                    <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>" autocomplete="off"
                        placeholder="Search articles, authors..."
                        class="bg-slate-800 border border-slate-700 text-white text-sm pl-4 pr-8 py-2 rounded-xl outline-none focus:border-blue-500 w-full transition placeholder-slate-500">

                    <span id="clearBtn"
                        class="hidden absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-slate-400 hover:text-red-400 font-bold text-lg">
                        ×
                    </span>

                    <div id="suggestBox"
                        class="absolute top-full left-0 mt-2 w-full bg-slate-800 border border-slate-700 rounded-xl shadow-xl z-50 overflow-hidden hidden">
                    </div>
                </div>

                <button class="bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm px-5 py-2 rounded-xl transition shadow-sm">
                    Search
                </button>

            </form>
        </div>
    </header>

    <div class="mb-auto">
        <?php if ($viewBlog) { ?>

            <main class="max-w-4xl mx-auto px-4 py-10">
                <div class="mb-6">
                    <a href="index.php" class="inline-flex items-center text-sm font-semibold text-blue-600 hover:text-blue-700 transition">
                        ← Back to Blogs Directory
                    </a>
                </div>

                <article class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <?php if(!empty($viewBlog['cover_image'])): ?>
                        <div class="w-full max-h-[450px] overflow-hidden bg-slate-100">
                            <img src="uploads/<?= htmlspecialchars($viewBlog['cover_image']) ?>" class="w-full h-full object-cover">
                        </div>
                    <?php endif; ?>

                    <div class="p-8 sm:p-12 space-y-6">
                        <div class="space-y-3 border-b border-slate-100 pb-6">
                            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight leading-tight">
                                <?= htmlspecialchars($viewBlog['title']) ?>
                            </h2>
                            <p class="text-lg text-slate-500 font-medium leading-relaxed">
                                <?= htmlspecialchars($viewBlog['subtitle']) ?>
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-4 text-xs font-semibold text-slate-400 uppercase tracking-wider bg-slate-50 px-6 py-3.5 rounded-2xl">
                            <div class="flex items-center gap-1.5">
                                <span class="text-slate-500">Written By:</span>
                                <span class="text-blue-600 font-bold"><?= htmlspecialchars($viewBlog['author_name'] ?? 'Guest Contributor') ?></span>
                            </div>
                            <div class="flex items-center gap-4">
                                <span>👁 <?= $viewBlog['view_count'] ?> Views</span>
                                <span>📅 <?= date('M d, Y', strtotime($viewBlog['upload_time'])) ?></span>
                            </div>
                        </div>

                        <div class="text-slate-700 text-base leading-relaxed whitespace-pre-line pt-4">
                            <?= htmlspecialchars($viewBlog['content']) ?>
                        </div>
                    </div>
                </article>
            </main>

        <?php } else { ?> 

            <main class="max-w-7xl mx-auto p-6 space-y-6">
                
                <?php if($search != ''): ?>
                    <div class="text-sm font-medium text-slate-500">
                        Showing results for lookup keyword: <span class="text-slate-800 font-bold">"<?= htmlspecialchars($search) ?>"</span>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php 
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) { 
                    ?>
                        <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden flex flex-col justify-between hover:shadow-md transition duration-200">
                            <div>
                                <div class="h-36 bg-slate-100 relative">
                                    <?php if(!empty($row['cover_image'])): ?>
                                        <img src="uploads/<?= htmlspecialchars($row['cover_image']) ?>" class="h-full w-full object-cover">
                                    <?php endif; ?>
                                    <span class="absolute top-2 right-2 bg-slate-900/70 text-white text-[10px] px-2 py-0.5 rounded">👁 <?= $row['view_count'] ?> Views</span>
                                </div>
                                <div class="p-4 space-y-1">
                                    <h4 class="font-bold text-slate-800 text-sm line-clamp-2 leading-snug"><?= htmlspecialchars($row['title']) ?></h4>
                                    <p class="text-xs text-slate-400 line-clamp-1"><?= htmlspecialchars($row['subtitle']) ?></p>
                                    <p class="text-[11px] font-medium text-slate-400 pt-1">By: <span class="text-blue-600 font-semibold"><?= htmlspecialchars($row['author_name'] ?? 'Unknown') ?></span></p>
                                </div>
                            </div>
                            <div class="p-4 pt-0">
                                <a href="?view=<?= $row['blog_id'] ?>"
                                    class="block text-center bg-slate-100 hover:bg-blue-600 hover:text-white text-slate-700 font-bold text-xs py-2.5 rounded-xl transition duration-150">
                                    Read Article →
                                </a>
                            </div>
                        </div>
                    <?php 
                        } 
                    } else {
                        echo "<div class='col-span-full bg-white p-12 text-center rounded-2xl border border-slate-200 text-slate-400 font-medium italic'>No matching records or uploaded entries found matching filter query.</div>";
                    }
                    ?>
                </div>
            </main>

        <?php } ?>
    </div>

    <footer class="bg-slate-900 border-t border-slate-800 text-slate-500 py-6 text-center text-xs font-medium mt-12">
        <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p>&copy; <?= date('Y') ?> PageDrop Systems Corporation. All rights reserved.</p>
            <div class="flex gap-4 text-slate-400">
                <a href="index.php" class="hover:text-blue-400 transition">Main Blog Feed</a>
                <span class="text-slate-700">|</span>
                <a href="index.php/admin" class="hover:text-blue-400 font-bold transition">Admin Management Suite</a>
            </div>
        </div>
    </footer>

    <script>
        const input = document.getElementById("searchInput");
        const box = document.getElementById("suggestBox");
        const clearBtn = document.getElementById("clearBtn");

        input.addEventListener("input", function() {
            if (this.value.length > 0) {
                clearBtn.classList.remove("hidden");
            } else {
                clearBtn.classList.add("hidden");
            }

            if (this.value.length < 1) {
                box.innerHTML = "";
                box.classList.add("hidden");
                return;
            }

            fetch("?suggest=" + encodeURIComponent(this.value))
                .then(res => res.json())
                .then(data => {
                    box.innerHTML = "";
                    if(data.length > 0) {
                        box.classList.remove("hidden");
                        data.forEach(item => {
                            let div = document.createElement("div");
                            div.innerText = item;
                            div.className = "px-4 py-2 text-xs font-medium text-slate-300 cursor-pointer hover:bg-slate-700/60 border-b border-slate-700/50 last:border-b-0 text-left transition";
                            div.onclick = function() {
                                input.value = item;
                                box.innerHTML = "";
                                box.classList.add("hidden");
                                input.form.submit();
                            };
                            box.appendChild(div);
                        });
                    } else {
                        box.classList.add("hidden");
                    }
                });
        });

        clearBtn.addEventListener("click", function() {
            input.value = "";
            box.innerHTML = "";
            box.classList.add("hidden");
            clearBtn.classList.add("hidden");
            window.location.href = "index.php";
        });

        document.addEventListener("click", function(e) {
            if (!input.contains(e.target) && !box.contains(e.target)) {
                box.innerHTML = "";
                box.classList.add("hidden");
            }
        });
    </script>
</body>
</html>