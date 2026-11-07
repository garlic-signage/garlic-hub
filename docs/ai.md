# AI Helpers

Garlic-Hub has small AI helpers where the work happens: they correct a text, describe and tag media, fill a
playlist on a theme and make an image from a description. There is no chat and no agent. Every helper makes a
suggestion, the user decides. All helpers are off until an AI is set up for their task.

The AI is any endpoint of the **OpenAI compatible API**: a local server such as Ollama, or a provider such as
OpenAI, Gemini or DeepInfra. The calls go from the server, never from the browser, so the keys stay on the server
and providers which block calls from a browser work, too.

## Setting up

### Providers and tasks

An AI is a **provider** (name, address of the API, optional key) and a **model** of it per **task**. A user or a
company can have several providers, e.g. a local Ollama for the texts and OpenAI for the images.

| Task                | What the model must do              | Used by                                        |
|---------------------|-------------------------------------|------------------------------------------------|
| Texts               | text, JSON answers                  | text correction, fill a playlist with AI       |
| Tag images          | understand images (vision model)    | suggest tags, tag a folder                     |
| Generate images     | make images (`/v1/images/generations`) | AI image in the uploader                    |

A task without a model is off: its helper does not appear.

### Who decides: company and user

The module admin of `users` decides per task on `/users/llm` (link on the administration page):

| Mode                                | Effect                                                                 |
|-------------------------------------|------------------------------------------------------------------------|
| global AI for all users             | everybody works with the provider and model of the company             |
| global AI for administrators only   | only admins (module admin or sub admin of any module) get the helper  |
| users use their own AI              | every user sets up his own provider and model in his account           |
| off (default)                       | nobody gets the helper                                                 |

The Core edition has one company (`company_id` 1), so its global AI is the AI of the installation. In the
Enterprise edition every customer sets up his own.

With "users use their own AI" the user enters his providers and models in the tab **AI** of his account
(`/account/settings#llm`). Tasks which the company provides appear there as provided, tasks which are off do not
appear.

In both forms the button next to the model loads the models of the address and offers them in a dialog. A key is
stored encrypted (`Crypt`) and never shown again: an empty field keeps it, as long as the address stays the same.

### Providers which work

| Provider                     | Texts | Tag images              | Generate images                    |
|------------------------------|:-----:|:-----------------------:|:----------------------------------:|
| Ollama (local)               | ✓     | ✓ with a vision model   | ✓ Flux and SD models (newer versions) |
| LM Studio, llama.cpp, vLLM   | ✓     | ✓ with a vision model   | –                                  |
| LocalAI (local)              | ✓     | ✓ with a vision model   | ✓ Stable Diffusion, Flux           |
| stable-diffusion.cpp `sd-server` (local) | – | –             | ✓                                  |
| OpenAI                       | ✓     | ✓                       | ✓ gpt-image                        |
| Gemini (OpenAI compatibility)| ✓     | ✓                       | not confirmed                      |
| DeepInfra, Together AI, Fireworks | ✓ | ✓ depending on the model | ✓ Flux and others               |
| Claude (OpenAI compatibility)| ✓     | ✓                       | –                                  |

Draw Things and ComfyUI offer no OpenAI compatible images API. An image made there is uploaded like any file.

## The helpers

| Helper                  | Where                                            | Task            | Saved                         |
|-------------------------|--------------------------------------------------|-----------------|-------------------------------|
| Correct text            | template composer, context menu of a text box    | Texts           | in the composer, undo works   |
| Suggest tags            | mediapool, context menu of a medium              | Tag images      | after the user saves the edit dialog |
| Tag folder              | mediapool, context menu of a folder              | Tag images      | at once, file by file         |
| Fill with AI            | playlist composer, insert menu                   | Texts           | the media the user ticks      |
| AI image                | mediapool, uploader tab "AI image"               | Generate images | when the user uploads it      |

### Correct text

Corrects spelling, grammar and punctuation of the selected text box (at most 5000 characters) and keeps its
language, wording and line breaks. The styles of single characters are removed, because their positions change.

### Suggest tags and tag a folder

The AI looks at one image of the medium, made from the original for the request and not stored:

- a picture, at most 1024 pixels,
- a video: the frame of its thumbnail (ffmpeg, at 2 seconds),
- a PDF: its first page.

SVG is left out, reading it may load other files. Audio and widgets have no image.

The answer is a short description and up to 10 tags in the language of the user. **Suggest tags** opens them in
the edit dialog: the description is added to the one there, the tags join the ones there; the user changes them
and saves. **Tag folder** works on the pictures, videos and PDFs of the folder which have no tags yet, one after
the other from the browser, and saves each suggestion at once. A dialog shows the progress and cancels after the
file in work. Subfolders are not part of it, like rights are not inherited. Both need the right to edit the medium.

### Tags

The tags of a medium are in `mediapool_files.tags`, each with commas around it: `,beach,sea,sand,`. A tag has no
comma, the same tag is stored once (`MediaService::encodeTags()`). The edit dialog has a field for them, the
information dialog shows them.

The imports of Pixabay and Unsplash write the tags of the platform into this field, too. The migration moved the
tags of media imported from Pixabay before from the description into the field.

