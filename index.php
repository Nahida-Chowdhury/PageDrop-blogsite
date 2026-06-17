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

/* ---------------- FETCH DYNAMIC SITE SETTINGS ---------------- */
$settings = [];
$settings_res = $conn->query("SELECT meta_key, meta_value FROM site_settings");
if ($settings_res) {
    while ($row = $settings_res->fetch_assoc()) {
        $settings[$row['meta_key']] = $row['meta_value'];
    }
}

// Fallback arrays if the database table runs empty
$hero_badge = $settings['hero_badge'] ?? 'Welcome to PageDrop';
$hero_title = $settings['hero_title'] ?? 'Where clear content meets minimal engineering.';
$hero_subtitle = $settings['hero_subtitle'] ?? 'Explore structured deep-dives on development workflows...';
$about_title = $settings['about_title'] ?? 'About PageDrop';
$about_subtitle = $settings['about_subtitle'] ?? 'We are an engineering-first platform.';
$about_content_title = $settings['about_content_title'] ?? 'Our Core Mandate';
$about_content_body = $settings['about_content_body'] ?? 'PageDrop aims to minimize unnecessary tracking networks...';


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

/* ---------------- MULTI-PAGE ROUTER ---------------- */
$page = $_GET['page'] ?? 'home';

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
    $page = 'view_post';
}

/* ---------------- CONTACT FORM SUBMISSION ---------------- */
$contact_success = false;
if ($page === 'contact' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inbound variables to prevent SQL injections
    $contact_name    = trim($conn->real_escape_string($_POST['contact_name'] ?? ''));
    $contact_email   = trim($conn->real_escape_string($_POST['contact_email'] ?? ''));
    $contact_subject = trim($conn->real_escape_string($_POST['contact_subject'] ?? ''));
    $contact_message = trim($conn->real_escape_string($_POST['contact_message'] ?? ''));

    // Validate that inputs are not blank spaces before running statement
    if (!empty($contact_name) && !empty($contact_email) && !empty($contact_message)) {
        $insert_sql = "INSERT INTO contact_messages (name, email, subject, message)
            VALUES (
            '$contact_name',
            '$contact_email',
            '$contact_subject',
            '$contact_message'
            )";

        if ($conn->query($insert_sql)) {
            $contact_success = true;
        }
    }
}

$contact_subject_prefill = '';

