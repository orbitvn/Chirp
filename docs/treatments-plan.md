# Road Treatments / Forward Works Programme — Plan

Working plan for adding road maintenance treatments to the Chirp map. Draft for discussion — not final.

## Where we are now (already built)

The `/maps` page (Leaflet + OpenStreetMap, centred on Hawke's Bay NZ) already supports:

- **Points** — right-click → modal (name, description, value, color) → click to place. Saved to MySQL (`points` table).
- **Lines** — toolbar → modal → click nodes, double-click to finish. Editable (drag nodes, drag mid-points to add nodes) via Leaflet-Geoman. Saved to MySQL (`lines` table).
- **Measure tools** — scale bar, live cursor lat/lng, click-to-measure distance, line length shown in popups. All using real great-circle distance.

Auth is still the demo session (`demo@chirp.com` / `password`) — not real DB auth.

## Decisions locked so far

- **Scope:** both proposed *and* completed, as a lifecycle — `status`: proposed → programmed → completed.
- **Time filtering:** not in v1. Show all treatments for now; add a date slider/filter later.
- **Treatment types:** fixed preset list (editable in code):
  chipseal reseal, thin AC overlay, rehabilitation (pavement rebuild), pre-reseal repairs, area-wide treatment, smoothing/levelling course.
- **Roads storage:** static GeoJSON file in `public/` (roads are reference data, rarely change, and must load client-side for snapping anyway — a DB table buys nothing).
- **v1 keeps it flat:** no projects grouping, no carriageway/side, edit-attributes-only. Prove the linear-referencing loop first, add sophistication later.

## The shape of the thing

Three layers stacked on the existing map:

1. **Roads** (LINZ centrelines) — read-only reference layer everything snaps to.
2. **Treatments** — linear-referenced stretches of road with type, status, and dates. The new editable layer.
3. **Points / lines** — existing free-form annotations, unchanged.

Everything stands on **linear referencing**: a treatment is *"road X, from 120 m to 480 m,"* not a standalone shape. Solid foundation = the rest is styling.

## Data model

**Roads:** static `public/data/roads.geojson` — LINZ **NZ Addresses: Roads** (layer 123110), cropped to Hawke's Bay, exported as **GeoJSON, WGS84 (EPSG:4326)**. Loaded client-side, indexed for fast hover. Each feature gives a road **id** and **name** (exact property names TBC when we see the file; the id is what treatments link to).

Licensing: CC BY 4.0 — must show attribution on the map: *"Sourced from the LINZ Data Service, CC BY 4.0."*

**Treatments table:**

| field | purpose |
|---|---|
| `road_id`, `road_name` | link to the LINZ centreline + display name |
| `from_m`, `to_m` | start/end distance along the road (chainage) |
| `treatment` | preset type |
| `status` | proposed / programmed / completed |
| `start_date`, `end_date` | date range |
| `cost`, `notes` | optional |

On-map geometry is **not stored** — computed live with `turf.lineSliceAlong(road, from_m, to_m)` so it always tracks the centreline.

## How placing a treatment works

Click "Add treatment" → click a start point then an end point near a road → each click snaps to the nearest point on that road (`turf.nearestPointOnLine`), which returns the distance-along → gives `from_m` / `to_m` automatically → modal collects type, status, dates, cost → save → the sliced stretch draws on top of the grey road.

Visual encoding — two channels so both dimensions read at a glance:
- **Color** = treatment type
- **Line style** = status (proposed dashed, programmed solid, completed solid + bold)

## Risks to keep in view

- **MultiLineString roads.** A non-contiguous named road comes from LINZ as a MultiLineString. Turf slice/nearest work cleanly on a single LineString, so we need a rule: snap to the nearest component part and reference within it. Fiddliest part of the build.
- **Overlapping treatments.** With no time slider in v1, multiple treatments on the same section (e.g. resealed 2020, programmed 2028) draw on top of each other. Offset them as parallel lines (`turf.lineOffset`) so both stay visible.
- **Performance.** Even a Hawke's Bay crop is thousands of segments. Rendering is fine; hover-testing all of them per mouse-move is not — use a spatial index (bbox prefilter) + throttled `mousemove`, testing only roads near the cursor.
- **Linear-referencing drift.** Swapping in a newer LINZ export with shifted geometry can drift stored `from_m`/`to_m`. Non-issue for a static snapshot; note it before this becomes long-lived data.

## Phasing

- **Phase 1 — Roads** *(blocked on the LINZ file export)*: load + render the GeoJSON (toggleable), hover shows road name + chainage, spatial index, LINZ attribution.
- **Phase 2 — Treatments backend** *(not blocked)*: migration, model, controller, routes.
- **Phase 3 — Placement + rendering**: snap-two-points flow, modal, slice + style, popup, edit/delete.
- **Phase 4 — Later**: time slider/date filter, overlap-offset polish, reporting/export.

## Still open (decide before Phase 3)

- **Grouping:** need a `projects`/scheme layer over treatments, or are flat treatments enough for v1? (Leaning flat.)
- **Carriageway/side:** capture left/right/both, or ignore for v1? (Leaning ignore.)
- **Editing depth:** attributes only, or also drag endpoints to re-reference `from_m`/`to_m`? (Leaning attributes only.)
- **Snap tolerance:** how close a click must be to a road to count — 30 m?

## Next step

User is exporting the LINZ **NZ Addresses: Roads** layer (Hawke's Bay crop, GeoJSON WGS84) → drop it at `public/data/roads.geojson`. Then start **Phase 1**. Phase 2 (backend) can proceed in parallel any time.
