# Investigation Report — TICKET-002: Form settings truncated on save

## Debugging Steps

1. Symptom pattern: large forms affected, small forms fine — immediately suggested a column size limit.
2. Reviewed `form_settings` schema — found `value VARCHAR(255)`. HTML email templates easily exceed 255 characters.
3. Confirmed MySQL silently truncates values exceeding VARCHAR limit when `STRICT_TRANS_TABLES` is not enforced — no error returned to the application.
4. Checked `KeyValueStore::set()` — no truncation on PHP side, issue was purely at DB level.

## Root Cause

`form_settings.value` was defined as `VARCHAR(255)`. Any value over 255 characters was silently truncated by MySQL on INSERT/UPDATE. Short templates saved fine; large HTML templates were cut off mid-content.

## Fix Description

Migration `0003` applied: `ALTER TABLE form_settings MODIFY COLUMN value MEDIUMTEXT` — supports up to 16MB. No PHP changes needed.

Note: already-truncated data in the database cannot be recovered. Affected users will need to re-save their templates.

## Response to Reporter

> Hi,
>
> We found the issue — the database field storing your email template had a 255-character limit, and anything longer was being silently cut off on save. Your branded HTML template exceeded this limit, which is why it kept coming back truncated.
>
> We've expanded the field to support much larger content. Please re-save your email template and it should persist correctly going forward. We're sorry for the disruption to your registration flow.