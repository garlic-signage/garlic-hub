-- the tags of Pixabay become the description again
UPDATE mediapool_files
SET media_description = CASE WHEN coalesce(media_description, '') = '' THEN replace(trim(tags, ','), ',', ', ') ELSE media_description END,
    metadata = json_set(metadata, '$.description', replace(trim(tags, ','), ',', ', ')),
    tags = NULL
WHERE json_valid(metadata)
  AND json_extract(metadata, '$.origin') = 'Pixabay'
  AND coalesce(tags, '') != '';

DROP TABLE `company_llm_tasks`;
DROP TABLE `company_llm_connections`;
DROP TABLE `user_llm_tasks`;
DROP TABLE `user_llm_connections`;
