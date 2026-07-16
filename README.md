# PageDrop - Dynamic Blog Management System

PageDrop is a modern blog management system developed using **PHP, MySQL, and Tailwind CSS**. It provides a clean blogging platform for visitors while offering an administrator dashboard to manage blog posts, authors, website content, and customer inquiries.

---

## Features

### User Features
- Home page with dynamic blog listing
- Blog search with live suggestions
- Filter blogs by:
  - All Blogs
  - Recent Posts
  - Most Popular
- Blog details page
- Multiple images for each blog
- View counter
- Dynamic About page
- Contact page
- Contact form with blog subject auto-fill
- Responsive user interface

---

### Admin Features

#### Authentication
- Secure Admin Login
- Password hashing
- Session authentication
- Session timeout
- Token-based session validation

#### Blog Management
- Create Blog
- Edit Blog
- Delete Blog
- Upload multiple images
- Assign authors
- View blog details
- Search blogs

#### Author Management
- Add author
- Remove author
- Assign author to blogs

#### Contact Management
- View customer enquiries
- View enquiry subject
- View sender information
- Delete enquiries

#### Website Settings
Dynamic management of:

- Hero Badge
- Hero Title
- Hero Subtitle
- About Title
- About Subtitle
- About Content Title
- About Content Body

No code editing is required to update these contents.

---

## Technologies Used

### Frontend

- HTML5
- Tailwind CSS
- JavaScript

### Backend

- PHP
- MySQL

### Database

- MySQL

---

## Database Tables

- admins
- authors
- blog
- contact_messages
- site_settings

---

## Project Structure

```
PageDrop/
│
├── uploads/
├── logo.gif
├── index.php
├── admin.php
├── README.md
└── auth.php
```

---

## Installation

### Clone the repository

```bash
git clone https://github.com/Nahida-Chowdhury/PageDrop-blogsite.git
```

### Move project

Place the project inside your web server directory.

Example:

```
xampp/htdocs/PageDrop
```

## Author

**Nahida Chowdhury**

Software Developer Intern
