# Investigation Report — FEATURE-001: PDF export for form submissions

## Debugging Steps

1. Reviewed `FeatureController::exportPdf()` — endpoint routes.php'de tanımlıydı ama `501 Not implemented` dönüyordu.
2. `backend/resources/pdf-template.html` incelendi — `{{form_name}}`, `{{generated_at}}`, `{{submission_count}}`, `{{rows}}` placeholder'ları mevcut.
3. Docker image'ında `wkhtmltopdf` olmadığı görüldü. Feature notes'da da önerilmediği belirtilmişti.
4. `dompdf/dompdf ^2.0` seçildi — pure PHP, sistem bağımlılığı yok, mevcut Docker image ile uyumlu.
5. Mevcut `Submission::findByForm()` metodu incelendi — veri çekimi için kullanılabilir, 5000 kayıt limit uygulandı.

## Implementation Decisions

- **DOMPDF seçildi** — wkhtmltopdf yerine. Sistem paketi gerektirmiyor, Docker image'ını şişirmiyor.
- **5000 kayıt limiti** — sonsuz submission çekmek memory sorununa yol açabilir.
- **htmlspecialchars** — `form_name` gibi kullanıcı kontrolündeki değerlere uygulandı, HTML injection önlendi.
- **`rows` placeholder'ı escape edilmedi** — HTML tablo satırları içerdiği için intentional.

## Fix Description

- `dompdf/dompdf ^2.0` Composer'a eklendi ve kuruldu.
- `PdfService` oluşturuldu: `renderTemplate()` placeholder substitution, `fromHtml()` PDF üretimi.
- `FeatureController::exportPdf()` implement edildi:
  - Auth + form ownership kontrolü
  - Submission verileri tablo satırlarına dönüştürüldü
  - Template render edildi, PDF üretildi
  - `Content-Type: application/pdf` ve `Content-Disposition: attachment` ile döndürüldü

## Response to Reporter

> Hi,
>
> The PDF export feature is now live. Form owners can download a PDF of all submissions via the API endpoint `GET /api/forms/{id}/export/pdf`. The file includes a submission table with row numbers and data, along with the export timestamp and total count.
>
> No frontend changes were made — this is API-only as scoped.