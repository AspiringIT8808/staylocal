USE staylocal_update;

-- Run once against an existing StayLocal database after backing it up.
ALTER TABLE apartments
    ADD COLUMN image_url VARCHAR(255) NOT NULL DEFAULT 'assets/feat-view.jpg' AFTER pet_friendly;

UPDATE apartments
SET image_url = CASE id
    WHEN 1 THEN 'assets/feat-view.jpg'
    WHEN 2 THEN 'assets/feat-bedroom.jpg'
    WHEN 3 THEN 'assets/feat-kitchen.jpg'
    WHEN 4 THEN 'assets/feat-view.jpg'
    WHEN 5 THEN 'assets/feat-bedroom.jpg'
    ELSE 'assets/feat-view.jpg'
END;

ALTER TABLE apartments
    DROP COLUMN accent,
    DROP COLUMN image_label;
