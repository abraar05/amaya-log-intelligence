# Deployment checklist

1. Upload `public/` contents into the hosting document root.
2. Create a MySQL database and database user.
3. Rename `config.example.php` to `config.php`; set `demo_mode` to `false` and fill in credentials.
4. Open `/install.php` once to create initial tables.
5. Delete `install.php`.
6. Enable HTTPS in hosting settings.
7. Verify PHP limits (`upload_max_filesize`, `post_max_size`, execution time) before large imports.
8. Test database backups and restore.
9. Restrict admin-only pages and add authentication before using real company records.
10. Configure refresh jobs only after confirming hosting cron and outbound HTTP availability.

## Current package scope
The included dashboard is a polished front-end and deployment scaffold. Demo metrics are illustrative. CSV selection and source registration currently run in preview mode and do not persist uploaded rows. Before treating this as a production system, implement authentication, server-side CSV parsing/storage, source CRUD, scheduled remote fetches, permission checks, and audit writes. This limitation is intentionally disclosed rather than implying unimplemented production functions are complete.
