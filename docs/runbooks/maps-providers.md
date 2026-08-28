# Maps providers

Family House Connect supports **any one** of three map providers. Admins paste a key for Google and/or Mapbox (or choose Leaflet with no key), select the active provider, then activate.

## Admin setup

1. Open **Admin → Platform Settings → Maps Providers** (`/admin/settings/maps`).
2. Choose **Leaflet**, **Google Maps**, or **Mapbox**.
3. Paste the matching key when required:
   - Google: Maps JavaScript API key (HTTP referrer restricted for web)
   - Mapbox: public access token
   - Leaflet: no key (OpenStreetMap tiles by default)
4. Save, then **Activate**.

API (global MFA + `platform.maps.*`):

- `GET/PUT /api/v1/admin/platform/maps`
- `POST/DELETE /api/v1/admin/platform/maps/activation`

## Public clients

- `GET /api/v1/maps/configuration` — active provider + client key / tile URL + default center
- `GET /api/v1/maps/places` — churches and home churches with coordinates

Web (`frontend/apps/web`) and mobile load that configuration and render an interactive map with pan/zoom, markers, “Use my location”, and directions.

**Activation gate (production):**

- When **no** maps provider is activated and design fixtures are **off**, public map surfaces show a clear **unavailable** state (no map canvas, no fixture/fake pins, no invented vendor keys).
- When a provider **is** activated, clients use the live bootstrap (`provider`, `client_api_key` / `tile_url`, places) exactly as returned by the API.
- Design fixtures (`FHC_ENABLE_DESIGN_FIXTURES=true`) may preview Leaflet + sample pins for UI work only.

Set `NEXT_PUBLIC_FHC_API_URL=http://localhost:8000/api/v1` for the web app.

Mobile accepts either dart-define (aligned helpers convert between them):

- `FHC_API_URL=http://localhost:8000/api/v1` — full `/api/v1` base for app transport
- `FHC_PUBLIC_API_BASE_URL=http://localhost:8000` — host origin for generated
  `api/clients/dart` clients

Defaults: `http://localhost:8000/api/v1` on web/desktop/iOS simulator, and
`http://10.0.2.2:8000/api/v1` on the Android emulator. See
`mobile/.env.example` and `mobile/dart-define.example.json`.

