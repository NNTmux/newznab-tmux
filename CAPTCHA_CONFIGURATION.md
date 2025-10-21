# CAPTCHA Configuration Guide

This application supports two CAPTCHA providers:
- **Google reCAPTCHA v2**
- **Cloudflare Turnstile**

## Important: Only One Provider at a Time

**You can only enable ONE captcha provider at a time.** The system will use the provider specified in `CAPTCHA_PROVIDER` environment variable.

## Configuration

### 1. Choose Your Provider

In your `.env` file, set the provider:

```env
CAPTCHA_PROVIDER=recaptcha  # or 'turnstile'
```

### 2. Google reCAPTCHA Configuration

To use Google reCAPTCHA:

```env
CAPTCHA_PROVIDER=recaptcha
NOCAPTCHA_ENABLED=true
NOCAPTCHA_SITEKEY=your_recaptcha_sitekey
NOCAPTCHA_SECRET=your_recaptcha_secret

# Disable Turnstile
TURNSTILE_ENABLED=false
```

**How to get reCAPTCHA keys:**
1. Visit [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin)
2. Register a new site with reCAPTCHA v2 (Checkbox)
3. Copy the Site Key and Secret Key

### 3. Cloudflare Turnstile Configuration

To use Cloudflare Turnstile:

```env
CAPTCHA_PROVIDER=turnstile
TURNSTILE_ENABLED=true
TURNSTILE_SITEKEY=your_turnstile_sitekey
TURNSTILE_SECRET=your_turnstile_secret

# Disable reCAPTCHA
NOCAPTCHA_ENABLED=false
```

**How to get Turnstile keys:**
1. Visit [Cloudflare Dashboard](https://dash.cloudflare.com/)
2. Go to Turnstile section
3. Create a new site
4. Copy the Site Key and Secret Key

## Features

### Where CAPTCHA is Used

CAPTCHA verification is applied to:
- **User Registration** (`/register`)
- **User Login** (`/login`)
- **Password Reset** (`/password/reset`)
- **Contact Form** (`/contact`)

### Switching Between Providers

To switch from one provider to another:

1. Update `CAPTCHA_PROVIDER` in `.env`
2. Enable the new provider and disable the old one
3. Clear config cache: `php artisan config:clear`

Example - Switching from reCAPTCHA to Turnstile:

```env
# Change this
CAPTCHA_PROVIDER=turnstile

# Enable Turnstile
TURNSTILE_ENABLED=true
TURNSTILE_SITEKEY=your_turnstile_sitekey
TURNSTILE_SECRET=your_turnstile_secret

# Disable reCAPTCHA
NOCAPTCHA_ENABLED=false
```

## Disabling CAPTCHA

To completely disable CAPTCHA protection:

```env
NOCAPTCHA_ENABLED=false
TURNSTILE_ENABLED=false
```

## Testing

### Test Keys

**Google reCAPTCHA Test Keys:**
- Site Key: `6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI`
- Secret Key: `6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe`

**Cloudflare Turnstile Test Keys:**
- Site Key: `1x00000000000000000000AA` (Always passes)
- Secret Key: `1x0000000000000000000000000000000AA`

## Troubleshooting

### CAPTCHA Not Showing

1. Check if CAPTCHA is enabled in `.env`
2. Verify you have both sitekey and secret configured
3. Clear config cache: `php artisan config:clear`
4. Check browser console for JavaScript errors

### Validation Failing

1. Verify the secret key is correct
2. Check if the domain is allowed in your CAPTCHA provider settings
3. Check application logs for detailed error messages

### Both Providers Enabled Warning

If you see a warning in logs about both providers being enabled, ensure you have:
- Set `CAPTCHA_PROVIDER` to either `recaptcha` or `turnstile`
- Disabled the provider you're not using by setting its `ENABLED` flag to `false`

## Technical Details

### Files Modified

- **Config:** `config/captcha.php` - Main configuration
- **Service:** `app/Services/TurnstileService.php` - Turnstile API integration
- **Helper:** `app/Support/CaptchaHelper.php` - Unified captcha interface
- **Rule:** `app/Rules/TurnstileRule.php` - Turnstile validation rule
- **Views:** Updated to use dynamic captcha provider
- **Requests:** Updated validation to support both providers

### API Endpoints

- **reCAPTCHA:** `https://www.google.com/recaptcha/api/siteverify`
- **Turnstile:** `https://challenges.cloudflare.com/turnstile/v0/siteverify`

## Security Notes

- Never commit your production keys to version control
- Use environment-specific `.env` files
- Regularly rotate your secret keys
- Monitor failed CAPTCHA attempts in logs

