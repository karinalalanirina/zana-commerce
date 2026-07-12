# Zana Payment Secret Review

Scope:

- whether payment/API secrets are exposed to authenticated tenant users or app clients

## Result

| Test / Endpoint | Result before | Fix needed? | Result after | Notes |
|---|---|---|---|---|
| `App\Http\Controllers\Api\App\BillingController::paymentGatewaySettings` | Decrypted gateway credentials were serialized into mobile/API responses | Yes | Fixed | Response now returns only explicit public keys via `App\Support\ZanaPaymentGatewayPresenter`. |
| `App\Http\Controllers\Api\App\PaymentGatewayController::show` | Admin API could reveal decrypted credentials including secrets | Yes | Fixed | Response now returns `credential_fields`, `credentials_set`, and `public_values` only. |
| `resources/views/admin/payment-gateways/index.blade.php` | Stored secrets were being pushed into the admin DOM as existing form values | Yes | Fixed | View now uses `credentials_set` plus public-only values and never renders stored secrets. |
| Credential update flow | Risk of wiping secrets during partial edits | Yes | Fixed | Blank or omitted secret fields now preserve existing encrypted values. |
| `App\Http\Controllers\Admin\TranslationProviderController::index` | Decrypted translation-provider credentials were attached to the admin view model | Yes | Fixed | Admin now receives only `credential_fields`, `credentials_set`, and `credentials_public_values` via `App\Support\ZanaTranslationProviderPresenter`. |
| `resources/views/admin/translation-providers/index.blade.php` | Stored translation-provider secrets were rendered back into password inputs | Yes | Fixed | Secret inputs stay blank, show only saved-state placeholders, and preserve stored encrypted values on partial updates. |

## Files Changed

- `app/Support/ZanaPaymentGatewayPresenter.php`
- `app/Support/ZanaTranslationProviderPresenter.php`
- `app/Http/Controllers/Api/App/BillingController.php`
- `app/Http/Controllers/Api/App/PaymentGatewayController.php`
- `app/Http/Controllers/Admin/PaymentGatewayController.php`
- `app/Http/Controllers/Admin/TranslationProviderController.php`
- `resources/views/admin/payment-gateways/index.blade.php`
- `resources/views/admin/translation-providers/index.blade.php`

## Verification

- `php artisan test --filter='PaymentGatewaySecurityTest|TranslationProviderSecurityTest|ZanaOfficialWhatsAppGuardTest'`
- `Tests\Feature\PaymentGatewaySecurityTest`: `9` passing tests, including:
  - guest/admin access control behavior on admin gateway endpoint
  - billing response contains only public gateway fields
  - admin endpoint omits stored secret values
  - partial updates preserve existing secrets
  - replacement secrets update intentionally
  - seeded secret value does not appear in log output
- `Tests\Feature\TranslationProviderSecurityTest`: `2` passing tests, including:
  - admin translation-provider page renders public endpoint values but never the seeded secret
  - blank secret submissions preserve the existing encrypted `api_key`

## Launch Impact

- Safe for local execution work: Yes
- Safe for pilot clients: Yes for the payment and translation-provider admin surfaces covered here
- Safe for paying clients: Yes for the specific admin secret surfaces covered here

## Remaining Risk

- Payment-gateway and translation-provider admin surfaces are now covered. Other secret-bearing admin areas should still be reviewed opportunistically, but the previously identified translation-provider follow-up is complete.
