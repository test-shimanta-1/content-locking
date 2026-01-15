# Content Lock

A WordPress plugin that prevents simultaneous editing of the same content and improves team collaboration by using WordPress’s native post locking and Heartbeat API.

## Overview

**Content Lock** ensures that only one user can edit a post or page at a time in the WordPress admin.  
When a user starts editing content, a lock is applied. Other users attempting to edit the same content are notified that it is already being managed by someone else.

The plugin automatically refreshes locks, cleans up stale sessions, and allows administrators to override locks when required—helping teams avoid accidental overwrites and editing conflicts.

---

## Key Features

- **Content Locking**  
  Automatically locks posts and pages when a user starts editing.

- **Real-Time Lock Status**  
  Displays who currently holds the lock and how long the content has been locked.

- **Automatic Lock Expiry**  
  Locks are refreshed using the WordPress Heartbeat API and expire after **20 minutes** of inactivity.

- **Heartbeat-Based Refresh**  
  Lock status is refreshed every **15 seconds** using the Heartbeat API to ensure accuracy.

- **Admin Override**  
  Administrators can break an active lock if necessary and take over editing.

- **Configurable Lock Expiration**  
  Control how long a content lock remains active. Lock duration can be configured from **1 to 1440 minutes** via **Settings → Reading**.

---

## Usage

### How It Works

- When a user opens a post or page for editing, a content lock is applied immediately.
- The lock is refreshed every **15 seconds** via the WordPress Heartbeat API.
- If the editing user becomes inactive, the lock automatically expires after **20 minutes**.
- Other users attempting to edit the same content will see a clear notification indicating:
  - Who is currently editing the content
  - How long the content has been locked
  - Content locks automatically expire after the configured time.
  - Once a lock expires, other users can immediately open and edit the content without manual intervention.

---

### Breaking a Lock (Admin Only)

- Administrators with the `break_content_lock` capability can manually break an existing lock.
- Locks can be broken from:
  - The post list screen
  - The lock notification displayed in the admin area
- Once broken, the administrator takes control of the content lock and can edit immediately.

---

## Installation

1. Upload the `content-lock` folder to the `/wp-content/plugins/` directory.  
2. Activate the plugin through the **Plugins** screen in WordPress.  
3. The plugin works automatically—no additional configuration is required.
