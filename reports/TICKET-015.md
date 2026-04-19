# Investigation Report — TICKET-015: Form logo shows the wrong image

## Root Cause

`FileUploadController` saved files using the original filename (`basename($orig)`). Two users uploading `logo.png` caused the second upload to overwrite the first. The original record still pointed to the same path, now serving the wrong user's image.

## Fix

Added `uniqid('', true)` prefix to the stored filename — every upload gets a unique path regardless of original name.

## Response to Reporter

> Hi,
>
> The issue was caused by uploaded files with the same name overwriting each other on the server. We've fixed this so every uploaded file now gets a unique identifier, preventing any overlap between accounts. Your logo should display correctly going forward.