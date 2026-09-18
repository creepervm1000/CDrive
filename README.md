<!--
  - SPDX-FileCopyrightText: 2026 CDrive contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# CDrive

CDrive is a privacy-hardened fork of the Nextcloud server (upstream base:
Nextcloud server 35). It keeps everything Nextcloud does as self-hosted
file sync/share software, and removes everything that phones home, nags,
limits, or advertises.

License: **GNU Affero General Public License v3** — see [COPYING](COPYING).
This is a modified version of Nextcloud server; all original copyright
notices are preserved in the source files, and CDrive changes are marked
per AGPLv3 §5(a). If you run this for anyone besides yourself, you must
offer them the Corresponding Source (§13) — e.g. by pointing them at
this repository.

## How it differs from upstream Nextcloud

### Branding
- Renamed to **CDrive** throughout defaults (name, entity, product, footer,
  colors, client/doc URL placeholders).
- Original CDrive logo set (SVG + PNG + ICO): black AMOLED tile with a
  green platter-ring "C". Replaces the logo, logo icon, favicons, touch
  icons, mail logo and the (now dead) enterprise logo files.
- Original procedural AMOLED background (`cdrive-amoled-glow.webp` + dark
  variant) is the new shipped default; no third-party artwork is loaded
  by default.

### Look: AMOLED only, no white surfaces
- New themes: **CDrive AMOLED Black**, **CDrive Green AMOLED**,
  **CDrive Orange AMOLED** (pure `#000000` backgrounds) plus a
  supplementary **CDrive monochrome app icons** theme (black & white icons).
- The built-in dark theme and the default CSS variables were switched to
  pure black as well, and the `cdrive` server theme forces black surfaces
  in legacy CSS. Light/white UI is effectively gone.

### Subscriptions and paywalls: removed
- No subscription keys, no subscription backend calls, no user-count
  thresholds (the old 500/1000 tiers), no over-limit/expired/missing
  nags or marketing e-mails, no updater-server switching to customer URLs.
- Formerly gated features are **free**: push notifications have no
  fair-use limit or user cap, all apps report as supported, the app-store
  allowlist works without a subscription, and no subscription key is ever
  sent anywhere.
- The support admin page shows only the (free, local) system report and
  community links; subscription UI, enterprise upsell, key forms, promo
  sections and the fair-use setup warnings are gone.
- The code-integrity setup check always passes: upstream signatures
  cannot pass on a fork by design (`occ integrity:check-*` still works
  manually for diffing against upstream).

### Telemetry: stripped, private by default
- Usage survey (`survey_client`): monthly reports and admin nags do
  nothing; reports are built locally only and never transmitted.
- Lookup server: opt-in, default empty — no user data is published
  anywhere unless the admin sets a URL.
- Announcements feed: opt-in via `announcements.feed_url`, default empty.
- User-triggered features (weather, photo places DB, update checker,
  app store, federation, push proxy) make no background calls on their
  own; anything they do send is governed by the firewall below.

### Egress firewall (Graphene-style, for the webapp)
Every outbound HTTP request through the server HTTP client is checked
before it leaves (`lib/private/Http/Client/CdriveEgressGuard.php`):
- `cdrive_egress_mode`: `allowlist` (default — block everything not
  allowed) or `denylist`.
- `cdrive_egress_denylist`: domains that are always blocked; each entry
  covers the domain **and all its subdomains**.
- `cdrive_egress_allowed_hosts`: allowed hosts (domain + subdomains).
- `cdrive_egress_app_allowlist`: app ids allowed to reach the network
  (`core` = the server itself).
- `cdrive_egress_allow_self` (default on): requests to the instance
  itself (from `overwrite.cli.url`, `overwritehost`, `trusted_domains`)
  pass automatically; the denylist still wins.
- Network log (on by default, capped): every allowed request with
  response status/duration/proxy, and every block with the reason and
  no response attached.

### Per-app forced proxying (anti-tracking)
- `cdrive_proxy_list`: global pool of HTTP/HTTPS proxy URLs.
- `cdrive_app_proxy`: per-app selection, e.g. `weather_status=auto`
  (random pool proxy) or `dav=http://proxy:8080` (pinned). Forced: an
  allowed app's traffic always goes through its proxy, so destinations
  never see the server IP — and only to firewall-allowed destinations.
- Failover: a failed connection retries once through a different proxy.
- Auto-nuke: 5 failures within 6 hours bans the proxy until the admin
  clears it; successes reset the counter. No working proxy → fail closed,
  never direct.

### Admin UI
All of the above is viewable and editable under
**Administration → CDrive** (enable the `cdrive` app first): firewall
mode/lists, proxy pool and per-app map with the nuked-proxy list and
unban button, telemetry opt-ins, and the live network log. The same keys
also work directly in `config.php`; see `config/config.sample.php`.

## Installing

This tree installs like upstream Nextcloud server 35: any web server
with PHP (see upstream docs for the required PHP modules), SQLite for
small setups (`--database=sqlite`) or MySQL/PostgreSQL, then the web
installer or `occ maintenance:install`. After installing, enable the
CDrive settings app (`occ app:enable cdrive`) and open
Administration → CDrive.

## Source layout notes (for developers)

- New code: `lib/private/Http/Client/CdriveEgressGuard.php`,
  `apps/cdrive/` (settings UI), `apps/theming/lib/Themes/CDrive*.php`,
  `themes/cdrive/` (theme, logo set, AMOLED CSS), shipped background art
  in `apps/theming/img/background/cdrive-amoled-glow*.webp`.
- Apps with authoritative Composer classmaps need new classes registered
  in `apps/<app>/composer/composer/autoload_{classmap,static}.php`
  (server core autoloading falls back gracefully; app autoloaders with
  `setClassMapAuthoritative(true)` do not).
- Every modified file keeps its upstream copyright and license header
  plus a dated CDrive notice; `-only` licensed files stayed `-only`.
