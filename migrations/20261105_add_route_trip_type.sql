-- Run once before deploying the One-Way Trip Routes enhancement.
-- Existing distances, fuel allocations and route_locations are unchanged.
ALTER TABLE routes
    ADD COLUMN trip_type ENUM('round_trip', 'one_way') NOT NULL DEFAULT 'round_trip';
