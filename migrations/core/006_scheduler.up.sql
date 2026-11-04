-- the last run of every task of the scheduler: when a task is due again
CREATE TABLE `scheduler_runs` (
    `task` VARCHAR(100) NOT NULL PRIMARY KEY,
    `last_run_at` DATETIME NOT NULL,
    `status` VARCHAR(10) NOT NULL DEFAULT '',
    `error` TEXT NULL
);
