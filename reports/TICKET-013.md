# Investigation Report — TICKET-013: CSV export shows garbled characters

## Root Cause

CSV file was exported without a UTF-8 BOM. Excel and most spreadsheet apps default to Windows-1252 encoding when no BOM is present, causing non-ASCII characters (accented letters, emoji, non-Latin scripts) to render as garbage.

## Fix

Added UTF-8 BOM (`\xEF\xBB\xBF`) as the first bytes of the CSV file. One line change.

## Response to Reporter

> Hi,

> The garbled characters were caused by a missing encoding marker in the exported file. Spreadsheet apps were misreading the file format as a result. We've added the correct marker so Excel and similar tools will now open the file with proper UTF-8 encoding. Please re-export and the special characters should display correctly.