# FEATURE-001: PDF export for form submissions

**Priority:** Medium  
**Type:** New feature

## Description

Product wants a way to download a PDF containing all submissions for a given form, using a provided HTML layout template. The implementation approach (library, external tool, or small service) is up to engineering.

## Acceptance (high level)

- Endpoint serves a valid PDF for an authorized form owner.
- Response uses appropriate PDF content type and a sensible filename.
- Unauthorized or unknown forms are rejected.

## Notes

A template file is provided in the repository under `backend/resources/`. The frontend is out of scope; implement the API behavior only.

## Implementation tip

We recommend a pure-PHP library such as **DOMPDF** (`composer require dompdf/dompdf`) for this task. It requires no system-level packages and works out of the box with the existing Docker image.

Avoid using `wkhtmltopdf` — it pulls in a large Qt/X11 dependency tree via apt that adds several hundred MB to the Docker image and significantly increases build time. Given the time-constrained nature of this event, every `docker compose build` cycle matters. If you iterate on the Dockerfile even once, the time cost compounds quickly.

If you still want to use `wkhtmltopdf` despite the above, you will need to switch the base image in `docker/php/Dockerfile` from `php:8.1-fpm-trixie` to `php:8.1-fpm-bookworm` (trixie has removed the package) and add `wkhtmltopdf` to the `apt-get install` line. Be warned: the resulting image build will be slow.
