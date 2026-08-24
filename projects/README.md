# Project profiles

Each directory under `projects/` represents one website/project. Keep project-specific database dumps, theme overrides, taxonomy mappings, prompts, and non-secret configuration here.

The shared WordPress plugin and worker live under `shared/plugins/`. New projects should reference the shared plugin instead of copying it.

Do not commit passwords, API keys, production dumps, or uploads. Use a local `.env` file and keep it ignored by Git.
