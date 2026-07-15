# Security Review Follow-up

These items came from the hoshdar.ir PDF review for `https://metalsp.ir`.

## Fixed in this codebase

- Added application security headers:
  - `X-Frame-Options: SAMEORIGIN`
  - `X-Content-Type-Options: nosniff`
  - `Content-Security-Policy`
  - `Strict-Transport-Security`
  - `Referrer-Policy`
  - `Permissions-Policy`
- Hardened `public/.htaccess` to block hidden files/directories such as `/.git/*` and `.env`.
- Removed sensitive private/admin paths from `robots.txt`.

## Must be fixed in hosting/server panel

- Close public access to ports `21`, `22`, and `3306`; allow them only from trusted IPs or VPN.
- Upgrade MariaDB from `10.3.23` to a supported LTS version such as `10.11.x` or newer.
- Make sure the web document root points only to Laravel `public/`, not the project root.
- Block direct web access to `.git`, `.env`, `storage`, `vendor`, and other project files at the web server level.
- Hide or minimize the `Server` response header if the hosting provider allows it.
- Check the discovered subdomain `www.bot.metalsp.ir` separately or remove it from the certificate/DNS if unused.
