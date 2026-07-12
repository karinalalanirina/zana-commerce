# Zana Platform Billing Currency Fix

## Source Of Truth

- Platform billing currency is stored in `system_settings.default_currency`.
- Subscription plans, upgrade prompts, pricing pages, and plan checkout should read that value through `App\Support\ZanaPlatformBillingCurrency`.
- Merchant storefront currency is separate and should not be used for plan/package billing.

## Fix Summary

- Repaired the legacy bug where `system_settings.default_currency` could be stored as `type=int` with a string value like `NGN`, which caused `SystemSetting::get()` to return `0`.
- Forced `default_currency` and `catalog_default_currency` to be treated as string settings at the `SystemSetting` layer so legacy bad rows recover automatically.
- Normalized `default_currency` to uppercase on save in `AdminPagesController::settingGeneralUpdate()`.
- Rejected invalid billing currencies that do not exist in the `currencies` table.
- Read the general-settings default currency back through `ZanaPlatformBillingCurrency::code()`.
- Added `Package::getPriceDisplayAttribute()` so plan/package views always have a currency-bearing display string.
- Moved subscription pricing pages and checkout defaults onto the platform billing currency resolver.
- Fixed the checkout summary view to read `Package::$currency` instead of the non-existent `currency_code` field.
- Fixed the admin currency catalog “set default” action to write `default_currency` as a string instead of using the old implicit `int` default.

## Fallback Behavior

- If `default_currency` is missing, Zana falls back to `USD`.
- If the configured currency code has no matching `currencies` row, billing displays still show an explicit code prefix like `BWP 10,000` instead of a naked number.

## Regression Coverage

- Admin save path persists `default_currency`.
- Reload path returns the saved platform billing currency.
- Non-admin users cannot change the platform billing currency.
- Package pricing uses the saved platform billing currency.
- Fallback pricing still shows an explicit currency prefix.
