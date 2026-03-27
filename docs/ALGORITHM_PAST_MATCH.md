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

## Weighted Formula

$$
Score = 0.35P + 0.25A + 0.20D + 0.10T + 0.10R
$$

Where:

- $P$ = proximity score
- $A$ = availability score
- $D$ = donation interval score
- $T$ = travel time score
- $R$ = reliability score

All component scores are normalized to 0..100.

## Validation Scenarios Covered

- Closest donor ranks higher than farther donor.
- Donor with last donation 20 days ago is excluded (minimum 56 days).
- Donor with availability false is excluded.
- Higher reliability score increases ranking position.

## Performance Notes

- SQL prefiltering minimizes in-memory dataset size.
- Geospatial bounding box reduces expensive distance calculations.
- Cached location maps and constant compatibility tables reduce repeated work.