### Search in the mediapool

The search field above the media list finds media by tags, file name and description in all folders the user may
read, from two letters on, at most 100, the newest first. The filter beside it limits the list and the search to
pictures, videos, audio or documents. A result shows its folder and offers editing only where the user may edit.
The search needs no AI, but it is what the AI helpers rely on.

The rule which folders a user may read is a condition of the query (`MediaService::readableDirectories()`): his
own folders, the public ones and the ones shared with him in his role; the module admin reads all. It must give
the same as `AclValidator::checkDirectoryPermissions()` for single folders; `MediaSearchTest` compares both.

### Fill a playlist with AI

In the composer of master and internal playlists the user enters a theme and a duration in minutes. Two
questions go to the AI:

1. The AI gives up to 8 search terms in the language of the user.
2. The search above finds the pictures and videos for them (at most 60). The AI gets them as a numbered list with
   name, tags, description, kind and seconds, chooses the fitting ones and orders them for the duration.

The AI sees only the media the user may read, and only their descriptions. It answers with numbers of the list,
so it can not invent media. The suggestion is a list with ticks; the ticked media are inserted at the end like
dropped media, with the rights and the time limit of the playlist. Media with good tags and descriptions are found
better.

### AI image

The user describes the image (at most 1000 characters) and chooses landscape (1536×1024), portrait (1024×1536) or
square. The image comes into the previews of the uploader like a photo of the camera and is uploaded from there
into the current folder; the description becomes the description of the medium.

The server asks for the image data in the answer (`b64_json`, not sent to the gpt-image models, which refuse it).
An answer with only a URL of the image is refused: the server would load an address which the endpoint chose.
Only PNG, JPEG and WebP go to the browser.

## Time limits

| Request                      | Server waits for the AI | Browser waits |
|------------------------------|------------------------:|--------------:|
| texts, tags                  | 60 s                    | 65 s          |
| fill with AI (two questions) | 60 s each               | 130 s         |
| image                        | 180 s                   | 185 s         |
| list of the models           | 5 s                     | 6 s           |

The browser waits a little longer than the server, so the user gets the message of the server. All other
requests of the browser stop after 6 seconds (`FetchClient`).

## Security and privacy

- **What leaves the server:** the text of the text box, the image of a medium (at most 1024 pixels), the theme of a
  playlist with the names, tags and descriptions of readable media, the description of an image. Nothing else,
  no user data. With a local server nothing leaves the network.
- **Rights:** a helper works only on what the user may edit (text box, medium, playlist) and offers only media the
  user may read.
- **The address of a provider** may be any http or https address, also in the intranet, because local servers are
  the main use. To keep it from being a tool to look into the intranet: no redirects, a connect timeout of
  5 seconds, and the user never sees the answer of the endpoint, only a translated message (`LlmException`).
  Image URLs of an answer are not loaded.
- **Keys** are encrypted in the database and never sent to the browser.
- **Suggestions** are confirmed by the user, except "tag folder", which the user starts for a folder and can
  change afterwards.

## For developers

```
Modules (controllers, MediaTaggingService, PlaylistFillingService)
   → Shared/Llm (LlmResolver, the helpers, settings)
      → Framework/Llm (LlmClient, OpenAiCompatibleClient, LlmClientFactory)
```

- **`Framework/Llm`** knows the API and nothing of users. `LlmClient` is the interface the helpers use
  (`complete()`, `listModels()`, `generateImage()`). `OpenAiCompatibleClient` is the only class which knows the
  library `openai-php/client`; another API is a new class and a new `api_type`. `LlmClientFactory` builds the
  client with Guzzle: http/https only, no redirects, timeouts.
- **`Shared/Llm`** decides which AI a user gets: `LlmResolver::isAvailable($UID, $task)` for showing a helper,
  `forUser($UID, $task)` for the client (throws `llm_not_configured`). `LlmTask` lists the tasks. The settings are
  in `user_llm_connections`, `user_llm_tasks`, `company_llm_connections` and `company_llm_tasks`. The helpers are
  `TextCorrection`, `MediaTagging`, `PlaylistComposition` and `ImageGeneration`: each one builds its prompt, asks
  for JSON where it needs a structure, removes the `<think>` part of reasoning models and checks the answer.
- **Modules** check the rights, collect the data and answer the browser. A controller turns an `LlmException`
  into a translated JSON error.

A new helper:

1. Choose a task of `LlmTask` or add one (it appears in both forms, with `llm_task_<task>` and `_hint` in
   `main.ini`).
2. A class in `Shared/Llm` with `isAvailable()` and the call: instructions in English, the language of the user as
   locale in the prompt, the answer checked, never trusted.
3. A controller in the module which checks the rights before it asks the AI, and the button only when
   `isAvailable()` is true.
4. In the JavaScript a timeout of `FetchClient.LLM_TIMEOUT` for the request.
5. Tests against a fake endpoint: `$container->set(LlmClientFactory::class, new LlmClientFactory(new Client(['handler' => HandlerStack::create($mockHandler)])))`,
   see `TextCorrectionControllerTest`.