if (isset($_GET['subject'])) {
    $contact_subject_prefill = htmlspecialchars($_GET['subject']);
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
    <title>PageDrop Blog | Professional Engineering Publications</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-50 min-h-screen font-sans flex flex-col justify-between text-slate-800">

    <header class="bg-slate-900 text-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 py-4 flex flex-col md:flex-row gap-4 md:gap-0 items-center justify-between">

            <div class="flex items-center gap-6">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl overflow-hidden ring-2 ring-blue-500/30">
                        <img src="logo.gif" class="w-full h-full object-cover" alt="Logo">
                    </div>
                    <h1 class="text-2xl font-black text-blue-400 tracking-tight">
                        <a href="index.php" class="hover:text-blue-300 transition">PageDrop</a>
                    </h1>
                </div>

                <nav class="hidden sm:flex items-center gap-5 text-sm font-semibold text-slate-300 ml-4">
                    <a href="index.php" class="hover:text-white transition <?= $page == 'home' ? 'text-blue-400' : '' ?>">Home</a>
                    <a href="index.php?page=about" class="hover:text-white transition <?= $page == 'about' ? 'text-blue-400' : '' ?>">About</a>
                    <a href="index.php?page=contact" class="hover:text-white transition <?= $page == 'contact' ? 'text-blue-400' : '' ?>">Contact</a>
                </nav>
            </div>

            <form method="GET" action="index.php" class="w-full md:w-auto flex items-center gap-3 relative">
                <input type="hidden" name="page" value="home">

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

        <?php if ($page === 'view_post' && $viewBlog): ?>
            <main class="max-w-4xl mx-auto px-4 py-10">
                <div class="mb-6">
                    <a href="index.php" class="inline-flex items-center text-sm font-semibold text-blue-600 hover:text-blue-700 transition">
                        ← Back to Blogs Directory
                    </a>
                </div>

                <article class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <?php if (!empty($viewBlog['cover_image'])):
                        $article_imgs = explode(',', $viewBlog['cover_image']);
                    ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 p-4 bg-slate-100 border-b">
                            <?php foreach ($article_imgs as $single_img): ?>
                                <div class="overflow-hidden bg-white rounded-2xl h-72">
                                    <img src="uploads/<?= htmlspecialchars(trim($single_img)) ?>" class="w-full h-full object-cover" alt="Cover Image">
                                </div>
                            <?php endforeach; ?>
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

                        <div class="pt-8 border-t border-slate-200">
                            <div class="bg-blue-50 border border-blue-100 rounded-2xl p-6 text-center">
                                <h3 class="text-xl font-bold text-slate-800 mb-2">
                                    Have Questions About This Article?
                                </h3>

                                <p class="text-slate-600 mb-4">
                                    Contact us regarding this blog post.
                                </p>

                                <a
                                    href="index.php?page=contact&subject=<?= urlencode($viewBlog['title']) ?>"
                                    class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-xl transition">
                                    Contact About This Blog
                                </a>
                            </div>
                        </div>
                    </div>
                </article>
            </main>

        <?php elseif ($page === 'about'): ?>
            <main class="max-w-4xl mx-auto px-6 py-16 space-y-12">
                <div class="text-center space-y-4">
                    <h2 class="text-4xl font-extrabold text-slate-900 tracking-tight"><?= htmlspecialchars($about_title) ?></h2>
                    <p class="text-xl text-slate-500 max-w-2xl mx-auto"><?= htmlspecialchars($about_subtitle) ?></p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center pt-6">
                    <div class="space-y-4">
                        <h3 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($about_content_title) ?></h3>
                        <p class="text-slate-600 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($about_content_body) ?></p>
                    </div>
                    <div class="bg-gradient-to-br from-blue-600 to-indigo-900 p-8 rounded-3xl text-white space-y-4 shadow-xl">
                        <h4 class="font-bold text-xl">The Technical Difference</h4>
                        <ul class="space-y-2 text-sm text-indigo-100">
                            <li>✓ Sub-millisecond interface state loads</li>
                            <li>✓ Isolated structural management models</li>
                            <li>✓ Open layout architecture for system logs</li>
                        </ul>
                    </div>
                </div>
            </main>

        <?php elseif ($page === 'contact'): ?>
            <main class="max-w-2xl mx-auto px-6 py-16">
                <div class="bg-white rounded-3xl p-8 sm:p-12 shadow-sm border border-slate-200 space-y-6">
                    <div class="space-y-2">
                        <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">Get In Touch</h2>
                        <p class="text-sm text-slate-500">Have suggestions, partnership proposals, or system inquiries? Let us know.</p>
                    </div>

                    <?php if ($contact_success): ?>
                        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl text-sm font-medium">
                            🎉 Success! Message package processed successfully. Our support desk will reach out shortly.
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="index.php?page=contact" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Full Name</label>
                            <input type="text" name="contact_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-blue-500 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Email Destination</label>
                            <input type="email" name="contact_email" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-blue-500 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-500 mb-1">
                                Subject
                            </label>

                            <input
                                type="text"
                                name="contact_subject"
                                value="<?= $contact_subject_prefill ?>"
                                placeholder="Enter subject"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-blue-500 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Detailed Inquiry Context</label>
                            <textarea name="contact_message" rows="5" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-blue-500 transition resize-none" placeholder="Enter full parameters here..."></textarea>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition shadow-md">
                            Send Message
                        </button>
                    </form>
                </div>
            </main>

        <?php else: ?>
            <section class="bg-slate-900 text-white py-28 sm:py-36 relative overflow-hidden border-b border-slate-800/80">
                <div class="absolute top-1/2 left-1/4 -translate-y-1/2 -translate-x-1/2 w-[500px] h-[500px] bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-2 gap-16 items-center relative z-10">
                    <div class="space-y-8">
                        <span class="bg-blue-500/10 text-blue-400 text-xs font-bold uppercase tracking-widest px-3.5 py-2 rounded-full border border-blue-500/20 shadow-xs">
                            <?= htmlspecialchars($hero_badge) ?>
                        </span>
                        <h2 class="text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight leading-[1.1]">
                            <?= htmlspecialchars($hero_title) ?>
                        </h2>
                        <p class="text-slate-400 text-base sm:text-lg leading-relaxed max-w-lg">
                            <?= htmlspecialchars($hero_subtitle) ?>
                        </p>
                        <div class="flex items-center gap-5 pt-2">
                            <a href="#articles" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold px-7 py-3.5 rounded-xl transition shadow-md hover:shadow-blue-600/20 hover:-translate-y-0.5 transform duration-150">
                                Explore Articles
                            </a>
                            <a href="index.php?page=about" class="text-slate-300 hover:text-white text-sm font-semibold transition flex items-center gap-1.5 group">
                                Learn More <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
                            </a>
                        </div>
                    </div>

                    <div class="hidden md:block bg-slate-950/60 border border-slate-800/80 rounded-3xl p-7 shadow-2xl backdrop-blur-xs max-w-lg justify-self-end w-full">
                        <div class="flex items-center justify-between border-b border-slate-800 pb-4 mb-5">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-red-500/80"></span>
                                <span class="w-3 h-3 rounded-full bg-yellow-500/80"></span>
                                <span class="w-3 h-3 rounded-full bg-green-500/80"></span>
                            </div>
                            <span class="text-xs font-mono text-slate-500">pagedrop_core.json</span>
                        </div>
                        <pre class="text-xs sm:text-sm font-mono text-slate-300 leading-relaxed overflow-x-auto"><code>{
                            <span class="text-blue-400">"status"</span>: <span class="text-emerald-400">"Operational"</span>,
                            <span class="text-blue-400">"database"</span>: <span class="text-emerald-400">"Connected"</span>,
                            <span class="text-blue-400">"encryption"</span>: <span class="text-emerald-400">"AES-256"</span>,
                            <span class="text-blue-400">"cdn_nodes"</span>: [
                                <span class="text-indigo-400">"Edge_Global_01"</span>,
                                <span class="text-indigo-400">"Edge_Global_02"</span>
                            ],
                            <span class="text-blue-400">"cache_hit_rate"</span>: <span class="text-amber-400">"99.4%"</span>
                        }</code></pre>
                    </div>
                </div>
            </section>

            <main id="articles" class="max-w-7xl mx-auto p-6 space-y-6 scroll-mt-20">
                <?php if ($search != ''): ?>
                    <div class="text-sm font-medium text-slate-500">
                        Showing results for lookup keyword: <span class="text-slate-800 font-bold">"<?= htmlspecialchars($search) ?>"</span>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $all_imgs = explode(',', $row['cover_image']);
                            $preview_thumb = !empty($all_imgs[0]) ? trim($all_imgs[0]) : '';
                    ?>
                            <a href="?view=<?= $row['blog_id'] ?>"
                                class="group block bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden flex flex-col justify-between hover:shadow-lg hover:-translate-y-1 transition-all duration-300">

                                <div>
                                    <div class="h-36 bg-slate-100 relative overflow-hidden">
                                        <?php if (!empty($preview_thumb)): ?>
                                            <img src="uploads/<?= htmlspecialchars($preview_thumb) ?>"
                                                class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-500"
                                                alt="Article Preview">
                                        <?php endif; ?>
                                        <span class="absolute top-2 right-2 bg-slate-900/70 text-white text-[10px] px-2 py-1 rounded">
                                            👁 <?= $row['view_count'] ?>
                                        </span>
                                    </div>

                                    <div class="p-4 space-y-1">
                                        <h4 class="font-bold text-slate-800 text-sm line-clamp-2 leading-snug group-hover:text-blue-600 transition-colors">
                                            <?= htmlspecialchars($row['title']) ?>
                                        </h4>
                                        <p class="text-xs text-slate-400 line-clamp-1"><?= htmlspecialchars($row['subtitle']) ?></p>
                                        <p class="text-[11px] font-medium text-slate-400 pt-1">
                                            By: <span class="text-gray-600 font-semibold"><?= htmlspecialchars($row['author_name'] ?? 'Unknown') ?></span>
                                        </p>
                                    </div>
                                </div>

                                <div class="p-4 pt-0">
                                    <div class="w-full text-center bg-slate-50 group-hover:bg-blue-600 group-hover:text-white text-slate-700 font-bold text-xs py-2.5 rounded-xl transition-all duration-300">
                                        Read Article →
                                    </div>
                                </div>
                            </a>
                    <?php
                        }
                    } else {
                        echo "<div class='col-span-full bg-white p-12 text-center rounded-2xl border border-slate-200 text-slate-400 font-medium italic'>No matching records found.</div>";
                    }
                    ?>
                </div>
            </main>

        <?php endif; ?>
    </div>

    <footer class="bg-slate-900 border-t border-slate-800 text-slate-500 py-6 text-center text-xs font-medium mt-12">
        <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p>&copy; <?= date('Y') ?> PageDrop Systems Corporation. All rights reserved.</p>
            <div class="flex gap-4 text-slate-400">
                <a href="index.php" class="hover:text-blue-400 transition">Main Blog Feed</a>
                <span class="text-slate-700">|</span>
                <a href="index.php/admin" class="hover:text-blue-400 font-bold transition">Admin Suite</a>
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
                    if (data.length > 0) {
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