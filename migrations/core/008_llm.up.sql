-- the own LLM of a user (OpenAI compatible endpoint), e.g. for the text correction in the composer.
-- The key is encrypted, a local LLM may have none
CREATE TABLE `user_llm` (
                            `UID` INTEGER NOT NULL PRIMARY KEY,
                            `api_type` VARCHAR(20) NOT NULL DEFAULT 'openai',
                            `base_url` VARCHAR(255) NOT NULL,
                            `model` VARCHAR(100) NOT NULL,
                            `api_key` TEXT NOT NULL DEFAULT '',
                            FOREIGN KEY (`UID`) REFERENCES `user_main` (`UID`) ON DELETE CASCADE
);

-- the LLM of a company: while it is there, the own ones of its users are not used. Core has only the company 1,
-- in Enterprise every customer has his own. released_for: only the admins or all users of the company
CREATE TABLE `company_llm` (
                               `company_id` INTEGER NOT NULL PRIMARY KEY,
                               `api_type` VARCHAR(20) NOT NULL DEFAULT 'openai',
                               `base_url` VARCHAR(255) NOT NULL,
                               `model` VARCHAR(100) NOT NULL,
                               `api_key` TEXT NOT NULL DEFAULT '',
                               `released_for` VARCHAR(10) NOT NULL DEFAULT 'all' CHECK (released_for IN ('admins', 'all'))
);
