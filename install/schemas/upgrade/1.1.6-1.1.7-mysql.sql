ALTER TABLE {{TABLE_PREFIX}}users_extra ADD COLUMN date_format varchar(32) NOT NULL DEFAULT 'F d, Y';
ALTER TABLE {{TABLE_PREFIX}}users_extra ADD COLUMN time_format varchar(32) NOT NULL DEFAULT 'G:i';
