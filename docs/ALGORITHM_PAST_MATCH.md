# PAST-Match Algorithm Explanation

## Objective

Rank eligible donors for a blood request using weighted factors so hospitals can contact the most suitable donors first.

## Pipeline

1. Filter candidate donors (`DonorFilterService`):
   - Blood type compatibility
   - Availability = true
   - Donation interval >= 56 days
   - Distance threshold (if coordinates available)
2. Score candidates (`PASTMatch`).
3. Sort descending by total score.
4. Persist top-ranked matches.

## Base Audit Formula

$$
BaseScore = (w_p \times Priority) + (w_a \times Availability) + (w_d \times Distance) + (w_t \times Time)
$$

Where:

- $Priority$ combines urgency pressure, arrival priority, and donation readiness
- $Availability$ combines live availability and donation interval eligibility
- $Distance$ combines proximity and transport accessibility
- $Time$ combines travel time, arrival priority, and traffic conditions
- $w_p, w_a, w_d, w_t$ are normalized admin-configurable weights

Each grouped component score is normalized to 0..100, and the resulting `BaseScore` remains normalized for explainability.

The admin settings page now stores explicit low, medium, high, and critical urgency profiles. The medium profile remains the default baseline, while each urgency tier can be tuned independently to shift the effective weighting toward either time sensitivity or donor sustainability.

## Operational Score

The final ranking score is a composite of three independent adjustments applied on top of the normalized base score:

$$
OperationalScore = BaseScore + EmergencyAdjustment - CooldownPenalty
$$

- **BaseScore** — normalized 0–100 weighted audit score; used for explainability and settings-based audit review
- **EmergencyAdjustment** — additive boost active only during emergency broadcast mode (see below)
- **CooldownPenalty** — subtractive fairness deduction for donors matched within the last 72 hours (see below)

Ties in `OperationalScore` are broken by `BaseScore` descending, then by donor ID ascending, making ranking fully deterministic for identical inputs.

## Emergency Adjustment

When emergency broadcast mode is active, PAST-Match adds a separate operational adjustment:

$$
OperationalScore = BaseScore + EmergencyAdjustment - CooldownPenalty
$$

The emergency adjustment is derived from fastest-arrival, travel-time, proximity, and reliability signals. It affects donor ranking during emergency operations, but it does not overwrite the normalized base audit score shown in explainability views.

## Fairness Rotation Cooldown

To prevent high-reliability donors from monopolising every request queue, donors who appear in a previous match within the last 72 hours receive a small deduction from their operational score. The penalty tiers are:

| Time since last match | Penalty |
|---|---|
| < 6 hours | −8 points |
| 6–24 hours | −5 points |
| 24–72 hours | −2 points |
| > 72 hours | 0 (no penalty) |

The `base_score` (audit score) is never modified; only `operational_score` is affected. This ensures the compatibility audit trail remains clean while still rotating the active donor pool over time.

## Location Fallback

When request coordinates are available but a donor has no coordinates, PAST-Match no longer drops the donor automatically if the donor and request share the same city. In that case the system estimates travel distance from city context, lowers accessibility confidence, and surfaces that reduced confidence in monitoring output.

## Validation Scenarios Covered

- Closest donor ranks higher than farther donor.
- Donor with last donation 20 days ago is excluded (minimum 56 days).
- Donor with availability false is excluded.
- Higher reliability score increases ranking position.
- Emergency adjustment can reorder candidates while preserving the base compatibility score for audit review.
- Donor matched within the last 6 hours receives an 8-point cooldown penalty, dropping below an equally-scored fresh donor.
- Two donors with identical scores are ranked deterministically by ascending donor ID.

## Performance Notes

- SQL prefiltering minimizes in-memory dataset size.
- Geospatial bounding box reduces expensive distance calculations.
- Cached location maps and constant compatibility tables reduce repeated work.
