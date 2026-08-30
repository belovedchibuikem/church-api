# Geography datasets

JSON under this folder is used by `php artisan geography:seed`.

## Refresh datasets

```bash
php scripts/download_geography_datasets.php
# or
php artisan geography:seed --download --states-only
```

## Seed into database tables

```bash
# Priority countries with localities (NG, GH, KE, …)
php artisan geography:seed --country=NG --country=GH --country=KE

# All countries + first-level states/regions
php artisan geography:seed --states-only

# Full import for every country that has a localities-XX.json file
php artisan geography:seed
```

## Tables

| Table | Role |
|-------|------|
| `countries` | ISO 3166-1 alpha-2 + name |
| `administrative_levels` | e.g. `state` (sort 10), `local_government` (sort 20) |
| `administrative_units` | States/regions and LGAs/cities (`parent_id` for cascade) |

## Public API

- `GET /api/v1/geography/countries`
- `GET /api/v1/geography/countries/{iso}`
- `GET /api/v1/geography/countries/{iso}/states`
- `GET /api/v1/geography/countries/{iso}/states/{state}/localities`
