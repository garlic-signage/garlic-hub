-- the AI providers of a user (OpenAI compatible endpoints), e.g. a local Ollama and OpenAI.
-- The key is encrypted, a local LLM may have none
CREATE TABLE `user_llm_connections` (
                                        `connection_id` INTEGER PRIMARY KEY,
                                        `UID` INTEGER NOT NULL,
                                        `name` VARCHAR(100) NOT NULL,
                                        `api_type` VARCHAR(20) NOT NULL DEFAULT 'openai',
                                        `base_url` VARCHAR(255) NOT NULL,
                                        `api_key` TEXT NOT NULL DEFAULT '',
                                        FOREIGN KEY (`UID`) REFERENCES `user_main` (`UID`) ON DELETE CASCADE
);

-- the model of a provider a user works with for a task, e.g. text. Used only while his company allows
-- the own AI for the task
CREATE TABLE `user_llm_tasks` (
                                  `UID` INTEGER NOT NULL,
                                  `task` VARCHAR(30) NOT NULL,
                                  `connection_id` INTEGER NOT NULL,
                                  `model` VARCHAR(100) NOT NULL,
                                  PRIMARY KEY (`UID`, `task`),
                                  FOREIGN KEY (`UID`) REFERENCES `user_main` (`UID`) ON DELETE CASCADE,
                                  FOREIGN KEY (`connection_id`) REFERENCES `user_llm_connections` (`connection_id`) ON DELETE CASCADE
);

-- the AI providers of a company. Core has only the company 1, in Enterprise every customer has his own
CREATE TABLE `company_llm_connections` (
                                           `connection_id` INTEGER PRIMARY KEY,
                                           `company_id` INTEGER NOT NULL DEFAULT 1,
                                           `name` VARCHAR(100) NOT NULL,
                                           `api_type` VARCHAR(20) NOT NULL DEFAULT 'openai',
                                           `base_url` VARCHAR(255) NOT NULL,
                                           `api_key` TEXT NOT NULL DEFAULT ''
);

-- who works with which AI for a task: the company with its model for all users or only for its admins, every user
-- with his own one, or nobody. Without a row the task is off
CREATE TABLE `company_llm_tasks` (
                                     `company_id` INTEGER NOT NULL,
                                     `task` VARCHAR(30) NOT NULL,
                                     `mode` VARCHAR(20) NOT NULL DEFAULT 'disabled' CHECK (mode IN ('company_all', 'company_admins', 'user_own', 'disabled')),
                                     `connection_id` INTEGER NULL,
                                     `model` VARCHAR(100) NOT NULL DEFAULT '',
                                     PRIMARY KEY (`company_id`, `task`),
                                     FOREIGN KEY (`connection_id`) REFERENCES `company_llm_connections` (`connection_id`) ON DELETE SET NULL
);
